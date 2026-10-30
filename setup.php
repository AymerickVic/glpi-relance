<?php

/**
 * GLPI Assist — relance automatique des tickets incomplets.
 *
 * Ce plugin est un logiciel libre ; vous pouvez le redistribuer et/ou le modifier
 * selon les termes de la GNU General Public License publiée par la Free Software
 * Foundation, version 3 ou ultérieure. Voir LICENSE.
 */

use Glpi\Plugin\Hooks;
use GlpiPlugin\Glpiassist\Config;
use GlpiPlugin\Glpiassist\Queue;
use GlpiPlugin\Glpiassist\Relance;

define('PLUGIN_GLPIASSIST_VERSION', '0.1.0');
define('PLUGIN_GLPIASSIST_MIN_GLPI', '11.0.0');
define('PLUGIN_GLPIASSIST_MAX_GLPI', '11.0.99');

function plugin_init_glpiassist(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['glpiassist'] = true;

    // Onglet de configuration dans Configuration > Générale
    Plugin::registerClass(Config::class, ['addtabon' => ['Config', 'Entity']]);
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['glpiassist'] = 'front/config.php';
    }

    // À la création d'un ticket : on empile, rien d'autre. Le modèle est appelé par la tâche planifiée.
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['glpiassist'] = [
        Ticket::class => [Queue::class, 'onTicketAdd'],
    ];

    // Boutons « envoyer au demandeur » / « ignorer » au pied de la timeline du ticket.
    $PLUGIN_HOOKS[Hooks::TIMELINE_ACTIONS]['glpiassist'] = [Relance::class, 'timelineActions'];
}

function plugin_version_glpiassist(): array
{
    return [
        'name'         => 'GLPI Assist',
        'version'      => PLUGIN_GLPIASSIST_VERSION,
        'author'       => 'Aymerick Victoire',
        'license'      => 'GPLv3+',
        'homepage'     => 'https://srv2106298.hstgr.cloud',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GLPIASSIST_MIN_GLPI,
                'max' => PLUGIN_GLPIASSIST_MAX_GLPI,
            ],
            'php' => [
                'min'  => '8.2',
                'exts' => ['curl' => ['required' => true], 'json' => ['required' => true]],
            ],
        ],
    ];
}

function plugin_glpiassist_check_prerequisites(): bool
{
    return true;
}

function plugin_glpiassist_check_config(bool $verbose = false): bool
{
    return true;
}
