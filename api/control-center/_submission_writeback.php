<?php
declare(strict_types=1);

require_once __DIR__ . '/_writeback.php';

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
        'timeout' => 60,
        'ignore_errors' => true,
    ]]);
    $raw = file_get_contents(be_cc_internal_api_origin() . $path, false, $context);
    $data = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (!is_array($data) || ($data['status'] ?? '') !== 'ok') {
        throw new RuntimeException(trim((string)($data['message'] ?? $data['error_message'] ?? 'Submission writeback failed.')));
    }
    return $data;
}

function be_cc_writeback_submission(array $case, string $action, array $payload): string
{
    $source = json_decode((string)($case['source_payload_json'] ?? ''), true);
    if (!is_array($source)) throw new RuntimeException('Submission source payload is missing.');
    $submissionId = (int)($source['id'] ?? $source['submission_id'] ?? 0);
    $status = trim((string)($source['status'] ?? ''));
    if ($submissionId <= 0) throw new RuntimeException('Submission id is invalid.');

    if ($action === 'approve') {
        if ($status === 'pending_review') {
            be_cc_internal_post('/api/submissions/release-payment.php', ['submission_id' => $submissionId]);
            return 'wait';
        }
        if (in_array($status, ['paid', 'in_review'], true)) {
            be_cc_internal_post('/api/submissions/approve.php', ['submission_id' => $submissionId]);
            return 'approve';
        }
        throw new DomainException('Diese Einreichung wartet aktuell auf Zahlung und kann noch nicht veröffentlicht werden.');
    }

    if ($action === 'reject') {
        $reason = trim((string)($payload['reason'] ?? 'Redaktionell nicht passend'));
        be_cc_internal_post('/api/submissions/reject.php', ['submission_id' => $submissionId, 'reason' => $reason]);
        return 'reject';
    }

    if ($action === 'snooze') return 'snooze';
    return $action;
}
