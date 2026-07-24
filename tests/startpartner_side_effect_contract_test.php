<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$startpartnerFiles = glob($root . '/api/startpartner/*.php') ?: [];
$assert(count($startpartnerFiles) === 7, 'Gate 1 muss genau Schema, Contract, Repository, Domain und drei Endpunkte besitzen.');
$combined = '';
foreach ($startpartnerFiles as $file) {
    $source = (string)file_get_contents($file);
    $combined .= "\n" . $source;
    $assert(!preg_match('/\b(CREATE|ALTER|DROP)\s+TABLE\b/i', $source), basename($file) . ' darf kein Runtime-DDL enthalten.');
}

foreach ([
    'be_send_mail',
    'stripe_checkout',
    'stripe_subscription',
    'publication_entitlements',
    'publication_consumptions',
    'INSERT INTO organizers',
    'INSERT INTO submissions',
    'curl_exec',
    'smtp_',
] as $forbiddenToken) {
    $assert(!str_contains($combined, $forbiddenToken), "Verbotene Nebenwirkung im Startpartner-Backend: {$forbiddenToken}");
}

$intake = (string)file_get_contents($root . '/api/startpartner/intake.php');
$candidates = (string)file_get_contents($root . '/api/startpartner/candidates.php');
$triage = (string)file_get_contents($root . '/api/startpartner/triage.php');
$contract = (string)file_get_contents($root . '/api/startpartner/_contract.php');
$domain = (string)file_get_contents($root . '/api/startpartner/_domain.php');
$repository = (string)file_get_contents($root . '/api/startpartner/_repository.php');
$schema = (string)file_get_contents($root . '/api/startpartner/_schema.php');

$assert(str_contains($intake, 'be_startpartner_require_gate1_environment'), 'Intake muss außerhalb Staging/Dev fail-closed sein.');
$assert(str_contains($intake, 'be_require_review_access'), 'Gate-1-Intake muss bis zum öffentlichen Cutover vollständig geschützt sein.');
$assert(str_contains($intake, "\$actorType = \$source === 'targeted_outreach' ? 'operator' : 'self_service'"), 'Beide Quellen müssen denselben Intake-Endpunkt mit korrektem Actor-Typ verwenden.');
$assert(str_contains($candidates, 'be_require_review_access'), 'Kandidatenliste muss geschützt sein.');
$assert(str_contains($triage, 'be_require_review_access'), 'Triage muss geschützt sein.');
$assert(str_contains($repository, "source_system' => 'startpartner_candidate'"), 'Control-Center-Projektion benötigt einen stabilen Source-System-Key.');
$assert(str_contains($schema, 'INFORMATION_SCHEMA.COLUMNS'), 'Runtime muss das versionierte Schema nur prüfen.');

$assert(!str_contains($contract, 'BE_STARTPARTNER_RETENTION_REVIEW_DAYS'), 'Gate 1 darf keine juristisch ungeklärte Aufbewahrungsdauer fest verdrahten.');
$assert(!preg_match('/RETENTION[^\n]*180|180[^\n]*RETENTION/i', $contract), 'Gate 1 darf 180 Tage nicht als Aufbewahrungsregel codieren.');
$assert(str_contains($contract, 'be_startpartner_normalize_retention_review_at'), 'Retention-Review muss als expliziter kontrollierter Eingang validiert werden.');
$assert(str_contains($domain, 'be_startpartner_assert_idempotent_replay_matches'), 'Idempotente Wiederholung muss den normalisierten Payload abgleichen.');
$assert(str_contains($domain, 'Idempotency-Key was already used with a different request payload.'), 'Abweichender Payload mit gleichem Idempotency-Key muss als Konflikt enden.');
$assert(str_contains($domain, "'detected_after_unique_conflict' => true"), 'Konkurrierende Dubletten müssen denselben nachvollziehbaren Auditpfad besitzen.');
$assert(str_contains($domain, 'be_startpartner_record_duplicate_after_race'), 'Unique-Konflikte müssen in einem eigenen atomaren Auditpfad nachgelesen werden.');

$publicHtml = (string)file_get_contents($root . '/startpartner/index.html');
$publicJs = (string)file_get_contents($root . '/js/startpartner-funnel.js');
$assert(str_contains($publicHtml, 'https://formspree.io/f/mrerpwjy'), 'Öffentliche Route muss in Gate 1 bei Formspree bleiben.');
$assert(str_contains($publicHtml, 'startpartner_6_months_limited'), 'Öffentlicher Lead-Typ muss unverändert bleiben.');
$assert(str_contains($publicJs, 'fetch('), 'Bestehender Formspree-Clientpfad muss unverändert vorhanden sein.');
$assert(!str_contains($publicHtml, '/api/startpartner/intake.php'), 'Öffentliches Formular darf noch nicht auf First Party umgestellt werden.');

$manifest = json_decode(
    (string)file_get_contents($root . '/api/sql/000_manifest.json'),
    true,
    512,
    JSON_THROW_ON_ERROR
);
$files = array_column((array)($manifest['migrations'] ?? []), 'file');
$assert(array_slice($files, -2) === ['007_runtime_schema_reconciliation.sql', '008_startpartner_candidates.sql'], 'Manifest muss Reconciliation vor Kandidatenschema ausführen.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Side-Effect Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Side-Effect Contract: OK ===\n";
