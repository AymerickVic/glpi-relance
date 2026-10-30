<?php

/**
 * Relance — licence : une clé, une vérification par jour, jamais de contenu de ticket.
 * Sans clé, le plugin fonctionne et ne contacte aucun serveur. Avec une clé, il envoie la clé,
 * la version du plugin, celle de GLPI et un condensé de l'identifiant d'instance.
 * Le code est public : les versions sont sur GitHub. La licence paie le support et le signalement.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Relance;

use Config as GlpiConfig;
use CronTask;

class Licence
{
    public const SERVEUR  = 'https://srv2106298.hstgr.cloud';
    public const VERSIONS = 'https://github.com/AymerickVic/glpi-relance/releases';
    public const CONTEXTE = 'plugin:Relance';

    /** État mémorisé : statut, expire, message, derniere_version, verifie_le. */
    public static function etat(): array
    {
        $v = GlpiConfig::getConfigurationValues(self::CONTEXTE, ['licence_statut', 'licence_expire', 'licence_message', 'derniere_version', 'licence_verifie_le']);
        return [
            'statut'           => $v['licence_statut'] ?? 'aucune',
            'expire'           => $v['licence_expire'] ?? '',
            'message'          => $v['licence_message'] ?? '',
            'derniere_version' => $v['derniere_version'] ?? '',
            'verifie_le'       => $v['licence_verifie_le'] ?? '',
        ];
    }

    /** Identifiant d'instance : un condensé de l'UUID GLPI, rien d'identifiant. */
    public static function instance(): string
    {
        $uuid = (string) (GlpiConfig::getConfigurationValue('core', 'instance_uuid') ?? '');
        return substr(hash('sha256', 'relance|' . $uuid), 0, 32);
    }

    public static function verifier(): array
    {
        $row = Config::rowFor(0);
        $cle = trim((string) ($row['licence_key'] ?? ''));

        if ($cle === '') {
            // Sans clé : aucun appel sortant, on efface l'état.
            GlpiConfig::deleteConfigurationValues(self::CONTEXTE, ['licence_statut', 'licence_expire', 'licence_message', 'derniere_version', 'licence_verifie_le']);
            return ['statut' => 'aucune', 'expire' => '', 'message' => 'Aucune clé de licence saisie.', 'derniere_version' => ''];
        }

        [$raw, $code, $err] = Llm::post(self::SERVEUR . '/api/licence/verifier', ['Content-Type: application/json'], [
            'cle'      => $cle,
            'version'  => PLUGIN_RELANCE_VERSION,
            'glpi'     => GLPI_VERSION,
            'instance' => self::instance(),
        ], 15);
        $d = json_decode($raw, true);

        if ($code === 200 && is_array($d)) {
            $etat = [
                'statut'           => !empty($d['valide']) ? 'valide' : 'invalide',
                'expire'           => (string) ($d['expire'] ?? ''),
                'message'          => (string) ($d['message'] ?? ''),
                'derniere_version' => (string) ($d['derniere_version'] ?? ''),
            ];
        } else {
            // Serveur injoignable : on garde l'état précédent, on note seulement l'échec.
            $prec = self::etat();
            $etat = ['statut' => $prec['statut'], 'expire' => $prec['expire'], 'message' => 'Serveur de licences injoignable, dernier état conservé.', 'derniere_version' => $prec['derniere_version']];
        }

        GlpiConfig::setConfigurationValues(self::CONTEXTE, [
            'licence_statut'     => $etat['statut'],
            'licence_expire'     => $etat['expire'],
            'licence_message'    => $etat['message'],
            'derniere_version'   => $etat['derniere_version'],
            'licence_verifie_le' => date('Y-m-d H:i:s'),
        ]);
        return $etat;
    }

    public static function miseAJourDisponible(): bool
    {
        $v = self::etat()['derniere_version'];
        return $v !== '' && version_compare($v, PLUGIN_RELANCE_VERSION, '>');
    }

    public static function cronInfo($name)
    {
        return $name === 'verifierlicence' ? ['description' => 'Relance : vérifier la licence et signaler les mises à jour'] : [];
    }

    public static function cronVerifierlicence(?CronTask $task = null): int
    {
        $e = self::verifier();
        $task?->log('Licence : ' . $e['statut'] . ' — ' . $e['message']);
        return 1;
    }

    /** Bandeau pour la page de configuration. */
    public static function bandeau(): string
    {
        $e = self::etat();
        $maj = self::miseAJourDisponible()
            ? ' Une version plus récente existe (' . htmlescape($e['derniere_version']) . ') : <a href="' . self::VERSIONS . '" target="_blank" rel="noopener">voir les versions</a>.'
            : '';
        return match ($e['statut']) {
            'valide'   => "<div class='alert alert-success'>Licence valide" . ($e['expire'] ? " jusqu'au " . htmlescape($e['expire']) : '') . ". Support et signalement des mises à jour inclus.$maj</div>",
            'invalide' => "<div class='alert alert-danger'>" . htmlescape($e['message']) . ' Le plugin continue de fonctionner, sans support.</div>',
            default    => "<div class='alert alert-secondary'>Sans clé de licence : le plugin fonctionne, ne contacte aucun serveur, et n'a pas droit au support. Les versions sont sur <a href='" . self::VERSIONS . "' target='_blank' rel='noopener'>GitHub</a>.</div>",
        };
    }
}
