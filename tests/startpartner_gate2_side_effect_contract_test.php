<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$gate2Domain = (string)file_get_contents($root . '/api/startpartner/_gate2_domain.php');
$profile = (string)file_get_contents($root . '/api/startpartner/profile.php');
$qualification = (string)file_get_contents($root . '/api/startpartner/qualification.php');
$action = (string)file_get_contents($root . '/api/startpartner/action.php');
$capacity = (string)file_get_contents($root . '/api/startpartner/capacity.php');
$candidates = (string)file_get_contents($root . '/api/startpartner/candidates.php');
$controlAction = (string)file_get_contents($root . '/api/control-center/action.php');
$gate2Migration = (string)file_get_contents($root . '/api/sql/010_startpartner_gate2_qualification_capacity.sql');

foreach ([$profile, $qualification, $action, $capacity, $candidates] as $endpoint) {
    $assert(str_contains($endpoint, 'be_require_review_access'), 'Jeder Gate-2-Endpunkt muss Reviewzugang verlangen.');
    $assert(str_contains($endpoint, 'be_startpartner_require_gate1_environment'), 'Jeder Gate-2-Endpunkt muss außerhalb Staging/Dev fail-closed sein.');
}
foreach ([$profile, $qualification, $action] as $mutationEndpoint) {
    $assert(str_contains($mutationEndpoint, 'STARTPARTNER_CONFLICT'), 'Mutationsendpunkte müssen stale writes als stabilen Konflikt ausgeben.');
    $assert(str_contains($mutationEndpoint, 'Zwischenzeitlich geändert.'), 'Konflikte benötigen klare deutsche UI-Sprache.');
}

$assert(str_contains($gate2Domain, 'startpartner_candidate_operations'), 'Operations-Idempotenz muss einen eigenen fachlichen Owner verwenden.');
$assert(str_contains($gate2Domain, 'candidate_revision_before'), 'Operationen müssen die vorherige Candidate-Revision sichern.');
$assert(str_contains($gate2Domain, 'candidate_revision_after'), 'Operationen müssen die neue Candidate-Revision sichern.');
$assert(str_contains($gate2Domain, 'FOR UPDATE'), 'Gate-2-Mutationen müssen Kandidat und Kapazität sperren.');
$assert(str_contains($gate2Domain, 'be_startpartner_gate2_payload_hash'), 'Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate2Domain, 'be_startpartner_gate2_project_control_case'), 'Control-Center-Projektion muss innerhalb des Domain-Writes aktualisiert werden.');
$assert(str_contains($gate2Domain, 'decision_readiness_revoked'), 'Verschlechterte Qualifikation muss Entscheidungsreife fail-closed aufheben.');
$assert(str_contains($gate2Domain, 'BE_STARTPARTNER_CAPACITY_SOFT_STOP = 6'), 'Soft-Stop muss bei sechs Reservierungen liegen.');
$assert(str_contains($gate2Domain, 'BE_STARTPARTNER_CAPACITY_HARD_STOP = 8'), 'Hard-Stop muss bei acht Reservierungen liegen.');
$assert(str_contains($gate2Domain, 'BE_STARTPARTNER_RESERVATION_MAX_DAYS = 30'), 'Reservierungen dürfen maximal 30 Tage laufen.');
$assert(str_contains($controlAction, "\$sourceSystem === 'startpartner_candidate'"), 'Generischer Control-Center-Writer muss Startpartner-Fälle abweisen.');
$assert(!is_file($root . '/api/startpartner/triage.php'), 'Paralleler Triage-Writer darf nicht bestehen bleiben.');

$assert(!str_contains($gate2Migration, 'ADD COLUMN IF NOT EXISTS'), 'Migration 010 darf keine MariaDB-only ADD-COLUMN-Syntax verwenden.');
$assert(!str_contains($gate2Migration, 'CREATE INDEX IF NOT EXISTS'), 'Migration 010 darf keine nicht portable CREATE-INDEX-Syntax verwenden.');
$assert(str_contains($gate2Migration, 'INFORMATION_SCHEMA.COLUMNS'), 'Migration 010 muss Spalten portabel reconciliieren.');
$assert(str_contains($gate2Migration, 'INFORMATION_SCHEMA.STATISTICS'), 'Migration 010 muss Indizes portabel reconciliieren.');
$assert(str_contains($gate2Migration, 'PREPARE be_stmt FROM @be_sql'), 'Migration 010 muss dieselbe bewährte dynamische Reconciliation wie Migration 007 nutzen.');
$assert(!str_contains($gate2Migration, 'candidate_revision_after BIGINT UNSIGNED NULL CHECK'), 'Spaltenlokaler CHECK darf keine andere Spalte referenzieren.');
$assert(str_contains($gate2Migration, 'CONSTRAINT chk_startpartner_operations_revision_order CHECK'), 'Revisionsreihenfolge benötigt einen portablen Tabellen-CHECK.');

$combined = implode("\n", [$gate2Domain, $profile, $qualification, $action, $capacity, $candidates]);
foreach ([
    'be_send_mail', 'stripe_checkout', 'stripe_subscription',
    'INSERT INTO organizers', 'INSERT INTO submissions',
    'publication_entitlements', 'publication_consumptions',
    'curl_exec', 'smtp_',
] as $forbidden) {
    $assert(!str_contains($combined, $forbidden), "Gate 2 darf keine gesperrte Nebenwirkung enthalten: {$forbidden}");
}

$publicHtml = (string)file_get_contents($root . '/startpartner/index.html');
$assert(str_contains($publicHtml, 'https://formspree.io/f/mrerpwjy'), 'Öffentlicher Startpartner-Funnel muss weiterhin Formspree verwenden.');
$assert(!str_contains($publicHtml, '/api/startpartner/'), 'Öffentlicher Funnel darf keinen Gate-2-Endpunkt verwenden.');

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-2 Side-Effect Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-2 Side-Effect Contract: OK ===\n";
