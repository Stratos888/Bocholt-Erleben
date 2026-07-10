<?php
declare(strict_types=1);

function be_cc_decode_payload(array $row): array
{
    $raw = (string)($row['source_payload_json'] ?? '');
    if ($raw === '') return [];
    $payload = json_decode($raw, true);
    return is_array($payload) ? $payload : [];
}

function be_cc_display_status(string $state): string
{
    return match ($state) {
        'new' => 'Neu',
        'decision_required' => 'Entscheidung erforderlich',
        'open' => 'Offen',
        'in_progress' => 'In Arbeit',
        'waiting' => 'Wartet',
        'blocked' => 'Blockiert',
        'snoozed' => 'Zurückgestellt',
        'done' => 'Erledigt',
        'rejected' => 'Abgelehnt',
        'information' => 'Information',
        'parked' => 'Geparkt',
        default => 'Unbekannt',
    };
}

function be_cc_action(string $key, string $label, bool $requiresInput = false, bool $destructive = false): array
{
    return ['key' => $key, 'label' => $label, 'requires_input' => $requiresInput, 'destructive' => $destructive];
}

function be_cc_case_presentation(array $row): array
{
    $source = (string)($row['source_system'] ?? '');
    $state = (string)($row['state'] ?? 'new');
    $type = (string)($row['case_type'] ?? 'intake');
    $payload = be_cc_decode_payload($row);
    $kind = 'other';
    $group = 'other';
    $primary = null;
    $secondary = [];
    $context = [];
    $links = [];
    $waitingFor = '';

    if ($source === 'inbox_feed') {
        $kind = (($payload['submission_kind'] ?? '') === 'activity') ? 'activity_candidate' : 'event_candidate';
        $group = 'new_content';
        $primary = be_cc_action('approve', 'Übernehmen');
        $secondary = [be_cc_action('snooze', 'Zurückstellen', true), be_cc_action('reject', 'Ablehnen', true, true)];
        $context = [
            'description' => (string)($payload['description'] ?? ''),
            'date' => (string)($payload['date'] ?? ''),
            'end_date' => (string)($payload['endDate'] ?? ''),
            'time' => (string)($payload['time'] ?? ''),
            'location' => (string)($payload['location'] ?? ''),
            'city' => (string)($payload['city'] ?? ''),
            'visual_key' => (string)($payload['visual_key'] ?? ''),
            'duplicate_hint' => (string)($payload['matched_event_id'] ?? ''),
        ];
        $url = trim((string)($payload['source_url'] ?? $payload['url'] ?? ''));
        if ($url !== '') $links[] = ['label' => 'Offizielle Quelle', 'url' => $url];
    } elseif ($source === 'submission_db') {
        $submissionStatus = (string)($payload['status'] ?? '');
        if ($submissionStatus === 'pending_review') {
            $kind = 'submission_pre_payment';
            $group = 'provider';
            $primary = be_cc_action('approve', 'Zahlung freigeben');
        } elseif (in_array($submissionStatus, ['paid', 'in_review'], true)) {
            $kind = 'submission_publish';
            $group = 'approvals';
            $primary = be_cc_action('approve', 'Veröffentlichen');
        } else {
            $kind = 'submission_waiting_payment';
            $group = 'provider';
            $waitingFor = 'Zahlungsbestätigung';
        }
        if ($primary !== null) {
            $secondary[] = be_cc_action('snooze', 'Zurückstellen', true);
            $secondary[] = be_cc_action('reject', 'Ablehnen', true, true);
        }
        $context = [
            'submission_status' => $submissionStatus,
            'submission_kind' => (string)($payload['submission_kind'] ?? ''),
            'date' => (string)($payload['start_date'] ?? ''),
            'time' => (string)($payload['time_text'] ?? ''),
            'location' => (string)($payload['location_name'] ?? ''),
            'organization' => (string)($payload['organization_name_snapshot'] ?? ''),
        ];
        $url = trim((string)($payload['event_url'] ?? $payload['ticket_url'] ?? ''));
        if ($url !== '') $links[] = ['label' => 'Einreichungsquelle', 'url' => $url];
    } elseif ($source === 'content_audit') {
        $issueCode = strtolower(trim((string)($payload['issue_code'] ?? '')));
        $group = 'quality';
        if (str_contains($issueCode, 'description')) {
            $kind = 'content_description_correction';
            $primary = be_cc_action('approve', 'Korrigieren und übernehmen', true);
        } elseif (str_contains($issueCode, 'source') || str_contains($issueCode, 'ticket_url')) {
            $kind = 'content_source_correction';
            $primary = be_cc_action('approve', 'Quelle korrigieren', true);
        } elseif (str_contains($issueCode, 'fact') || str_contains($issueCode, 'evidence')) {
            $kind = 'content_fact_check';
            $primary = be_cc_action('approve', 'Als korrekt bestätigen');
        } else {
            $kind = 'content_quality_review';
            $primary = be_cc_action('approve', 'Prüfung abschließen');
        }
        $secondary = [be_cc_action('snooze', 'Zurückstellen', true), be_cc_action('reject', 'Ablehnen', true, true)];
        $context = [
            'issue_code' => (string)($payload['issue_code'] ?? ''),
            'issue_text' => (string)($payload['issue_text'] ?? ''),
            'recommended_action' => (string)($payload['recommended_action'] ?? ''),
            'current_description' => (string)($payload['description'] ?? ''),
            'suggested_url' => (string)($payload['suggested_url'] ?? ''),
            'source_url' => (string)($payload['source_url'] ?? ''),
            'content_type' => (string)($payload['content_type'] ?? ''),
        ];
        $url = trim((string)($payload['suggested_url'] ?? $payload['source_url'] ?? ''));
        if ($url !== '') $links[] = ['label' => 'Quelle', 'url' => $url];
    } elseif (in_array($source, ['growth_backlog', 'repo_workpack'], true) && $type !== 'task') {
        $kind = 'backlog_item';
        $group = 'backlog';
        $primary = be_cc_action('convert_to_task', 'Als Aufgabe starten');
        $secondary = [];
        if ($source === 'growth_backlog') $secondary[] = be_cc_action('edit_source', 'Bearbeiten', true);
        $secondary[] = be_cc_action('complete', 'Abschließen');
        $secondary[] = be_cc_action('reject', 'Verwerfen', true, true);
        $context = [
            'backlog_type' => (string)($payload['type'] ?? ''),
            'source' => (string)($payload['source'] ?? $payload['source_document'] ?? ''),
            'recommended_action' => (string)($payload['recommended_action'] ?? $payload['next_action'] ?? ''),
            'expected_benefit' => (string)($payload['expected_benefit'] ?? ''),
        ];
    } elseif ($type === 'task') {
        $kind = 'manual_task';
        $group = 'tasks';
        if ($state === 'waiting') {
            $waitingFor = (string)($row['blocked_reason'] ?? '') ?: 'Externe Rückmeldung oder Ergebnis';
        } elseif (!in_array($state, ['done', 'rejected', 'parked'], true)) {
            $primary = be_cc_action('complete', 'Erledigen');
        }
        $secondary = [be_cc_action('snooze', 'Zurückstellen', true)];
    } elseif ($type === 'idea') {
        $kind = 'manual_idea';
        $group = 'ideas';
        $primary = be_cc_action('convert_to_task', 'In Aufgabe umwandeln');
        $secondary = [be_cc_action('park', 'Parken'), be_cc_action('reject', 'Verwerfen', false, true)];
    } elseif ($type === 'information') {
        $kind = 'system_information';
        $group = 'information';
    }

    return [
        'case_kind' => $kind,
        'queue_group' => $group,
        'display_status' => be_cc_display_status($state),
        'primary_action' => $primary,
        'secondary_actions' => $secondary,
        'decision_context' => $context,
        'source_links' => $links,
        'waiting_for' => $waitingFor,
    ];
}
