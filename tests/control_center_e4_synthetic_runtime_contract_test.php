<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/control-center/_e4_synthetic_runtime.php';

function e4_assert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

function e4_expect_exception(callable $callback, string $class): void
{
    try {
        $callback();
    } catch (Throwable $error) {
        e4_assert($error instanceof $class, 'Unexpected exception: ' . get_class($error));
        return;
    }
    throw new RuntimeException('Expected exception was not raised: ' . $class);
}

$targetSha = str_repeat('a', 40);
$runKey = substr($targetSha, 0, 12) . '-123456789';
$caseId = '123e4567-e89b-42d3-a456-426614174000';
$operationId = 'e4:' . $runKey . ':partial-failed';
$sourceReference = BE_CC_E4_PREFIX . '-' . $runKey . '-resume';

$state = [
    'global' => ['cases' => 0, 'operations' => 0],
    'run' => ['cases' => 0, 'operations' => 0],
    'cases' => [
        $caseId => [
            'id' => $caseId,
            'state' => 'decision_required',
            'source_system' => 'inbox_feed',
            'source_reference' => $sourceReference,
            'decision_ready' => 1,
        ],
    ],
    'operations' => [],
    'seeded' => null,
    'cleanup_run_key' => null,
];

$dependencies = [
    'environment' => 'staging',
    'deployed_build' => substr($targetSha, 0, 12),
    'counts' => static function(?string $wantedRunKey) use (&$state): array {
        return $wantedRunKey === null ? $state['global'] : $state['run'];
    },
    'lookup_case' => static function(string $wantedCaseId) use (&$state): ?array {
        return $state['cases'][$wantedCaseId] ?? null;
    },
    'seed_failed_operation' => static function(
        string $wantedOperationId,
        string $wantedCaseId,
        string $payloadHash,
        string $errorText
    ) use (&$state): void {
        $state['seeded'] = [
            'operation_id' => $wantedOperationId,
            'case_id' => $wantedCaseId,
            'payload_hash' => $payloadHash,
            'error_text' => $errorText,
        ];
        $state['operations'][$wantedOperationId] = [
            'operation_id' => $wantedOperationId,
            'case_id' => $wantedCaseId,
            'action' => 'approve',
            'status' => 'failed',
            'error_text' => $errorText,
        ];
    },
    'states' => static function(array $caseIds, array $operationIds) use (&$state): array {
        return [
            'cases' => array_combine($caseIds, array_map(
                static fn(string $id): ?array => $state['cases'][$id] ?? null,
                $caseIds
            )) ?: [],
            'operations' => array_combine($operationIds, array_map(
                static fn(string $id): ?array => $state['operations'][$id] ?? null,
                $operationIds
            )) ?: [],
        ];
    },
    'cleanup' => static function(string $wantedRunKey) use (&$state): array {
        $state['cleanup_run_key'] = $wantedRunKey;
        $deleted = [
            'cases' => count($state['cases']),
            'operations' => count($state['operations']),
        ];
        $state['cases'] = [];
        $state['operations'] = [];
        $state['run'] = ['cases' => 0, 'operations' => 0];
        $state['global'] = ['cases' => 0, 'operations' => 0];
        return $deleted;
    },
];

$baseInput = [
    'target_sha' => $targetSha,
    'run_key' => $runKey,
];

$preflight = be_cc_e4_execute($baseInput + ['mode' => 'preflight'], $dependencies);
e4_assert($preflight['mutation'] === false, 'Preflight must be read-only.');
e4_assert($preflight['build'] === substr($targetSha, 0, 12), 'Preflight build mismatch.');

e4_expect_exception(
    static fn() => be_cc_e4_execute(
        $baseInput + ['mode' => 'preflight'],
        array_merge($dependencies, ['environment' => 'production'])
    ),
    DomainException::class
);

e4_expect_exception(
    static fn() => be_cc_e4_execute(
        $baseInput + ['mode' => 'preflight'],
        array_merge($dependencies, ['deployed_build' => 'bbbbbbbbbbbb'])
    ),
    DomainException::class
);

e4_expect_exception(
    static fn() => be_cc_e4_execute(
        ['mode' => 'preflight', 'target_sha' => $targetSha, 'run_key' => 'wrong-123'],
        $dependencies
    ),
    InvalidArgumentException::class
);

$note = 'E4 synthetic partial failure ' . $runKey;
$seeded = be_cc_e4_execute($baseInput + [
    'mode' => 'seed_failed_operation',
    'case_id' => $caseId,
    'operation_id' => $operationId,
    'decision_note' => $note,
], $dependencies);
e4_assert($seeded['mutation'] === true, 'Fault injection must be declared as mutation.');
e4_assert($seeded['status'] === 'failed', 'Fault injection status mismatch.');
$expectedHash = be_cc_operation_payload_hash(
    be_cc_e4_failed_operation_payload($operationId, $note)
);
e4_assert($state['seeded']['payload_hash'] === $expectedHash, 'Fault-injection payload hash mismatch.');

e4_expect_exception(
    static fn() => be_cc_e4_execute($baseInput + [
        'mode' => 'seed_failed_operation',
        'case_id' => $caseId,
        'operation_id' => 'e4:' . $runKey . ':wrong-operation',
        'decision_note' => $note,
    ], $dependencies),
    DomainException::class
);

$states = be_cc_e4_execute($baseInput + [
    'mode' => 'states',
    'case_ids' => [$caseId],
    'operation_ids' => [$operationId],
], $dependencies);
e4_assert($states['mutation'] === false, 'State lookup must be read-only.');
e4_assert(($states['states']['cases'][$caseId]['source_reference'] ?? '') === $sourceReference, 'Synthetic case state missing.');
e4_assert(($states['states']['operations'][$operationId]['status'] ?? '') === 'failed', 'Synthetic operation state missing.');

$cleanup = be_cc_e4_execute($baseInput + ['mode' => 'cleanup'], $dependencies);
e4_assert($cleanup['mutation'] === true, 'Cleanup must be declared as mutation.');
e4_assert($state['cleanup_run_key'] === $runKey, 'Cleanup escaped the active run key.');
e4_assert($cleanup['remaining'] === ['cases' => 0, 'operations' => 0], 'Run cleanup was incomplete.');
e4_assert($cleanup['global_remaining'] === ['cases' => 0, 'operations' => 0], 'Global synthetic cleanup was incomplete.');

$nonSyntheticDependencies = $dependencies;
$nonSyntheticDependencies['lookup_case'] = static fn(string $id): ?array => [
    'id' => $id,
    'state' => 'decision_required',
    'source_system' => 'inbox_feed',
    'source_reference' => 'real-event',
    'decision_ready' => 1,
];
e4_expect_exception(
    static fn() => be_cc_e4_execute($baseInput + [
        'mode' => 'seed_failed_operation',
        'case_id' => $caseId,
        'operation_id' => $operationId,
        'decision_note' => $note,
    ], $nonSyntheticDependencies),
    DomainException::class
);

print("Control Center server-mediated E4 runtime contract: OK\n");
