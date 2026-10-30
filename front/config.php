<?php

/**
 * GLPI Assist — page de configuration (lien « Configurer » dans la liste des plugins).
 * Licence GPLv3+, voir LICENSE.
 */

use GlpiPlugin\Glpiassist\Config;

Session::checkRight('config', UPDATE);

Html::header('GLPI Assist', $_SERVER['PHP_SELF'], 'config', 'plugins');
(new Config())->showForm(0);
Html::footer();
