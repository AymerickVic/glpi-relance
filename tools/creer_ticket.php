<?php

/**
 * Outil de développement : crée un ticket par le vrai chemin (Ticket::add → hook item_add) puis lance la tâche.
 * Usage (dans le conteneur) : php plugins/glpiassist/tools/creer_ticket.php "Titre" "Description"
 */

use GlpiPlugin\Glpiassist\Queue;

// GLPI 11 : plus d'inc/includes.php, on démarre le noyau comme bin/console.
chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();

$titre = $argv[1] ?? 'Mon ordinateur ne marche plus';
$desc  = $argv[2] ?? 'Bonjour, ça ne marche plus, merci de faire le nécessaire. Jean Dupont, poste 0612345678';

$_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
$_SESSION['glpiID'] = 2; // glpi (super-admin)
$_SESSION['glpiactiveentities'] = [0];
$_SESSION['glpiactive_entity'] = 0;
$_SESSION['glpiactiveprofile'] = ['id' => 4, 'interface' => 'central'];

$t = new Ticket();
$id = $t->add([
    'name'             => $titre,
    'content'          => $desc,
    'entities_id'      => 0,
    '_users_id_requester' => 2,
    '_disablenotif'    => true,
]);
echo "Ticket créé : $id\n";

$task = new CronTask();
$task->getFromDBbyName(Queue::class, 'processqueue');
$r = Queue::cronProcessqueue($task);
echo "cronProcessqueue → $r\n";

global $DB;
foreach ($DB->request(['FROM' => 'glpi_plugin_glpiassist_queue', 'WHERE' => ['tickets_id' => $id]]) as $q) {
    echo "File : {$q['status']} (tentatives {$q['attempts']}) " . ($q['last_error'] ?? '') . "\n";
}
foreach ($DB->request(['FROM' => 'glpi_itilfollowups', 'WHERE' => ['itemtype' => 'Ticket', 'items_id' => $id]]) as $f) {
    echo "Suivi (privé={$f['is_private']}, user {$f['users_id']}) :\n" . strip_tags(str_replace('</p>', "\n", $f['content'])) . "\n";
}
foreach ($DB->request(['FROM' => 'glpi_plugin_glpiassist_logs', 'WHERE' => ['tickets_id' => $id]]) as $l) {
    echo "Journal : {$l['outcome']}, {$l['provider']}, masquages {$l['masked_count']}, {$l['duration_ms']} ms\n";
}
