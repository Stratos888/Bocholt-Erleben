<?php
declare(strict_types=1);

require_once __DIR__ . '/_writeback.php';

const BE_CC_SUBMISSION_INTERNAL_TIMEOUT_SECONDS = 15;

function be_cc_internal_api_origin(): string
{
    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') throw new RuntimeException('Internal API host is unavailable.');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host;
}

function be_cc_internal_post(string $path, array $payload): array
{
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $headers = "Content-Type: application/json; charset=utf-8\r\nAccept: application/json\r\nX-BE-Review-Password: " . be_review_password() . "\r\n";
    $context = stream_context_create(['http' => [
        'method' => 'POST',
        'header' => $headers,
        'content' => $body,
        'timeout' => BE_CC_SUBMISSION_INTERNAL_TIMEOUT_SECONDS,
        'ignore_errors' => true,
    ]]);
    $raw = @file_get_contents(be_cc_internal_api_origin() . $path, false, $context);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data) || ($data['status'] ?? '') !== 'ok') {
        throw new RuntimeException(trim((string)($data['message'] ?? $data['error_message'] ?? 'Submission writeback failed or timed out.')));
    }
    return is_array($data['data'] ?? null) ? $data['data'] : [];
}

function be_cc_submission_current_status(PDO $pdo, int $submissionId): string
{
    $stmt = $pdo->prepare('SELECT status FROM submissions WHERE id=:id LIMIT 1');
    $stmt->execute(['id' => $submissionId]);
    $status = $stmt->fetchColumn();
    if ($status === false) throw new RuntimeException('Submission konnte nach dem Writeback nicht zurückgelesen werden.');
    return trim((string)$status);
}

function be_cc_verify_submission_status(PDO $pdo, int $submissionId, array $expected): string
{
    $status = be_cc_submission_current_status($pdo, $submissionId);
    if (!in_array($status, $expected, true)) {
        throw new RuntimeException(sprintf(
            'Submission-Writeback nicht bestätigt: erwartet %s, gefunden "%s".',
            implode(' oder ', array_map(static fn(string $value): string => '"' . $value . '"', $expected)),
            $status
        ));
    }
    return $status;
}

function be_cc_submission_rejection_reason(array $payload): string
{
    $reason = trim((string)($payload['decision_note'] ?? $payload['reason'] ?? ''));
    if ($reason !== '') return $reason;
    $meta = function_exists('be_cc_decision_class_meta') ? be_cc_decision_class_meta(trim((string)($payload['decision_class'] ?? ''))) : [];
    $label = trim((string)($meta['label'] ?? ''));
    return $label !== '' ? $label : 'Redaktionell nicht passend';
}

function be_cc_writeback_submission(array $case, string $action, array $payload): array
{
    $startedAt = microtime(true);
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    if (!is_array($source)) throw new RuntimeException('Submission source payload is missing.');
    $submissionId = (int)($source['id'] ?? $source['submission_id'] ?? 0);
    $status = trim((string)($source['status'] ?? ''));
    if ($submissionId <= 0) throw new RuntimeException('Submission id is invalid.');
    $pdo = be_db();

    if ($action === 'approve') {
        if ($status === 'pending_review') {
            be_cc_internal_post('/api/submissions/release-payment.php', ['submission_id' => $submissionId]);
            $verifiedStatus = be_cc_verify_submission_status($pdo, $submissionId, ['payment_released']);
            return [
                'effective_action' => 'wait',
                'transport' => 'internal_api',
                'source_verified' => true,
                'source_status' => $verifiedStatus,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
        }
        if (in_array($status, ['paid', 'in_review'], true)) {
            be_cc_internal_post('/api/submissions/approve.php', ['submission_id' => $submissionId]);
            $verifiedStatus = be_cc_verify_submission_status($pdo, $submissionId, ['approved']);
            return [
                'effective_action' => 'approve',
                'transport' => 'internal_api',
                'source_verified' => true,
                'source_status' => $verifiedStatus,
                'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
            ];
        }
        throw new DomainException('Diese Einreichung wartet aktuell auf Zahlung und kann noch nicht veröffentlicht werden.');
    }

    if ($action === 'reject') {
        $reason = be_cc_submission_rejection_reason($payload);
        be_cc_internal_post('/api/submissions/reject.php', ['submission_id' => $submissionId, 'reason' => $reason]);
        $verifiedStatus = be_cc_verify_submission_status($pdo, $submissionId, ['rejected']);
        return [
            'effective_action' => 'reject',
            'transport' => 'internal_api',
            'source_verified' => true,
            'source_status' => $verifiedStatus,
            'reason' => $reason,
            'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
        ];
    }

    if ($action === 'snooze') {
        return [
            'effective_action' => 'snooze',
            'transport' => 'local_schedule',
            'source_verified' => true,
            'source_status' => be_cc_submission_current_status($pdo, $submissionId),
            'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
        ];
    }

    return [
        'effective_action' => $action,
        'transport' => 'none',
        'source_verified' => true,
        'source_status' => be_cc_submission_current_status($pdo, $submissionId),
        'duration_ms' => (int)round((microtime(true) - $startedAt) * 1000),
    ];
}
