<?php

/**
 * Relance — file d'attente et tâche planifiée.
 * Le hook de création ne fait qu'empiler. La tâche appelle le modèle et écrit le suivi privé.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Relance;

use CommonDBTM;
use CronTask;
use ITILFollowup;
use Ticket;

class Queue extends CommonDBTM
{
    /** Au-delà, un passage de la tâche s'arrête et reprend au suivant (mode interne = requête web). */
    private const BUDGET_SECONDES = 40;
    private const ESSAIS_MAX = 3;

    public static function getTable($classname = null)
    {
        return 'glpi_plugin_relance_queue';
    }

    public static function getTypeName($nb = 0)
    {
        return 'Relance';
    }

    /** Hook item_add sur Ticket : empiler, c'est tout. Aucun appel réseau ici. */
    public static function onTicketAdd(Ticket $ticket): void
    {
        global $DB;

        $config = Config::forEntity((int) $ticket->fields['entities_id']);
        if (!$config['is_active']) {
            return;
        }
        if (in_array((int) $ticket->fields['itilcategories_id'], $config['excluded_categories'], true)) {
            return;
        }
        // Un ticket daté d'il y a plus d'une heure est une reprise ou une migration, pas une demande à relancer.
        if (!empty($ticket->fields['date']) && strtotime($ticket->fields['date']) < time() - 3600) {
            return;
        }
        if (countElementsInTable(self::getTable(), ['tickets_id' => $ticket->getID()]) > 0) {
            return;
        }

        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->insert(self::getTable(), [
            'tickets_id'    => $ticket->getID(),
            'entities_id'   => (int) $ticket->fields['entities_id'],
            'status'        => 'pending',
            'date_creation' => $now,
            'date_mod'      => $now,
        ]);
    }

    public static function cronInfo($name)
    {
        return match ($name) {
            'processqueue' => ['description' => 'Relance : traiter les tickets en attente', 'parameter' => 'Tickets par passage'],
            'purgelogs'    => ['description' => 'Relance : purger le journal', 'parameter' => 'Jours conservés'],
            default        => [],
        };
    }

    public static function cronProcessqueue(?CronTask $task = null): int
    {
        global $DB;

        $limite = max(1, (int) ($task?->fields['param'] ?? 20));
        $debut = microtime(true);
        $fait = 0;
        $echecs = 0;

        // Lignes restées en « working » (processus tué) : remises en attente après 10 minutes, d'après la date du passage en working.
        $DB->update(self::getTable(), ['status' => 'pending', 'date_mod' => date('Y-m-d H:i:s')], [
            'status'   => 'working',
            'date_mod' => ['<', date('Y-m-d H:i:s', time() - 600)],
        ]);
        // Lignes qui ont épuisé leurs essais sans jamais être marquées : en échec, pour que le diagnostic les voie.
        $DB->update(self::getTable(), ['status' => 'failed', 'date_mod' => date('Y-m-d H:i:s')], [
            'status'   => 'pending',
            'attempts' => ['>=', self::ESSAIS_MAX],
        ]);

        $it = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['status' => 'pending', 'attempts' => ['<', self::ESSAIS_MAX]],
            'ORDER' => 'id ASC',
            'LIMIT' => $limite,
        ]);

        foreach ($it as $row) {
            if (microtime(true) - $debut > self::BUDGET_SECONDES) {
                $task?->log('Budget de temps atteint, reprise au prochain passage');
                break;
            }
            $essai = (int) $row['attempts'] + 1;
            $DB->update(self::getTable(), ['status' => 'working', 'attempts' => $essai, 'date_mod' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
            try {
                $outcome = self::traiter((int) $row['tickets_id']);
                $DB->update(self::getTable(), ['status' => 'done', 'last_error' => null, 'date_done' => date('Y-m-d H:i:s'), 'date_mod' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
                $task?->log("Ticket {$row['tickets_id']} : $outcome");
                $fait++;
            } catch (\Throwable $e) {
                $msg = Llm::sansSecret($e->getMessage());
                // Clé refusée (401/403) : inutile de réessayer, c'est la configuration qui est en cause.
                $definitif = $essai >= self::ESSAIS_MAX || preg_match('/^Réponse 40[13] /u', $msg);
                $DB->update(self::getTable(), ['status' => $definitif ? 'failed' : 'pending', 'last_error' => $msg, 'date_mod' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
                $task?->log("Ticket {$row['tickets_id']} : " . ($definitif ? 'ÉCHEC, ' : 'erreur, nouvel essai, ') . $msg);
                $echecs += $definitif ? 1 : 0;
            }
        }

        $task?->setVolume($fait);
        if ($echecs > 0) {
            $task?->log("$echecs ticket(s) en échec définitif : voir la page Diagnostic du plugin");
        }
        return $fait > 0 ? 1 : 0;
    }

    public static function cronPurgelogs(?CronTask $task = null): int
    {
        global $DB;
        $jours = max(1, (int) ($task?->fields['param'] ?? 90));
        $DB->delete('glpi_plugin_relance_logs', ['date_creation' => ['<', date('Y-m-d H:i:s', time() - $jours * DAY_TIMESTAMP)]]);
        $DB->delete(self::getTable(), ['status' => ['done', 'failed'], 'date_creation' => ['<', date('Y-m-d H:i:s', time() - 7 * DAY_TIMESTAMP)]]);
        // La question proposée n'a plus de raison d'exister une fois envoyée ou ignorée : 30 jours puis suppression.
        $DB->delete('glpi_plugin_relance_relances', ['status' => ['sent', 'dismissed'], 'date_creation' => ['<', date('Y-m-d H:i:s', time() - 30 * DAY_TIMESTAMP)]]);
        return 1;
    }

    /** Échecs définitifs des dernières 24 h, pour le bandeau d'alerte. */
    public static function echecsRecents(): int
    {
        return countElementsInTable(self::getTable(), ['status' => 'failed', 'date_mod' => ['>', date('Y-m-d H:i:s', time() - DAY_TIMESTAMP)]]);
    }

    /** @return string ce qui s'est passé, pour le journal de la tâche */
    private static function traiter(int $tickets_id): string
    {
        global $DB;

        $ticket = new Ticket();
        if (!$ticket->getFromDB($tickets_id)) {
            return 'ticket introuvable';
        }
        if ((int) $ticket->fields['status'] >= Ticket::SOLVED) {
            return 'déjà résolu';
        }
        // Jamais deux relances pour un même ticket, même après un réessai.
        if (countElementsInTable('glpi_plugin_relance_relances', ['tickets_id' => $tickets_id]) > 0) {
            return 'déjà traité';
        }

        $config = Config::forEntity((int) $ticket->fields['entities_id']);
        if (!$config['is_active']) {
            return 'désactivé';
        }

        $t0 = microtime(true);
        $r = Relance::analyser($ticket, $config);

        $outcome = 'rien à demander';
        if ($r['manque']) {
            // La relance est enregistrée AVANT le suivi : si le suivi échoue, on réessaie sans doublon possible.
            $DB->insert('glpi_plugin_relance_relances', [
                'tickets_id'    => $tickets_id,
                'manque'        => implode(',', $r['manque']),
                'question'      => $r['question'],
                'status'        => 'proposed',
                'date_creation' => date('Y-m-d H:i:s'),
            ]);
            $relances_id = (int) $DB->insertId();

            $fu = new ITILFollowup();
            $ok = $fu->add([
                'itemtype'      => Ticket::class,
                'items_id'      => $tickets_id,
                'users_id'      => Config::technicalUserId(),
                'is_private'    => 1,
                'content'       => Relance::suivi($r['manque'], $r['question']),
                '_disablenotif' => true,
            ]);
            if (!$ok) {
                $DB->delete('glpi_plugin_relance_relances', ['id' => $relances_id]);
                throw new \RuntimeException('ITILFollowup::add a échoué');
            }
            $DB->update('glpi_plugin_relance_relances', ['itilfollowups_id' => (int) $fu->getID()], ['id' => $relances_id]);
            $outcome = 'relance proposée (' . implode(', ', $r['manque']) . ')';
        }

        $DB->insert('glpi_plugin_relance_logs', [
            'tickets_id'    => $tickets_id,
            'fonction'      => 'relance',
            'provider'      => $config['provider'],
            'model'         => $config['model'],
            'tokens'        => $r['tokens'],
            'duration_ms'   => $r['duration_ms'] ?: (int) ((microtime(true) - $t0) * 1000),
            'http_code'     => $r['http_code'],
            'masked_count'  => $r['masked'],
            'outcome'       => $r['manque'] ? 'relance' : 'rien',
            'date_creation' => date('Y-m-d H:i:s'),
        ]);

        return $outcome;
    }
}
