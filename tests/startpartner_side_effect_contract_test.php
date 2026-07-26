<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};

$expectedStartpartnerFiles = [
    '_schema.php', '_contract.php', '_repository.php', '_domain.php', '_gate2_domain.php',
    'intake.php', 'candidates.php', 'profile.php', 'qualification.php', 'action.php', 'capacity.php',
    'gate2-staging-smoke-199.php', 'gate2-staging-smoke-auto-199.php',
];
$startpartnerFiles = glob($root . '/api/startpartner/*.php') ?: [];
$actualNames = array_map('basename', $startpartnerFiles);
sort($actualNames);
$expectedNames = $expectedStartpartnerFiles;
sort($expectedNames);
$assert($actualNames === $expectedNames, 'Startpartner muss genau die kanonischen Gate-1-/Gate-2-Owner und die zeitlich begrenzten Evidence-Endpunkte besitzen.');
$assert(!is_file($root . '/api/startpartner/triage.php'), 'Der parallele Gate-1-Triage-Writer muss entfernt sein.');

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
$profile = (string)file_get_contents($root . '/api/startpartner/profile.php');
$qualification = (string)file_get_contents($root . '/api/startpartner/qualification.php');
$action = (string)file_get_contents($root . '/api/startpartner/action.php');
$capacity = (string)file_get_contents($root . '/api/startpartner/capacity.php');
$gate2Domain = (string)file_get_contents($root . '/api/startpartner/_gate2_domain.php');
$contract = (string)file_get_contents($root . '/api/startpartner/_contract.php');
$domain = (string)file_get_contents($root . '/api/startpartner/_domain.php');
$repository = (string)file_get_contents($root . '/api/startpartner/_repository.php');
$schema = (string)file_get_contents($root . '/api/startpartner/_schema.php');
$controlAction = (string)file_get_contents($root . '/api/control-center/action.php');
$stagingSmoke = (string)file_get_contents($root . '/api/startpartner/gate2-staging-smoke-199.php');
$stagingSmokeAuto = (string)file_get_contents($root . '/api/startpartner/gate2-staging-smoke-auto-199.php');
$deploySmoke = (string)file_get_contents($root . '/tools/smoke-check-deploy.py');

