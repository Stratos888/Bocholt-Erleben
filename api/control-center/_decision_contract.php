<?php
declare(strict_types=1);

/**
 * Side-effect-free decision contract for the control center.
 * Phase 1 only: runtime actions do not require this file yet.
 */

function be_cc_contract_root(): string
{
    return dirname(__DIR__, 2);
}

function be_cc_read_json_contract(string $path): array
{
    if (!is_file($path)) throw new RuntimeException('Contract file not found: ' . $path);
    $decoded = json_decode((string)file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Contract must decode to an object: ' . $path);
    return $decoded;
}

function be_cc_content_ops_decision_contract(): array
{
    static $contract = null;
    if ($contract === null) {
        $contract = be_cc_read_json_contract(be_cc_contract_root() . '/data/content_ops_decision_classes.json');
    }
    return $contract;
}

function be_cc_editorial_contract_data(): array
{
    static $contract = null;
    if ($contract === null) {
        $contract = be_cc_read_json_contract(be_cc_contract_root() . '/data/control_center_editorial_contract.json');
    }
    return $contract;
}

function be_cc_unicode_lower(mixed $value): string
{
    $text = (string)$value;
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function be_cc_unicode_length(mixed $value): int
{
    $text = (string)$value;
    return function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : strlen($text);
}

function be_cc_decision_token(mixed $value): string
{
    $raw = trim(be_cc_unicode_lower($value));
    $raw = strtr($raw, ['ä'=>'ae','ö'=>'oe','ü'=>'ue','ß'=>'ss']);
    return trim((string)preg_replace('/[^a-z0-9]+/', '_', $raw), '_');
}

function be_cc_decision_class_exists(string $decisionClass): bool
{
    $classes = be_cc_content_ops_decision_contract()['decision_classes'] ?? [];
    return isset($classes[$decisionClass]) && is_array($classes[$decisionClass]);
}

function be_cc_decision_class_meta(string $decisionClass): array
{
    $classes = be_cc_content_ops_decision_contract()['decision_classes'] ?? [];
    return is_array($classes[$decisionClass] ?? null) ? $classes[$decisionClass] : [];
}

function be_cc_valid_iso_day(string $value): bool
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) return false;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value;
}

function be_cc_validate_operator_decision(string $action, array $payload, array $context = []): array
{
    $contract = be_cc_editorial_contract_data()['decisions'] ?? [];
    $action = be_cc_decision_token($action);
    $decisionClass = be_cc_decision_token($payload['decision_class'] ?? '');
    $eventUpdates = is_array($payload['event_updates'] ?? null) ? $payload['event_updates'] : [];

    if ($action === 'approve' && $decisionClass === '') {
        $decisionClass = $eventUpdates ? 'corrected' : 'accepted';
    }

    if ($action === 'reject') {
        $allowed = array_map('strval', $contract['reject_classes'] ?? []);
        if ($decisionClass === '' || !in_array($decisionClass, $allowed, true)) {
            throw new InvalidArgumentException('Ablehnung erfordert eine gültige decision_class.');
        }
    } elseif ($action === 'snooze') {
        $required = (string)($contract['snooze_class'] ?? 'snoozed');
        if ($decisionClass !== $required) {
            throw new InvalidArgumentException('Zurückstellen erfordert decision_class=snoozed.');
        }
        $until = trim((string)($payload['suppress_until'] ?? $payload['until'] ?? ''));
        if (!be_cc_valid_iso_day($until)) {
            throw new InvalidArgumentException('Zurückstellen erfordert ein gültiges Wiedervorlagedatum.');
        }
        $today = trim((string)($context['today'] ?? ''));
        if ($today !== '' && be_cc_valid_iso_day($today) && $until < $today) {
            throw new InvalidArgumentException('Wiedervorlagedatum darf nicht in der Vergangenheit liegen.');
        }
        $payload['suppress_until'] = $until;
    } elseif ($action === 'approve') {
        $allowed = array_map('strval', $contract['approve_classes'] ?? []);
        if (!in_array($decisionClass, $allowed, true)) {
            throw new InvalidArgumentException('Übernahme erfordert accepted, confirmed oder corrected.');
        }
        if ($eventUpdates && $decisionClass !== 'corrected') {
            throw new InvalidArgumentException('Eine fachlich geänderte Fassung muss als corrected gespeichert werden.');
        }
    } else {
        throw new InvalidArgumentException('Unbekannte redaktionelle Aktion: ' . $action);
    }

    if (!be_cc_decision_class_exists($decisionClass)) {
        throw new InvalidArgumentException('decision_class ist nicht Teil der kanonischen Taxonomie.');
    }

    $note = trim((string)($payload['decision_note'] ?? $payload['reason'] ?? $payload['note'] ?? ''));
    return [
        'action' => $action,
        'decision_class' => $decisionClass,
        'decision_note' => $note,
        'suppress_until' => trim((string)($payload['suppress_until'] ?? '')),
        'recheck_at' => trim((string)($payload['recheck_at'] ?? '')),
        'reopen_policy' => trim((string)($payload['reopen_policy'] ?? '')),
        'source_fingerprint' => trim((string)($payload['source_fingerprint'] ?? '')),
        'content_fingerprint' => trim((string)($payload['content_fingerprint'] ?? '')),
        'event_updates' => $eventUpdates,
        'meta' => be_cc_decision_class_meta($decisionClass),
    ];
}
