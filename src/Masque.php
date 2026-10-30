<?php

/**
 * Relance — masquage avant envoi au modèle.
 * Filtrage par motifs : il rate des choses. Les catégories sensibles s'excluent dans la configuration.
 * On évite de masquer ce qu'on veut justement obtenir (numéros de poste et d'inventaire).
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Relance;

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
            // mot de passe : « mdp : xxx », « password=xxx », « mon mot de passe est xxx »
            '/\b(mot de passe|mdp|password|passwd|pwd|pass)\b\s*(?:est|is|c\'est|:|=)?\s*[:=]?\s*(\S+)/iu' => '$1 : [SECRET]',
            // IBAN (majuscules ou minuscules)
            '/\b[A-Za-z]{2}\d{2}(?:\s?[A-Za-z0-9]{4}){3,7}\s?[A-Za-z0-9]{1,4}\b/u' => '[IBAN]',
            // carte bancaire : quatre groupes de quatre chiffres
            '/\b\d{4}([ -]?)\d{4}\1\d{4}\1\d{4}\b/' => '[CARTE]',
            // NAS canadien 123-456-789 / 123 456 789
            '/\b\d{3}[ -]\d{3}[ -]\d{3}\b/' => '[NAS]',
            // NIR français 15 chiffres (avec ou sans espaces)
            '/\b[12]\s?\d{2}\s?\d{2}\s?\d{2}\s?\d{3}\s?\d{3}\s?\d{2}\b/' => '[NIR]',
            // e-mail
            '/[\w.+-]+@[\w-]+\.[\w.-]+/u' => '[EMAIL]',
            // téléphone (FR, CA, international)
            '/(?:\+|00)\d{1,3}[ .-]?(?:\(?\d{1,3}\)?[ .-]?)?\d{2,3}[ .-]?\d{2,3}[ .-]?\d{2,4}\b/' => '[TEL]',
            '/\b\(?\d{3}\)?[ .-]\d{3}[ .-]\d{4}\b/' => '[TEL]',
            '/\b0\d(?:[ .-]?\d{2}){4}\b/' => '[TEL]',
        ];

        foreach ($motifs as $re => $rep) {
            $texte = preg_replace($re, $rep, $texte, -1, $c);
            $n += $c;
        }

        return [$texte, $n];
    }
}
