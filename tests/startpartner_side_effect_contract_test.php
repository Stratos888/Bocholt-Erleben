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
    '_schema.php', '_contract.php', '_repository.php', '_domain.php',
    '_gate2_domain.php', '_gate3_domain.php', '_gate3_presentation.php',
    '_gate4_contract.php', '_gate4_domain.php', '_gate4_schema.php',
    '_gate4_state.php', '_gate4_projection.php', '_gate4_operation.php',
    '_gate4_readiness_actions.php', '_gate4_activation_domain.php', '_gate4_portal_domain.php',
    'intake.php', 'candidates.php', 'profile.php', 'qualification.php',
    'action.php', 'capacity.php', 'pilot.php', 'onboarding.php', 'content.php', 'activation.php',
];
$startpartnerFiles = glob($root . '/api/startpartner/*.php') ?: [];
$actualNames = array_map('basename', $startpartnerFiles);
sort($actualNames);
$expectedNames = $expectedStartpartnerFiles;
sort($expectedNames);
$assert(
    $actualNames === $expectedNames,
    'Startpartner muss nach Gate 4 ausschließlich die kanonischen Runtime-Owner besitzen.'
);
foreach ([
    'triage.php',
    'gate2-staging-smoke-199.php',
    'gate2-staging-smoke-auto-199.php',
    'gate2-staging-lifecycle-199.php',
    'gate2-staging-status-199.php',
    '_gate3_staging_migration_231.php',
    '_gate3_staging_lifecycle_231.php',
] as $removedFile) {
    $assert(!is_file($root . '/api/startpartner/' . $removedFile), "Temporäre Startpartner-Datei muss entfernt sein: {$removedFile}");
}

$combined = '';
foreach ($startpartnerFiles as $file) {
    $source = (string)file_get_contents($file);
    $combined .= "\n" . $source;
    $assert(!preg_match('/\b(CREATE|ALTER|DROP)\s+TABLE\b/i', $source), basename($file) . ' darf kein Runtime-DDL enthalten.');
    if (basename($file) !== '_gate3_domain.php') {
        $assert(
            !str_contains($source, 'INSERT INTO organizers'),
            basename($file) . ' darf keinen Organizer anlegen.'
        );
    }
}

foreach ([
    'be_send_mail',
    'stripe_checkout',
    'stripe_subscription',
    'INSERT INTO organizer_magic_links',
    'INSERT INTO organizer_portal_sessions',
    'INSERT INTO subscriptions',
    'INSERT INTO publication_entitlements',
    'INSERT INTO publication_consumptions',
    'curl_exec',
    'smtp_',
] as $forbiddenToken) {
    $assert(!str_contains($combined, $forbiddenToken), "Verbotene Nebenwirkung im Startpartner-Backend: {$forbiddenToken}");
}

$gate4Files = glob($root . '/api/startpartner/_gate4_*.php') ?: [];
$gate4Combined = '';
foreach ($gate4Files as $file) {
    $gate4Combined .= "\n" . (string)file_get_contents($file);
}
$assert(substr_count($gate4Combined, 'INSERT INTO submissions') === 1, 'Nur der Gate-4-Portalowner darf genau einen Pilot-Submission-Insert besitzen.');
$assert(str_contains($gate4Combined, "payment_kind' => 'startpartner_pilot'"), 'Der Gate-4-Submission-Insert muss eindeutig als Pilotpfad markiert sein.');
$assert(str_contains($gate4Combined, "mail_effect' => 'none'"), 'Der Gate-4-Submissionpfad muss den No-Send-Vertrag belegen.');
$assert(str_contains($gate4Combined, "stripe_effect' => 'none'"), 'Der Gate-4-Submissionpfad muss den No-Stripe-Vertrag belegen.');

