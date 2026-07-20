<?php
declare(strict_types=1);

require_once __DIR__ . '/_operations.php';

const BE_CC_E4_PREFIX = 'be-e4-synthetic';

function be_cc_e4_validate_target_sha(mixed $value): string
{
    $sha = strtolower(trim((string)$value));
    if (preg_match('/^[0-9a-f]{40}$/', $sha) !== 1) {
        throw new InvalidArgumentException('E4 target_sha must be an exact 40-character commit SHA.');
    }
    return $sha;
}

function be_cc_e4_validate_run_key(mixed $value, string $targetSha): string
{
    $runKey = strtolower(trim((string)$value));
    $expectedPrefix = substr($targetSha, 0, 12) . '-';
    if (
        preg_match('/^[0-9a-f]{12}-[0-9]{5,20}$/', $runKey) !== 1
        || !str_starts_with($runKey, $expectedPrefix)
    ) {
        throw new InvalidArgumentException('E4 run_key is invalid or does not belong to target_sha.');
    }
    return $runKey;
}

function be_cc_e4_validate_context(array $input, string $environment, string $deployedBuild): array
{
    if (strtolower(trim($environment)) !== 'staging') {
        throw new DomainException('Synthetic E4 runtime operations are allowed only on staging.');
    }

    $targetSha = be_cc_e4_validate_target_sha($input['target_sha'] ?? '');
    $runKey = be_cc_e4_validate_run_key($input['run_key'] ?? '', $targetSha);
    $expectedBuild = substr($targetSha, 0, 12);
    if (!hash_equals($expectedBuild, trim($deployedBuild))) {
        throw new DomainException('Synthetic E4 target does not match the deployed staging build.');
    }

    return [
        'target_sha' => $targetSha,
        'expected_build' => $expectedBuild,
        'run_key' => $runKey,
        'case_prefix' => BE_CC_E4_PREFIX . '-' . $runKey . '-',
        'operation_prefix' => 'e4:' . $runKey . ':',
    ];
}

function be_cc_e4_validate_case_id(mixed $value): string
{
    $caseId = strtolower(trim((string)$value));
    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $caseId) !== 1) {
        throw new InvalidArgumentException('Synthetic E4 case_id is invalid.');
    }
    return $caseId;
}

function be_cc_e4_validate_operation_id(mixed $value, string $operationPrefix): string
{
    $operationId = trim((string)$value);
    if (
        !str_starts_with($operationId, $operationPrefix)
        || !be_cc_operation_id_valid($operationId)
    ) {
        throw new InvalidArgumentException('Synthetic E4 operation_id is outside the active run scope.');
    }
    return $operationId;
}

function be_cc_e4_failed_operation_payload(string $operationId, string $note): array
{
    return [
        'decision_class' => 'accepted',
        'decision_note' => trim($note),
        'suppress_until' => '',
        'recheck_at' => '',
        'reopen_policy' => '',
        'source_fingerprint' => '',
        'content_fingerprint' => '',
        'event_updates' => [],
        'operation_id' => $operationId,
        'legacy_operation_id' => false,
    ];
}

function be_cc_e4_validate_synthetic_case(?array $case, string $casePrefix): array
{
    if (!is_array($case)) {
        throw new DomainException('Synthetic E4 case was not found.');
    }
    if (
        (string)($case['source_system'] ?? '') !== 'inbox_feed'
        || !str_starts_with((string)($case['source_reference'] ?? ''), $casePrefix)
    ) {
        throw new DomainException('E4 runtime operation refused a non-synthetic case.');
    }
    return $case;
}

function be_cc_e4_string_list(mixed $value, int $maxItems = 8): array
{
    if (!is_array($value) || !array_is_list($value) || count($value) > $maxItems) {
        throw new InvalidArgumentException('Synthetic E4 identifier list is invalid.');
    }
    return array_values(array_unique(array_map(static fn(mixed $item): string => trim((string)$item), $value)));
}

