<?php
declare(strict_types=1);

require_once __DIR__ . '/_domain.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $candidateId = trim((string)($_GET['id'] ?? ''));
    $data = $candidateId !== ''
        ? be_startpartner_get_candidate(be_db(), $candidateId)
        : [
            'items' => be_startpartner_list_candidates(be_db(), [
                'status' => trim((string)($_GET['status'] ?? '')),
                'source' => trim((string)($_GET['source'] ?? '')),
                'limit' => (int)($_GET['limit'] ?? 100),
            ]),
        ];

    if (isset($data['items'])) {
        $data['total'] = count($data['items']);
    }
    be_json_response(200, ['status' => 'ok', 'data' => $data]);
} catch (InvalidArgumentException|DomainException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    $statusCode = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:') ? 503 : 404;
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
