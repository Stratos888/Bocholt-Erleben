<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $pdo = be_db();
    $candidateId = trim((string)($_GET['id'] ?? ''));
    if ($candidateId !== '') {
        $data = be_startpartner_gate3_candidate_detail($pdo, $candidateId);
    } else {
        $items = be_startpartner_gate2_list_candidates($pdo, [
            'status' => trim((string)($_GET['status'] ?? '')),
            'source' => trim((string)($_GET['source'] ?? '')),
            'scope' => trim((string)($_GET['scope'] ?? '')),
            'assigned_to' => trim((string)($_GET['assigned_to'] ?? '')),
            'decision_ready' => trim((string)($_GET['decision_ready'] ?? '')),
            'overdue' => !empty($_GET['overdue']),
            'limit' => (int)($_GET['limit'] ?? 100),
        ]);
        foreach ($items as &$item) {
            $item['gate3'] = be_startpartner_gate3_state($pdo, (string)$item['id'], false);
        }
        unset($item);
        $data = [
            'items' => $items,
            'capacity' => be_startpartner_gate2_capacity($pdo),
            'total' => count($items),
        ];
    }

    be_json_response(200, ['status' => 'ok', 'data' => $data]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    $schemaMissing = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:')
        || str_starts_with($error->getMessage(), 'STARTPARTNER_GATE3_SCHEMA_MISSING:');
    $statusCode = $schemaMissing ? 503 : 404;
    be_json_response($statusCode, [
        'status' => 'error',
        'message' => $statusCode === 503 ? 'Startpartner schema is not ready.' : $error->getMessage(),
        'error_message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Startpartner candidates could not be loaded.',
        'error_message' => $error->getMessage(),
    ]);
}