/**
 * Execute the narrowly scoped staging-only E4 database contract.
 *
 * Required dependencies:
 * - counts(?string $runKey): array{cases:int,operations:int}
 * - lookup_case(string $caseId): ?array
 * - seed_failed_operation(string $operationId,string $caseId,string $payloadHash,string $errorText): void
 * - states(array $caseIds,array $operationIds): array
 * - cleanup(string $runKey): array{cases:int,operations:int}
 */
function be_cc_e4_execute(array $input, array $dependencies): array
{
    foreach (['counts', 'lookup_case', 'seed_failed_operation', 'states', 'cleanup'] as $name) {
        if (!isset($dependencies[$name]) || !is_callable($dependencies[$name])) {
            throw new LogicException('Synthetic E4 dependency is missing: ' . $name);
        }
    }

    $context = be_cc_e4_validate_context(
        $input,
        (string)($dependencies['environment'] ?? ''),
        (string)($dependencies['deployed_build'] ?? '')
    );
    $mode = strtolower(trim((string)($input['mode'] ?? '')));

    if ($mode === 'preflight') {
        $global = ($dependencies['counts'])(null);
        $run = ($dependencies['counts'])($context['run_key']);
        if ($global !== ['cases' => 0, 'operations' => 0]) {
            throw new DomainException('Pre-existing synthetic database state found.');
        }
        return [
            'mode' => $mode,
            'mutation' => false,
            'environment' => 'staging',
            'build' => $context['expected_build'],
            'schema_present' => true,
            'global_synthetic' => $global,
            'run_synthetic' => $run,
        ];
    }

    if ($mode === 'seed_failed_operation') {
        $caseId = be_cc_e4_validate_case_id($input['case_id'] ?? '');
        $operationId = be_cc_e4_validate_operation_id(
            $input['operation_id'] ?? '',
            $context['operation_prefix']
        );
        if ($operationId !== $context['operation_prefix'] . 'partial-failed') {
            throw new DomainException('Only the exact controlled partial-failure operation may be seeded.');
        }
        $case = be_cc_e4_validate_synthetic_case(
            ($dependencies['lookup_case'])($caseId),
            $context['case_prefix']
        );
        $note = trim((string)($input['decision_note'] ?? ''));
        if ($note === '' || !str_contains($note, $context['run_key'])) {
            throw new InvalidArgumentException('Synthetic E4 decision_note must contain the active run_key.');
        }
        $payload = be_cc_e4_failed_operation_payload($operationId, $note);
        ($dependencies['seed_failed_operation'])(
            $operationId,
            $caseId,
            be_cc_operation_payload_hash($payload),
            'E4 controlled partial state after confirmed Event write.'
        );
        return [
            'mode' => $mode,
            'mutation' => true,
            'case_id' => (string)$case['id'],
            'operation_id' => $operationId,
            'status' => 'failed',
        ];
    }

    if ($mode === 'states') {
        $caseIds = array_map('be_cc_e4_validate_case_id', be_cc_e4_string_list($input['case_ids'] ?? []));
        $operationIds = array_map(
            static fn(string $value): string => be_cc_e4_validate_operation_id($value, $context['operation_prefix']),
            be_cc_e4_string_list($input['operation_ids'] ?? [])
        );
        foreach ($caseIds as $caseId) {
            $case = ($dependencies['lookup_case'])($caseId);
            if ($case !== null) be_cc_e4_validate_synthetic_case($case, $context['case_prefix']);
        }
        return [
            'mode' => $mode,
            'mutation' => false,
            'states' => ($dependencies['states'])($caseIds, $operationIds),
        ];
    }

    if ($mode === 'cleanup') {
        $deleted = ($dependencies['cleanup'])($context['run_key']);
        return [
            'mode' => $mode,
            'mutation' => true,
            'deleted' => $deleted,
            'remaining' => ($dependencies['counts'])($context['run_key']),
            'global_remaining' => ($dependencies['counts'])(null),
        ];
    }

    if ($mode === 'counts') {
        return [
            'mode' => $mode,
            'mutation' => false,
            'run' => ($dependencies['counts'])($context['run_key']),
            'global' => ($dependencies['counts'])(null),
        ];
    }

    throw new InvalidArgumentException('Unsupported synthetic E4 runtime mode.');
}
