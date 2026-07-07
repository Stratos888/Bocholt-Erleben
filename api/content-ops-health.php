<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-health.php | Zweck: liefert Run-Health-Freshness fuer Content-Ops-Roboter aus SQL; Umfang: geschuetzte Betreiber-API === */
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    be_json_response(405, ['status' => 'error', 'message' => 'Method not allowed.']);
}
be_require_review_access();

function coh_key(string $value, int $max = 160): string {
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9_\-.]+/', '_', $value) ?? '';
    return substr(trim($value, '_-.'), 0, $max);
}

function coh_targets(): array {
    $path = dirname(__DIR__) . '/data/content_ops_run_health_targets.json';
    if (!is_file($path)) {
        throw new RuntimeException('Run health target config is missing.');
    }
    $payload = json_decode((string)file_get_contents($path), true);
    if (!is_array($payload) || !isset($payload['targets']) || !is_array($payload['targets'])) {
        throw new RuntimeException('Run health target config is invalid.');
    }
    return $payload['targets'];
}

function coh_latest_run(PDO $pdo, string $environment, string $sourceMode): ?array {
    $stmt = $pdo->prepare(
        "SELECT generated_at_utc, source_mode, status, action_required, workflow_name, github_run_id, github_run_url, summary_json
         FROM content_ops_run
         WHERE environment = ? AND source_mode = ?
         ORDER BY generated_at_utc DESC, id DESC
         LIMIT 1"
    );
    $stmt->execute([$environment, $sourceMode]);
    $row = $stmt->fetch();
    return is_array($row) ? $row : null;
}

function coh_age_hours(?string $generatedAt): ?float {
    if (!$generatedAt) {
        return null;
    }
    try {
        $runTime = new DateTimeImmutable($generatedAt, new DateTimeZone('UTC'));
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        return round(max(0, $now->getTimestamp() - $runTime->getTimestamp()) / 3600, 2);
    } catch (Throwable $e) {
        return null;
    }
}

function coh_classify(array $target, ?array $run): array {
    $sourceMode = coh_key((string)($target['source_mode'] ?? ''), 80);
    $label = trim((string)($target['label'] ?? $sourceMode));
    $required = (bool)($target['required'] ?? false);
    $warnAfter = (float)($target['warn_after_hours'] ?? 0);
    $staleAfter = (float)($target['stale_after_hours'] ?? 0);

    if (!$run) {
        return [
            'source_mode' => $sourceMode,
            'label' => $label,
            'status' => $required ? 'missing_required' : 'missing_optional',
            'severity' => $required ? 'error' : 'info',
            'action_required' => $required,
            'age_hours' => null,
            'warn_after_hours' => $warnAfter,
            'stale_after_hours' => $staleAfter,
            'message' => $required ? 'Pflichtlauf fehlt.' : 'Optionaler ereignisgetriebener Lauf nicht beobachtet.',
        ];
    }

    $generatedAt = (string)($run['generated_at_utc'] ?? '');
    $age = coh_age_hours($generatedAt);
    $runStatus = trim((string)($run['status'] ?? ''));

    if ($age === null) {
        $status = 'invalid_timestamp';
        $severity = $required ? 'error' : 'warning';
        $actionRequired = $required;
    } elseif ($staleAfter > 0 && $age >= $staleAfter) {
        $status = 'stale';
        $severity = $required ? 'error' : 'warning';
        $actionRequired = $required;
    } elseif ($warnAfter > 0 && $age >= $warnAfter) {
        $status = 'late';
        $severity = 'warning';
        $actionRequired = false;
    } elseif (in_array($runStatus, ['source_artifact_missing', 'missing_source', 'failed'], true) || str_starts_with($runStatus, 'skipped_sql_error')) {
        $status = 'degraded';
        $severity = 'warning';
        $actionRequired = false;
    } else {
        $status = 'ok';
        $severity = 'ok';
        $actionRequired = false;
    }

    return [
        'source_mode' => $sourceMode,
        'label' => $label,
        'status' => $status,
        'severity' => $severity,
        'action_required' => $actionRequired,
        'age_hours' => $age,
        'generated_at_utc' => $generatedAt,
        'run_status' => $runStatus,
        'workflow_name' => (string)($run['workflow_name'] ?? ''),
        'github_run_id' => (string)($run['github_run_id'] ?? ''),
        'github_run_url' => (string)($run['github_run_url'] ?? ''),
        'warn_after_hours' => $warnAfter,
        'stale_after_hours' => $staleAfter,
    ];
}

$environment = coh_key((string)($_GET['environment'] ?? 'staging'), 32) ?: 'staging';

try {
    $pdo = be_db();
    $targets = coh_targets();
    $items = [];
    foreach ($targets as $target) {
        if (!is_array($target)) {
            continue;
        }
        $sourceMode = coh_key((string)($target['source_mode'] ?? ''), 80);
        if ($sourceMode === '') {
            continue;
        }
        $items[] = coh_classify($target, coh_latest_run($pdo, $environment, $sourceMode));
    }

    $errors = array_values(array_filter($items, fn($item) => ($item['severity'] ?? '') === 'error'));
    $warnings = array_values(array_filter($items, fn($item) => ($item['severity'] ?? '') === 'warning'));
    $actionRequired = array_values(array_filter($items, fn($item) => !empty($item['action_required'])));

    $status = count($errors) > 0 ? 'error' : (count($warnings) > 0 ? 'warning' : 'ok');

    be_json_response(200, [
        'status' => $status,
        'environment' => $environment,
        'generated_at_utc' => gmdate('c'),
        'summary' => [
            'total' => count($items),
            'ok' => count(array_filter($items, fn($item) => ($item['severity'] ?? '') === 'ok')),
            'warnings' => count($warnings),
            'errors' => count($errors),
            'action_required' => count($actionRequired),
        ],
        'items' => $items,
    ]);
} catch (Throwable $e) {
    be_json_response(500, [
        'status' => 'error',
        'message' => 'Content Ops health could not be loaded.',
        'error_class' => get_class($e),
        'error_message' => $e->getMessage(),
    ]);
}
/* === END FILE: api/content-ops-health.php === */
