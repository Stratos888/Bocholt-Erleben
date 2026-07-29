<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate4_domain.php';

function be_startpartner_gate4_upsert_measurement(PDO $pdo, string $pilotId, array $input): array
{
    $contentLinkId=filter_var($input['content_link_id']??null,FILTER_VALIDATE_INT);
    if($contentLinkId===false||$contentLinkId<1)throw new InvalidArgumentException('content_link_id is required.');
    $status=strtolower(be_startpartner_gate4_text($input['status']??null,'status',32));
    if(!in_array($status,['pending','ready','blocked'],true))throw new InvalidArgumentException('measurement status is invalid.');
    $operator=be_startpartner_gate4_text($input['operator_name']??null,'operator_name');
    $blocker=trim((string)($input['blocker_reason']??''));
    if($status==='blocked'&&$blocker==='')throw new InvalidArgumentException('blocker_reason is required for blocked measurement.');
    $content=$pdo->prepare('SELECT * FROM startpartner_pilot_content_links WHERE id=:id AND pilot_id=:pilot_id LIMIT 1 FOR UPDATE');
    $content->execute(['id'=>(int)$contentLinkId,'pilot_id'=>$pilotId]);
    $row=$content->fetch(PDO::FETCH_ASSOC);
    if(!is_array($row))throw new DomainException('pilot content link not found.');
    $evidence=is_array($input['evidence']??null)?json_encode($input['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):null;
    $stmt=$pdo->prepare("INSERT INTO startpartner_pilot_measurement_preflights (pilot_id,organizer_id,content_link_id,reporting_target_type,reporting_target_id,status,checked_at,checked_by,evidence_json,blocker_reason) VALUES (:pilot_id,:organizer_id,:content_link_id,:target_type,:target_id,:status,CASE WHEN :ready_status='ready' THEN UTC_TIMESTAMP() ELSE NULL END,:checked_by,:evidence_json,:blocker_reason) ON DUPLICATE KEY UPDATE status=VALUES(status),checked_at=CASE WHEN VALUES(status)='ready' THEN UTC_TIMESTAMP() ELSE NULL END,checked_by=VALUES(checked_by),evidence_json=VALUES(evidence_json),blocker_reason=VALUES(blocker_reason),reporting_target_type=VALUES(reporting_target_type),reporting_target_id=VALUES(reporting_target_id)");
    $stmt->execute(['pilot_id'=>$pilotId,'organizer_id'=>(int)$row['organizer_id'],'content_link_id'=>(int)$contentLinkId,'target_type'=>(string)$row['reporting_target_type'],'target_id'=>(string)$row['reporting_target_id'],'status'=>$status,'ready_status'=>$status,'checked_by'=>$operator,'evidence_json'=>$evidence,'blocker_reason'=>$blocker!==''?$blocker:null]);
    return be_startpartner_gate4_readiness($pdo,$pilotId);
}

function be_startpartner_gate4_upsert_distribution(PDO $pdo, string $pilotId, array $input): array
{
    $id=filter_var($input['commitment_id']??null,FILTER_VALIDATE_INT);
    $channel=strtolower(be_startpartner_gate4_text($input['channel']??null,'channel',64));
    $allowed=['website','social_media','newsletter','member_communication','qr_code','on_site','specific_page_share','other'];
    if(!in_array($channel,$allowed,true))throw new InvalidArgumentException('distribution channel is invalid.');
    $planned=be_startpartner_gate4_text($input['planned_at']??null,'planned_at',32);
    $plannedAt=(new DateTimeImmutable($planned))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $target=be_startpartner_gate4_text($input['target_reference']??null,'target_reference',2048);
    $status=strtolower(be_startpartner_gate4_text($input['status']??'planned','status',32));
    if(!in_array($status,['planned','ready','completed','blocked','cancelled'],true))throw new InvalidArgumentException('distribution status is invalid.');
    $operator=be_startpartner_gate4_text($input['operator_name']??null,'operator_name');
    $blocker=trim((string)($input['blocker_reason']??''));
    if($status==='blocked'&&$blocker==='')throw new InvalidArgumentException('blocker_reason is required for blocked distribution.');
    $evidence=is_array($input['evidence']??null)?json_encode($input['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):null;
    if($id!==false&&$id>0){
        $stmt=$pdo->prepare('UPDATE startpartner_pilot_distribution_commitments SET channel=:channel,planned_at=:planned_at,target_reference=:target_reference,status=:status,evidence_json=:evidence_json,blocker_reason=:blocker_reason,operator_name=:operator_name WHERE id=:id AND pilot_id=:pilot_id');
        $stmt->execute(['channel'=>$channel,'planned_at'=>$plannedAt,'target_reference'=>$target,'status'=>$status,'evidence_json'=>$evidence,'blocker_reason'=>$blocker!==''?$blocker:null,'operator_name'=>$operator,'id'=>(int)$id,'pilot_id'=>$pilotId]);
        if($stmt->rowCount()!==1)throw new DomainException('distribution commitment not found.');
    }else{
        $stmt=$pdo->prepare('INSERT INTO startpartner_pilot_distribution_commitments (pilot_id,channel,planned_at,target_reference,status,evidence_json,blocker_reason,operator_name) VALUES (:pilot_id,:channel,:planned_at,:target_reference,:status,:evidence_json,:blocker_reason,:operator_name)');
        $stmt->execute(['pilot_id'=>$pilotId,'channel'=>$channel,'planned_at'=>$plannedAt,'target_reference'=>$target,'status'=>$status,'evidence_json'=>$evidence,'blocker_reason'=>$blocker!==''?$blocker:null,'operator_name'=>$operator]);
    }
    return be_startpartner_gate4_readiness($pdo,$pilotId);
}
