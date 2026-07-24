<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/api/startpartner/_domain.php';

$d=getenv('STARTPARTNER_TEST_DSN')?:'';$u=getenv('STARTPARTNER_TEST_USER')?:'';$p=getenv('STARTPARTNER_TEST_PASSWORD')?:'';
if($d===''||$u==='')exit(2);
$db=new PDO($d,$u,$p,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
$f=[];$ok=static function(bool $v,string $m)use(&$f){if(!$v)$f[]=$m;};
$n=static function(string $t,string $w='1=1',array $a=[])use($db):int{$s=$db->prepare("SELECT COUNT(*) FROM $t WHERE $w");$s->execute($a);return(int)$s->fetchColumn();};
$m='GATE1_SYNTHETIC_194_'.strtoupper(bin2hex(random_bytes(4)));$k=strtolower($m).'-key';
$i=['source'=>'targeted_outreach','source_reference'=>'contract','organization_name'=>$m,'contacts'=>[['email'=>strtolower($m).'@example.org','is_primary'=>true],['email'=>'second-'.strtolower($m).'@example.org']],'website_url'=>'https://example.org/'.strtolower($m),'description_text'=>'Synthetic candidate','desired_content_scope'=>'both','form_version'=>'gate1-v1','idempotency_key'=>$k];
$locked=['organizers','submissions','subscriptions','publication_entitlements','publication_consumptions'];$before=[];foreach($locked as$t)$before[$t]=$n($t);
$r=be_startpartner_create_candidate($db,$i,'operator','mysql-contract');$id=(string)($r['candidate']['id']??'');
$ok($r['created']===true&&$id!=='','create');$ok(count($r['candidate']['contacts']??[])===2,'contacts');
$ok($n('startpartner_candidates','id=:id',['id'=>$id])===1,'candidate');$ok($n('startpartner_candidate_contacts','candidate_id=:id',['id'=>$id])===2,'contact rows');
$ok($n('startpartner_candidate_events','candidate_id=:id',['id'=>$id])===1,'create event');$ok($n('control_cases',"source_system='startpartner_candidate' AND source_reference=:id",['id'=>$id])===1,'case');
$r2=be_startpartner_create_candidate($db,$i,'operator','mysql-contract');$ok(!$r2['created']&&$r2['idempotent_replay']&&(string)$r2['candidate']['id']===$id,'replay');$ok($n('startpartner_candidate_events','candidate_id=:id',['id'=>$id])===1,'replay event');
$i2=$i;$i2['idempotency_key'].='-other';$r3=be_startpartner_create_candidate($db,$i2,'operator','mysql-contract');$ok($r3['duplicate_identity']&&(string)$r3['candidate']['id']===$id,'duplicate');$ok($n('startpartner_candidate_events','candidate_id=:id',['id'=>$id])===2,'duplicate event');
be_startpartner_triage_candidate($db,$id,'qualified',null,'mysql-contract');be_startpartner_triage_candidate($db,$id,'waitlisted','Capacity reserved.','mysql-contract');
$s=$db->prepare("SELECT state,decision_ready FROM control_cases WHERE source_system='startpartner_candidate' AND source_reference=:id");$s->execute(['id'=>$id]);$c=$s->fetch();$ok(($c['state']??'')==='parked'&&(int)($c['decision_ready']??1)===0,'projection');foreach($locked as$t)$ok($n($t)===$before[$t],"side effect $t");
$rm=$m.'_ROLLBACK';$db->exec("CREATE TRIGGER gate1_contract_failure BEFORE INSERT ON control_cases FOR EACH ROW BEGIN IF NEW.object_title=".$db->quote($rm)." THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic failure'; END IF; END");
try{$x=$i;$x['organization_name']=$rm;$x['contacts'][0]['email']='r-'.strtolower($m).'@example.org';$x['contacts'][1]['email']='r2-'.strtolower($m).'@example.org';$x['idempotency_key'].='-rollback';try{be_startpartner_create_candidate($db,$x,'operator','mysql-contract');$f[]='rollback not triggered';}catch(Throwable $e){}$ok($n('startpartner_candidates','organization_name=:n',['n'=>$rm])===0,'rollback residue');}finally{$db->exec('DROP TRIGGER IF EXISTS gate1_contract_failure');}
$q=$db->prepare("DELETE FROM control_cases WHERE source_system='startpartner_candidate' AND source_reference=:id");$q->execute(['id'=>$id]);$q=$db->prepare('DELETE FROM startpartner_candidates WHERE id=:id');$q->execute(['id'=>$id]);
$ok($n('startpartner_candidates','id=:id',['id'=>$id])===0,'cleanup candidate');$ok($n('startpartner_candidate_contacts','candidate_id=:id',['id'=>$id])===0,'cleanup contacts');$ok($n('startpartner_candidate_events','candidate_id=:id',['id'=>$id])===0,'cleanup events');$ok($n('control_cases',"source_system='startpartner_candidate' AND source_reference=:id",['id'=>$id])===0,'cleanup case');
if($f){fwrite(STDERR,"FAILED: ".implode(',',$f)."\n");exit(1);}echo "=== Startpartner MySQL Contract: OK ===\n";
