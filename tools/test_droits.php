<?php

/**
 * Outil de développement : crée un compte « demandeur » (profil Self-Service) et vérifie
 * qu'il ne peut pas envoyer une relance sur son propre ticket. Affiche aussi le résultat
 * pour un super-admin. Usage : php plugins/relance/tools/test_droits.php
 */

use GlpiPlugin\Relance\Relance;

chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();

$_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');

// Compte demandeur
$user = new User();
if (!$user->getFromDBbyName('demandeur')) {
    $pid = 0;
    foreach ((new Profile())->find(['interface' => 'helpdesk'], ['id'], 1) as $p) {
        $pid = (int) $p['id'];
    }
    $user->add(['name' => 'demandeur', 'realname' => 'Demandeur Test', 'password' => 'demandeur', 'password2' => 'demandeur', 'is_active' => 1, 'authtype' => Auth::DB_GLPI, '_profiles_id' => $pid, '_entities_id' => 0, '_is_recursive' => 1]);
    $user->getFromDBbyName('demandeur');
}
$uid = $user->getID();

// Son ticket
$_SESSION['glpiID'] = $uid;
$_SESSION['glpiactiveentities'] = [0];
$_SESSION['glpiactive_entity'] = 0;
$t = new Ticket();
$tid = $t->add(['name' => 'Ça marche plus', 'content' => 'rien ne marche aidez moi', 'entities_id' => 0, '_users_id_requester' => $uid, '_disablenotif' => true]);
echo "Ticket $tid du demandeur\n";

// Session du demandeur, interface helpdesk
$auth = new Auth();
Session::init($auth); // vide
$_SESSION['glpiID'] = $uid;
Session::initEntityProfiles($uid);
$pu = (new Profile_User())->find(['users_id' => $uid], [], 1);
$prof = (int) reset($pu)['profiles_id'];
Session::changeProfile($prof);
$t->getFromDB($tid);
echo 'Demandeur (interface ' . Session::getCurrentInterface() . ') peut envoyer : ' . (Relance::peutEnvoyer($t) ? 'OUI (BUG)' : 'non (attendu)') . "\n";

// Session super-admin
$_SESSION['glpiID'] = 2;
Session::initEntityProfiles(2);
Session::changeProfile(4);
$t->getFromDB($tid);
echo 'Super-admin (interface ' . Session::getCurrentInterface() . ') peut envoyer : ' . (Relance::peutEnvoyer($t) ? 'oui (attendu)' : 'NON (BUG)') . "\n";
