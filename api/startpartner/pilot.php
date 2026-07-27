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
    $candidateId = trim((string)($_GET['candidate_id'] ?? ''));
    $pilotId = trim((string)($_GET['id'] ?? ''));

    if ($candidateId === '' && $pilotId === '') {
        throw new InvalidArgumentException('candidate_id or pilot id is required.');
    }
    if ($candidateId === '') {
        $statement = $pdo->prepare(
            'SELECT candidate_id FROM startpartner_pilots WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $pilotId]);
        $resolved = $statement->fetchColumn();
        if ($resolved === false) {
            throw new RuntimeException('Startpartner pilot not found.');
        }
        $candidateId = (string)$resolved;
    }

    $data = be_startpartner_gate3_state($pdo, $candidateId, true);
    if ($data['pilot'] === null) {
        throw new RuntimeException('Startpartner pilot not found.');
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
        'message' => 'Startpartner pilot could not be loaded.',
        'error_message' => $error->getMessage(),
    ]);
}
