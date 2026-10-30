<?php

/**
 * GLPI Assist — enregistrement de la configuration.
 * Licence GPLv3+, voir LICENSE.
 */

use GlpiPlugin\Glpiassist\Config;

Session::checkRight('config', UPDATE);

$config = new Config();
$entities_id = (int) ($_POST['entities_id'] ?? 0);

if (isset($_POST['update'])) {
    if (!isset($_POST['excluded_categories'])) {
        $_POST['excluded_categories'] = [];
    }
    $existante = Config::rowFor($entities_id);
    if ($existante) {
        $_POST['id'] = $existante['id'];
        $config->update($_POST);
    } else {
        unset($_POST['id']);
        $config->add($_POST);
    }
    Session::addMessageAfterRedirect('Configuration enregistrée.', true, INFO);
} elseif (isset($_POST['heriter']) && $entities_id > 0) {
    // Retirer la configuration propre : l'entité hérite à nouveau de son parent.
    if ($existante = Config::rowFor($entities_id)) {
        $config->delete(['id' => $existante['id']], true);
    }
    Session::addMessageAfterRedirect("L'entité hérite à nouveau de la configuration parente.", true, INFO);
}
Html::back();
