<?php

/**
 * Outil de développement : vérifie l'héritage de configuration par entité.
 * Crée une sous-entité « Client Test » désactivée, y crée un ticket (doit être ignoré),
 * puis un ticket à la racine (doit être traité).
 */

use GlpiPlugin\Glpiassist\Config;
use GlpiPlugin\Glpiassist\Queue;

chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();

global $DB;
$_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
$_SESSION['glpiID'] = 2;
$_SESSION['glpiactiveentities'] = [0];
$_SESSION['glpiactive_entity'] = 0;
$_SESSION['glpiactiveprofile'] = ['id' => 4, 'interface' => 'central'];

$entity = new Entity();
$eid = 0;
foreach ($entity->find(['name' => 'Client Test']) as $e) {
    $eid = (int) $e['id'];
}
if (!$eid) {
    $eid = (int) $entity->add(['name' => 'Client Test', 'entities_id' => 0]);
}
$_SESSION['glpiactiveentities'] = [0, $eid];
echo "Sous-entité : $eid\n";

// Config propre à la sous-entité : désactivée
if (!Config::rowFor($eid)) {
    (new Config())->add(['entities_id' => $eid, 'is_active' => 0, 'provider' => 'dry']);
}
$c = Config::forEntity($eid);
echo "Config vue par la sous-entité : entité {$c['entities_id']}, active={$c['is_active']}, provider={$c['provider']}\n";
$c0 = Config::forEntity(0);
echo "Config vue par la racine : entité {$c0['entities_id']}, active={$c0['is_active']}, provider={$c0['provider']}\n";

$t = new Ticket();
$id1 = $t->add(['name' => 'Ticket client test', 'content' => 'rien ne marche', 'entities_id' => $eid, '_users_id_requester' => 2, '_disablenotif' => true]);
$n1 = countElementsInTable('glpi_plugin_glpiassist_queue', ['tickets_id' => $id1]);
echo "Ticket $id1 (sous-entité désactivée) → en file : $n1 (attendu 0)\n";

$t = new Ticket();
$id2 = $t->add(['name' => 'Ticket racine', 'content' => 'rien ne marche', 'entities_id' => 0, '_users_id_requester' => 2, '_disablenotif' => true]);
$n2 = countElementsInTable('glpi_plugin_glpiassist_queue', ['tickets_id' => $id2]);
echo "Ticket $id2 (racine active) → en file : $n2 (attendu 1)\n";

// Retour à l'héritage : la sous-entité doit maintenant voir la config racine
(new Config())->delete(['id' => Config::rowFor($eid)['id']], true);
$c = Config::forEntity($eid);
echo "Après suppression de la config propre : la sous-entité voit l'entité {$c['entities_id']} (attendu 0)\n";

// On vide la file pour ne pas appeler le modèle inutilement
$DB->delete('glpi_plugin_glpiassist_queue', ['tickets_id' => $id2]);
