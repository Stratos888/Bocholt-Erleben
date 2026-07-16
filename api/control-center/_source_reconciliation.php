<?php
declare(strict_types=1);

/**
 * Canonical source-to-local state contract for all source-backed control-center cases.
 * Source systems remain authoritative; local cases are projections of their current state.
 */

function be_cc_source_state_token(mixed $value): string
{
    $text = trim((string)$value);
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    return trim((string)preg_replace('/\s+/u', ' ', $text));
}

function be_cc_source_terminal_target(string $sourceSystem, mixed $sourceStatus): ?string
{
    $status = be_cc_source_state_token($sourceStatus);

    if ($sourceSystem === 'inbox_feed') {
        if (in_array($status, ['übernommen', 'uebernommen', 'approved', 'done', 'archived'], true)) return 'done';
        if (in_array($status, ['verworfen', 'verwerfen', 'rejected'], true)) return 'rejected';
        return null;
    }

    if ($sourceSystem === 'submission_db') {
        if (in_array($status, ['approved', 'published'], true)) return 'done';
        if ($status === 'rejected') return 'rejected';
        if (in_array($status, ['cancelled', 'canceled', 'withdrawn', 'expired'], true)) return 'done';
        return null;
    }

    if ($sourceSystem === 'content_audit') {
        if ($status === 'ignored') return 'rejected';
        if (in_array($status, ['done', 'checked', 'verified', 'corrected', 'resolved', 'archived'], true)) return 'done';
        return null;
    }

    return null;
}

function be_cc_action_expected_local_state(string $action, string $effectiveAction = ''): ?string
{
    $action = trim($effectiveAction !== '' ? $effectiveAction : $action);
    return match ($action) {
        'approve', 'complete' => 'done',
        'reject' => 'rejected',
        'snooze' => 'snoozed',
        'wait' => 'waiting',
        'block' => 'blocked',
        default => null,
    };
}

function be_cc_case_is_review_visible(array $row, ?DateTimeImmutable $now = null): bool
{
    $state = trim((string)($row['state'] ?? ''));
    if (in_array($state, ['done', 'rejected', 'information', 'parked'], true)) return false;
    if ($state !== 'snoozed') return true;

    $until = trim((string)($row['snoozed_until'] ?? ''));
    if ($until === '') return false;
    try {
        $deadline = new DateTimeImmutable($until);
        return $deadline <= ($now ?? new DateTimeImmutable('now'));
    } catch (Throwable) {
        return false;
    }
}

function be_cc_reconcile_source_case(PDO $pdo, string $sourceSystem, string $sourceReference, string $targetState, array $evidence = []): int
{
    if (!in_array($targetState, ['done', 'rejected'], true)) {
        throw new InvalidArgumentException('Source reconciliation only accepts terminal local states.');
    }

    $lookup = $pdo->prepare('SELECT id, state FROM control_cases WHERE source_system=:system AND source_reference=:reference');
    $lookup->execute(['system' => $sourceSystem, 'reference' => $sourceReference]);
    $rows = $lookup->fetchAll(PDO::FETCH_ASSOC);
    $changed = 0;

    foreach ($rows as $row) {
        $caseId = (string)($row['id'] ?? '');
        $from = (string)($row['state'] ?? '');
        if ($caseId === '' || $from === $targetState) continue;

        $update = $pdo->prepare('UPDATE control_cases SET state=:state, completed_at=NOW(), snoozed_until=NULL, blocked_reason=NULL, updated_at=NOW() WHERE id=:id');
        $update->execute(['state' => $targetState, 'id' => $caseId]);
        if ($update->rowCount() !== 1) continue;

        if (function_exists('be_cc_record_event')) {
            be_cc_record_event($pdo, $caseId, 'source_reconciled', $from, $targetState, $evidence + [
                'source_system' => $sourceSystem,
                'source_reference' => $sourceReference,
            ], 'system');
        }
        $changed++;
    }

    return $changed;
}

function be_cc_reconcile_source_snooze(PDO $pdo, string $sourceSystem, string $sourceReference, string $until = '', array $evidence = []): int
{
    $normalizedUntil = null;
    if (trim($until) !== '') {
        try {
            $normalizedUntil = (new DateTimeImmutable($until))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            throw new InvalidArgumentException('Source snooze date is invalid.');
        }
    }

    $lookup = $pdo->prepare("SELECT id, state, snoozed_until FROM control_cases WHERE source_system=:system AND source_reference=:reference AND state NOT IN ('done','rejected','parked','information')");
    $lookup->execute(['system' => $sourceSystem, 'reference' => $sourceReference]);
    $rows = $lookup->fetchAll(PDO::FETCH_ASSOC);
    $changed = 0;

    foreach ($rows as $row) {
        $caseId = (string)($row['id'] ?? '');
        $from = (string)($row['state'] ?? '');
        if ($caseId === '') continue;
        $effectiveUntil = $normalizedUntil ?? ($row['snoozed_until'] ?? null);
        if ($from === 'snoozed' && (string)($row['snoozed_until'] ?? '') === (string)$effectiveUntil) continue;

        $update = $pdo->prepare('UPDATE control_cases SET state=\'snoozed\', snoozed_until=:until, completed_at=NULL, blocked_reason=NULL, updated_at=NOW() WHERE id=:id');
        $update->execute(['until' => $effectiveUntil, 'id' => $caseId]);
        if ($update->rowCount() !== 1) continue;

        if (function_exists('be_cc_record_event')) {
            be_cc_record_event($pdo, $caseId, 'source_reconciled_snooze', $from, 'snoozed', $evidence + [
                'source_system' => $sourceSystem,
                'source_reference' => $sourceReference,
                'snoozed_until' => $effectiveUntil,
            ], 'system');
        }
        $changed++;
    }

    return $changed;
}

function be_cc_assert_case_postcondition(PDO $pdo, string $caseId, string $expectedState): array
{
    $stmt = $pdo->prepare('SELECT id, state, snoozed_until, completed_at FROM control_cases WHERE id=:id');
    $stmt->execute(['id' => $caseId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new RuntimeException('Der lokale Vorgang konnte nach der Aktion nicht zurückgelesen werden.');

    $actualState = (string)($row['state'] ?? '');
    if ($actualState !== $expectedState) {
        throw new RuntimeException(sprintf('Lokaler Abschluss nicht bestätigt: erwartet "%s", gefunden "%s".', $expectedState, $actualState));
    }

    $reviewVisible = be_cc_case_is_review_visible($row);
    if (in_array($expectedState, ['done', 'rejected', 'snoozed'], true) && $reviewVisible) {
        throw new RuntimeException('Der abgeschlossene oder zurückgestellte Vorgang ist weiterhin als aktive Prüfung sichtbar.');
    }

    return [
        'verified' => true,
        'state' => $actualState,
        'review_visible' => $reviewVisible,
        'completed_at' => $row['completed_at'] ?? null,
        'snoozed_until' => $row['snoozed_until'] ?? null,
    ];
}
