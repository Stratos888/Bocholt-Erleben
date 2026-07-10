<?php
declare(strict_types=1);

require_once __DIR__ . '/_content_ops.php';

function be_cc_parse_process_timestamp(mixed $value): ?DateTimeImmutable
{
    $raw = trim((string)$value);
    if ($raw === '') return null;
    try {
        return new DateTimeImmutable($raw);
    } catch (Throwable) {
        return null;
    }
}

function be_cc_integrated_process_health(array $contentOps): array
{
    $health = be_cc_process_health($contentOps);
    if (empty($contentOps['available'])) return $health;

    $weekly = $contentOps['processes']['weekly_ki_websearch'] ?? null;
    $intake = $contentOps['processes']['manual_ki_intake'] ?? null;
    $weeklyCreatedWork = is_array($weekly)
        && !empty($weekly['action_required'])
        && !str_contains(strtolower((string)($weekly['status'] ?? '')), 'fail')
        && !str_contains(strtolower((string)($weekly['status'] ?? '')), 'error')
        && !str_contains(strtolower((string)($weekly['status'] ?? '')), 'missing');

    if (!$weeklyCreatedWork) return $health;

    $weeklyAt = be_cc_parse_process_timestamp($weekly['generated_at_utc'] ?? null);
    $intakeAt = be_cc_parse_process_timestamp(is_array($intake) ? ($intake['generated_at_utc'] ?? null) : null);
    $handoffConfirmed = $weeklyAt instanceof DateTimeImmutable
        && $intakeAt instanceof DateTimeImmutable
        && $intakeAt >= $weeklyAt;

    foreach ($health['items'] as &$item) {
        if (($item['key'] ?? '') !== 'manual_ki_intake') continue;
        if ($handoffConfirmed) {
            $item['status'] = 'ok';
            $item['message'] = 'Der nachgelagerte KI-Intake wurde nach dem kandidaterzeugenden Suchlauf bestätigt.';
            $item['depends_on'] = 'weekly_ki_websearch';
        } else {
            $item['status'] = 'attention';
            $item['message'] = 'Der letzte KI-Suchlauf hat Prüfkandidaten erzeugt, aber ein nachgelagerter Intake-Lauf ist nicht bestätigt.';
            $item['depends_on'] = 'weekly_ki_websearch';
        }
        break;
    }
    unset($item);

    if (!$handoffConfirmed) {
        $health['status'] = 'attention';
        $health['message'] = 'Die Übergabe von der KI-Eventsuche in den KI-Intake ist nicht vollständig bestätigt.';
    }
    return $health;
}
