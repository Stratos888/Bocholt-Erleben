<?php
declare(strict_types=1);

require_once __DIR__ . '/_github_repo.php';

function be_cc_activity_audit_updates(array $updates): array
{
    $mapping = [
        'title' => 'title',
        'description' => 'description',
        'category' => 'kategorie',
        'location' => 'location',
        'address' => 'maps_query',
        'source_url' => 'url',
        'visual_key' => 'visual_key',
    ];
    $resolved = [];
    foreach ($mapping as $source => $target) {
        if (!array_key_exists($source, $updates)) continue;
        $resolved[$target] = trim((string)$updates[$source]);
    }
    return be_cc_normalize_activity_updates($resolved);
}

function be_cc_writeback_activity_audit_verified(array $case, string $action, array $payload, array $decision): array
{
    $startedAt = microtime(true);
    $source = be_cc_editorial_payload($case);
    $table = be_cc_content_audit_verified_table();
    $identity = be_cc_audit_identity($source);
    $resolution = be_cc_resolve_audit_row($table['rows'], $identity);
    if (($resolution['status'] ?? '') !== 'resolved') {
        throw new DomainException('Der Aktivitäts-Auditfall konnte nicht eindeutig aufgelöst werden. Bitte den aktuellen Stand neu laden.');
    }

    $activityId = trim((string)($source['content_id'] ?? $case['object_id'] ?? ''));
    if ($activityId === '') throw new RuntimeException('Die Aktivitäts-ID des Auditfalls fehlt.');

    $writtenContentFields = [];
    $repoResult = null;
    if ($action === 'approve' && $decision['event_updates']) {
        if (!be_cc_activity_writeback_available()) {
            throw new RuntimeException(be_cc_activity_writeback_status()['message']);
        }
        $activityUpdates = be_cc_activity_audit_updates($decision['event_updates']);
        $repoResult = be_cc_update_activity_in_repo($activityId, $activityUpdates);
        $writtenContentFields = array_keys($activityUpdates);
    }

    $now = gmdate('Y-m-d\TH:i:s');
    $updates = match ($action) {
        'approve' => $decision['event_updates']
            ? ['status'=>'corrected','action_state'=>'activity_repo_corrected','verification_status'=>'corrected']
            : ['status'=>'verified','action_state'=>'verified_until_next_review','verification_status'=>'confirmed'],
        'reject' => ['status'=>'ignored','action_state'=>'ignored'],
        'snooze' => ['status'=>'snoozed','action_state'=>'snoozed','next_review_at'=>$decision['suppress_until'],'next_check_at'=>$decision['suppress_until']],
        default => throw new InvalidArgumentException('Unbekannte Aktivitäts-Audit-Aktion.'),
    };
    $updates += [
        'verified_at' => $now,
        'last_verified_at' => $now,
        'verified_by' => 'control_center',
        'review_note' => $decision['decision_note'],
    ];
    be_cc_source_batch_update($table, (int)$resolution['row_number'], $updates);

    $lastError = null;
    for ($attempt = 1; $attempt <= BE_CC_SOURCE_VERIFY_ATTEMPTS; $attempt++) {
        try {
            $freshTable = be_cc_content_audit_verified_table();
            $freshResolution = be_cc_resolve_audit_row($freshTable['rows'], $identity);
            if (($freshResolution['status'] ?? '') !== 'resolved') {
                throw new RuntimeException('Aktivitäts-Auditfall wurde nach dem Writeback nicht wiedergefunden.');
            }
            be_cc_source_verify_fields((array)$freshResolution['row'], $updates);
            return [
                'transport' => $repoResult === null ? 'google_sheets_verified_batch' : 'github_repo_and_google_sheets_verified_batch',
                'source_verified' => true,
                'resolution' => $freshResolution,
                'written_content_fields' => $writtenContentFields,
                'written_audit_fields' => array_keys($updates),
                'commit_sha' => trim((string)($repoResult['commit_sha'] ?? '')),
                'verification_attempts' => $attempt,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
        } catch (RuntimeException $error) {
            $lastError = $error;
            if ($attempt < BE_CC_SOURCE_VERIFY_ATTEMPTS) usleep(150000);
        }
    }
    throw new RuntimeException('Aktivitäts-Audit-Writeback wurde nicht bestätigt. ' . ($lastError?->getMessage() ?? ''));
}
