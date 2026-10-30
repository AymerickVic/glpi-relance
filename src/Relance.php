<?php

/**
 * Relance — la relance : qu'est-ce qui manque dans ce ticket, et quelle question poser.
 * Licence GPLv3+, voir LICENSE.
 */

namespace GlpiPlugin\Relance;

use ITILFollowup;
use Ticket;
use User;

class Relance
{
    public const CHAMPS = ['poste', 'message_erreur', 'depuis_quand', 'perimetre'];

    public static function libelles(array $cles): array
    {
        $l = [
            'poste'          => 'le nom ou le numéro d\'inventaire du poste',
            'message_erreur' => 'le message d\'erreur exact ou une capture d\'écran',
            'depuis_quand'   => 'depuis quand le problème se produit',
            'perimetre'      => 'si vous êtes seul concerné ou tout le service',
        ];
        return array_values(array_intersect_key($l, array_flip($cles)));
    }

    /**
     * @return array{manque: string[], question: string, masked: int, tokens: int, http_code: int, duration_ms: int}
     */
    public static function analyser(Ticket $ticket, array $config): array
    {
        $noms = [];
        foreach ($ticket->getUsers(\CommonITILActor::REQUESTER) as $u) {
            $user = new User();
            if ($user->getFromDB($u['users_id'])) {
                $noms['DEMANDEUR'] = $user->getFriendlyName();
                if (!empty($user->fields['firstname'])) {
                    $noms['DEMANDEUR_PRENOM'] = $user->fields['firstname'];
                }
            }
        }

        $titre = strip_tags((string) $ticket->fields['name']);
        $description = mb_substr(trim(html_entity_decode(strip_tags((string) $ticket->fields['content']))), 0, 2000);

        [$titreM, $n1] = Masque::appliquer($titre, $noms);
        [$descM, $n2]  = Masque::appliquer($description, $noms);

        $lang = $config['language'] ?? 'fr';
        $system = $lang === 'en'
            ? "You help an IT helpdesk. Given a new ticket, decide which of these facts are missing and would be needed to start working on it: poste (computer name or asset number), message_erreur (exact error message or screenshot), depuis_quand (since when), perimetre (one person or a whole team). Only list what is genuinely missing AND genuinely needed for this kind of request; a request like 'printer in room 204 is down' needs nothing. Answer in JSON only: {\"manque\": [...keys...], \"question\": \"one short, polite message to the requester asking for exactly those facts, in English, or empty string if nothing is missing\"}."
            : "Tu aides un helpdesk informatique. On te donne un nouveau ticket. Dis quelles informations, parmi celles-ci, manquent et seraient nécessaires pour commencer à traiter : poste (nom ou numéro d'inventaire du poste), message_erreur (message d'erreur exact ou capture), depuis_quand (depuis quand), perimetre (une personne ou tout le service). Ne liste que ce qui manque vraiment ET qui est vraiment utile pour ce type de demande ; une demande comme « imprimante HS salle 204 » n'a besoin de rien. Réponds en JSON seulement : {\"manque\": [...clés...], \"question\": \"un message court et poli au demandeur qui demande exactement ces informations, en français, ou chaîne vide si rien ne manque\"}.";

        $user = "Titre : $titreM\n\nDescription :\n$descM";

        $r = Llm::complete($config, $system, $user);
        $data = json_decode(self::extraireJson($r['text']), true);
        $manque = [];
        if (is_array($data) && isset($data['manque']) && is_array($data['manque'])) {
            $manque = array_values(array_intersect(array_map('strval', $data['manque']), self::CHAMPS));
        }
        $question = is_array($data) ? trim((string) ($data['question'] ?? '')) : '';
        if ($manque && $question === '') {
            $question = 'Pour avancer, pouvez-vous me préciser : ' . implode(', ', self::libelles($manque)) . ' ?';
        }

        return [
            'manque'      => $manque,
            'question'    => $manque ? $question : '',
            'masked'      => $n1 + $n2,
            'tokens'      => $r['tokens'],
            'http_code'   => $r['http_code'],
            'duration_ms' => $r['duration_ms'],
        ];
    }

    /** Texte du suivi privé : trois lignes, pas plus. */
    public static function suivi(array $manque, string $question): string
    {
        $lignes = 'Manque : ' . implode(', ', self::libelles($manque)) . '.';
        // GLPI purifie lui-même le HTML des suivis ; on n'échappe que < et >, pas les apostrophes.
        return "<p><b>$lignes</b></p><p>Question prête à envoyer au demandeur :</p><blockquote>" . nl2br(htmlspecialchars($question, ENT_NOQUOTES)) . '</blockquote>';
    }

    /** Relance en attente d'un clic pour ce ticket, ou null. */
    public static function proposee(int $tickets_id): ?array
    {
        global $DB;
        foreach ($DB->request(['FROM' => 'glpi_plugin_relance_relances', 'WHERE' => ['tickets_id' => $tickets_id, 'status' => 'proposed'], 'ORDER' => 'id DESC', 'LIMIT' => 1]) as $row) {
            return $row;
        }
        return null;
    }

    /**
     * Qui peut envoyer ou ignorer une relance : un technicien (interface centrale) qui a le droit
     * d'ajouter un suivi sur tout ticket et de modifier celui-ci. Jamais le demandeur sur son propre ticket.
     */
    public static function peutEnvoyer(Ticket $ticket): bool
    {
        return \Session::getCurrentInterface() === 'central'
            && \Session::haveRight(ITILFollowup::$rightname, ITILFollowup::ADDALLITEM)
            && $ticket->canUpdateItem();
    }

    /** Hook timeline_actions : les deux boutons, seulement s'il y a une relance proposée et que l'utilisateur peut l'envoyer. */
    public static function timelineActions(array $params): void
    {
        global $CFG_GLPI;

        $item = $params['item'] ?? null;
        if (!$item instanceof Ticket || !self::peutEnvoyer($item)) {
            return;
        }
        $relance = self::proposee($item->getID());
        if (!$relance) {
            return;
        }
        $url = $CFG_GLPI['root_doc'] . '/plugins/relance/front/envoyer.form.php';
        echo "<form method='post' action='$url' class='d-inline-block ms-2'>";
        echo "<input type='hidden' name='id' value='" . (int) $relance['id'] . "'>";
        echo "<input type='hidden' name='tickets_id' value='" . $item->getID() . "'>";
        echo "<button type='submit' name='envoyer' class='btn btn-primary btn-sm' title='Ajoute la question en suivi public et met le ticket en attente'>";
        echo "<i class='ti ti-send me-1'></i>Envoyer la relance au demandeur</button> ";
        echo "<button type='submit' name='ignorer' class='btn btn-outline-secondary btn-sm'>Ignorer</button>";
        \Html::closeForm();
    }

    private static function extraireJson(string $t): string
    {
        $t = trim($t);
        if (str_starts_with($t, '```')) {
            $t = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $t);
        }
        if (($p = strpos($t, '{')) !== false && ($q = strrpos($t, '}')) !== false && $q > $p) {
            return substr($t, $p, $q - $p + 1);
        }
        return $t;
    }
}
