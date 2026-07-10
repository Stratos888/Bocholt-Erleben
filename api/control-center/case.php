<?php
declare(strict_types=1);

require __DIR__ . '/_domain.php';

be_require_review_access();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}

try {
    be_cc_require_schema();
    $id = trim((string)($_GET['id'] ?? ''));
    if ($id === '') throw new InvalidArgumentException('Case id is required.');
    $stmt = be_db()->prepare('SELECT * FROM control_cases WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    if (!$row) throw new RuntimeException('Case not found.');
    $item = be_cc_case_from_row($row);
    $payload = json_decode((string)($row['source_payload_json'] ?? ''), true);
    $item['source_payload'] = is_array($payload) ? $payload : [];
    be_json_response(200, ['status' => 'ok', 'data' => $item]);
} catch (InvalidArgumentException $error) {
    be_json_response(422, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (RuntimeException $error) {
    be_json_response(404, ['status' => 'error', 'message' => $error->getMessage()]);
} catch (Throwable $error) {
    be_json_response(500, ['status' => 'error', 'message' => 'Vorgang konnte nicht geladen werden.', 'error_message' => $error->getMessage()]);
}
