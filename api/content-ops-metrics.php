<?php
declare(strict_types=1);
/* === BEGIN FILE: api/content-ops-metrics.php | Zweck: liefert Content-Ops-Metriken fuer die spaetere zentrale Verwaltung; Umfang: geschuetzter 28-Tage-Vergleich und Wochenverlauf === */
require __DIR__ . '/_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']); }
be_require_review_access();

function cm_key(string $v, int $max = 160): string { $v = strtolower(trim($v)); $v = preg_replace('/[^a-z0-9_\-.]+/', '_', $v) ?? ''; return substr(trim($v, '_-.'), 0, $max); }
function cm_bounds(int $days): array { $today = new DateTimeImmutable('today', new DateTimeZone('UTC')); $end = $today; $start = $today->modify('-'.max(1,$days-1).' days'); $prevEnd = $start->modify('-1 day'); $prevStart = $prevEnd->modify('-'.max(1,$days-1).' days'); return [[$start->format('Y-m-d'),$end->format('Y-m-d')],[$prevStart->format('Y-m-d'),$prevEnd->format('Y-m-d')]]; }
function cm_metric_rows(PDO $pdo, string $env, string $start, string $end): array { $s=$pdo->prepare("SELECT metric_key,metric_scope,dimension_key,ROUND(SUM(metric_value),4) AS metric_value FROM content_ops_metric_daily WHERE environment=? AND metric_date BETWEEN ? AND ? GROUP BY metric_key,metric_scope,dimension_key ORDER BY metric_key,metric_scope,dimension_key"); $s->execute([$env,$start,$end]); return $s->fetchAll() ?: []; }
function cm_index(array $rows): array { $out=[]; foreach($rows as $r){ $key=($r['metric_key']??'').'|'.($r['metric_scope']??'').'|'.($r['dimension_key']??''); $out[$key]=$r; } return $out; }
function cm_compare(array $cur, array $prev): array { $c=cm_index($cur); $p=cm_index($prev); $keys=array_unique(array_merge(array_keys($c),array_keys($p))); sort($keys); $out=[]; foreach($keys as $k){ $b=$c[$k]??$p[$k]??[]; $cv=(float)($c[$k]['metric_value']??0); $pv=(float)($p[$k]['metric_value']??0); $out[]=['metric_key'=>(string)($b['metric_key']??''),'metric_scope'=>(string)($b['metric_scope']??''),'dimension_key'=>(string)($b['dimension_key']??''),'previous_value'=>$pv,'current_value'=>$cv,'delta'=>round($cv-$pv,4)]; } return $out; }

$env = cm_key((string)($_GET['environment'] ?? 'staging'), 32) ?: 'staging';
$days = max(7, min(90, (int)($_GET['days'] ?? 28)));
[$current, $previous] = cm_bounds($days);
try {
    $pdo = be_db();
    $currentRows = cm_metric_rows($pdo, $env, $current[0], $current[1]);
    $previousRows = cm_metric_rows($pdo, $env, $previous[0], $previous[1]);
    $actions = $pdo->prepare("SELECT action_type,finding_type,user_action_required,COUNT(*) AS total FROM content_ops_action_log WHERE environment=? AND generated_at_utc >= CONCAT(?, ' 00:00:00') AND generated_at_utc <= CONCAT(?, ' 23:59:59') GROUP BY action_type,finding_type,user_action_required ORDER BY total DESC");
    $actions->execute([$env,$current[0],$current[1]]);
    $rules = $pdo->prepare("SELECT rule_key,rule_type,rule_class,SUM(applied_count) AS applied_count,SUM(prevented_count) AS prevented_count,SUM(recurrence_count) AS recurrence_count,SUM(false_positive_count) AS false_positive_count FROM feedback_rule_effectiveness_daily WHERE environment=? AND metric_date BETWEEN ? AND ? GROUP BY rule_key,rule_type,rule_class ORDER BY applied_count DESC, prevented_count DESC");
    $rules->execute([$env,$current[0],$current[1]]);
    be_json_response(200, ['status'=>'ok','environment'=>$env,'period_days'=>$days,'periods'=>['current'=>['start'=>$current[0],'end'=>$current[1]],'previous'=>['start'=>$previous[0],'end'=>$previous[1]]],'metric_comparison'=>cm_compare($currentRows,$previousRows),'actions_current'=>$actions->fetchAll() ?: [],'feedback_rules_current'=>$rules->fetchAll() ?: []]);
} catch (Throwable $e) { be_json_response(500, ['status'=>'error','message'=>'Content Ops metrics could not be loaded.','error_class'=>get_class($e),'error_message'=>$e->getMessage()]); }
/* === END FILE: api/content-ops-metrics.php === */
