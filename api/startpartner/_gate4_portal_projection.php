<?php
declare(strict_types=1);

function be_startpartner_gate4_portal_projection(array $candidate): array
{
    $gate4 = is_array($candidate['gate4'] ?? null) ? $candidate['gate4'] : [];
    $pilot = is_array($gate4['pilot'] ?? null) ? $gate4['pilot'] : [];
    $gate3 = is_array($candidate['gate3'] ?? null) ? $candidate['gate3'] : [];

    $scopes = [];
    foreach ((array)($gate3['scopes'] ?? []) as $scope) {
        if (!is_array($scope) || !in_array((string)($scope['scope_key'] ?? ''), ['events', 'activities'], true)) {
            continue;
        }
        $scopes[] = [
            'scope_key' => (string)$scope['scope_key'],
            'status' => (string)($scope['status'] ?? 'planned'),
            'limit_value' => isset($scope['limit_value']) ? (int)$scope['limit_value'] : null,
            'is_unlimited' => (int)($scope['is_unlimited'] ?? 0) === 1,
            'period_unit' => (string)($scope['period_unit'] ?? ''),
        ];
    }

    $contentLinks = [];
    foreach ((array)($gate4['content_links'] ?? []) as $row) {
        if (!is_array($row)) {
            continue;
        }
        $contentLinks[] = [
            'submission_id' => (int)($row['submission_id'] ?? 0),
            'content_type' => (string)($row['content_type'] ?? ''),
            'status' => (string)($row['status'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'start_date' => $row['start_date'] ?? null,
            'location_name' => $row['location_name'] ?? null,
        ];
    }

    $onboarding = is_array($gate4['onboarding'] ?? null) ? $gate4['onboarding'] : [];
    return [
        'phase' => (string)($gate4['phase'] ?? 'onboarding'),
        'active' => !empty($gate4['active']),
        'activation_ready' => !empty($gate4['activation_ready']),
        'pilot' => [
            'status' => (string)($pilot['status'] ?? 'onboarding'),
            'activation_date_local' => $pilot['activation_date_local'] ?? null,
            'planned_end_date' => $pilot['planned_end_date'] ?? null,
        ],
        'scopes' => $scopes,
        'onboarding' => [
            'complete_count' => (int)($onboarding['completed_count'] ?? 0),
            'total_count' => (int)($onboarding['total_count'] ?? 14),
        ],
        'content_links' => $contentLinks,
    ];
}
