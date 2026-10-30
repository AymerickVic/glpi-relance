<?php

/**
 * Évaluation : 50 tickets variés passés dans le modèle configuré (entité racine).
 * Pour chacun, ce qu'on attendait (relance ou rien) et ce que le modèle a décidé.
 * Écrit docs/evaluation.md. Usage : php plugins/relance/tools/evaluer.php
 * Les tickets ne sont pas créés dans GLPI : on appelle l'analyse directement sur un objet Ticket en mémoire.
 */

use GlpiPlugin\Relance\Config;
use GlpiPlugin\Relance\Relance;

chdir(__DIR__ . '/../../..');
require 'vendor/autoload.php';
$kernel = new \Glpi\Kernel\Kernel();
$kernel->boot();

// [titre, description, attendu] — attendu : 'relance' s'il manque quelque chose d'utile, 'rien' sinon.
$jeu = [
    // Vagues : relance attendue
    ['Ça marche plus', 'Bonjour, ça ne marche plus, merci de faire le nécessaire.', 'relance'],
    ['Problème ordinateur', 'Mon ordinateur a un problème depuis ce matin.', 'relance'],
    ['Urgent', 'Je ne peux plus travailler, aidez-moi vite svp.', 'relance'],
    ['Outlook', 'Outlook ne fonctionne pas.', 'relance'],
    ['Plus de son', 'Je n\'ai plus de son sur mon poste.', 'relance'],
    ['Imprimante', 'L\'imprimante ne marche pas.', 'relance'],
    ['Erreur', 'J\'ai une erreur quand je lance le logiciel.', 'relance'],
    ['Lenteur', 'Tout est très lent.', 'relance'],
    ['Wifi', 'Je n\'arrive pas à me connecter au wifi.', 'relance'],
    ['Écran', 'Mon écran fait des trucs bizarres.', 'relance'],
    ['Accès refusé', 'Accès refusé quand j\'ouvre le dossier partagé.', 'relance'],
    ['Teams', 'Teams plante tout le temps.', 'relance'],
    ['VPN', 'Le VPN ne se connecte pas, j\'ai besoin de travailler de chez moi.', 'relance'],
    ['Mot de passe', 'Je n\'arrive plus à me connecter.', 'relance'],
    ['Logiciel compta', 'Le logiciel de compta se ferme tout seul.', 'relance'],
    ['Clavier', 'Certaines touches ne répondent plus.', 'relance'],
    ['Mail bloqué', 'Je ne reçois plus de mails depuis un moment.', 'relance'],
    ['Pb impression', 'Impossible d\'imprimer, ça reste en file d\'attente.', 'relance'],
    ['Souris', 'La souris saute partout.', 'relance'],
    ['Application métier', 'L\'application affiche une erreur et je ne peux pas valider ma commande.', 'relance'],
    ['Session', 'Ma session se bloque régulièrement.', 'relance'],
    ['Fichier corrompu', 'Un fichier Excel ne s\'ouvre plus, il dit qu\'il est corrompu.', 'relance'],
    ['Webcam', 'Ma webcam n\'est pas détectée en visio.', 'relance'],
    ['Scanner', 'Le scanner ne scanne plus.', 'relance'],
    ['Pas de réseau', 'Pas de réseau sur mon poste.', 'relance'],
    // Complets : rien attendu
    ['Imprimante HS salle 204', 'L\'imprimante HP de la salle 204 (inventaire IMP-0042) affiche l\'erreur 49.4C02 depuis ce matin 8h, tout le service compta est bloqué.', 'rien'],
    ['Accès au partage compta', 'Je n\'arrive plus à ouvrir le lecteur S: sur mon poste PC-COMPTA-07, message « Accès refusé », depuis la mise à jour d\'hier. Juste moi je crois.', 'rien'],
    ['Demande de création de compte', 'Merci de créer un compte AD pour Marie Durand, qui arrive lundi 3 novembre au service RH, mêmes droits que Paul Martin.', 'rien'],
    ['Commande d\'un écran', 'Je souhaite commander un second écran 24 pouces pour le poste PC-MKT-12, budget validé par mon responsable.', 'rien'],
    ['Outlook erreur 0x8004010F', 'Sur PC-VENTE-03, Outlook affiche l\'erreur 0x8004010F à chaque envoi depuis hier 14h. Je suis le seul concerné, mes collègues n\'ont rien.', 'rien'],
    ['Réinitialisation mot de passe', 'Je suis jdupont, j\'ai oublié mon mot de passe Windows, merci de le réinitialiser. Poste PC-ADM-05.', 'rien'],
    ['Installation logiciel', 'Pouvez-vous installer 7-Zip sur le poste PC-RH-02 ? Pas d\'urgence.', 'rien'],
    ['Wifi salle de réunion B', 'Le wifi de la salle B ne diffuse plus le SSID ENTREPRISE depuis ce matin 9h, toutes les personnes en réunion sont concernées. La borne est la AP-B-01.', 'rien'],
    ['VPN erreur 809', 'Depuis mon portable LAP-0123 à la maison, le VPN affiche l\'erreur 809 depuis dimanche soir. Ça marchait vendredi.', 'rien'],
    ['Ajout d\'une imprimante', 'Merci d\'ajouter l\'imprimante IMP-0042 sur mon poste PC-COMPTA-07.', 'rien'],
    ['Teams : micro non détecté', 'Sur PC-MKT-12, Teams ne détecte pas le micro du casque Jabra depuis la mise à jour Windows de ce matin. Le micro marche dans les paramètres Windows. Seulement moi.', 'rien'],
    ['Question licence', 'Combien de licences Visio reste-t-il sur le contrat ? C\'est pour un nouveau projet.', 'rien'],
    ['Écran noir PC-ACC-01', 'Le PC d\'accueil PC-ACC-01 reste écran noir au démarrage depuis ce matin 7h45, la LED est allumée, aucun message. Le poste d\'accueil est bloqué.', 'rien'],
    ['Boîte partagée', 'Merci d\'ajouter Sophie Leroy à la boîte partagée contact@entreprise.fr, validé par la direction.', 'rien'],
    ['Lenteur Excel fichier budget', 'Sur PC-FIN-04, le fichier Budget2026.xlsx met 3 minutes à s\'ouvrir depuis lundi, les autres fichiers s\'ouvrent normalement. Les collègues du service ont le même problème sur ce fichier.', 'rien'],
    ['Badge', 'Mon badge d\'accès n° 4471 ne fonctionne plus sur la porte du parking depuis hier soir.', 'rien'],
    ['Clavier portable', 'Sur mon portable LAP-0456, les touches A, Z et E ne répondent plus depuis ce matin après un café renversé.', 'rien'],
    ['Départ collaborateur', 'Jean Petit quitte l\'entreprise le 15 novembre, merci de désactiver ses comptes à cette date.', 'rien'],
    ['Pas de réseau bureau 312', 'Pas de réseau dans le bureau 312 depuis 10h, les deux postes PC-312-A et PC-312-B. La prise murale est la 3-12-07.', 'rien'],
    ['Scanner Kyocera', 'Le scanner du Kyocera 2e étage (inventaire MFP-0007) renvoie « erreur SMB » à chaque scan vers le dossier partagé depuis ce matin, tout l\'étage.', 'rien'],
    ['Formation', 'Y a-t-il une formation prévue sur le nouveau logiciel de notes de frais ?', 'rien'],
    ['Restauration fichier', 'Merci de restaurer le fichier S:\\Compta\\Factures\\2026-10\\F-10234.pdf supprimé par erreur ce matin vers 11h.', 'rien'],
    ['Webcam LAP-0789', 'Sur LAP-0789, la webcam intégrée n\'est plus détectée par Teams ni par l\'appli Caméra depuis la mise à jour d\'hier. Le gestionnaire de périphériques affiche un point d\'exclamation sur la caméra.', 'rien'],
    ['Changement de bureau', 'Je déménage du bureau 210 au 215 vendredi, merci de prévoir le déplacement de mon poste PC-RH-02 et du téléphone.', 'rien'],
    ['Session bloquée PC-VENTE-03', 'Sur PC-VENTE-03, ma session se fige environ toutes les heures depuis mardi, sans message. Je dois redémarrer. Mes collègues n\'ont pas ça.', 'rien'],
];

