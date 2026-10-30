<?php

/**
 * Relance — le technicien envoie la relance au demandeur, ou l'ignore.
 * Réservé à l'interface centrale, avec le droit d'ajouter un suivi sur tout ticket et de modifier le ticket.
 * Le suivi public est signé par le technicien qui clique, pas par le plugin.
 * Licence GPLv3+, voir LICENSE.
 */

use Glpi\Exception\Http\AccessDeniedHttpException;
use GlpiPlugin\Relance\Relance;

global $DB;

$id         = (int) ($_POST['id'] ?? 0);
$tickets_id = (int) ($_POST['tickets_id'] ?? 0);

$ticket = new Ticket();
if ($id <= 0 || !$ticket->getFromDB($tickets_id) || !Relance::peutEnvoyer($ticket)) {
    throw new AccessDeniedHttpException();
}

$relance = null;
foreach ($DB->request(['FROM' => 'glpi_plugin_relance_relances', 'WHERE' => ['id' => $id, 'tickets_id' => $tickets_id, 'status' => 'proposed']]) as $row) {
    $relance = $row;
}
if (!$relance) {
    Session::addMessageAfterRedirect('Cette relance a déjà été traitée.', false, WARNING);
    Html::back();
}

$now = date('Y-m-d H:i:s');

if (isset($_POST['envoyer'])) {
    $fu = new ITILFollowup();
    $ok = $fu->add([
        'itemtype'   => Ticket::class,
        'items_id'   => $tickets_id,
        'users_id'   => Session::getLoginUserID(),
        'is_private' => 0,
        'content'    => nl2br(htmlspecialchars($relance['question'], ENT_NOQUOTES)),
    ]);
    if ($ok) {
        $ticket->update(['id' => $tickets_id, 'status' => Ticket::WAITING]);
        $DB->update('glpi_plugin_relance_relances', ['status' => 'sent', 'itilfollowups_id' => (int) $fu->getID(), 'users_id' => Session::getLoginUserID(), 'date_mod' => $now], ['id' => $id]);
        Session::addMessageAfterRedirect('Relance envoyée au demandeur, ticket mis en attente.', false, INFO);
    } else {
        Session::addMessageAfterRedirect("Le suivi n'a pas pu être ajouté.", false, ERROR);
    }
} elseif (isset($_POST['ignorer'])) {
    $DB->update('glpi_plugin_relance_relances', ['status' => 'dismissed', 'users_id' => Session::getLoginUserID(), 'date_mod' => $now], ['id' => $id]);
    Session::addMessageAfterRedirect('Relance ignorée.', false, INFO);
}

Html::back();
