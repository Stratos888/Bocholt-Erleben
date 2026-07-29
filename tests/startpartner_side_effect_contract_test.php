<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$expectedStartpartnerFiles = [
    '_schema.php', '_contract.php', '_repository.php', '_domain.php',
    '_gate2_domain.php', '_gate3_domain.php', '_gate3_presentation.php', '_gate4_domain.php',
    'intake.php', 'candidates.php', 'profile.php', 'qualification.php',
    'action.php', 'capacity.php', 'pilot.php', 'onboarding.php', 'content.php', 'activation.php',
];
$startpartnerFiles = glob($root . '/api/startpartner/*.php') ?: [];
$actualNames = array_map('basename', $startpartnerFiles);
sort($actualNames);
$expectedNames = $expectedStartpartnerFiles;
sort($expectedNames);
$assert($actualNames === $expectedNames, 'Startpartner muss ausschließlich die kanonischen Gate-1-bis-Gate-4-Runtime-Owner besitzen.');

foreach (['triage.php','gate2-staging-smoke-199.php','gate2-staging-smoke-auto-199.php','gate2-staging-lifecycle-199.php','gate2-staging-status-199.php','_gate3_staging_migration_231.php','_gate3_staging_lifecycle_231.php'] as $removedFile) {
    $assert(!is_file($root . '/api/startpartner/' . $removedFile), "Temporäre Startpartner-Datei muss entfernt sein: {$removedFile}");
}

$combined = '';
foreach ($startpartnerFiles as $file) {
    $source = (string)file_get_contents($file);
    $combined .= "\n" . $source;
    $assert(!preg_match('/\b(CREATE|ALTER|DROP)\s+TABLE\b/i', $source), basename($file) . ' darf kein Runtime-DDL enthalten.');
    if (basename($file) !== '_gate3_domain.php') {
        $assert(!str_contains($source, 'INSERT INTO organizers'), basename($file) . ' darf keinen Organizer anlegen.');
    }
}
foreach (['be_send_mail','stripe_checkout','stripe_subscription','INSERT INTO organizer_magic_links','INSERT INTO organizer_portal_sessions','INSERT INTO subscriptions','INSERT INTO submissions','INSERT INTO publication_entitlements','INSERT INTO publication_consumptions','curl_exec','smtp_'] as $forbiddenToken) {
    $assert(!str_contains($combined, $forbiddenToken), "Verbotene Nebenwirkung im Startpartner-Backend: {$forbiddenToken}");
}

$files = [];
foreach (['intake','candidates','profile','qualification','action','capacity','pilot','onboarding','content','activation'] as $name) {
    $files[$name] = (string)file_get_contents($root . '/api/startpartner/' . $name . '.php');
}
$gate2Domain = (string)file_get_contents($root . '/api/startpartner/_gate2_domain.php');
$gate3Domain = (string)file_get_contents($root . '/api/startpartner/_gate3_domain.php');
$gate4Domain = (string)file_get_contents($root . '/api/startpartner/_gate4_domain.php');
$gate3Presentation = (string)file_get_contents($root . '/api/startpartner/_gate3_presentation.php');
$contract = (string)file_get_contents($root . '/api/startpartner/_contract.php');
$domain = (string)file_get_contents($root . '/api/startpartner/_domain.php');
$repository = (string)file_get_contents($root . '/api/startpartner/_repository.php');
$schema = (string)file_get_contents($root . '/api/startpartner/_schema.php');
$controlAction = (string)file_get_contents($root . '/api/control-center/action.php');
$deploySmoke = (string)file_get_contents($root . '/tools/smoke-check-deploy.py');

