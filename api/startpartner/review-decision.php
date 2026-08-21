<?php
declare(strict_types=1);

require_once __DIR__ . '/_review_communication.php';

be_startpartner_require_gate1_environment();
be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $input = json_decode((string)file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($input)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }
    $candidateId = trim((string)($input['candidate_id'] ?? ''));
    if ($candidateId === '') {
        throw new InvalidArgumentException('candidate_id is required.');
    }

    $pdo = be_db();
    $result = be_startpartner_review_decision($pdo, $candidateId, $input);
    $decision = trim((string)($input['decision'] ?? ''));
    $topic = be_startpartner_review_communication_topic_for_decision($decision);
    $decisionOperationId = be_startpartner_gate2_operation_id($input['operation_id'] ?? null);
    $attemptState = be_startpartner_review_communication_event_state(
        $pdo,
        $candidateId,
        $topic,
        $decisionOperationId
    );
    $communication = [
        'status' => $attemptState ?? 'skipped',
        'sent' => $attemptState === 'sent',
        'idempotent_replay' => $attemptState !== null,
        'candidate' => $attemptState !== null
            ? be_startpartner_gate2_candidate_detail($pdo, $candidateId)
            : (array)($result['candidate'] ?? []),
    ];

    if (($result['idempotent_replay'] ?? false) !== true || $attemptState === null) {
        $candidate = (array)($result['candidate'] ?? []);
        $customerMessage = $decision === 'needs_information'
            ? be_startpartner_clean_text($input['reason'] ?? null, 5000, 'reason')
            : ($decision === 'reject'
                ? be_startpartner_clean_text($input['customer_message'] ?? null, 5000, 'customer_message')
                : null);
        try {
            $communication = be_startpartner_review_communication_send($pdo, $candidateId, [
                'topic' => $topic,
                'operation_id' => $decisionOperationId,
                'expected_revision' => (int)($candidate['revision'] ?? 0),
                'operator_name' => $input['operator_name'] ?? null,
                'customer_message' => $customerMessage,
            ]);
        } catch (Throwable $mailError) {
            error_log('Startpartner review communication orchestration failed: ' . $mailError->getMessage());
            $communication = [
                'status' => 'failed',
                'sent' => false,
                'idempotent_replay' => false,
                'failure_code' => 'orchestration_failed',
                'candidate' => be_startpartner_gate2_candidate_detail($pdo, $candidateId),
            ];
        }
    }

    if (is_array($communication['candidate'] ?? null)) {
        $result['candidate'] = $communication['candidate'];
    }
    $result['communication'] = $communication;
    be_json_response(200, ['status' => 'ok', 'data' => $result]);
} catch (BeStartpartnerConflictException $error) {
    be_json_response(409, [
        'status' => 'error',
        'code' => 'STARTPARTNER_CONFLICT',
        'message' => 'Zwischenzeitlich geändert.',
        'current' => $error->currentState,
        'error_message' => $error->getMessage(),
    ]);
} catch (JsonException|InvalidArgumentException|DomainException $error) {
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
        'message' => 'Startpartner review decision could not be applied.',
        'error_message' => $error->getMessage(),
    ]);
}