$intake = (string)file_get_contents($root . '/api/startpartner/intake.php');
$candidates = (string)file_get_contents($root . '/api/startpartner/candidates.php');
$profile = (string)file_get_contents($root . '/api/startpartner/profile.php');
$qualification = (string)file_get_contents($root . '/api/startpartner/qualification.php');
$action = (string)file_get_contents($root . '/api/startpartner/action.php');
$capacity = (string)file_get_contents($root . '/api/startpartner/capacity.php');
$pilot = (string)file_get_contents($root . '/api/startpartner/pilot.php');
$onboarding = (string)file_get_contents($root . '/api/startpartner/onboarding.php');
$content = (string)file_get_contents($root . '/api/startpartner/content.php');
$activation = (string)file_get_contents($root . '/api/startpartner/activation.php');
$gate2Domain = (string)file_get_contents($root . '/api/startpartner/_gate2_domain.php');
$gate3Domain = (string)file_get_contents($root . '/api/startpartner/_gate3_domain.php');
$gate3Presentation = (string)file_get_contents($root . '/api/startpartner/_gate3_presentation.php');
$contract = (string)file_get_contents($root . '/api/startpartner/_contract.php');
$domain = (string)file_get_contents($root . '/api/startpartner/_domain.php');
$repository = (string)file_get_contents($root . '/api/startpartner/_repository.php');
$schema = (string)file_get_contents($root . '/api/startpartner/_schema.php');
$controlAction = (string)file_get_contents($root . '/api/control-center/action.php');
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
    'pilot.php' => $pilot,
    'onboarding.php' => $onboarding,
    'activation.php' => $activation,
] as $name => $source) {
    $assert(str_contains($source, 'be_require_review_access'), "{$name} muss geschützt sein.");
    $assert(str_contains($source, 'be_startpartner_require_gate1_environment'), "{$name} muss außerhalb Staging/Dev fail-closed sein.");
}
$assert(str_contains($content, 'be_startpartner_gate4_portal_session'), 'Pilotinhalt darf nur über eine vorhandene Organizer-Session eingereicht werden.');
$assert(str_contains($content, 'be_startpartner_require_gate1_environment'), 'Pilotinhalt muss außerhalb Staging/Dev fail-closed sein.');
$assert(!str_contains($content, 'be_require_review_access'), 'Der eingeloggte Pilotinhaltpfad darf nicht das interne Reviewpasswort verlangen.');
$assert(str_contains($profile, 'BeStartpartnerConflictException'), 'Profiländerungen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($qualification, 'BeStartpartnerConflictException'), 'Qualifikationsänderungen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($action, 'BeStartpartnerConflictException'), 'Fachaktionen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($gate2Domain, 'expected_revision'), 'Jede Gate-2-Mutation benötigt eine erwartete Candidate-Revision.');
$assert(str_contains($gate2Domain, 'payload_hash'), 'Gate-2-Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate2Domain, 'be_startpartner_gate2_project_control_case'), 'Control-Center-Projektion muss aus der Startpartner-Domäne erfolgen.');
$assert(str_contains($gate3Domain, 'expected_revision'), 'Jede Gate-3-Mutation benötigt eine erwartete Candidate-Revision.');
$assert(str_contains($gate3Domain, 'payload_hash'), 'Gate-3-Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate3Domain, 'be_startpartner_gate3_project_control_case'), 'Gate-3-Projektion muss aus der Startpartner-Domäne erfolgen.');
$assert(str_contains($gate3Domain, "'pending_activation'"), 'Gate 3 muss die Pilotberechtigung fail-closed anlegen.');
$assert(str_contains($gate3Domain, 'be_startpartner_gate3_guard_gate2_action'), 'Gate 3 muss spätere Reservierungsänderungen blockieren.');
$assert(substr_count($gate3Domain, 'INSERT INTO organizers') === 1, 'Nur der atomare Gate-3-Owner darf genau einen Organizer-Insert besitzen.');
$assert(str_contains($gate3Presentation, 'Bedingungen bestätigen und Pilot anlegen'), 'Gate-3-Hauptaktion fehlt.');
$assert(str_contains($pilot, 'be_startpartner_gate3_state'), 'Pilot-Readback muss den bestehenden Gate-3-Owner einschließen.');
$assert(str_contains($gate4Combined, 'expected_pilot_revision'), 'Jede Gate-4-Mutation benötigt eine erwartete Pilotrevision.');
$assert(str_contains($gate4Combined, 'payload_hash'), 'Gate-4-Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate4Combined, 'be_startpartner_gate4_project_control_case'), 'Gate-4-Projektion muss aus der Startpartner-Domäne erfolgen.');
$assert(str_contains($gate4Combined, "status = 'released'"), 'Gate-4-Aktivierung muss die Reservierung geordnet freigeben.');
$assert(str_contains($controlAction, "\$sourceSystem === 'startpartner_candidate'"), 'Der generische Control-Center-Writer muss Startpartner-Fälle abweisen.');
$assert(str_contains($repository, "source_system' => 'startpartner_candidate'"), 'Control-Center-Projektion benötigt einen stabilen Source-System-Key.');
$assert(str_contains($schema, 'INFORMATION_SCHEMA.COLUMNS'), 'Runtime muss das versionierte Schema nur prüfen.');

foreach ([
    '_gate3_staging_migration_231.php',
    '_gate3_staging_lifecycle_231.php',
    'check_removed_gate3_temporary_endpoints',
    'check_gate3_staging_migration',
    'check_gate3_staging_lifecycle',
    'load_deploy_review_password',
    'write_gate3_diagnostic',
    '231_gate3_staging_lifecycle_completed',
    'deploy/api/_config.php',
] as $removedEvidenceToken) {
    $assert(!str_contains($deploySmoke, $removedEvidenceToken), "Generischer Deploy-Smoke enthält noch Gate-3-Evidence: {$removedEvidenceToken}");
}

foreach ([
    'gate2-staging-status-199.php',
    'gate2-staging-lifecycle-199.php',
    'gate2-cleanup-diagnostic-199.json',
    'check_gate2_staging_cleanup_status',
    'check_removed_gate2_temporary_endpoints',
    'GATE2_SYNTHETIC_199_',
    '199_gate2_staging_lifecycle_completed',
] as $removedEvidenceToken) {
    $assert(!str_contains($deploySmoke, $removedEvidenceToken), "Generischer Deploy-Smoke enthält noch temporäre Gate-2-Evidence: {$removedEvidenceToken}");
}

$assert(!str_contains($contract, 'BE_STARTPARTNER_RETENTION_REVIEW_DAYS'), 'Gate 1 darf keine juristisch ungeklärte Aufbewahrungsdauer fest verdrahten.');
$assert(!preg_match('/RETENTION[^\n]*180|180[^\n]*RETENTION/i', $contract), 'Gate 1 darf 180 Tage nicht als Aufbewahrungsregel codieren.');
$assert(str_contains($contract, 'be_startpartner_normalize_retention_review_at'), 'Retention-Review muss als expliziter kontrollierter Eingang validiert werden.');
$assert(str_contains($domain, 'be_startpartner_assert_idempotent_replay_matches'), 'Idempotente Wiederholung muss den normalisierten Payload abgleichen.');
$assert(str_contains($domain, 'Idempotency-Key was already used with a different request payload.'), 'Abweichender Payload mit gleichem Idempotency-Key muss als Konflikt enden.');
$assert(str_contains($domain, "'detected_after_unique_conflict' => true"), 'Konkurrierende Dubletten müssen denselben nachvollziehbaren Auditpfad besitzen.');
$assert(str_contains($domain, 'be_startpartner_record_duplicate_after_race'), 'Unique-Konflikte müssen in einem eigenen atomaren Auditpfad nachgelesen werden.');

$publicHtml = (string)file_get_contents($root . '/startpartner/index.html');
$publicJs = (string)file_get_contents($root . '/js/startpartner-funnel.js');
$assert(str_contains($publicHtml, 'https://formspree.io/f/mrerpwjy'), 'Öffentliche Route muss in Gate 4 bei Formspree bleiben.');
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
$gate2Index = array_search('010_startpartner_gate2_qualification_capacity.sql', $files, true);
$gate3Index = array_search('011_startpartner_gate3_terms_organizer_entitlement.sql', $files, true);
$gate4Index = array_search('012_startpartner_gate4_onboarding_content_activation.sql', $files, true);
$assert(
    $reconciliationIndex !== false && $candidateIndex === $reconciliationIndex + 1,
    'Manifest muss Reconciliation unmittelbar vor Kandidatenschema ausführen.'
);
$assert(
    $gate2Index !== false && $gate3Index === $gate2Index + 1,
    'Manifest muss Gate 3 unmittelbar nach dem Gate-2-Schema ausführen.'
);
$assert(
    $gate3Index !== false && $gate4Index === $gate3Index + 1,
    'Manifest muss Gate 4 unmittelbar nach dem Gate-3-Schema ausführen.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Side-Effect Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Side-Effect Contract: OK ===\n";
