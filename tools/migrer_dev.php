<?php

/**
 * Outil de développement : rejoue la partie « mise à jour » de l'installation sans désinstaller
 * (donc sans perdre la configuration ni la clé API). Puis vérifie la licence et affiche l'état.
 */

use GlpiPlugin\Glpiassist\Licence;

chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();

// Le noyau a déjà chargé setup.php (plugin actif) ; hook.php ne l'est qu'à l'install.
if (!function_exists('plugin_glpiassist_install')) {
    require 'plugins/glpiassist/hook.php';
}

$_SESSION['glpi_currenttime'] = date('Y-m-d H:i:s');
plugin_glpiassist_install();
echo "install (idempotent) : ok\n";

if ($cle = $argv[1] ?? '') {
    global $DB;
    $DB->update('glpi_plugin_glpiassist_configs', ['licence_key' => strtoupper($cle)], ['entities_id' => 0]);
    echo "clé enregistrée\n";
}

$e = Licence::verifier();
echo 'licence : ' . json_encode($e, JSON_UNESCAPED_UNICODE) . "\n";
echo 'instance : ' . Licence::instance() . "\n";
