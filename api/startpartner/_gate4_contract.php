<?php
declare(strict_types=1);

const BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS = [
    'terms_confirmed',
    'organizer_linked',
    'contact_confirmed',
    'portal_access_tested',
    'pilot_entitlement_readback',
    'service_scope_confirmed',
    'sources_recorded',
    'maintenance_path_agreed',
    'content_rights_cleared',
    'first_content_ready',
    'editorial_review_ready',
    'measurement_ready',
    'activation_target_set',
];

const BE_STARTPARTNER_GATE4_MANUAL_ONBOARDING_ITEMS = [
    'portal_access_tested',
    'content_rights_cleared',
    'activation_target_set',
];

const BE_STARTPARTNER_GATE4_CONTENT_TYPES = ['event', 'activity'];
const BE_STARTPARTNER_GATE4_ITEM_STATUSES = ['pending', 'complete', 'blocked', 'not_applicable'];
const BE_STARTPARTNER_GATE4_DISTRIBUTION_STATUSES = ['planned', 'ready', 'completed', 'blocked', 'cancelled'];
const BE_STARTPARTNER_GATE4_PILOT_STATUSES = [
    'onboarding', 'activation_ready', 'active', 'paused', 'closing',
    'converted', 'ended_without_conversion', 'terminated',
];
const BE_STARTPARTNER_GATE4_TERMINAL_PILOT_STATUSES = ['converted', 'ended_without_conversion', 'terminated'];
const BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS = ['day_30', 'day_90', 'month_5', 'final'];

function be_startpartner_gate4_reporting_target_id(int $organizerId): string
{
    if ($organizerId < 1) {
        throw new InvalidArgumentException('organizer_id must be positive.');
    }
    return 'organizer-' . substr(hash('sha256', 'organizer:' . $organizerId), 0, 16);
}

function be_startpartner_gate4_validate_local_date(mixed $value): string
{
    $text = trim((string)$value);
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('Europe/Berlin'));
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$date
        || (is_array($errors) && (($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0))
        || $date->format('Y-m-d') !== $text
    ) {
        throw new InvalidArgumentException('activation_date_local must use YYYY-MM-DD.');
    }
    return $text;
}

function be_startpartner_gate4_add_calendar_months(string $localDate, int $months): string
{
    $dateText = be_startpartner_gate4_validate_local_date($localDate);
    if ($months < 1 || $months > 24) {
        throw new InvalidArgumentException('calendar month offset is invalid.');
    }
    [$year, $month, $day] = array_map('intval', explode('-', $dateText));
    $monthIndex = ($year * 12 + ($month - 1)) + $months;
    $targetYear = intdiv($monthIndex, 12);
    $targetMonth = ($monthIndex % 12) + 1;
    $targetMonthStart = new DateTimeImmutable(
        sprintf('%04d-%02d-01', $targetYear, $targetMonth),
        new DateTimeZone('Europe/Berlin')
    );
    $lastDay = (int)$targetMonthStart->modify('last day of this month')->format('j');
    return sprintf('%04d-%02d-%02d', $targetYear, $targetMonth, min($day, $lastDay));
}

function be_startpartner_gate4_activation_window(string $localDate): array
{
    $activationDate = be_startpartner_gate4_validate_local_date($localDate);
    $plannedEndDate = be_startpartner_gate4_add_calendar_months($activationDate, 6);
    $timezone = new DateTimeZone('Europe/Berlin');
    $startLocal = new DateTimeImmutable($activationDate . ' 00:00:00', $timezone);
    $endLocal = new DateTimeImmutable($plannedEndDate . ' 23:59:59', $timezone);
    return [
        'activation_date_local' => $activationDate,
        'planned_end_date' => $plannedEndDate,
        'starts_at_utc' => $startLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        'ends_at_utc' => $endLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
    ];
}

function be_startpartner_gate4_pilot_month_window(
    string $activationDateLocal,
    int $monthIndex,
    ?string $plannedEndDate = null
): array {
    $activationDate = be_startpartner_gate4_validate_local_date($activationDateLocal);
    if ($monthIndex < 1 || $monthIndex > 6) {
        throw new InvalidArgumentException('pilot month index must be between 1 and 6.');
    }
    $timezone = new DateTimeZone('Europe/Berlin');
    $startDate = $monthIndex === 1
        ? $activationDate
        : be_startpartner_gate4_add_calendar_months($activationDate, $monthIndex - 1);
    $nextStartDate = $monthIndex < 6
        ? be_startpartner_gate4_add_calendar_months($activationDate, $monthIndex)
        : null;
    if ($monthIndex === 6) {
        $endDate = be_startpartner_gate4_validate_local_date(
            $plannedEndDate ?? be_startpartner_gate4_add_calendar_months($activationDate, 6)
        );
    } else {
        $endDate = (new DateTimeImmutable($nextStartDate . ' 00:00:00', $timezone))
            ->modify('-1 day')
            ->format('Y-m-d');
    }
    return [
        'pilot_month_index' => $monthIndex,
        'start_date_local' => $startDate,
        'end_date_local' => $endDate,
        'next_start_date_local' => $nextStartDate,
    ];
}

