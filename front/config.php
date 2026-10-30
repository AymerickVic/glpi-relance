<?php

/**
 * Relance — page de configuration (lien « Configurer » dans la liste des plugins).
 * Licence GPLv3+, voir LICENSE.
 */

use GlpiPlugin\Relance\Config;

Session::checkRight('config', UPDATE);

Html::header('Relance', $_SERVER['PHP_SELF'], 'config', 'plugins');
(new Config())->showForm(0);
Html::footer();
