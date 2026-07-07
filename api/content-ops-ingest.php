<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-ingest.php | Zweck: speichert Content-Ops-Metriken aus GitHub Actions per HTTPS in Strato-MySQL; Umfang: token-geschuetzter POST-Endpunkt === */
require __DIR__ . '/_bootstrap.php';

function co_token(): string {
    $cfg = be_get_config();
    $token = trim((string)($cfg['content_ops']['ingest_token'] ?? $cfg['content_ops_ingest_token'] ?? ''));
    if ($token === '') { $token = trim((string)getenv('CONTENT_OPS_INGEST_TOKEN')); }
    if ($token === '') { throw new RuntimeException('Ingest token missing.'); }
    return $token;
}
function co_header_token(): string {
    $token = be_request_header('X-BE-Content-Ops-Token');
    if ($token !== '') { return $token; }
    $auth = be_request_header('Authorization');
    return preg_match('/^Bearer\s+(.+)$/i', $auth, $m) ? trim((string)$m[1]) : '';
}
function co_key(string $v, int $max = 160): string {
    $v = strtolower(trim($v));
    $v = preg_replace('/[^a-z0-9_\-.]+/', '_', $v) ?? '';
    return substr(trim($v, '_-.'), 0, $max);
}
function co_text(string $v, int $max = 512): string {
    $v = trim(preg_replace('/\s+/', ' ', $v) ?? '');
    return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
}
function co_json(mixed $v): string { return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR); }
function co_dt(string $v): string {
    try { $d = trim($v) !== '' ? new DateTimeImmutable($v) : new DateTimeImmutable('now', new DateTimeZone('UTC')); }
    catch (Throwable $e) { $d = new DateTimeImmutable('now', new DateTimeZone('UTC')); }
    return $d->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}
