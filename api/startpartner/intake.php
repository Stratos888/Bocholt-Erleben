<?php
declare(strict_types=1);

require_once __DIR__ . '/_public_intake.php';

be_startpartner_require_gate1_environment();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

function be_startpartner_intake_wants_json(): bool
{
    $contentType = strtolower(trim((string)($_SERVER['CONTENT_TYPE'] ?? '')));
    $accept = strtolower(trim(be_request_header('Accept')));
    return str_contains($contentType, 'application/json') || str_contains($accept, 'application/json');
}

function be_startpartner_intake_read_input(bool $wantsJson): array
{
    if (!$wantsJson) {
        return is_array($_POST) ? $_POST : [];
    }

    $raw = (string)file_get_contents('php://input');
    if (trim($raw) === '') {
        throw new InvalidArgumentException('Invalid JSON body.');
    }

    $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Invalid JSON body.');
    }

    return $decoded;
}

function be_startpartner_intake_redirect(string $target): never
{
    header('Location: ' . $target, true, 303);
    exit;
}

$wantsJson = be_startpartner_intake_wants_json();

try {
    $input = be_startpartner_intake_read_input($wantsJson);
    $source = trim((string)($input['source'] ?? 'self_service'));
    $headerIdempotencyKey = be_request_header('Idempotency-Key');

    if ($source === 'targeted_outreach') {
        be_require_review_access();
        if ($headerIdempotencyKey !== '') {
            $input['idempotency_key'] = $headerIdempotencyKey;
        }

        $result = be_startpartner_create_candidate(
            be_db(),
            $input,
            'operator',
            'review-access'
        );

        be_json_response($result['created'] ? 201 : 200, [
            'status' => 'ok',
            'data' => $result,
        ]);
    }

    if ($source !== 'self_service') {
        throw new InvalidArgumentException('Invalid intake source.');
    }

    if (be_startpartner_public_is_honeypot($input)) {
        if ($wantsJson) {
            be_json_response(200, [
                'status' => 'ok',
                'data' => [
                    'stored' => true,
                    'confirmation_mail_sent' => false,
                ],
            ]);
        }
        be_startpartner_intake_redirect('/startpartner/erfolg/?mail=pending');
    }

    $publicInput = be_startpartner_public_prepare_input($input, $headerIdempotencyKey);
    $result = be_startpartner_create_candidate(
        be_db(),
        $publicInput,
        'self_service',
        'public-startpartner-form'
    );

    $mailSent = false;
    if (($result['created'] ?? false) === true) {
        $candidate = is_array($result['candidate'] ?? null) ? $result['candidate'] : [];
        $mailSent = be_startpartner_public_send_received_mail($candidate);
    }

    $publicResult = [
        'stored' => true,
        'confirmation_mail_sent' => $mailSent,
    ];

    if ($wantsJson) {
        be_json_response($result['created'] ? 201 : 200, [
            'status' => 'ok',
            'data' => $publicResult,
        ]);
    }

    be_startpartner_intake_redirect('/startpartner/erfolg/?mail=' . ($mailSent ? 'sent' : 'pending'));
} catch (JsonException|InvalidArgumentException|DomainException $error) {
    if (!$wantsJson) {
        be_startpartner_intake_redirect('/startpartner/?error=submit');
    }
    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (RuntimeException $error) {
    $statusCode = str_starts_with($error->getMessage(), 'STARTPARTNER_SCHEMA_MISSING:') ? 503 : 500;
    if (!$wantsJson) {
        be_startpartner_intake_redirect('/startpartner/?error=submit');
    }
    be_json_response($statusCode, [
        'status' => 'error',
        'message' => $statusCode === 503 ? 'Startpartner schema is not ready.' : 'The Startpartner request could not be stored.',
        'error_message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    if (!$wantsJson) {
        be_startpartner_intake_redirect('/startpartner/?error=submit');
    }
    be_json_response(500, [
        'status' => 'error',
        'message' => 'The Startpartner request could not be stored.',
        'error_message' => $error->getMessage(),
    ]);
}