$config = Config::forEntity(0);
echo "Fournisseur : {$config['provider']} / {$config['model']}\n";

$resultats = [];
$tokens = 0;
$ms = 0;
$ok = 0;
foreach ($jeu as $i => [$titre, $desc, $attendu]) {
    $t = new Ticket();
    $t->fields = ['id' => 0, 'name' => $titre, 'content' => $desc, 'entities_id' => 0];
    try {
        $r = Relance::analyser($t, $config);
        $obtenu = $r['manque'] ? 'relance' : 'rien';
        $tokens += $r['tokens'];
        $ms += $r['duration_ms'];
        $detail = $r['manque'] ? implode(', ', $r['manque']) : '';
    } catch (\Throwable $e) {
        $obtenu = 'erreur';
        $detail = $e->getMessage();
    }
    $juste = $obtenu === $attendu;
    $ok += $juste ? 1 : 0;
    $resultats[] = [$titre, $attendu, $obtenu, $juste, $detail];
    echo sprintf("%2d. %-32s attendu %-7s obtenu %-7s %s %s\n", $i + 1, mb_substr($titre, 0, 32), $attendu, $obtenu, $juste ? 'ok' : 'FAUX', $detail);
}

$n = count($jeu);
$fausses = count(array_filter($resultats, fn($r) => $r[1] === 'rien' && $r[2] === 'relance'));
$manquees = count(array_filter($resultats, fn($r) => $r[1] === 'relance' && $r[2] === 'rien'));
// Haiku 4.5 : ordre de grandeur 1 $/M entrée, 5 $/M sortie ; on compte tout au tarif sortie, donc majoré.
$cout = $tokens / 1_000_000 * 5;

