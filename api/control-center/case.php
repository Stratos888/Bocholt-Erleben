<?php
declare(strict_types=1);
require __DIR__ . '/_domain.php';
require_once __DIR__ . '/_content_source.php';
require_once __DIR__ . '/_editorial_contracts.php';
be_require_review_access();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') { header('Allow: GET'); be_json_response(405, ['status'=>'error','message'=>'Method not allowed.']); }
function be_cc_safe_description_suggestion(array $event): string {
    $title=trim((string)($event['title']??'')); if($title==='')return '';
    $parts=[$title.' findet']; $date=be_cc_parse_iso_date((string)($event['date']??'')); if($date!==null)$parts[]='am '.$date->format('d.m.Y');
    $time=trim((string)($event['time']??'')); if($time!=='')$parts[]='um '.$time.' Uhr';
    $location=trim((string)($event['location']??'')); $city=trim((string)($event['city']??'')); if($location!=='')$parts[]='in '.$location;
    if($city!==''&&($location===''||!str_contains(mb_strtolower($location,'UTF-8'),mb_strtolower($city,'UTF-8'))))$parts[]='in '.$city;
    $text=implode(' ',$parts).' statt.'; if(trim((string)($event['source_url']??''))!=='')$text.=' Weitere Informationen gibt es auf der offiziellen Veranstaltungsseite.'; return $text;
}
try {
    be_cc_require_schema(); $id=trim((string)($_GET['id']??'')); if($id==='')throw new InvalidArgumentException('Case id is required.');
    $stmt=be_db()->prepare('SELECT * FROM control_cases WHERE id = :id'); $stmt->execute(['id'=>$id]); $row=$stmt->fetch(); if(!$row)throw new RuntimeException('Case not found.');
    $item=be_cc_case_from_row($row); $payload=json_decode((string)($row['source_payload_json']??''),true); $payload=is_array($payload)?$payload:[];
    if((string)($row['source_system']??'')==='inbox_feed'){
        $review=be_cc_event_candidate_review_contract($payload); $item['review_contract']=$review; $item['decision_context']['review_contract']=$review; $item['decision_ready']=(bool)($review['decision_gate']['ready']??false);
    }
    if((string)($row['source_system']??'')==='content_audit'){
        $contentId=trim((string)($row['object_id']??$payload['content_id']??''));
        if($contentId!=='')foreach(be_cc_event_items(true) as $event){
            if((string)($event['id']??'')!==$contentId)continue;
            $current=trim((string)($event['description']??'')); $suggested=trim((string)($payload['suggested_description']??$payload['replacement_text']??'')); if($suggested==='')$suggested=be_cc_safe_description_suggestion($event);
            $payload['current_description']=$current; $payload['current_description_hash']=hash('sha256',$current); $payload['description']=$current; $payload['suggested_description']=$suggested;
            foreach(['title','date','time','city','location','address','source_url','ticket_url','visual_key','visual_motif'] as $field)$payload[$field]=(string)($event[$field]??$payload[$field]??'');
            $payload['endDate']=(string)($event['end_date']??''); $payload['end_date']=(string)($event['end_date']??''); $payload['category']=(string)($event['category']??'');
            $item['decision_context']['current_description']=$current; $item['decision_context']['suggested_description']=$suggested; $item['decision_context']['current_event']=$event; break;
        }
    }
    $item['source_payload']=$payload; be_json_response(200,['status'=>'ok','data'=>$item]);
} catch(InvalidArgumentException $error){be_json_response(422,['status'=>'error','message'=>$error->getMessage()]);}
catch(RuntimeException $error){be_json_response(404,['status'=>'error','message'=>$error->getMessage()]);}
catch(Throwable $error){be_json_response(500,['status'=>'error','message'=>'Vorgang konnte nicht geladen werden.','error_message'=>$error->getMessage()]);}