function be_startpartner_gate4_pilot_month_index(
    string $activationDateLocal,
    string $localDate,
    ?string $plannedEndDate = null
): ?int {
    $date = be_startpartner_gate4_validate_local_date($localDate);
    for ($index = 1; $index <= 6; $index++) {
        $window = be_startpartner_gate4_pilot_month_window($activationDateLocal, $index, $plannedEndDate);
        if ($date >= $window['start_date_local'] && $date <= $window['end_date_local']) {
            return $index;
        }
    }
    return null;
}

function be_startpartner_gate4_checkpoint_key(mixed $value): string
{
    $key = strtolower(trim((string)$value));
    if (!in_array($key, BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS, true)) {
        throw new InvalidArgumentException('checkpoint_key is invalid.');
    }
    return $key;
}

function be_startpartner_gate4_checkpoint_schedule(string $activationDateLocal, string $plannedEndDate): array
{
    $activationDate = be_startpartner_gate4_validate_local_date($activationDateLocal);
    $endDate = be_startpartner_gate4_validate_local_date($plannedEndDate);
    $timezone = new DateTimeZone('Europe/Berlin');
    $activation = new DateTimeImmutable($activationDate . ' 00:00:00', $timezone);
    $monthSixStart = be_startpartner_gate4_add_calendar_months($activationDate, 5);
    $monthFiveEnd = (new DateTimeImmutable($monthSixStart . ' 00:00:00', $timezone))
        ->modify('-1 day')
        ->format('Y-m-d');
    return [
        'day_30' => [
            'checkpoint_key' => 'day_30',
            'due_date_local' => $activation->modify('+30 days')->format('Y-m-d'),
            'deadline_date_local' => $endDate,
        ],
        'day_90' => [
            'checkpoint_key' => 'day_90',
            'due_date_local' => $activation->modify('+90 days')->format('Y-m-d'),
            'deadline_date_local' => $endDate,
        ],
        'month_5' => [
            'checkpoint_key' => 'month_5',
            'due_date_local' => $monthFiveEnd,
            'deadline_date_local' => $monthFiveEnd,
        ],
        'final' => [
            'checkpoint_key' => 'final',
            'due_date_local' => $monthSixStart,
            'deadline_date_local' => $endDate,
        ],
    ];
}

function be_startpartner_gate4_onboarding_key(mixed $value): string
{
    $key = strtolower(trim((string)$value));
    if (!in_array($key, BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS, true)) {
        throw new InvalidArgumentException('onboarding item_key is invalid.');
    }
    return $key;
}

function be_startpartner_gate4_onboarding_item_is_manual(string $key): bool
{
    return in_array($key, BE_STARTPARTNER_GATE4_MANUAL_ONBOARDING_ITEMS, true);
}

function be_startpartner_gate4_manual_onboarding_key(mixed $value): string
{
    $key = be_startpartner_gate4_onboarding_key($value);
    if (!be_startpartner_gate4_onboarding_item_is_manual($key)) {
        throw new DomainException('Dieser Onboardingpunkt wird aus dem fachlichen Quellsystem abgeleitet und kann nicht manuell überschrieben werden.');
    }
    return $key;
}

function be_startpartner_gate4_item_status(mixed $value): string
{
    $status = strtolower(trim((string)$value));
    if (!in_array($status, BE_STARTPARTNER_GATE4_ITEM_STATUSES, true)) {
        throw new InvalidArgumentException('onboarding status is invalid.');
    }
    return $status;
}

function be_startpartner_gate4_content_type(mixed $value): string
{
    $type = strtolower(trim((string)$value));
    if (!in_array($type, BE_STARTPARTNER_GATE4_CONTENT_TYPES, true)) {
        throw new InvalidArgumentException('content_type is invalid.');
    }
    return $type;
}

function be_startpartner_gate4_required_item_rows(array $rows): array
{
    $byKey = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $key = strtolower(trim((string)($row['item_key'] ?? '')));
        if (in_array($key, BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS, true)) {
            $byKey[$key] = $row;
        }
    }
    $result = [];
    foreach (BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key) {
        $result[] = $byKey[$key] ?? [
            'item_key' => $key,
            'status' => 'pending',
            'is_required' => 1,
            'is_hard_blocker' => 1,
            'evidence_text' => null,
            'evidence_reference' => null,
            'operator_reference' => null,
            'completed_at' => null,
            'revision' => 0,
        ];
    }
    return $result;
}

function be_startpartner_gate4_onboarding_readiness(array $rows): array
{
    $items = be_startpartner_gate4_required_item_rows($rows);
    $blockers = [];
    $completed = 0;
    foreach ($items as $item) {
        $status = (string)($item['status'] ?? 'pending');
        $required = (int)($item['is_required'] ?? 1) === 1;
        if ($status === 'complete' || (!$required && $status === 'not_applicable')) {
            $completed++;
            continue;
        }
        if ($required) {
            $blockers[] = [
                'code' => $status === 'blocked' ? 'hard_blocker' : 'required_item_open',
                'item_key' => (string)$item['item_key'],
                'message' => $status === 'blocked'
                    ? 'Onboardingpunkt ist blockiert.'
                    : 'Verbindlicher Onboardingpunkt ist noch offen.',
            ];
        }
    }
    return [
        'ready' => $blockers === [],
        'completed_count' => $completed,
        'total_count' => count($items),
        'blockers' => $blockers,
        'items' => $items,
    ];
}
