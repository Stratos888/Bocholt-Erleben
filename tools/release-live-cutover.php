<?php
declare(strict_types=1);

/* Temporary SHA-bound live release cutover writer for Workpack #294.
 * Random filename, random token, POST only, removed in the same workflow.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow, noarchive');

const BE_CUTOVER_TOKEN_HASH = '__TOKEN_HASH__';
const BE_CUTOVER_EXPECTED_MAIN_BUILD = '__EXPECTED_MAIN_BUILD__';
const BE_CUTOVER_EXPECTED_RELEASE_SHA = '__EXPECTED_RELEASE_SHA__';
const BE_CUTOVER_EXPECTED_SERVER_VERSION = '8.0.36';
const BE_CUTOVER_LOCK = 'bocholt_release_cutover_294';

const BE_CUTOVER_EXISTING_TABLES = [
    'organizers','submissions','subscriptions','publication_entitlements','publication_consumptions',
    'organizer_magic_links','organizer_portal_sessions','webhook_events','value_metric_daily',
    'control_cases','control_case_events','control_content_changes','control_development_snapshots',
    'control_operations','control_editorial_feedback',
];
const BE_CUTOVER_CONTENT_OPS_TABLES = [
    'content_ops_run','content_ops_metric_daily','content_ops_action_log','feedback_rule_effectiveness_daily',
];
const BE_CUTOVER_STARTPARTNER_TABLES = [
    'startpartner_candidates','startpartner_candidate_contacts','startpartner_candidate_events',
    'startpartner_candidate_qualifications','startpartner_candidate_decisions','startpartner_candidate_reservations',
    'startpartner_candidate_waitlist','startpartner_candidate_operations','startpartner_pilot_terms_acceptances',
    'startpartner_pilots','startpartner_pilot_scopes','startpartner_pilot_entitlements','startpartner_pilot_events',
    'startpartner_pilot_onboarding_items','startpartner_pilot_content_links','startpartner_pilot_measurement_preflights',
    'startpartner_pilot_distribution_commitments','startpartner_pilot_usages',
];
const BE_CUTOVER_EXPECTED_MIGRATION_KEYS = [
    '007_runtime_schema_reconciliation','008_startpartner_candidates','009_control_center_runtime_schema',
    '010_startpartner_gate2_qualification_capacity','011_startpartner_gate3_terms_organizer_entitlement',
    '012_startpartner_gate4_onboarding_content_activation',
];
const BE_CUTOVER_MIGRATION_ORDER = [
    'api/sql/009_content_ops_metrics.sql',
    'api/sql/007_runtime_schema_reconciliation.sql',
    'api/sql/008_startpartner_candidates.sql',
    'api/sql/009_control_center_runtime_schema.sql',
    'api/sql/010_startpartner_gate2_qualification_capacity.sql',
    'api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql',
    'api/sql/012_startpartner_gate4_onboarding_content_activation.sql',
];
const BE_CUTOVER_EXPECTED_BLOBS = [
    'api/sql/009_content_ops_metrics.sql' => '94142f3cd1a33b17b0ad0dc94577fcfb06256184',
    'api/sql/007_runtime_schema_reconciliation.sql' => '06d43472c1fbf64e5cc8251d7c8291db99a71e75',
    'api/sql/008_startpartner_candidates.sql' => '2da3de7037dd08b0f1350fd9565803189fb90b42',
    'api/sql/009_control_center_runtime_schema.sql' => '8e6fee02b6838afe1874a9bd66d31980a4604778',
    'api/sql/010_startpartner_gate2_qualification_capacity.sql' => '6a5320844a7b34457ec17d1cab35b002c61f08f0',
    'api/sql/011_startpartner_gate3_terms_organizer_entitlement.sql' => '06d6bd37bfc816116e07cdb050618ad76133f658',
    'api/sql/012_startpartner_gate4_onboarding_content_activation.sql' => 'ced7186170bb82f4df166f517b4bb7948fca3587',
];

require __DIR__ . '/_bootstrap.php';

function out(array $payload, int $status = 200): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR), "\n";
    exit;
}
function must(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function require_token(): void {
    $token = trim((string)($_SERVER['HTTP_X_BE_RELEASE_TOKEN'] ?? ''));
    if ($token === '' || !hash_equals(BE_CUTOVER_TOKEN_HASH, hash('sha256', $token))) out(['status'=>'not_found'], 404);
}
function all(PDO $pdo, string $sql, array $params=[]): array {
    $s=$pdo->prepare($sql); $s->execute($params); $r=$s->fetchAll(PDO::FETCH_ASSOC); $s->closeCursor(); return is_array($r)?$r:[];
}
function scalar(PDO $pdo, string $sql, array $params=[]): mixed {
    $s=$pdo->prepare($sql); $s->execute($params); $v=$s->fetchColumn(); $s->closeCursor(); return $v;
}
function table_exists(PDO $pdo,string $db,string $table): bool {
    return (int)scalar($pdo,'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t',[':db'=>$db,':t'=>$table])===1;
}
function column_exists(PDO $pdo,string $db,string $table,string $column): bool {
    return (int)scalar($pdo,'SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND COLUMN_NAME=:c',[':db'=>$db,':t'=>$table,':c'=>$column])===1;
}
function index_exists(PDO $pdo,string $db,string $table,string $index): bool {
    return (int)scalar($pdo,'SELECT COUNT(DISTINCT INDEX_NAME) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=:db AND TABLE_NAME=:t AND INDEX_NAME=:i',[':db'=>$db,':t'=>$table,':i'=>$index])===1;
}
function table_count(PDO $pdo,string $table): int { $t=str_replace('`','``',$table); return (int)scalar($pdo,'SELECT COUNT(*) FROM `'.$t.'`'); }
function counts(PDO $pdo,array $tables): array { $r=[]; foreach($tables as $t)$r[$t]=table_count($pdo,$t); ksort($r); return $r; }
function existing(PDO $pdo,string $db,array $tables): array { $r=[]; foreach($tables as $t)if(table_exists($pdo,$db,$t))$r[]=$t; sort($r); return $r; }
function migration_keys(PDO $pdo,string $db): array {
    if(!table_exists($pdo,$db,'app_schema_migrations'))return [];
    $r=[]; foreach(all($pdo,'SELECT migration_key FROM app_schema_migrations ORDER BY migration_key') as $row){$k=(string)($row['migration_key']??'');if($k!=='')$r[]=$k;} sort($r); return $r;
}
function orphan_counts(PDO $pdo,string $db): array {
    $r=[];
    if(table_exists($pdo,$db,'control_cases')){
        if(table_exists($pdo,$db,'control_case_events'))$r['control_case_events']=(int)scalar($pdo,'SELECT COUNT(*) FROM control_case_events e LEFT JOIN control_cases c ON c.id=e.case_id WHERE c.id IS NULL');
        if(table_exists($pdo,$db,'control_operations'))$r['control_operations']=(int)scalar($pdo,'SELECT COUNT(*) FROM control_operations o LEFT JOIN control_cases c ON c.id=o.case_id WHERE c.id IS NULL');
        if(table_exists($pdo,$db,'control_editorial_feedback'))$r['control_editorial_feedback']=(int)scalar($pdo,'SELECT COUNT(*) FROM control_editorial_feedback f LEFT JOIN control_cases c ON c.id=f.case_id WHERE c.id IS NULL');
    }
    ksort($r); return $r;
}
function fk(PDO $pdo,string $db,string $table,string $name): ?array {
    $rows=all($pdo,'SELECT UPDATE_RULE,DELETE_RULE FROM INFORMATION_SCHEMA.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=:db AND TABLE_NAME=:t AND CONSTRAINT_NAME=:n',[':db'=>$db,':t'=>$table,':n'=>$name]);
    return count($rows)===1?['update_rule'=>(string)$rows[0]['UPDATE_RULE'],'delete_rule'=>(string)$rows[0]['DELETE_RULE']]:null;
}
function state(PDO $pdo,string $db,string $version,string $build): array {
    $sp=existing($pdo,$db,BE_CUTOVER_STARTPARTNER_TABLES); $co=existing($pdo,$db,BE_CUTOVER_CONTENT_OPS_TABLES);
    $spRows=[]; foreach($sp as $t)$spRows[$t]=table_count($pdo,$t); ksort($spRows);
    $coRows=[]; foreach($co as $t)$coRows[$t]=table_count($pdo,$t); ksort($coRows);
    return [
        'environment'=>function_exists('be_app_env_value')?be_app_env_value():'unknown','database'=>$db,'server_version'=>$version,'build'=>$build,
        'required_existing_tables'=>existing($pdo,$db,BE_CUTOVER_EXISTING_TABLES),'content_ops_existing'=>$co,'content_ops_row_counts'=>$coRows,
        'startpartner_existing'=>$sp,'startpartner_row_counts'=>$spRows,'migration_keys'=>migration_keys($pdo,$db),
        'submissions_flags'=>[
            'activity_opening_json'=>column_exists($pdo,$db,'submissions','activity_opening_json'),
            'activity_image_json'=>column_exists($pdo,$db,'submissions','activity_image_json'),
            'organizer_edited_at'=>column_exists($pdo,$db,'submissions','organizer_edited_at'),
            'idx_submissions_organizer_edited_at'=>index_exists($pdo,$db,'submissions','idx_submissions_organizer_edited_at'),
        ],
        'control_orphan_counts'=>orphan_counts($pdo,$db),
        'control_fks'=>[
            'control_case_events'=>fk($pdo,$db,'control_case_events','fk_control_case_events_case'),
            'control_operations'=>fk($pdo,$db,'control_operations','fk_control_operations_case'),
            'control_editorial_feedback'=>fk($pdo,$db,'control_editorial_feedback','fk_control_editorial_feedback_case'),
        ],
    ];
}
function zeroes(array $counts): bool { foreach($counts as $v)if((int)$v!==0)return false; return true; }
function sorted(array $v): array { sort($v); return $v; }
function exact_pre(array $s): bool {
    return $s['environment']==='live' && $s['server_version']===BE_CUTOVER_EXPECTED_SERVER_VERSION && $s['build']===BE_CUTOVER_EXPECTED_MAIN_BUILD
        && $s['required_existing_tables']===sorted(BE_CUTOVER_EXISTING_TABLES) && $s['content_ops_existing']===[] && $s['startpartner_existing']===[] && $s['migration_keys']===[]
        && $s['submissions_flags']===['activity_opening_json'=>true,'activity_image_json'=>true,'organizer_edited_at'=>true,'idx_submissions_organizer_edited_at'=>false]
        && $s['control_orphan_counts']===['control_case_events'=>0,'control_editorial_feedback'=>0,'control_operations'=>0]
        && ($s['control_fks']['control_case_events']??null)===['update_rule'=>'NO ACTION','delete_rule'=>'CASCADE']
        && ($s['control_fks']['control_operations']??null)===null && ($s['control_fks']['control_editorial_feedback']??null)===null;
}
function exact_post(array $s): bool {
    $events=$s['control_fks']['control_case_events']??null;
    return $s['environment']==='live' && $s['server_version']===BE_CUTOVER_EXPECTED_SERVER_VERSION && $s['build']===BE_CUTOVER_EXPECTED_MAIN_BUILD
        && $s['required_existing_tables']===sorted(BE_CUTOVER_EXISTING_TABLES) && $s['content_ops_existing']===sorted(BE_CUTOVER_CONTENT_OPS_TABLES)
        && zeroes($s['content_ops_row_counts']??[]) && $s['startpartner_existing']===sorted(BE_CUTOVER_STARTPARTNER_TABLES) && zeroes($s['startpartner_row_counts']??[])
        && $s['migration_keys']===sorted(BE_CUTOVER_EXPECTED_MIGRATION_KEYS)
        && $s['submissions_flags']===['activity_opening_json'=>true,'activity_image_json'=>true,'organizer_edited_at'=>true,'idx_submissions_organizer_edited_at'=>true]
        && $s['control_orphan_counts']===['control_case_events'=>0,'control_editorial_feedback'=>0,'control_operations'=>0]
        && is_array($events) && ($events['delete_rule']??'')==='CASCADE' && in_array(($events['update_rule']??''),['NO ACTION','CASCADE'],true)
        && ($s['control_fks']['control_operations']??null)===['update_rule'=>'CASCADE','delete_rule'=>'CASCADE']
        && ($s['control_fks']['control_editorial_feedback']??null)===['update_rule'=>'CASCADE','delete_rule'=>'CASCADE'];
}
function split_sql(string $sql): array {
    $out=[];$buf='';$quote=null;$line=false;$block=false;$n=strlen($sql);
    for($i=0;$i<$n;$i++){
        $c=$sql[$i];$next=$i+1<$n?$sql[$i+1]:'';
        if($line){if($c==="\n"){$line=false;$buf.="\n";}continue;}
        if($block){if($c==='*'&&$next==='/'){$block=false;$i++;}continue;}
        if($quote!==null){$buf.=$c;if($c==='\\'&&$next!==''){$buf.=$next;$i++;continue;}if($c===$quote){if($next===$quote&&$quote!=='`'){$buf.=$next;$i++;continue;}$quote=null;}continue;}
        if($c==='-'&&$next==='-'&&($i+2>=$n||ctype_space($sql[$i+2]))){$line=true;$i++;continue;}
        if($c==='#'){$line=true;continue;} if($c==='/'&&$next==='*'){$block=true;$i++;continue;}
        if($c==="'"||$c==='"'||$c==='`'){$quote=$c;$buf.=$c;continue;}
        if($c===';'){$stmt=trim($buf);if($stmt!=='')$out[]=$stmt;$buf='';continue;} $buf.=$c;
    }
    $stmt=trim($buf);if($stmt!=='')$out[]=$stmt;return $out;
}
function execute_statement(PDO $pdo,string $sql): void {
    $s=$pdo->prepare($sql);try{$s->execute();do{if($s->columnCount()>0)$s->fetchAll(PDO::FETCH_NUM);}while($s->nextRowset());}finally{$s->closeCursor();}
}
function git_blob_sha1(string $content): string { return sha1('blob '.strlen($content)."\0".$content); }
function validate_payload_migrations(array $input): array {
    $items=$input['migrations']??null; must(is_array($items)&&count($items)===count(BE_CUTOVER_MIGRATION_ORDER),'Migration payload count mismatch.');
    $validated=[];
    foreach(BE_CUTOVER_MIGRATION_ORDER as $i=>$path){
        $item=$items[$i]??null; must(is_array($item)&&($item['path']??null)===$path,'Migration path/order mismatch at position '.($i+1).'.');
        $sql=base64_decode((string)($item['content_b64']??''),true); must(is_string($sql)&&trim($sql)!=='','Invalid migration content: '.$path);
        must(hash_equals(BE_CUTOVER_EXPECTED_BLOBS[$path],git_blob_sha1($sql)),'Git blob digest mismatch: '.$path);
        $validated[]=['path'=>$path,'sql'=>$sql,'blob_sha'=>BE_CUTOVER_EXPECTED_BLOBS[$path]];
    }
    return $validated;
}
function apply_migration(PDO $pdo,array $m): array {
    $statements=split_sql($m['sql']);must(count($statements)>0,'No SQL statements: '.$m['path']);
    foreach($statements as $i=>$stmt){try{execute_statement($pdo,$stmt);}catch(Throwable $e){$excerpt=preg_replace('/\s+/',' ',trim($stmt));throw new RuntimeException('Migration '.$m['path'].' failed at statement '.($i+1).': '.substr((string)$excerpt,0,220).' | '.$e->getMessage(),0,$e);}}
    return ['path'=>$m['path'],'blob_sha'=>$m['blob_sha'],'statements'=>count($statements)];
}

require_token();
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')out(['status'=>'method_not_allowed'],405);
$pdo=null;$locked=false;$writeStarted=false;$before=null;$beforeCounts=null;$results=[];
try{
    $input=json_decode((string)file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);must(is_array($input),'Invalid request.');
    must((string)($input['expected_release_sha']??'')===BE_CUTOVER_EXPECTED_RELEASE_SHA,'Release SHA mismatch.');
    must((string)($input['expected_current_build']??'')===BE_CUTOVER_EXPECTED_MAIN_BUILD,'Current build contract mismatch.');
    $migrations=validate_payload_migrations($input);
    $buildPath=dirname(__DIR__).'/meta/build.txt';$build=is_file($buildPath)?trim((string)file_get_contents($buildPath)):'';must($build===BE_CUTOVER_EXPECTED_MAIN_BUILD,'Live build changed before DB cutover.');
    $cfg=be_get_config();$configured=trim((string)($cfg['db']['name']??''));$pdo=be_db();$db=(string)scalar($pdo,'SELECT DATABASE()');$version=(string)scalar($pdo,'SELECT VERSION()');
    must($configured!==''&&hash_equals($configured,$db),'Database identity mismatch.');must($version===BE_CUTOVER_EXPECTED_SERVER_VERSION,'Unexpected MySQL version.');must((function_exists('be_app_env_value')?be_app_env_value():'unknown')==='live','Environment is not live.');
    $pdo->exec('SET SESSION lock_wait_timeout=20');$ls=$pdo->prepare('SELECT GET_LOCK(:n,0)');$ls->execute([':n'=>BE_CUTOVER_LOCK]);$locked=(int)$ls->fetchColumn()===1;$ls->closeCursor();must($locked,'Cutover lock is already held.');
    $before=state($pdo,$db,$version,$build);
    if(exact_post($before))out(['report_type'=>'release_live_cutover_294','status'=>'PASS','action'=>'already_applied','environment'=>'live','current_build'=>$build,'release_sha'=>BE_CUTOVER_EXPECTED_RELEASE_SHA,'write_operations_executed'=>false,'personal_or_business_rows_exported'=>false,'state_before'=>$before,'state_after'=>$before,'existing_row_counts_unchanged'=>true,'migration_results'=>[]]);
    must(exact_pre($before),'Live schema no longer matches authorized pre-cutover state.');$beforeCounts=counts($pdo,BE_CUTOVER_EXISTING_TABLES);
    foreach($migrations as $m){
        if($m['path']==='api/sql/010_startpartner_gate2_qualification_capacity.sql')must(table_exists($pdo,$db,'startpartner_candidates')&&table_count($pdo,'startpartner_candidates')===0,'Startpartner candidates are not empty before migration 010.');
        $writeStarted=true;$results[]=apply_migration($pdo,$m);
        if($m['path']!=='api/sql/009_content_ops_metrics.sql'){$key=basename($m['path'],'.sql');must((int)scalar($pdo,'SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key=:k',[':k'=>$key])===1,'Migration key missing: '.$key);}
    }
    $afterCounts=counts($pdo,BE_CUTOVER_EXISTING_TABLES);must($afterCounts===$beforeCounts,'Pre-existing live row counts changed.');$after=state($pdo,$db,$version,$build);must(exact_post($after),'Live schema postconditions incomplete.');
    out(['report_type'=>'release_live_cutover_294','generated_at_utc'=>gmdate('c'),'status'=>'PASS','action'=>'applied','environment'=>'live','current_build'=>$build,'release_sha'=>BE_CUTOVER_EXPECTED_RELEASE_SHA,'write_operations_executed'=>true,'personal_or_business_rows_exported'=>false,'state_before'=>$before,'state_after'=>$after,'existing_row_counts_before'=>$beforeCounts,'existing_row_counts_after'=>$afterCounts,'existing_row_counts_unchanged'=>true,'migration_results'=>$results]);
}catch(Throwable $e){out(['report_type'=>'release_live_cutover_294','generated_at_utc'=>gmdate('c'),'status'=>'FAIL','error_class'=>get_class($e),'error_code'=>(string)$e->getCode(),'error_message'=>$e->getMessage(),'write_operations_executed'=>$writeStarted,'personal_or_business_rows_exported'=>false,'state_before'=>$before,'existing_row_counts_before'=>$beforeCounts,'migration_results'=>$results],500);}finally{
    if($pdo instanceof PDO&&$locked){try{$r=$pdo->prepare('SELECT RELEASE_LOCK(:n)');$r->execute([':n'=>BE_CUTOVER_LOCK]);$r->fetchColumn();$r->closeCursor();}catch(Throwable){}}
}
