<?php
declare(strict_types=1);

/** Side-effect-free idempotency contract. Persistence follows in Phase 2. */

function be_cc_operation_normalize(mixed $value): mixed
{
    if (!is_array($value)) return $value;
    if (array_is_list($value)) return array_map('be_cc_operation_normalize', $value);
    ksort($value);
    foreach ($value as $key => $item) $value[$key] = be_cc_operation_normalize($item);
    return $value;
}

function be_cc_operation_payload_hash(array $payload): string
{
    return hash('sha256', json_encode(be_cc_operation_normalize($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
}

function be_cc_operation_id_valid(string $operationId): bool
{
    return preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{7,127}$/', $operationId) === 1;
}

function be_cc_operation_reservation_decision(array $existingOperations, string $operationId, string $caseId, string $action, array $payload): array
{
    if (!be_cc_operation_id_valid($operationId)) throw new InvalidArgumentException('operation_id ist ungültig.');
    if (trim($caseId) === '' || trim($action) === '') throw new InvalidArgumentException('case_id und action sind erforderlich.');
    $payloadHash = be_cc_operation_payload_hash($payload);
    foreach ($existingOperations as $existing) {
        if (!is_array($existing) || (string)($existing['operation_id'] ?? '') !== $operationId) continue;
        $same = (string)($existing['case_id'] ?? '') === $caseId
            && (string)($existing['action'] ?? '') === $action
            && (string)($existing['payload_hash'] ?? '') === $payloadHash;
        if (!$same) {
            return ['decision'=>'conflict', 'operation_id'=>$operationId, 'payload_hash'=>$payloadHash, 'existing'=>$existing];
        }
        return ['decision'=>'replay', 'operation_id'=>$operationId, 'payload_hash'=>$payloadHash, 'existing'=>$existing];
    }
    return [
        'decision'=>'reserve',
        'operation_id'=>$operationId,
        'case_id'=>$caseId,
        'action'=>$action,
        'payload_hash'=>$payloadHash,
        'status'=>'started',
    ];
}
