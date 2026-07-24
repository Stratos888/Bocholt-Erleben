<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_domain.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$expectException = static function(callable $callback, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message;
    } catch (InvalidArgumentException|DomainException $expected) {
    }
};

$normalized = be_startpartner_normalize_intake([
    'source' => 'self_service',
    'organization_name' => '  Beispiel-Verein e. V.  ',
    'contact_name' => 'Max Beispiel',
    'email' => '  INFO@EXAMPLE.ORG ',
    'website_url' => 'example.org/start',
    'description_text' => " Ein lokales   Angebot. ",
    'desired_content_scope' => 'both',
    'privacy_confirmed' => true,
    'privacy_policy_version' => 'privacy-2026-07',
    'form_version' => 'gate1-v1',
    'idempotency_key' => 'domain-contract-0001',
]);

$assert($normalized['organization_name'] === 'Beispiel-Verein e. V.', 'Organisation muss nur fachlich bereinigt, nicht umbenannt werden.');
$assert($normalized['organization_name_normalized'] === 'beispiel verein e v', 'Organisationsidentität muss stabil normalisiert werden.');
$assert($normalized['contacts'][0]['email_normalized'] === 'info@example.org', 'E-Mail muss kleingeschrieben und getrimmt werden.');
$assert($normalized['contacts'][0]['is_primary'] === true, 'Ein einzelner Kontakt muss Primärkontakt sein.');
$assert($normalized['website_url'] === 'https://example.org/start', 'Website muss ein sicheres Standardschema erhalten.');
$assert($normalized['description_text'] === 'Ein lokales Angebot.', 'Freitext muss deterministisch normalisiert werden.');
$assert(strlen($normalized['identity_key']) === 64, 'Identity-Key muss SHA-256 sein.');
$assert(strlen($normalized['idempotency_key_hash']) === 64, 'Idempotency-Key muss gehasht gespeichert werden.');
$assert(!array_key_exists('privacy_confirmed', $normalized), 'Ein bloßes Checkbox-Flag darf nicht als Vertragsnachweis gespeichert werden.');

$sameIdentity = be_startpartner_normalize_intake([
    'source' => 'targeted_outreach',
    'organization_name' => 'beispiel verein e v',
    'contacts' => [
        ['email' => 'info@example.org', 'is_primary' => true],
        ['email' => 'zweite@example.org'],
    ],
    'form_version' => 'gate1-v1',
    'idempotency_key' => 'domain-contract-0002',
]);
$assert($sameIdentity['identity_key'] === $normalized['identity_key'], 'Großschreibung und Trennzeichen dürfen keine neue Identität erzeugen.');
$assert(count($sameIdentity['contacts']) === 2, 'Mehrere Kontakte müssen unterstützt werden.');
$assert($sameIdentity['contacts'][1]['is_primary'] === false, 'Nicht primäre Kontakte müssen erhalten bleiben.');

$expectException(static fn() => be_startpartner_normalize_intake([
    'source' => 'self_service',
    'organization_name' => 'Ohne Datenschutz',
    'email' => 'valid@example.org',
]), 'Self-Service ohne Datenschutzversion muss abgelehnt werden.');
$expectException(static fn() => be_startpartner_normalize_intake([
    'source' => 'targeted_outreach',
    'organization_name' => 'Doppelte Kontakte',
    'contacts' => [
        ['email' => 'same@example.org', 'is_primary' => true],
        ['email' => 'SAME@example.org'],
    ],
    'idempotency_key' => 'domain-contract-0003',
]), 'Doppelte normalisierte Kontaktadressen müssen abgelehnt werden.');
$expectException(static fn() => be_startpartner_normalize_intake([
    'source' => 'targeted_outreach',
    'organization_name' => 'Zwei Primärkontakte',
    'contacts' => [
        ['email' => 'one@example.org', 'is_primary' => true],
        ['email' => 'two@example.org', 'is_primary' => true],
    ],
    'idempotency_key' => 'domain-contract-0004',
]), 'Mehr als ein Primärkontakt muss abgelehnt werden.');

$assert(be_startpartner_transition_allowed('new', 'qualified'), 'new -> qualified muss erlaubt sein.');
$assert(be_startpartner_transition_allowed('qualified', 'waitlisted'), 'qualified -> waitlisted muss erlaubt sein.');
$assert(be_startpartner_transition_allowed('waitlisted', 'qualified'), 'waitlisted -> qualified muss für Kapazitätsfreigabe erlaubt sein.');
$assert(!be_startpartner_transition_allowed('rejected', 'qualified'), 'Terminale Zustände dürfen nicht reaktiviert werden.');
$assert(!be_startpartner_transition_allowed('new', 'new'), 'No-op-Transitionen dürfen kein Auditereignis erzeugen.');
$assert(be_startpartner_case_state('qualified') === 'decision_required', 'Qualifiziert muss als Entscheidung in der Steuerzentrale erscheinen.');
$assert(be_startpartner_case_state('waitlisted') === 'parked', 'Warteliste muss geparkt werden.');
$assert(be_startpartner_case_state('routed_to_regular_product') === 'done', 'Regulärer Produktweg muss Gate 1 abschließen.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Domain Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Domain Contract: OK ===\n";
