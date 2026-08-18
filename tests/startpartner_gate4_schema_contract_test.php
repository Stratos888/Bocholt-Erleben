<?php
declare(strict_types=1);
$dsn=getenv('STARTPARTNER_TEST_DSN')?:'';$user=getenv('STARTPARTNER_TEST_USER')?:'';$password=getenv('STARTPARTNER_TEST_PASSWORD')?:'';
if($dsn===''){fwrite(STDERR,"STARTPARTNER_TEST_DSN is required.\n");exit(2);} $pdo=new PDO($dsn,$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$required=[
 'startpartner_pilots'=>['activation_date_local','planned_end_date'],
 'startpartner_pilot_onboarding_items'=>['pilot_id','item_key','status','evidence_text','revision'],
 'startpartner_pilot_content_links'=>['pilot_id','organizer_id','submission_id','content_type','status','reporting_target_id'],
 'startpartner_pilot_measurement_preflights'=>['pilot_id','content_link_id','status','metrics_owner','reporting_target_id'],
 'startpartner_pilot_distribution_commitments'=>['pilot_id','channel','planned_at','status'],
 'startpartner_pilot_usages'=>['pilot_id','pilot_entitlement_id','content_link_id','submission_id','pilot_month_index'],
];
$db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();$stmt=$pdo->prepare('SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:db');$stmt->execute(['db'=>$db]);$present=[];foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$present[$row['TABLE_NAME']][$row['COLUMN_NAME']]=true;
$failures=[];foreach($required as $table=>$columns){foreach($columns as $column){if(empty($present[$table][$column]))$failures[]="{$table}.{$column} missing";}}
$count=(int)$pdo->query("SELECT COUNT(*) FROM app_schema_migrations WHERE migration_key='012_startpartner_gate4_onboarding_content_activation'")->fetchColumn();if($count!==1)$failures[]='Migration 012 marker missing or duplicated.';
if($failures){fwrite(STDERR,"=== Startpartner Gate-4 Schema Contract: FAILED ===\n- ".implode("\n- ",$failures)."\n");exit(1);}echo "=== Startpartner Gate-4 Schema Contract: OK ===\n";
