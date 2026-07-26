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
    'gate2-staging-status-199.php',
];
$startpartnerFiles = glob($root . '/api/startpartner/*.php') ?: [];
$actualNames = array_map('basename', $startpartnerFiles);
sort($actualNames);
$expectedNames = $expectedStartpartnerFiles;
sort($expectedNames);
$assert(
    $actualNames === $expectedNames,
    'Startpartner muss genau die kanonischen Gate-1-/Gate-2-Owner und den zeitlich begrenzten Cleanup-Status besitzen.'
);
$assert(!is_file($root . '/api/startpartner/triage.php'), 'Der parallele Gate-1-Triage-Writer muss entfernt sein.');
$assert(!is_file($root . '/api/startpartner/gate2-staging-smoke-199.php'), 'Der fehlerhafte erste Lifecycle-Endpunkt muss entfernt bleiben.');
$assert(!is_file($root . '/api/startpartner/gate2-staging-smoke-auto-199.php'), 'Der fehlerhafte erste Lifecycle-Adapter muss entfernt bleiben.');
$assert(!is_file($root . '/api/startpartner/gate2-staging-lifecycle-199.php'), 'Der erfolgreich abgeschlossene finale Lifecycle-Endpunkt muss entfernt sein.');

$combined = '';
foreach ($startpartnerFiles as $file) {
    $source = (string)file_get_contents($file);
    $combined .= "\n" . $source;
    $assert(!preg_match('/\b(CREATE|ALTER|DROP)\s+TABLE\b/i', $source), basename($file) . ' darf kein eingebettetes Runtime-DDL enthalten.');
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
$stagingStatus = (string)file_get_contents($root . '/api/startpartner/gate2-staging-status-199.php');
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

$assert(str_contains($stagingStatus, "be_app_env_value() !== 'staging'"), 'Cleanup-Status muss außerhalb Staging unsichtbar bleiben.');
$assert(str_contains($stagingStatus, 'Bocholt-Erleben-Deploy-Smoke/1.0'), 'Cleanup-Status muss den kanonischen Deploy-Smoke verlangen.');
$assert(str_contains($stagingStatus, 'HTTP_X_BE_EXPECTED_BUILD'), 'Cleanup-Status muss an den exakten Build-Marker gebunden sein.');
$assert(str_contains($stagingStatus, 'function be_gate2_status_scalar'), 'Native PDO-SELECTs benötigen einen vollständig konsumierenden Scalar-Reader.');
$assert(str_contains($stagingStatus, '$statement->closeCursor()'), 'Native PDO-SELECT-Cursor müssen geschlossen werden.');
$assert(str_contains($stagingStatus, "BE_GATE2_COMPLETION_MARKER = '199_gate2_staging_lifecycle_completed'"), 'Cleanup muss ausschließlich den belegten Completion-Marker besitzen.');
$assert(str_contains($stagingStatus, 'SELECT GET_LOCK(:lock_name, 0)'), 'Marker-Cleanup benötigt einen exklusiven DB-Lock.');
$assert(str_contains($stagingStatus, 'SELECT RELEASE_LOCK(:lock_name)'), 'Marker-Cleanup muss den DB-Lock freigeben.');
$assert(str_contains($stagingStatus, 'DELETE FROM app_schema_migrations WHERE migration_key = :marker_key'), 'Cleanup darf ausschließlich den Completion-Marker löschen.');
$assert(substr_count($stagingStatus, 'DELETE FROM') === 1, 'Cleanup-Status darf genau eine DELETE-Anweisung besitzen.');
foreach ([
    'DELETE FROM startpartner_',
    'DELETE FROM control_',
    'DELETE FROM organizers',
    'DELETE FROM submissions',
    'DELETE FROM subscriptions',
    'DELETE FROM publication_',
] as $forbiddenDelete) {
    $assert(!str_contains($stagingStatus, $forbiddenDelete), "Cleanup-Status enthält verbotenen Delete: {$forbiddenDelete}");
}
$assert(str_contains($stagingStatus, '$deployAuthorized'), 'Diagnosezugriff darf den Marker-Cleanup nicht auslösen.');
$assert(str_contains($stagingStatus, "'cleanup_action'"), 'Cleanup-Antwort muss ihre Aktion explizit ausweisen.');
$assert(str_contains($stagingStatus, "'marker_cleanup'"), 'Cleanup-Antwort muss Before-/After-Evidence liefern.');
$assert(str_contains($stagingStatus, "'lifecycle_endpoint_present'"), 'Cleanup-Antwort muss die Entfernung des Lifecycle-Endpunkts beweisen.');
$assert(!str_contains($stagingStatus, 'gate2-staging-lifecycle-199.php\';'), 'Cleanup-Status darf den Lifecycle nicht mehr einbinden.');
$assert(!str_contains($stagingStatus, '.sql'), 'Cleanup-Status darf keine SQL-Migrationsdatei referenzieren.');
$assert(str_contains($stagingStatus, 'GATE2_SYNTHETIC_199_%'), 'Cleanup muss weiterhin sämtliche stabilen synthetischen Identitäten auf Residue prüfen.');

$assert(str_contains($deploySmoke, 'def check_gate2_staging_cleanup_status'), 'Deploy-Smoke muss Marker-Cleanup und Zero-Residue prüfen.');
$assert(str_contains($deploySmoke, '/api/startpartner/gate2-staging-status-199.php'), 'Deploy-Smoke muss ausschließlich den staging-only Cleanup-Status aufrufen.');
$assert(str_contains($deploySmoke, 'residue.get("total") != 0'), 'Deploy-Smoke muss Zero-Residue fail-fast prüfen.');
$assert(!str_contains($deploySmoke, 'gate2-staging-lifecycle-199.php'), 'Deploy-Smoke darf den entfernten Lifecycle nicht direkt aufrufen.');

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