$assert(str_contains($files['intake'], 'be_startpartner_require_gate1_environment'), 'Intake muss außerhalb Staging/Dev fail-closed sein.');
$assert(str_contains($files['intake'], 'be_require_review_access'), 'Gate-1-Intake muss bis zum öffentlichen Cutover geschützt sein.');
$assert(str_contains($files['intake'], "\$actorType = \$source === 'targeted_outreach' ? 'operator' : 'self_service'"), 'Beide Quellen müssen denselben Intake-Endpunkt verwenden.');
foreach ($files as $name => $source) {
    $assert(str_contains($source, 'be_require_review_access'), "{$name}.php muss geschützt sein.");
    $assert(str_contains($source, 'be_startpartner_require_gate1_environment'), "{$name}.php muss außerhalb Staging/Dev fail-closed sein.");
}
$assert(str_contains($files['profile'], 'BeStartpartnerConflictException'), 'Profiländerungen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($files['qualification'], 'BeStartpartnerConflictException'), 'Qualifikationsänderungen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($files['action'], 'BeStartpartnerConflictException'), 'Fachaktionen müssen Konflikte als HTTP 409 behandeln.');
$assert(str_contains($files['activation'], 'BeStartpartnerConflictException'), 'Gate-4-Aktivierung muss Konflikte als HTTP 409 behandeln.');

