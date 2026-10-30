<?php

/**
 * Relance — installation et désinstallation.
 * Licence GPLv3+, voir LICENSE.
 */

use GlpiPlugin\Relance\Config;
use GlpiPlugin\Relance\Licence;
use GlpiPlugin\Relance\Queue;

function plugin_relance_install(): bool
{
    global $DB;

    $migration = new Migration(PLUGIN_RELANCE_VERSION);

    $charset   = DBConnection::getDefaultCharset();
    $collation = DBConnection::getDefaultCollation();
    $keysign   = DBConnection::getDefaultPrimaryKeySignOption();

    // Configuration par entité (héritage : la ligne de l'entité 0 vaut pour tout le monde tant qu'une entité n'a pas la sienne).
    if (!$DB->tableExists('glpi_plugin_relance_configs')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_relance_configs` (
            `id` int {$keysign} NOT NULL AUTO_INCREMENT,
            `entities_id` int {$keysign} NOT NULL DEFAULT 0,
            `is_active` tinyint NOT NULL DEFAULT 0,
            `provider` varchar(32) NOT NULL DEFAULT 'dry',
            `model` varchar(128) NOT NULL DEFAULT '',
            `endpoint` varchar(255) NOT NULL DEFAULT '',
            `api_key` text DEFAULT NULL,
            `licence_key` varchar(255) NOT NULL DEFAULT '',
            `language` varchar(8) NOT NULL DEFAULT 'fr',
            `excluded_categories` text DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `entities_id` (`entities_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
        $DB->doQuery("INSERT INTO `glpi_plugin_relance_configs` (`entities_id`, `is_active`, `provider`) VALUES (0, 0, 'dry')");
    }

    // File d'attente : le hook de création y dépose le ticket, la tâche planifiée la vide.
    if (!$DB->tableExists('glpi_plugin_relance_queue')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_relance_queue` (
            `id` int {$keysign} NOT NULL AUTO_INCREMENT,
            `tickets_id` int {$keysign} NOT NULL,
            `entities_id` int {$keysign} NOT NULL DEFAULT 0,
            `status` varchar(16) NOT NULL DEFAULT 'pending',
            `attempts` int NOT NULL DEFAULT 0,
            `last_error` text DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            `date_done` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `status` (`status`),
            KEY `tickets_id` (`tickets_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Relances proposées : la question, en attente du clic du technicien.
    if (!$DB->tableExists('glpi_plugin_relance_relances')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_relance_relances` (
            `id` int {$keysign} NOT NULL AUTO_INCREMENT,
            `tickets_id` int {$keysign} NOT NULL,
            `manque` varchar(255) NOT NULL DEFAULT '',
            `question` text NOT NULL,
            `status` varchar(16) NOT NULL DEFAULT 'proposed',
            `itilfollowups_id` int {$keysign} NOT NULL DEFAULT 0,
            `users_id` int {$keysign} NOT NULL DEFAULT 0,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // Journal : métadonnées seulement, jamais le texte envoyé ni la réponse.
    if (!$DB->tableExists('glpi_plugin_relance_logs')) {
        $DB->doQuery("CREATE TABLE `glpi_plugin_relance_logs` (
            `id` int {$keysign} NOT NULL AUTO_INCREMENT,
            `tickets_id` int {$keysign} NOT NULL,
            `fonction` varchar(32) NOT NULL,
            `provider` varchar(32) NOT NULL,
            `model` varchar(128) NOT NULL DEFAULT '',
            `tokens` int NOT NULL DEFAULT 0,
            `duration_ms` int NOT NULL DEFAULT 0,
            `http_code` int NOT NULL DEFAULT 0,
            `masked_count` int NOT NULL DEFAULT 0,
            `outcome` varchar(32) NOT NULL DEFAULT '',
            `date_creation` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `tickets_id` (`tickets_id`),
            KEY `date_creation` (`date_creation`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset} COLLATE={$collation} ROW_FORMAT=DYNAMIC;");
    }

    // 0.1.0 → : date du dernier changement d'état dans la file (détection des lignes bloquées)
    if (!$DB->fieldExists('glpi_plugin_relance_queue', 'date_mod')) {
        $migration->addField('glpi_plugin_relance_queue', 'date_mod', 'timestamp', ['null' => true]);
        $migration->migrationOneTable('glpi_plugin_relance_queue');
    }

    // 0.1.0 → : clé de licence (entité racine seulement)
    if (!$DB->fieldExists('glpi_plugin_relance_configs', 'licence_key')) {
        $migration->addField('glpi_plugin_relance_configs', 'licence_key', 'string', ['value' => '']);
        $migration->migrationOneTable('glpi_plugin_relance_configs');
    }

    Config::ensureTechnicalUser();

    CronTask::register(Licence::class, 'verifierlicence', DAY_TIMESTAMP, [
        'state'   => CronTask::STATE_WAITING,
        'comment' => 'Relance : vérifie la licence et signale les mises à jour (envoie la clé et les versions, jamais de contenu)',
    ]);

    CronTask::register(Queue::class, 'processqueue', MINUTE_TIMESTAMP, [
        'state'   => CronTask::STATE_WAITING,
        'param'   => 20,
        'comment' => 'Relance : traite les tickets en attente (relance pour informations manquantes)',
    ]);

    CronTask::register(Queue::class, 'purgelogs', DAY_TIMESTAMP, [
        'state'   => CronTask::STATE_WAITING,
        'param'   => 90,
        'comment' => 'Relance : purge le journal au-delà de N jours',
    ]);

    $migration->executeMigration();

    return true;
}

function plugin_relance_uninstall(): bool
{
    global $DB;

    foreach (['glpi_plugin_relance_configs', 'glpi_plugin_relance_queue', 'glpi_plugin_relance_relances', 'glpi_plugin_relance_logs'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`");
        }
    }

    \Config::deleteConfigurationValues(Licence::CONTEXTE, ['licence_statut', 'licence_expire', 'licence_message', 'derniere_version', 'licence_verifie_le']);

    // Les suivis déjà écrits restent dans les tickets : ils appartiennent au client.
    // L'utilisateur technique reste aussi (il est auteur de ces suivis) ; on le désactive.
    Config::disableTechnicalUser();

    return true;
}
