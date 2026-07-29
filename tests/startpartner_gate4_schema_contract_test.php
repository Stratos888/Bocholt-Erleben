<?php
declare(strict_types=1);

$dsn=getenv('STARTPARTNER_TEST_DSN')?:'';$user=getenv('STARTPARTNER_TEST_USER')?:'';$password=getenv('STARTPARTNER_TEST_PASSWORD')?:'';
if($dsn===''){fwrite(STDERR,"STARTPARTNER_TEST_DSN is required.\n");exit(2);} 
$pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$required=[
 'startpartner_pilot_onboarding_items'=>['pilot_id','item_key','status','evidence_json','blocker_reason','completed_at'],
 'startpartner_pilot_content_links'=>['pilot_id','organizer_id','submission_id','content_type','publication_status','reporting_target_id'],
 'startpartner_pilot_measurement_preflights'=>['pilot_id','content_link_id','reporting_target_id','status','checked_at','blocker_reason'],
 'startpartner_pilot_distribution_commitments'=>['pilot_id','channel','planned_at','target_reference','status'],
 'startpartner_pilot_usage'=>['pilot_id','entitlement_id','content_link_id','usage_kind','pilot_month_index','units'],
 'startpartner_pilot_activation_records'=>['pilot_id','content_link_id','operation_id','activation_date_local','timezone_name','planned_end_date'],
 'startpartner_pilot_operations'=>['operation_id','pilot_id','payload_hash','expected_candidate_revision','expected_pilot_revision','status'],
];
$db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
$stmt=$pdo->prepare('SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:schema');$stmt->execute(['schema'=>$db]);$present=[];
foreach($stmt->fetchAll() as $row)$present[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']]=true;
$failures=[];
foreach($required as $table=>$columns){if(!isset($present[$table])){$failures[]="Missing table {$table}";continue;}foreach($columns as $column)if(!isset($present[$table][$column]))$failures[]="Missing column {$table}.{$column}";}
$count=(int)$pdo->query("SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key='012_startpartner_gate4_onboarding_content_activation'")->fetchColumn();
if($count!==1)$failures[]='Migration 012 must be recorded exactly once.';
foreach(['subscriptions','publication_entitlements','publication_consumptions','organizer_magic_links','organizer_portal_sessions'] as $table){$count=(int)$pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();if($count!==0)$failures[]="Locked table {$table} must remain empty in schema contract.";}
if($failures!==[]){fwrite(STDERR,implode("\n",$failures)."\n");exit(1);} 
printf("=== Startpartner Gate-4 Schema Contract: OK ===\n");