$assert(str_contains($gate2Domain, 'expected_revision'), 'Gate 2 benötigt eine erwartete Candidate-Revision.');
$assert(str_contains($gate2Domain, 'payload_hash'), 'Gate-2-Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate2Domain, 'be_startpartner_gate2_project_control_case'), 'Gate-2-Projektion muss aus der Domäne erfolgen.');
$assert(str_contains($gate3Domain, 'expected_revision'), 'Gate 3 benötigt eine erwartete Candidate-Revision.');
$assert(str_contains($gate3Domain, 'payload_hash'), 'Gate-3-Operationen müssen payloadgebunden sein.');
$assert(str_contains($gate3Domain, 'be_startpartner_gate3_project_control_case'), 'Gate-3-Projektion muss aus der Domäne erfolgen.');
$assert(str_contains($gate3Domain, "'pending_activation'"), 'Gate 3 muss die Pilotberechtigung fail-closed anlegen.');
$assert(str_contains($gate3Domain, 'be_startpartner_gate3_guard_gate2_action'), 'Gate 3 muss spätere Reservierungsänderungen blockieren.');
$assert(substr_count($gate3Domain, 'INSERT INTO organizers') === 1, 'Nur der atomare Gate-3-Owner darf genau einen Organizer-Insert besitzen.');
$assert(str_contains($gate3Presentation, 'Bedingungen bestätigen und Pilot anlegen'), 'Gate-3-Hauptaktion fehlt.');
$assert(str_contains($files['pilot'], 'be_startpartner_gate3_state'), 'Pilot-Readback muss den Gate-3-Owner verwenden.');

$assert(str_contains($gate4Domain, 'BE_STARTPARTNER_GATE4_REQUIRED_ITEMS'), 'Gate 4 benötigt die kanonische Onboarding-Checkliste.');
$assert(substr_count($gate4Domain, "'terms_confirmed'") === 1, 'Gate-4-Checkliste darf nicht dupliziert werden.');
$assert(str_contains($gate4Domain, 'expected_pilot_revision'), 'Gate-4-Aktivierung benötigt eine erwartete Pilotrevision.');
$assert(str_contains($gate4Domain, 'payload_hash'), 'Gate-4-Aktivierung muss payloadgebunden sein.');
$assert(str_contains($gate4Domain, "status='active'"), 'Gate 4 muss Pilot, Scopes und Berechtigung explizit aktivieren.');
$assert(str_contains($gate4Domain, "status='released'"), 'Gate 4 muss die aktive Reservierung geordnet freigeben.');
$assert(str_contains($gate4Domain, 'Europe/Berlin'), 'Gate 4 muss die lokale Aktivierungszeitzone festlegen.');
$assert(str_contains($gate4Domain, 'startpartner_pilot_usages'), 'Pilotverbräuche benötigen einen eigenen Owner.');
$assert(!str_contains($gate4Domain, 'publication_consumptions'), 'Pilotverbräuche dürfen reguläre Publication-Consumptions nicht verwenden.');

$assert(str_contains($controlAction, "\$sourceSystem === 'startpartner_candidate'"), 'Der generische Control-Center-Writer muss Startpartner-Fälle abweisen.');
$assert(str_contains($repository, "source_system' => 'startpartner_candidate'"), 'Control-Center-Projektion benötigt einen stabilen Source-System-Key.');
$assert(str_contains($schema, 'INFORMATION_SCHEMA.COLUMNS'), 'Runtime muss das versionierte Schema nur prüfen.');

foreach (['_gate3_staging_migration_231.php','_gate3_staging_lifecycle_231.php','check_removed_gate3_temporary_endpoints','check_gate3_staging_migration','check_gate3_staging_lifecycle','load_deploy_review_password','write_gate3_diagnostic','231_gate3_staging_lifecycle_completed','deploy/api/_config.php','gate2-staging-status-199.php','gate2-staging-lifecycle-199.php','gate2-cleanup-diagnostic-199.json','check_gate2_staging_cleanup_status','check_removed_gate2_temporary_endpoints','GATE2_SYNTHETIC_199_','199_gate2_staging_lifecycle_completed'] as $removedEvidenceToken) {
    $assert(!str_contains($deploySmoke, $removedEvidenceToken), "Generischer Deploy-Smoke enthält temporäre Evidence: {$removedEvidenceToken}");
}

$assert(!str_contains($contract, 'BE_STARTPARTNER_RETENTION_REVIEW_DAYS'), 'Gate 1 darf keine juristisch ungeklärte Aufbewahrungsdauer fest verdrahten.');
$assert(!preg_match('/RETENTION[^\n]*180|180[^\n]*RETENTION/i', $contract), 'Gate 1 darf 180 Tage nicht als Aufbewahrungsregel codieren.');
$assert(str_contains($contract, 'be_startpartner_normalize_retention_review_at'), 'Retention-Review muss kontrolliert validiert werden.');
$assert(str_contains($domain, 'be_startpartner_assert_idempotent_replay_matches'), 'Idempotente Wiederholung muss den Payload abgleichen.');
$assert(str_contains($domain, 'Idempotency-Key was already used with a different request payload.'), 'Abweichender Payload muss als Konflikt enden.');
$assert(str_contains($domain, "'detected_after_unique_conflict' => true"), 'Konkurrierende Dubletten benötigen denselben Auditpfad.');
$assert(str_contains($domain, 'be_startpartner_record_duplicate_after_race'), 'Unique-Konflikte benötigen einen atomaren Auditpfad.');

$publicHtml=(string)file_get_contents($root.'/startpartner/index.html');
$publicJs=(string)file_get_contents($root.'/js/startpartner-funnel.js');
$assert(str_contains($publicHtml,'https://formspree.io/f/mrerpwjy'),'Öffentliche Route muss weiterhin Formspree verwenden.');
$assert(str_contains($publicHtml,'startpartner_6_months_limited'),'Öffentlicher Lead-Typ muss unverändert bleiben.');
$assert(str_contains($publicJs,'fetch('),'Bestehender Formspree-Clientpfad muss vorhanden sein.');
$assert(!str_contains($publicHtml,'/api/startpartner/intake.php'),'Öffentliches Formular darf nicht auf First Party umgestellt werden.');

$manifest=json_decode((string)file_get_contents($root.'/api/sql/000_manifest.json'),true,512,JSON_THROW_ON_ERROR);
$migrationFiles=array_column((array)($manifest['migrations']??[]),'file');
$reconciliationIndex=array_search('007_runtime_schema_reconciliation.sql',$migrationFiles,true);
$candidateIndex=array_search('008_startpartner_candidates.sql',$migrationFiles,true);
$gate2Index=array_search('010_startpartner_gate2_qualification_capacity.sql',$migrationFiles,true);
$gate3Index=array_search('011_startpartner_gate3_terms_organizer_entitlement.sql',$migrationFiles,true);
$gate4Index=array_search('012_startpartner_gate4_onboarding_content_activation.sql',$migrationFiles,true);
$assert($reconciliationIndex!==false && $candidateIndex===$reconciliationIndex+1,'Manifest muss Reconciliation unmittelbar vor Kandidatenschema ausführen.');
$assert($gate2Index!==false && $gate3Index===$gate2Index+1,'Manifest muss Gate 3 unmittelbar nach Gate 2 ausführen.');
$assert($gate3Index!==false && $gate4Index===$gate3Index+1,'Manifest muss Gate 4 unmittelbar nach Gate 3 ausführen.');

if ($failures!==[]) {
    fwrite(STDERR,"=== Startpartner Side-Effect Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR,'- '.$failure."\n");
    exit(1);
}
echo "=== Startpartner Side-Effect Contract: OK ===\n";
