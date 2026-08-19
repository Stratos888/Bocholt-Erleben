<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/api/startpartner/_public_intake.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(be_startpartner_public_scope('events') === 'events', 'Event-Scope muss unverändert bleiben.');
$assert(be_startpartner_public_scope('activities') === 'activities', 'Activity-Scope muss unverändert bleiben.');
$assert(be_startpartner_public_scope('both') === 'both', 'Both-Scope muss unverändert bleiben.');
$assert(be_startpartner_public_scope('unsure') === 'unknown', 'Unsicher muss auf den kanonischen unknown-Scope normalisiert werden.');

$invalidScopeRejected = false;
try {
    be_startpartner_public_scope('invalid');
} catch (InvalidArgumentException) {
    $invalidScopeRejected = true;
}
$assert($invalidScopeRejected, 'Unbekannter öffentlicher Scope muss abgewiesen werden.');

$input = [
    'source' => 'self_service',
    'desired_content_scope' => 'unsure',
    'organization' => 'Bocholt Browser Test',
    'contact_name' => 'Erika Beispiel',
    'email' => 'erika@example.test',
    'website' => 'example.test/angebot',
    'description' => 'Wir bieten regelmäßige lokale Veranstaltungen und Aktivitäten an.',
    'privacy_confirmed' => true,
    'website_confirm' => '',
];
$idempotencyKey = 'public-intake-contract-297-0001';
$prepared = be_startpartner_public_prepare_input($input, $idempotencyKey);

$assert($prepared['source'] === 'self_service', 'Public Intake muss source serverseitig auf self_service binden.');
$assert($prepared['organization_name'] === 'Bocholt Browser Test', 'Organisation wird nicht korrekt übernommen.');
$assert($prepared['contact_name'] === 'Erika Beispiel', 'Ansprechperson wird nicht korrekt übernommen.');
$assert($prepared['email'] === 'erika@example.test', 'E-Mail wird nicht korrekt übernommen.');
$assert($prepared['desired_content_scope'] === 'unknown', 'Scope-Normalisierung fehlt.');
$assert($prepared['privacy_confirmed'] === true, 'Datenschutzbestätigung muss serverseitig als true vorliegen.');
$assert($prepared['privacy_policy_version'] === BE_STARTPARTNER_PUBLIC_PRIVACY_VERSION, 'Datenschutzversion muss serverseitig gesetzt werden.');
$assert($prepared['form_version'] === BE_STARTPARTNER_PUBLIC_FORM_VERSION, 'Formularversion muss serverseitig gesetzt werden.');
$assert($prepared['idempotency_key'] === $idempotencyKey, 'Idempotency-Key muss an die Domain durchgereicht werden.');
$assert($prepared['source_reference'] === $idempotencyKey, 'Source-Referenz muss die stabile Anfrage-ID verwenden.');

$reviewAt = new DateTimeImmutable((string)$prepared['retention_review_at'], new DateTimeZone('UTC'));
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$assert($reviewAt > $now, 'Operativer Review-Zeitpunkt muss in der Zukunft liegen.');
$assert($reviewAt < $now->modify('+32 days'), 'Operativer Review-Zeitpunkt darf keine langfristige Retention-Frist simulieren.');

$missingContactRejected = false;
try {
    be_startpartner_public_prepare_input(array_diff_key($input, ['contact_name' => true]), $idempotencyKey);
} catch (InvalidArgumentException) {
    $missingContactRejected = true;
}
$assert($missingContactRejected, 'Öffentlicher Intake muss eine Ansprechperson verlangen.');

$missingPrivacyRejected = false;
try {
    $withoutPrivacy = $input;
    $withoutPrivacy['privacy_confirmed'] = false;
    be_startpartner_public_prepare_input($withoutPrivacy, $idempotencyKey);
} catch (InvalidArgumentException) {
    $missingPrivacyRejected = true;
}
$assert($missingPrivacyRejected, 'Öffentlicher Intake muss die Datenschutzbestätigung verlangen.');

$assert(be_startpartner_public_is_honeypot(['website_confirm' => 'bot.example']) === true, 'Gefüllter Honeypot muss erkannt werden.');
$assert(be_startpartner_public_is_honeypot(['website_confirm' => '']) === false, 'Leerer Honeypot darf echte Anfragen nicht blockieren.');

$mail = be_startpartner_public_mail_payload([
    'organization_name' => 'Bocholt Browser Test',
    'desired_content_scope' => 'both',
    'contacts' => [[
        'contact_name' => 'Erika Beispiel',
        'email' => 'erika@example.test',
        'is_primary' => 1,
    ]],
]);
$assert($mail['subject'] === 'Deine Startpartner-Anfrage ist angekommen', 'Mail-Betreff ist nicht kanonisch.');
$assert($mail['to_address'] === 'erika@example.test', 'Mail muss an den Primärkontakt gehen.');
$assert($mail['to_name'] === 'Erika Beispiel', 'Mail muss die Ansprechperson verwenden.');
$assert(str_contains($mail['mail_data']['intro'], 'Vielen Dank für deine Anfrage'), 'Mail muss den Eingang bestätigen.');
$assert(str_contains($mail['mail_data']['notice_text'], 'keine Zahlungsart'), 'Mail muss die No-Payment-Grenze transparent nennen.');
$assert(str_contains($mail['mail_data']['notice_text'], 'kein kostenpflichtiger Tarif'), 'Mail muss automatische Bezahlwirkung ausschließen.');

$intakeSource = (string)file_get_contents($root . '/api/startpartner/intake.php');
$publicSource = (string)file_get_contents($root . '/api/startpartner/_public_intake.php');

$assert(str_contains($intakeSource, 'be_startpartner_require_gate1_environment();'), 'Öffentlicher Cutover bleibt staging/dev-fail-closed.');
$assert(str_contains($intakeSource, "if (\$source === 'targeted_outreach')"), 'Interner Outreach-Pfad muss explizit getrennt bleiben.');
$assert(str_contains($intakeSource, 'be_require_review_access();'), 'Interner Outreach-Pfad muss geschützt bleiben.');
$assert(str_contains($intakeSource, "if (\$source !== 'self_service')"), 'Unbekannte Quellen müssen abgewiesen werden.');
$assert(str_contains($intakeSource, 'be_startpartner_public_is_honeypot'), 'Öffentlicher Endpoint muss Honeypot vor DB-Write prüfen.');
$assert(str_contains($intakeSource, "(\$result['created'] ?? false) === true"), 'Mail darf nur nach neuem Candidate versucht werden.');
$assert(str_contains($intakeSource, 'confirmation_mail_sent'), 'API muss den Mailzustand ohne PII an den Client zurückgeben.');
$assert(str_contains($intakeSource, "'stored' => true"), 'API muss den gespeicherten Eingang bestätigen.');
$assert(!str_contains($intakeSource, 'formspree'), 'First-Party-Endpoint darf Formspree nicht verwenden.');

foreach ([
    'stripe',
    'INSERT INTO subscriptions',
    'INSERT INTO publication_entitlements',
    'INSERT INTO publication_consumptions',
    'INSERT INTO organizer_magic_links',
    'INSERT INTO organizer_portal_sessions',
] as $forbiddenToken) {
    $assert(!str_contains(strtolower($publicSource . "\n" . $intakeSource), strtolower($forbiddenToken)), "Public Intake enthält verbotenen Effekt: {$forbiddenToken}");
}

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Public Intake Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Public Intake Contract: OK ===\n";
