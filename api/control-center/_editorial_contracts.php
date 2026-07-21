<?php
declare(strict_types=1);

require_once __DIR__ . '/_decision_contract.php';

/** Side-effect-free normalized review contract for event candidates. */

function be_cc_contract_value(array $row, array $aliases, mixed $default = ''): mixed
{
    foreach ($aliases as $alias) {
        if (array_key_exists($alias, $row)) return $row[$alias];
    }
    return $default;
}

function be_cc_contract_text(array $row, array $aliases): string
{
    $value = be_cc_contract_value($row, $aliases, '');
    return is_array($value) ? trim(implode(', ', array_map('strval', $value))) : trim((string)$value);
}

function be_cc_contract_bool(array $row, array $aliases): bool
{
    $value = be_cc_contract_value($row, $aliases, false);
    if (is_bool($value)) return $value;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','ja','hard','required'], true);
}

function be_cc_contract_url_valid(string $value): bool
{
    return $value !== '' && filter_var($value, FILTER_VALIDATE_URL) !== false && preg_match('/^https?:\/\//i', $value) === 1;
}

function be_cc_contract_add_issue(array &$issues, string $code, string $field, string $message): void
{
    $issues[] = ['code'=>$code, 'field'=>$field, 'message'=>$message];
}

require_once __DIR__ . '/_event_review_tasks.php';
