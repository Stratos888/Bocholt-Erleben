<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$decisionEndpoint = (string)file_get_contents($root . '/api/startpartner/review-decision.php');
$decisionDomain = (string)file_get_contents($root . '/api/startpartner/_review_decision_domain.php');
$communication = (string)file_get_contents($root . '/api/startpartner/_review_communication.php');
$communicationEndpoint = (string)file_get_contents($root . '/api/startpartner/review-communication.php');
$frontend = (string)file_get_contents($root . '/js/control-center/startpartner-ai-review.js');

$assert(str_contains($decisionEndpoint, "require_once __DIR__ . '/_review_communication.php'"), 'Review-Entscheidung muss den dedizierten Kommunikationsowner laden.');
$assert(str_contains($decisionEndpoint, 'be_startpartner_review_communication_topic_for_decision'), 'Review-Entscheidung muss jede Entscheidung auf ein festes Mail-Topic abbilden.');
$assert(str_contains($decisionEndpoint, "(\$result['idempotent_replay'] ?? false) !== true || \$attemptState === null"), 'Idempotenter Replay darf nur bei fehlendem Kommunikationsversuch nachsenden.');
$assert(str_contains($decisionEndpoint, "\$decision === 'reject'"), 'Ablehnungsnachricht muss getrennt vom internen Entscheidungsgrund behandelt werden.');
$assert(str_contains($decisionEndpoint, "\$input['customer_message']"), 'Ablehnung benötigt ein separates externes Nachrichtenfeld.');

$assert(substr_count($communication, 'be_send_mail(') === 1, 'Der Kommunikationsowner muss genau einen physischen Mail-Sendepunkt besitzen.');
$assert(str_contains($communication, "'question' => ['needs_information', 'contact_pending', 'awaiting_response']"), 'Rückfragen müssen Legacy-, Vorbereitungs- und Wartezustand kontrolliert unterstützen.');
$assert(str_contains($communication, "'accepted' => ['accepted_pending_terms']"), 'Aufnahme-Mail darf nur nach erfolgreicher Aufnahmeentscheidung versendet werden.');
$assert(str_contains($communication, "'rejected' => ['rejected']"), 'Ablehnungs-Mail darf nur nach erfolgreicher Ablehnung versendet werden.');
$assert(str_contains($communication, "'waitlisted' => ['waitlisted']"), 'Wartelisten-Mail darf nur nach erfolgreicher Wartelistenentscheidung versendet werden.');
$assert(str_contains($communication, "'review_mail_sent'"), 'Erfolgreicher Versand muss im Startpartner-Audit protokolliert werden.');
$assert(str_contains($communication, "'review_mail_failed'"), 'Fehlgeschlagener Versand muss im Startpartner-Audit protokolliert werden.');
$assert(str_contains($communication, "'mark_contact_pending'"), 'Eine Rückfrage muss vor dem Versand als vorbereitet markiert werden können.');
$assert(str_contains($communication, "'mark_awaiting_response'"), 'Rückmeldung ausstehend darf erst über den kontrollierten Versandpfad gesetzt werden.');
$mailSendPos = strpos($communication, 'be_send_mail(');
$awaitSyncPos = $mailSendPos === false ? false : strpos($communication, 'be_startpartner_review_communication_mark_awaiting(', $mailSendPos);
$assert($mailSendPos !== false && $awaitSyncPos !== false && $mailSendPos < $awaitSyncPos, 'Rückmeldung ausstehend darf erst nach dem physischen Mailversuch gesetzt werden.');
$assert(!str_contains($communication, 'INSERT INTO organizers'), 'Review-Kommunikation darf keinen Organizer anlegen.');
$assert(!str_contains($communication, 'INSERT INTO submissions'), 'Review-Kommunikation darf keine Submission anlegen.');
$assert(!str_contains($communication, 'publication_entitlements'), 'Review-Kommunikation darf keine Veröffentlichungserlaubnis verändern.');
$assert(!str_contains($communication, 'stripe_'), 'Review-Kommunikation darf keinen Stripe-Pfad besitzen.');
$assert(!str_contains($communication, 'magic_link'), 'Review-Kommunikation darf keinen Magic-Link-Pfad besitzen.');

$assert(str_contains($communicationEndpoint, 'be_startpartner_require_gate1_environment'), 'Kommunikationsendpunkt muss auf Staging/Dev begrenzt sein.');
$assert(str_contains($communicationEndpoint, 'be_require_review_access'), 'Kommunikationsendpunkt muss Review-Zugriff verlangen.');
$assert(str_contains($communicationEndpoint, 'be_startpartner_review_communication_send'), 'Kommunikationsendpunkt darf nur den dedizierten Kommunikationsowner aufrufen.');

$assert(str_contains($frontend, 'Nachgereichte Angaben aus einer Rückfrage:'), 'Prüfprompt muss nachgereichte Angaben berücksichtigen.');
$assert(str_contains($frontend, "entry?.from_status==='awaiting_response'"), 'Frontend muss die jüngste Rückmeldung aus dem Auditverlauf lesen.');
$assert(str_contains($frontend, 'data-review-action="send_review_question"'), 'Vorbereitete Rückfrage braucht einen normalen Senden-Button.');
$assert(str_contains($frontend, 'data-review-action="record_review_reply"'), 'Wartezustand braucht einen Antwort-eintragen-Pfad.');
$assert(str_contains($frontend, 'data-review-action="resend_review_question"'), 'Wartezustand braucht einen bewussten erneuten Versand.');
$assert(str_contains($frontend, "'/api/startpartner/review-communication.php'"), 'Frontend muss den geschützten Kommunikationsendpunkt verwenden.');
$assert(str_contains($frontend, "action:'start_qualification',reason:reply"), 'Nachgereichte Antwort muss kontrolliert zurück in die Prüfung führen.');
$assert(str_contains($frontend, "'sp-review-customer-message'"), 'Ablehnung muss interne Begründung und externe Nachricht trennen.');
$assert(str_contains($frontend, 'Die interne Begründung wird nicht automatisch nach außen übernommen.'), 'UI muss die Trennung interner und externer Ablehnungsgründe erklären.');

$assert(str_contains($decisionDomain, "'needs_information'"), 'Review-Domain muss den fachlichen Rückfrageentscheid weiterhin unterstützen.');
$assert(!str_contains($decisionDomain, 'be_send_mail('), 'Fachliche Review-Domain bleibt frei vom physischen Mailversand.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Review Communication Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Review Communication Contract: OK ===\n";
