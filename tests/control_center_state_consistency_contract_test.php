<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_source_reconciliation.php';

$failures = [];
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$matrix = [
    ['inbox_feed', 'review', null],
    ['inbox_feed', 'später prüfen', null],
    ['inbox_feed', 'übernommen', 'done'],
    ['inbox_feed', 'verworfen', 'rejected'],
    ['inbox_feed', 'verwerfen', 'rejected'],
    ['submission_db', 'pending_review', null],
    ['submission_db', 'payment_released', null],
    ['submission_db', 'paid', null],
    ['submission_db', 'approved', 'done'],
    ['submission_db', 'rejected', 'rejected'],
    ['submission_db', 'withdrawn', 'done'],
    ['content_audit', 'open', null],
    ['content_audit', 'snoozed', null],
    ['content_audit', 'verified', 'done'],
    ['content_audit', 'corrected', 'done'],
    ['content_audit', 'ignored', 'rejected'],
];
foreach ($matrix as [$source, $status, $expected]) {
    $assert(be_cc_source_terminal_target($source, $status) === $expected, "Statusmatrix falsch: {$source}/{$status}");
}

$actionMatrix = [
    ['approve', '', 'done'],
    ['reject', '', 'rejected'],
    ['snooze', '', 'snoozed'],
    ['approve', 'wait', 'waiting'],
    ['complete', '', 'done'],
];
foreach ($actionMatrix as [$action, $effective, $expected]) {
    $assert(be_cc_action_expected_local_state($action, $effective) === $expected, "Lokaler Zielstatus falsch: {$action}/{$effective}");
}

$now = new DateTimeImmutable('2026-07-16 12:00:00');
$assert(be_cc_case_is_review_visible(['state'=>'decision_required'], $now), 'Offener Entscheidungsfall muss sichtbar sein.');
$assert(!be_cc_case_is_review_visible(['state'=>'rejected'], $now), 'Abgelehnter Fall darf nicht sichtbar sein.');
$assert(!be_cc_case_is_review_visible(['state'=>'snoozed','snoozed_until'=>'2026-07-20 00:00:00'], $now), 'Zukünftige Wiedervorlage darf nicht sichtbar sein.');
$assert(be_cc_case_is_review_visible(['state'=>'snoozed','snoozed_until'=>'2026-07-15 00:00:00'], $now), 'Fällige Wiedervorlage muss wieder sichtbar sein.');

$root = dirname(__DIR__);
$read = static fn(string $path): string => (string)file_get_contents($root . '/' . $path);
$action = $read('api/control-center/action.php');
$overview = $read('api/control-center/overview.php');
$sheetInbox = $read('api/control-center/_sheet_inbox_source.php');
$submissions = $read('api/control-center/_submission_source.php');
$sources = $read('api/control-center/_sources.php');
$reconciliation = $read('api/control-center/_source_reconciliation.php');
$verifiedWriteback = $read('api/control-center/_verified_source_writeback.php');
$activityAuditWriteback = $read('api/control-center/_activity_audit_writeback.php');
$inboxWriteback = $read('api/control-center/_inbox_decision_writeback.php');
$app = $read('js/control-center/app.js');
$reviewActions = $read('js/control-center/review-actions.js');
$shared = $read('js/control-center/shared.js');
$loader = $read('js/control-center.js');
$html = $read('steuerzentrale/index.html');

