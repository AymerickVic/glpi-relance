<?php

/**
 * Relance — diagnostic : dit en clair ce qui va et ce qui ne va pas.
 * Licence GPLv3+, voir LICENSE.
 */

use GlpiPlugin\Relance\Config;
use GlpiPlugin\Relance\Llm;

Session::checkRight('config', UPDATE);

global $DB;

Html::header('Relance — Diagnostic', $_SERVER['PHP_SELF'], 'config', 'plugins');

$lignes = [];
$ok = fn(bool $b) => $b ? "<span class='badge bg-success'>OK</span>" : "<span class='badge bg-danger'>Problème</span>";

$lignes[] = [$ok(version_compare(GLPI_VERSION, PLUGIN_RELANCE_MIN_GLPI, '>=')), 'Version GLPI ' . GLPI_VERSION];
$lignes[] = [$ok(version_compare(PHP_VERSION, '8.2', '>=')), 'PHP ' . PHP_VERSION];
$lignes[] = [$ok(function_exists('curl_init')), 'Extension curl'];

$config = Config::forEntity(0);
$lignes[] = [$ok((bool) $config['is_active']), $config['is_active'] ? 'Plugin activé' : 'Plugin désactivé dans la configuration'];
$lignes[] = [$ok($config['provider'] === 'dry' || $config['provider'] === 'ollama' || $config['api_key'] !== ''), 'Fournisseur : ' . $config['provider'] . ($config['provider'] !== 'dry' && $config['provider'] !== 'ollama' && $config['api_key'] === '' ? ' (aucune clé API)' : '')];

$uid = Config::technicalUserId();
$lignes[] = [$ok($uid > 0), $uid > 0 ? "Utilisateur technique « " . Config::TECH_USER_LOGIN . " » présent (id $uid)" : 'Utilisateur technique absent : réinstaller le plugin'];

$cron = new CronTask();
$cronOk = $cron->getFromDBbyName(GlpiPlugin\Relance\Queue::class, 'processqueue');
$lignes[] = [$ok($cronOk && (int) $cron->fields['state'] !== CronTask::STATE_DISABLE), $cronOk ? 'Tâche planifiée « processqueue » : ' . ($cron->fields['lastrun'] ? 'dernier passage ' . $cron->fields['lastrun'] : 'jamais exécutée encore') : 'Tâche planifiée absente'];

$pending = countElementsInTable('glpi_plugin_relance_queue', ['status' => 'pending']);
$failed  = countElementsInTable('glpi_plugin_relance_queue', ['status' => 'failed']);
$lignes[] = [$ok($failed === 0), "File d'attente : $pending en attente, $failed en échec"];

// Test de connexion au modèle, à la demande
$test = null;
if (isset($_GET['test'])) {
    try {
        $r = Llm::complete($config, 'Réponds en JSON : {"ok": true}', 'ping', 20);
        $test = [$ok(true), 'Connexion au modèle : réponse en ' . $r['duration_ms'] . ' ms (' . $r['tokens'] . ' jetons)'];
    } catch (\Throwable $e) {
        $test = [$ok(false), 'Connexion au modèle : ' . htmlspecialchars($e->getMessage())];
    }
}

echo "<div class='card'><div class='card-body'><h3>Diagnostic Relance</h3><table class='table'>";
foreach ($lignes as [$badge, $texte]) {
    echo "<tr><td style='width:90px'>$badge</td><td>$texte</td></tr>";
}
if ($test) {
    echo "<tr><td>{$test[0]}</td><td>{$test[1]}</td></tr>";
}
echo '</table>';
echo "<a class='btn btn-outline-primary' href='?test=1'>Tester la connexion au modèle</a> ";
echo "<a class='btn btn-outline-secondary' href='config.php'>Retour à la configuration</a>";

$derniers = $DB->request(['FROM' => 'glpi_plugin_relance_logs', 'ORDER' => 'id DESC', 'LIMIT' => 10]);
echo "<h4 class='mt-4'>Dix derniers traitements</h4><table class='table table-sm'><tr><th>Date</th><th>Ticket</th><th>Résultat</th><th>Fournisseur</th><th>Jetons</th><th>Durée</th><th>Masquages</th></tr>";
foreach ($derniers as $l) {
    echo "<tr><td>{$l['date_creation']}</td><td>{$l['tickets_id']}</td><td>{$l['outcome']}</td><td>{$l['provider']}</td><td>{$l['tokens']}</td><td>{$l['duration_ms']} ms</td><td>{$l['masked_count']}</td></tr>";
}
echo '</table></div></div>';

Html::footer();
