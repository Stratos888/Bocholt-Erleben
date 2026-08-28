<?php
declare(strict_types=1);

require dirname(__DIR__) . '/_bootstrap.php';
require_once dirname(__DIR__) . '/startpartner/_gate4_domain.php';

function be_startpartner_portal_staging_diagnostic(Throwable $error): ?array
{
    $host = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
    $normalizedHost = preg_replace('/:\\d+$/', '', $host);
    if (is_string($normalizedHost) && $normalizedHost !== '') {
        $host = $normalizedHost;
    }

    if (!hash_equals('staging.bocholt-erleben.de', $host)) {
        return null;
    }

    $message = trim((string)$error->getMessage());
    $message = preg_replace('/[\\x00-\\x1F\\x7F]+/', ' ', $message) ?? '';
    $message = preg_replace('/\\s+/', ' ', $message) ?? '';
    $message = preg_replace(
        '/(password|passwd|pwd|token|secret|authorization)(\\s*[=:]\\s*)([^,;\\s]+)/i',
        '$1$2[redacted]',
        $message
    ) ?? '';
    if (strlen($message) > 320) {
        $message = substr($message, 0, 320) . '…';
    }

    return [
        'type' => get_class($error),
        'message' => $message,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    $pdo = be_db();
    $session = be_startpartner_gate4_portal_session($pdo);
    $candidate = be_startpartner_gate4_portal_candidate($pdo, (int)$session['organizer_id']);
    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'organizer_id' => (int)$session['organizer_id'],
            'gate4' => be_startpartner_gate4_portal_projection($candidate),
        ],
    ]);
} catch (InvalidArgumentException $error) {
    be_json_response(401, [
        'status' => 'error',
        'message' => 'Für den Startpartner-Pilot ist ein gültiger Veranstalterzugang erforderlich.',
    ]);
} catch (DomainException $error) {
    be_json_response(404, [
        'status' => 'error',
        'message' => 'Für diesen Veranstalter ist aktuell kein eindeutiger Startpartner-Pilot verfügbar.',
    ]);
} catch (Throwable $error) {
    $payload = [
        'status' => 'error',
        'message' => 'Der Pilotstatus konnte gerade nicht geladen werden.',
    ];
    $diagnostic = be_startpartner_portal_staging_diagnostic($error);
    if ($diagnostic !== null) {
        $payload['diagnostic'] = $diagnostic;
    }
    be_json_response(500, $payload);
}
