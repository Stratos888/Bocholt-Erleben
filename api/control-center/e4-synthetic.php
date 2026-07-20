<?php
declare(strict_types=1);

require_once __DIR__ . '/_schema.php';
require_once __DIR__ . '/_e4_synthetic_runtime.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) throw new InvalidArgumentException('Invalid JSON body.');

    be_cc_ensure_schema();
    $pdo = be_db();
    $buildPath = dirname(__DIR__, 2) . '/meta/build.txt';
    $deployedBuild = is_file($buildPath) ? trim((string)file_get_contents($buildPath)) : '';

    $counts = static function(?string $runKey) use ($pdo): array {
        $casePattern = $runKey === null
            ? BE_CC_E4_PREFIX . '-%'
            : BE_CC_E4_PREFIX . '-' . $runKey . '-%';
        $operationPattern = $runKey === null ? 'e4:%' : 'e4:' . $runKey . ':%';

        $caseStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM control_cases
             WHERE source_system='inbox_feed' AND source_reference LIKE :pattern"
        );
        $caseStmt->execute(['pattern' => $casePattern]);

        $operationStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM control_operations WHERE operation_id LIKE :pattern'
        );
        $operationStmt->execute(['pattern' => $operationPattern]);

        return [
            'cases' => (int)$caseStmt->fetchColumn(),
            'operations' => (int)$operationStmt->fetchColumn(),
        ];
    };

    $lookupCase = static function(string $caseId) use ($pdo): ?array {
        $stmt = $pdo->prepare(
            'SELECT id,state,source_system,source_reference,decision_ready
             FROM control_cases WHERE id=:id'
        );
        $stmt->execute(['id' => $caseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    };

    $seedFailedOperation = static function(
        string $operationId,
        string $caseId,
        string $payloadHash,
        string $errorText
    ) use ($pdo): void {
        $stmt = $pdo->prepare(
            "INSERT INTO control_operations
             (operation_id,case_id,action,payload_hash,status,error_text,completed_at)
             VALUES (:operation_id,:case_id,'approve',:payload_hash,'failed',:error_text,NOW())"
        );
        $stmt->execute([
            'operation_id' => $operationId,
            'case_id' => $caseId,
            'payload_hash' => $payloadHash,
            'error_text' => $errorText,
        ]);
    };

    $states = static function(array $caseIds, array $operationIds) use ($pdo): array {
        $cases = [];
        $caseStmt = $pdo->prepare(
            'SELECT id,state,source_system,source_reference,decision_ready
             FROM control_cases WHERE id=:id'
        );
        foreach ($caseIds as $caseId) {
            $caseStmt->execute(['id' => $caseId]);
            $row = $caseStmt->fetch(PDO::FETCH_ASSOC);
            $cases[$caseId] = is_array($row) ? $row : null;
        }

        $operations = [];
        $operationStmt = $pdo->prepare(
            'SELECT operation_id,case_id,action,status,error_text
             FROM control_operations WHERE operation_id=:id'
        );
        foreach ($operationIds as $operationId) {
            $operationStmt->execute(['id' => $operationId]);
            $row = $operationStmt->fetch(PDO::FETCH_ASSOC);
            $operations[$operationId] = is_array($row) ? $row : null;
        }

        return ['cases' => $cases, 'operations' => $operations];
    };

    $cleanup = static function(string $runKey) use ($pdo): array {
        $casePattern = BE_CC_E4_PREFIX . '-' . $runKey . '-%';
        $operationPattern = 'e4:' . $runKey . ':%';
        $pdo->beginTransaction();
        try {
            $caseIdsStmt = $pdo->prepare(
                "SELECT id FROM control_cases
                 WHERE source_system='inbox_feed' AND source_reference LIKE :pattern"
            );
            $caseIdsStmt->execute(['pattern' => $casePattern]);
            $caseIds = array_values(array_filter(array_map(
                static fn(array $row): string => trim((string)($row['id'] ?? '')),
                $caseIdsStmt->fetchAll(PDO::FETCH_ASSOC)
            )));

            $operationDelete = $pdo->prepare(
                'DELETE FROM control_operations WHERE operation_id LIKE :pattern'
            );
            $operationDelete->execute(['pattern' => $operationPattern]);
            $deletedOperations = $operationDelete->rowCount();

            if ($caseIds !== []) {
                $placeholders = implode(',', array_fill(0, count($caseIds), '?'));
                $byCaseDelete = $pdo->prepare(
                    'DELETE FROM control_operations WHERE case_id IN (' . $placeholders . ')'
                );
                $byCaseDelete->execute($caseIds);
                $deletedOperations += $byCaseDelete->rowCount();
            }

            $caseDelete = $pdo->prepare(
                "DELETE FROM control_cases
                 WHERE source_system='inbox_feed' AND source_reference LIKE :pattern"
            );
            $caseDelete->execute(['pattern' => $casePattern]);
            $deletedCases = $caseDelete->rowCount();
            $pdo->commit();

            return ['cases' => $deletedCases, 'operations' => $deletedOperations];
        } catch (Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    };

    $data = be_cc_e4_execute($input, [
        'environment' => be_app_env_value(),
        'deployed_build' => $deployedBuild,
        'counts' => $counts,
        'lookup_case' => $lookupCase,
        'seed_failed_operation' => $seedFailedOperation,
        'states' => $states,
        'cleanup' => $cleanup,
    ]);

    be_json_response(200, ['status' => 'ok', 'data' => $data]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Synthetic E4 runtime operation failed.',
        'error_message' => $error->getMessage(),
    ]);
}