function co_schema(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_ops_run (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,run_fingerprint CHAR(64) NOT NULL,generated_at_utc DATETIME NOT NULL,environment VARCHAR(32) NOT NULL DEFAULT '',branch_name VARCHAR(64) NOT NULL DEFAULT '',workflow_name VARCHAR(191) NOT NULL DEFAULT '',github_run_id VARCHAR(64) NOT NULL DEFAULT '',github_run_url VARCHAR(512) NOT NULL DEFAULT '',source_mode VARCHAR(80) NOT NULL DEFAULT '',status VARCHAR(80) NOT NULL DEFAULT '',action_required TINYINT(1) NOT NULL DEFAULT 0,summary_json MEDIUMTEXT NULL,metrics_json MEDIUMTEXT NULL,findings_json MEDIUMTEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (id),UNIQUE KEY uq_content_ops_run_fingerprint (run_fingerprint),KEY idx_content_ops_run_lookup (environment, source_mode, generated_at_utc)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_ops_metric_daily (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,metric_date DATE NOT NULL,environment VARCHAR(32) NOT NULL DEFAULT '',metric_key VARCHAR(160) NOT NULL DEFAULT '',metric_scope VARCHAR(80) NOT NULL DEFAULT '',dimension_key VARCHAR(191) NOT NULL DEFAULT '',metric_value DECIMAL(18,4) NOT NULL DEFAULT 0,source_mode VARCHAR(80) NOT NULL DEFAULT '',run_fingerprint CHAR(64) NOT NULL DEFAULT '',dimensions_json MEDIUMTEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (id),UNIQUE KEY uq_content_ops_metric_run (metric_date, environment, metric_key, metric_scope, dimension_key, source_mode, run_fingerprint),KEY idx_content_ops_metric_lookup (environment, metric_key, metric_date),KEY idx_content_ops_metric_scope (environment, metric_scope, metric_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS content_ops_action_log (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,action_fingerprint CHAR(64) NOT NULL,generated_at_utc DATETIME NOT NULL,environment VARCHAR(32) NOT NULL DEFAULT '',source_mode VARCHAR(80) NOT NULL DEFAULT '',source_workflow VARCHAR(191) NOT NULL DEFAULT '',action_type VARCHAR(120) NOT NULL DEFAULT '',finding_type VARCHAR(191) NOT NULL DEFAULT '',entity_type VARCHAR(80) NOT NULL DEFAULT '',entity_id VARCHAR(191) NOT NULL DEFAULT '',title VARCHAR(255) NOT NULL DEFAULT '',severity VARCHAR(40) NOT NULL DEFAULT '',confidence VARCHAR(80) NOT NULL DEFAULT '',user_action_required TINYINT(1) NOT NULL DEFAULT 0,status VARCHAR(80) NOT NULL DEFAULT 'open',run_fingerprint CHAR(64) NOT NULL DEFAULT '',details_json MEDIUMTEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (id),UNIQUE KEY uq_content_ops_action (action_fingerprint, run_fingerprint),KEY idx_content_ops_action_lookup (environment, action_type, generated_at_utc),KEY idx_content_ops_action_manual (environment, user_action_required, generated_at_utc)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback_rule_effectiveness_daily (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,metric_date DATE NOT NULL,environment VARCHAR(32) NOT NULL DEFAULT '',rule_key VARCHAR(191) NOT NULL DEFAULT '',rule_type VARCHAR(80) NOT NULL DEFAULT '',rule_class VARCHAR(120) NOT NULL DEFAULT '',applied_count INT NOT NULL DEFAULT 0,prevented_count INT NOT NULL DEFAULT 0,recurrence_count INT NOT NULL DEFAULT 0,false_positive_count INT NOT NULL DEFAULT 0,source_mode VARCHAR(80) NOT NULL DEFAULT '',run_fingerprint CHAR(64) NOT NULL DEFAULT '',details_json MEDIUMTEXT NULL,created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (id),UNIQUE KEY uq_feedback_rule_effectiveness_run (metric_date, environment, rule_key, source_mode, run_fingerprint),KEY idx_feedback_rule_lookup (environment, rule_type, rule_class, metric_date)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function co_finding_fp(string $env, string $mode, array $f): string {
    return hash('sha256', co_json(['environment'=>$env,'source_mode'=>$mode,'finding_type'=>(string)($f['finding_type']??''),'entity_type'=>(string)($f['entity_type']??''),'entity_id'=>(string)($f['entity_id']??''),'title'=>(string)($f['title']??''),'severity'=>(string)($f['severity']??''),'safe_action'=>(string)($f['safe_action']??'')]));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']); }
try { $expected = co_token(); } catch (Throwable $e) { be_json_response(503, ['status'=>'error','message'=>'Content Ops ingest is not configured.']); }
if (!hash_equals($expected, co_header_token())) { be_json_response(401, ['status'=>'error','message'=>'Content Ops ingest access denied.']); }
$raw = file_get_contents('php://input') ?: '';
if (strlen($raw) > 5 * 1024 * 1024) { be_json_response(413, ['status'=>'error','message'=>'Payload too large.']); }
$payload = json_decode($raw, true);
if (!is_array($payload)) { be_json_response(400, ['status'=>'error','message'=>'Invalid JSON.']); }
$runs = isset($payload['runs']) && is_array($payload['runs']) ? $payload['runs'] : [$payload];

try {
    $pdo = be_db(); co_schema($pdo); $pdo->beginTransaction(); $stored = 0;
    $runStmt = $pdo->prepare("INSERT INTO content_ops_run (run_fingerprint,generated_at_utc,environment,branch_name,workflow_name,github_run_id,github_run_url,source_mode,status,action_required,summary_json,metrics_json,findings_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE generated_at_utc=VALUES(generated_at_utc),status=VALUES(status),action_required=VALUES(action_required),summary_json=VALUES(summary_json),metrics_json=VALUES(metrics_json),findings_json=VALUES(findings_json)");
    $metricStmt = $pdo->prepare("INSERT INTO content_ops_metric_daily (metric_date,environment,metric_key,metric_scope,dimension_key,metric_value,source_mode,run_fingerprint,dimensions_json) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE metric_value=VALUES(metric_value),dimensions_json=VALUES(dimensions_json)");
    $findStmt = $pdo->prepare("INSERT INTO content_ops_action_log (action_fingerprint,generated_at_utc,environment,source_mode,source_workflow,action_type,finding_type,entity_type,entity_id,title,severity,confidence,user_action_required,status,run_fingerprint,details_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE severity=VALUES(severity),confidence=VALUES(confidence),user_action_required=VALUES(user_action_required),details_json=VALUES(details_json)");
    $effStmt = $pdo->prepare("INSERT INTO feedback_rule_effectiveness_daily (metric_date,environment,rule_key,rule_type,rule_class,applied_count,prevented_count,recurrence_count,false_positive_count,source_mode,run_fingerprint,details_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE applied_count=VALUES(applied_count),prevented_count=VALUES(prevented_count),recurrence_count=VALUES(recurrence_count),false_positive_count=VALUES(false_positive_count),details_json=VALUES(details_json)");
    foreach ($runs as $run) {
        if (!is_array($run)) { continue; }
        $fp = co_text((string)($run['run_fingerprint'] ?? ''), 64); $mode = co_key((string)($run['source_mode'] ?? ''), 80);
        if ($fp === '' || $mode === '') { continue; }
        $generated = co_dt((string)($run['generated_at_utc'] ?? '')); $date = substr($generated, 0, 10); $env = co_key((string)($run['environment'] ?? 'unknown'), 32) ?: 'unknown';
        $metrics = is_array($run['metrics'] ?? null) ? $run['metrics'] : []; $findings = is_array($run['findings'] ?? null) ? $run['findings'] : []; $effects = is_array($run['rule_effects'] ?? null) ? $run['rule_effects'] : [];
        $runStmt->execute([$fp,$generated,$env,co_key((string)($run['branch']??''),64),co_text((string)($run['workflow']??''),191),co_text((string)($run['github_run_id']??''),64),co_text((string)($run['github_run_url']??''),512),$mode,co_key((string)($run['status']??'ok'),80),!empty($run['action_required'])?1:0,co_json(is_array($run['summary']??null)?$run['summary']:[]),co_json($metrics),co_json(array_slice($findings,0,300))]);
        foreach ($metrics as $m) { if (!is_array($m)) { continue; } $mk=co_key((string)($m['metric_key']??''),160); if ($mk==='') { continue; } $metricStmt->execute([$date,$env,$mk,co_key((string)($m['metric_scope']??'run'),80)?:'run',co_text((string)($m['dimension_key']??''),191),(float)($m['metric_value']??0),$mode,$fp,co_json(is_array($m['dimensions']??null)?$m['dimensions']:[])]); }
        foreach ($findings as $f) { if (!is_array($f)) continue; $manual=!empty($f['user_action_required']); $findStmt->execute([co_finding_fp($env,$mode,$f),$generated,$env,$mode,co_text((string)($f['source_workflow']??''),191),co_key((string)($f['safe_action']??'observe'),120)?:'observe',co_key((string)($f['finding_type']??''),191),co_key((string)($f['entity_type']??'system'),80)?:'system',co_text((string)($f['entity_id']??''),191),co_text((string)($f['title']??''),255),co_key((string)($f['severity']??'info'),40)?:'info',co_key((string)($f['confidence']??'observed'),80)?:'observed',$manual?1:0,$manual?'open':'auto_routed',$fp,co_json(is_array($f['details']??null)?$f['details']:[])]); }
        foreach ($effects as $e) { if (!is_array($e)) continue; $rk=co_text((string)($e['rule_key']??''),191); if ($rk==='') continue; $effStmt->execute([$date,$env,$rk,co_key((string)($e['rule_type']??''),80),co_key((string)($e['rule_class']??''),120),(int)($e['applied_count']??0),(int)($e['prevented_count']??0),(int)($e['recurrence_count']??0),(int)($e['false_positive_count']??0),$mode,$fp,co_json(is_array($e['details']??null)?$e['details']:[])]); }
        $stored++;
    }
    $pdo->commit(); be_json_response(200, ['status'=>'ok','stored_runs'=>$stored]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) { $pdo->rollBack(); }
    be_json_response(500, ['status'=>'error','message'=>'Content Ops ingest failed.','error_class'=>get_class($e),'error_message'=>$e->getMessage()]);
}
/* === END FILE: api/content-ops-ingest.php === */