$md = "# Évaluation sur $n tickets — " . date('Y-m-d') . "\n\n";
$md .= "Modèle : {$config['provider']} / {$config['model']}. Jeu de test : 25 tickets vagues (relance attendue) et 25 tickets complets ou sans besoin de relance (rien attendu), rédigés à la main en français, dans `tools/evaluer.php`.\n\n";
$md .= "- Décisions justes : **$ok / $n**\n";
$md .= "- Relances injustifiées (ticket complet relancé) : **$fausses**\n";
$md .= "- Relances manquées (ticket vague non relancé) : **$manquees**\n";
$md .= '- Jetons au total : ' . number_format($tokens) . ', soit environ ' . number_format($tokens / $n) . " par ticket ; coût majoré ≈ " . number_format($cout, 3) . " \$ pour les $n, donc moins d'un centime par ticket\n";
$md .= '- Durée moyenne : ' . number_format($ms / $n) . " ms par ticket\n\n";
$md .= "| # | Ticket | Attendu | Obtenu | Juste | Manque relevé |\n|---|---|---|---|---|---|\n";
foreach ($resultats as $i => [$titre, $attendu, $obtenu, $juste, $detail]) {
    $md .= '| ' . ($i + 1) . ' | ' . str_replace('|', '\\|', $titre) . " | $attendu | $obtenu | " . ($juste ? 'oui' : '**non**') . ' | ' . str_replace('|', '\\|', $detail) . " |\n";
}
$md .= "\nCe que le jeu ne mesure pas : la qualité de la question rédigée (relue par un humain avant envoi), les tickets avec pièces jointes, et les descriptions longues de plusieurs paragraphes.\n";

file_put_contents(__DIR__ . '/../docs/evaluation.md', $md);
echo "\nJustes : $ok/$n, injustifiées : $fausses, manquées : $manquees, jetons : $tokens\n";