foreach (['be_cc_assert_case_postcondition','source_verified','completion','be_cc_writeback_inbox_approve_verified','be_cc_writeback_audit_verified','be_cc_writeback_activity_audit_verified'] as $marker) {
    $assert(str_contains($action, $marker), "Action-E2E-Vertrag fehlt: {$marker}");
}
foreach ([$sheetInbox, $submissions, $sources] as $sourceFile) {
    $assert(str_contains($sourceFile, 'be_cc_reconcile_source_case'), 'Eine Quellkette besitzt keine lokale Reconciliation.');
}
$assert(str_contains($sheetInbox, 'be_cc_reconcile_source_snooze'), 'Sheet-Inbox reconciliiert Quell-Wiedervorlagen nicht.');
$assert(str_contains($sources, 'be_cc_reconcile_source_snooze'), 'Fallback- oder Auditquelle reconciliiert Wiedervorlagen nicht.');
$assert(str_contains($sources, "'reopen_terminal' => true"), 'Wiederkehrende Content-Audit-Befunde können nicht neu geöffnet werden.');
$assert(str_contains($reconciliation, 'source_reconciled_snooze'), 'Gemeinsamer Snooze-Reconciliation-Vertrag fehlt.');
$assert(str_contains($activityAuditWriteback, 'be_cc_update_activity_in_repo'), 'Aktivitäts-Audit-Korrekturen besitzen keinen Repo-Writeback.');
$assert(!str_contains($activityAuditWriteback, 'be_cc_verified_current_event'), 'Aktivitäts-Audit darf keinen Event-Lookup verwenden.');
$assert(str_contains($reviewActions, 'activityAuditFields'), 'Frontend besitzt keinen typgerechten Aktivitäts-Audit-Editor.');
$assert(str_contains($overview, 'be_cc_sync_sheet_inbox'), 'Overview führt die führende Inbox-Synchronisation nicht aus.');
$assert(strpos($app, "api('/api/control-center/overview.php'") < strpos($app, "api('/api/control-center/cases.php?active=1'"), 'Quellsynchronisation muss vor der aktiven Fallabfrage stehen.');
$assert(!str_contains($app, "Promise.all([\n      api('/api/control-center/overview.php')"), 'Overview und Fallabfrage dürfen nicht parallel starten.');
$assert(str_contains($app, 'throwOnError'), 'Reload-Fehler werden nicht an Aktionen weitergegeben.');
$assert(str_contains($reviewActions, 'assertCompletion'), 'Frontend prüft den Endzustand nicht.');
$assert(str_contains($reviewActions, 'Entscheidung wurde gespeichert, aber die Ansicht konnte nicht konsistent aktualisiert werden'), 'Frontend unterscheidet gespeicherte Aktion und Reload-Fehler nicht.');
$assert(str_contains($shared, 'caseIsReviewVisible'), 'Wiedervorlagen besitzen keinen zentralen Sichtbarkeitsvertrag.');
$assert(str_contains($verifiedWriteback, "BE_CC_SOURCE_HTTP_TIMEOUT_SECONDS = 8"), 'Gemeinsamer Source-Writeback ist nicht hart begrenzt.');
$assert(str_contains($verifiedWriteback, 'be_cc_source_batch_update'), 'Gemeinsamer Batch-Writeback fehlt.');
$assert(str_contains($inboxWriteback, "'tab' => 'Inbox'"), 'Inbox-Tabelle liefert keinen vollständigen Batch-Vertrag.');

$modules = ['app','backlog','review','review-render','review-actions','manage','manage-render','manage-actions','development'];
foreach ($modules as $module) {
    $source = $read('js/control-center/' . $module . '.js');
    $assert(!str_contains($source, '2026-07-15-control-center-editorial-v1') && !str_contains($source, '2026-07-16-inbox-writeback-v3') && !str_contains($source, '2026-07-16-inbox-writeback-v4'), "Veraltete Modulversion in {$module}.js");
}
$assert(str_contains($loader, '2026-07-16-e2e-state-v5'), 'Top-Level-Loader lädt nicht das konsolidierte Bundle.');
$assert(str_contains($html, '2026-07-16-e2e-state-v5'), 'HTML lädt nicht den konsolidierten Controller.');

if ($failures) {
    fwrite(STDERR, "=== Control Center State Consistency Contract: FAILED ===\n");
    foreach ($failures as $failure) fwrite(STDERR, '- ' . $failure . "\n");
    exit(1);
}

echo "=== Control Center State Consistency Contract: OK ===\n";
