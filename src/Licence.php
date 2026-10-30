<?php

/**
 * GLPI Assist — licence : une clé, une vérification par jour, jamais de contenu de ticket.
 * Sans clé valide, le plugin fonctionne ; il n'a simplement plus droit aux mises à jour ni au support.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Glpiassist;

use Config as GlpiConfig;
use CronTask;

class Licence
{
    public const SERVEUR = 'https://srv2106298.hstgr.cloud';
    public const CONTEXTE = 'plugin:Glpiassist';

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
        return substr(hash('sha256', 'glpiassist|' . $uuid), 0, 32);
    }

    /** Ce qui est envoyé au serveur : clé, version du plugin, version GLPI, identifiant d'instance. Rien d'autre. */
    public static function verifier(): array
    {
        $row = Config::rowFor(0);
        $cle = trim((string) ($row['licence_key'] ?? ''));
        $etat = ['statut' => 'aucune', 'expire' => '', 'message' => 'Aucune clé de licence saisie.', 'derniere_version' => ''];

        if ($cle !== '') {
            $ch = curl_init(self::SERVEUR . '/api/licence/verifier');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode(['cle' => $cle, 'version' => PLUGIN_GLPIASSIST_VERSION, 'glpi' => GLPI_VERSION, 'instance' => self::instance()]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 8,
                CURLOPT_TIMEOUT        => 15,
            ]);
            $raw = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            $d = is_string($raw) ? json_decode($raw, true) : null;

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
        } else {
            // Même sans clé, on regarde s'il existe une version plus récente.
            $raw = @file_get_contents(self::SERVEUR . '/api/licence/version', false, stream_context_create(['http' => ['timeout' => 8]]));
            $d = is_string($raw) ? json_decode($raw, true) : null;
            $etat['derniere_version'] = (string) ($d['derniere_version'] ?? '');
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
        return $v !== '' && version_compare($v, PLUGIN_GLPIASSIST_VERSION, '>');
    }

    public static function cronInfo($name)
    {
        return $name === 'verifierlicence' ? ['description' => 'GLPI Assist : vérifier la licence et les mises à jour'] : [];
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
            ? " Une version plus récente existe (" . htmlspecialchars($e['derniere_version']) . ") : <a href='" . self::SERVEUR . "/api/licence/telecharger?cle=' target='_blank'>télécharger</a> avec votre clé."
            : '';
        return match ($e['statut']) {
            'valide'   => "<div class='alert alert-success'>Licence valide" . ($e['expire'] ? " jusqu'au " . htmlspecialchars($e['expire']) : '') . ". Mises à jour et support inclus.$maj</div>",
            'invalide' => "<div class='alert alert-danger'>" . htmlspecialchars($e['message']) . " Le plugin continue de fonctionner, sans mises à jour ni support.</div>",
            default    => "<div class='alert alert-secondary'>Sans clé de licence : le plugin fonctionne, mais sans mises à jour ni support. " . ($maj ?: '') . '</div>',
        };
    }
}
