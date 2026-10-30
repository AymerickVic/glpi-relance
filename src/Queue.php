<?php

/**
 * GLPI Assist — file d'attente et tâche planifiée.
 * Le hook de création ne fait qu'empiler. La tâche appelle le modèle et écrit le suivi privé.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Glpiassist;

use CommonDBTM;
use CronTask;
use ITILFollowup;
use Ticket;

class Queue extends CommonDBTM
{
    public static function getTable($classname = null)
    {
        return 'glpi_plugin_glpiassist_queue';
    }

    public static function getTypeName($nb = 0)
    {
        return 'GLPI Assist';
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

        $DB->insert(self::getTable(), [
            'tickets_id'    => $ticket->getID(),
            'entities_id'   => (int) $ticket->fields['entities_id'],
            'status'        => 'pending',
            'date_creation' => $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    public static function cronInfo($name)
    {
        return match ($name) {
            'processqueue' => ['description' => 'GLPI Assist : traiter les tickets en attente', 'parameter' => 'Tickets par passage'],
            'purgelogs'    => ['description' => 'GLPI Assist : purger le journal', 'parameter' => 'Jours conservés'],
            default        => [],
        };
    }

    public static function cronProcessqueue(?CronTask $task = null): int
    {
        global $DB;

        $limite = max(1, (int) ($task?->fields['param'] ?? 20));
        $fait = 0;

        $it = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['status' => 'pending', 'attempts' => ['<', 3]],
            'ORDER' => 'id ASC',
            'LIMIT' => $limite,
        ]);

        foreach ($it as $row) {
            $DB->update(self::getTable(), ['status' => 'working', 'attempts' => (int) $row['attempts'] + 1], ['id' => $row['id']]);
            try {
                $outcome = self::traiter((int) $row['tickets_id']);
                $DB->update(self::getTable(), ['status' => 'done', 'last_error' => null, 'date_done' => date('Y-m-d H:i:s')], ['id' => $row['id']]);
                $task?->log("Ticket {$row['tickets_id']} : $outcome");
                $fait++;
            } catch (\Throwable $e) {
                $retry = (int) $row['attempts'] + 1 < 3;
                $DB->update(self::getTable(), ['status' => $retry ? 'pending' : 'failed', 'last_error' => mb_substr($e->getMessage(), 0, 1000)], ['id' => $row['id']]);
                $task?->log("Ticket {$row['tickets_id']} : erreur, " . $e->getMessage());
            }
        }

        $task?->setVolume($fait);
        return $fait > 0 ? 1 : 0;
    }

    public static function cronPurgelogs(?CronTask $task = null): int
    {
        global $DB;
        $jours = max(1, (int) ($task?->fields['param'] ?? 90));
        $DB->delete('glpi_plugin_glpiassist_logs', ['date_creation' => ['<', date('Y-m-d H:i:s', time() - $jours * DAY_TIMESTAMP)]]);
        $DB->delete(self::getTable(), ['status' => ['done', 'failed'], 'date_creation' => ['<', date('Y-m-d H:i:s', time() - 7 * DAY_TIMESTAMP)]]);
        return 1;
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

        $config = Config::forEntity((int) $ticket->fields['entities_id']);
        if (!$config['is_active']) {
            return 'désactivé';
        }

        $t0 = microtime(true);
        $r = Relance::analyser($ticket, $config);

        $outcome = 'rien à demander';
        if ($r['manque']) {
            $users_id = Config::technicalUserId();
            $fu = new ITILFollowup();
            $ok = $fu->add([
                'itemtype'      => Ticket::class,
                'items_id'      => $tickets_id,
                'users_id'      => $users_id,
                'is_private'    => 1,
                'content'       => Relance::suivi($r['manque'], $r['question']),
                '_disablenotif' => true,
            ]);
            $outcome = $ok ? 'relance proposée (' . implode(', ', $r['manque']) . ')' : 'échec écriture du suivi';
            if (!$ok) {
                throw new \RuntimeException('ITILFollowup::add a échoué');
            }
            $DB->insert('glpi_plugin_glpiassist_relances', [
                'tickets_id'       => $tickets_id,
                'manque'           => implode(',', $r['manque']),
                'question'         => $r['question'],
                'status'           => 'proposed',
                'itilfollowups_id' => (int) $fu->getID(),
                'date_creation'    => date('Y-m-d H:i:s'),
            ]);
        }

        $DB->insert('glpi_plugin_glpiassist_logs', [
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
