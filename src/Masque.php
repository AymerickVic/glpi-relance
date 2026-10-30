<?php

/**
 * GLPI Assist — masquage avant envoi au modèle.
 * Filtrage par motifs : il rate des choses. Les catégories sensibles s'excluent dans la configuration.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Glpiassist;

class Masque
{
    /** @return array{0: string, 1: int} texte masqué, nombre de remplacements */
    public static function appliquer(string $texte, array $noms = []): array
    {
        $n = 0;

        // Noms du demandeur et des techniciens → jetons
        foreach ($noms as $jeton => $nom) {
            $nom = trim((string) $nom);
            if (strlen($nom) >= 3) {
                $texte = preg_replace('/' . preg_quote($nom, '/') . '/iu', "[$jeton]", $texte, -1, $c);
                $n += $c;
            }
        }

        $motifs = [
            // mot de passe : « mdp : xxx », « password=xxx », « pwd xxx »
            '/\b(mot de passe|mdp|password|passwd|pwd|pass)\s*[:=]?\s*\S+/iu' => '$1 : [SECRET]',
            // IBAN
            '/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,7}\s?[A-Z0-9]{1,4}\b/u' => '[IBAN]',
            // carte bancaire 13-19 chiffres avec séparateurs
            '/\b(?:\d[ -]?){13,19}\b/' => '[CARTE]',
            // NAS canadien 123-456-789 / 123 456 789
            '/\b\d{3}[ -]\d{3}[ -]\d{3}\b/' => '[NAS]',
            // NIR français 15 chiffres (avec ou sans espaces)
            '/\b[12]\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{3}\s?\d{3}\s?\d{2}\b/' => '[NIR]',
            // e-mail
            '/[\w.+-]+@[\w-]+\.[\w.-]+/u' => '[EMAIL]',
            // téléphone (FR, CA) : séquences de 10 chiffres avec séparateurs
            '/(?:\+?\d{1,3}[ .-]?)?(?:\(?\d{3}\)?[ .-]?)\d{3}[ .-]?\d{4}\b/' => '[TEL]',
            '/\b0\d(?:[ .-]?\d{2}){4}\b/' => '[TEL]',
        ];

        foreach ($motifs as $re => $rep) {
            $texte = preg_replace($re, $rep, $texte, -1, $c);
            $n += $c;
        }

        return [$texte, $n];
    }
}