$assert(str_contains($intake, 'be_startpartner_require_gate1_environment'), 'Intake muss außerhalb Staging/Dev fail-closed sein.');
$assert(str_contains($intake, 'be_require_review_access'), 'Gate-1-Intake muss bis zum öffentlichen Cutover vollständig geschützt sein.');
$assert(str_contains($intake, "\$actorType = \$source === 'targeted_outreach' ? 'operator' : 'self_service'"), 'Beide Quellen müssen denselben Intake-Endpunkt mit korrektem Actor-Typ verwenden.');
foreach ([
    'candidates.php' => $candidates,
    'profile.php' => $profile,
    'qualification.php' => $qualification,
    'action.php' => $action,
    'capacity.php' => $capacity,
] as $name => $source) {
    $assert(str_contains($source, 'be_require_review_access'), "{$name} muss geschützt sein.");
    $assert(str_contains($source, 'be_startpartner_require_gate1_environment'), "{$name} muss außerhalb Staging/Dev fail-closed sein.");
}
$assert(str_contains($profile, 'BeStartpartnerConflictException'), 'Profiländerungen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($qualification, 'BeStartpartnerConflictException'), 'Qualifikationsänderungen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($action, 'BeStartpartnerConflictException'), 'Fachaktionen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($gate2Domain, 'expected_revision'), 'Jede Gate-2-Mutation benötigt eine erwartete Candidate-Revision.');
$assert(str_contains($gate2Domain, 'payload_hash'), 'Gate-2-Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate2Domain, 'be_startpartner_gate2_project_control_case'), 'Control-Center-Projektion muss aus der Startpartner-Domäne erfolgen.');
$assert(str_contains($controlAction, "\$sourceSystem === 'startpartner_candidate'"), 'Der generische Control-Center-Writer muss Startpartner-Fälle abweisen.');
$assert(str_contains($repository, "source_system' => 'startpartner_candidate'"), 'Control-Center-Projektion benötigt einen stabilen Source-System-Key.');
$assert(str_contains($schema, 'INFORMATION_SCHEMA.COLUMNS'), 'Runtime muss das versionierte Schema nur prüfen.');

$assert(str_contains($stagingSmoke, "be_app_env_value() !== 'staging'"), 'Evidence-Endpunkt muss außerhalb Staging unsichtbar bleiben.');
$assert(str_contains($stagingSmoke, 'BE_GATE2_SMOKE_TOKEN_HASH'), 'Evidence-Endpunkt muss tokengebunden sein.');
$assert(str_contains($stagingSmoke, "GET_LOCK('bocholt_gate2_staging_smoke_199'"), 'Evidence-Endpunkt muss einen exklusiven DB-Lock besitzen.');
$assert(str_contains($stagingSmoke, 'be_gate2_smoke_locked_counts'), 'Evidence-Endpunkt muss gesperrte Tabellen vor und nach dem Lauf vergleichen.');
$assert(str_contains($stagingSmoke, 'be_gate2_smoke_cleanup'), 'Evidence-Endpunkt muss einen garantierten Cleanup besitzen.');
$assert(str_contains($stagingSmoke, 'GATE2_SYNTHETIC_199_'), 'Evidence-Endpunkt muss stabile synthetische Identitäten verwenden.');
$assert(str_contains($stagingSmoke, '009_control_center_runtime_schema.sql'), 'Evidence-Endpunkt muss ausschließlich die versionierte Control-Center-Migration anwenden.');
$assert(str_contains($stagingSmoke, '010_startpartner_gate2_qualification_capacity.sql'), 'Evidence-Endpunkt muss ausschließlich die versionierte Gate-2-Migration anwenden.');
$assert(!str_contains($stagingSmoke, 'BE_GATE2_SMOKE_TOKEN_HASH = ' . "'oud"), 'Der Klartexttoken darf nicht im fachlichen Evidence-Endpunkt stehen.');

$assert(str_contains($stagingSmokeAuto, "be_app_env_value() !== 'staging'"), 'Deploy-Smoke-Adapter muss außerhalb Staging unsichtbar bleiben.');
$assert(str_contains($stagingSmokeAuto, "Bocholt-Erleben-Deploy-Smoke/1.0"), 'Deploy-Smoke-Adapter muss den kanonischen Smoke-User-Agent verlangen.');
$assert(str_contains($stagingSmokeAuto, 'HTTP_X_BE_EXPECTED_BUILD'), 'Deploy-Smoke-Adapter muss den exakten Build-Marker verlangen.');
$assert(str_contains($stagingSmokeAuto, "'/meta/build.txt'"), 'Deploy-Smoke-Adapter muss den deployten Build serverseitig zurücklesen.');
$assert(str_contains($stagingSmokeAuto, "require __DIR__ . '/gate2-staging-smoke-199.php'"), 'Deploy-Smoke-Adapter darf keinen parallelen Lifecycle besitzen.');

$assert(str_contains($deploySmoke, 'def check_gate2_staging_lifecycle'), 'Deploy-Smoke muss den Gate-2-Lifecycle explizit besitzen.');
$assert(str_contains($deploySmoke, '/api/startpartner/gate2-staging-smoke-auto-199.php'), 'Deploy-Smoke muss ausschließlich den staging-only Adapter aufrufen.');
$assert(str_contains($deploySmoke, 'headers={"X-BE-Expected-Build": build_id}'), 'Deploy-Smoke muss den exakten Build-Marker mitsenden.');
$assert(str_contains($deploySmoke, 'result = request_url('), 'Gate-2-Lifecycle muss als einzelner Request ohne Retry ausgeführt werden.');
$assert(str_contains($deploySmoke, 'cleanup.get("residue", {}).get("total") != 0'), 'Deploy-Smoke muss Zero-Residue-Cleanup fail-fast prüfen.');
$assert(str_contains($deploySmoke, 'before.get("locked_counts") != after.get("locked_counts")'), 'Deploy-Smoke muss gesperrte Tabellen vor/nach dem Lauf vergleichen.');

$assert(!str_contains($contract, 'BE_STARTPARTNER_RETENTION_REVIEW_DAYS'), 'Gate 1 darf keine juristisch ungeklärte Aufbewahrungsdauer fest verdrahten.');
$assert(!preg_match('/RETENTION[^\n]*180|180[^\n]*RETENTION/i', $contract), 'Gate 1 darf 180 Tage nicht als Aufbewahrungsregel codieren.');
$assert(str_contains($contract, 'be_startpartner_normalize_retention_review_at'), 'Retention-Review muss als expliziter kontrollierter Eingang validiert werden.');
$assert(str_contains($domain, 'be_startpartner_assert_idempotent_replay_matches'), 'Idempotente Wiederholung muss den normalisierten Payload abgleichen.');
$assert(str_contains($domain, 'Idempotency-Key was already used with a different request payload.'), 'Abweichender Payload mit gleichem Idempotency-Key muss als Konflikt enden.');
$assert(str_contains($domain, "'detected_after_unique_conflict' => true"), 'Konkurrierende Dubletten müssen denselben nachvollziehbaren Auditpfad besitzen.');
$assert(str_contains($domain, 'be_startpartner_record_duplicate_after_race'), 'Unique-Konflikte müssen in einem eigenen atomaren Auditpfad nachgelesen werden.');

$publicHtml = (string)file_get_contents($root . '/startpartner/index.html');
$publicJs = (string)file_get_contents($root . '/js/startpartner-funnel.js');
$assert(str_contains($publicHtml, 'https://formspree.io/f/mrerpwjy'), 'Öffentliche Route muss in Gate 2 bei Formspree bleiben.');
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
$reconciliationIndex = array_search('007_runtime_schema_reconciliation.sql', $files, true);
$candidateIndex = array_search('008_startpartner_candidates.sql', $files, true);
$assert(
    $reconciliationIndex !== false && $candidateIndex === $reconciliationIndex + 1,
    'Manifest muss Reconciliation unmittelbar vor Kandidatenschema ausführen.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Side-Effect Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Side-Effect Contract: OK ===\n";
