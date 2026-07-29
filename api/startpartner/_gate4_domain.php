<?php
declare(strict_types=1);

require_once __DIR__ . '/_gate3_domain.php';

const BE_STARTPARTNER_GATE4_REQUIRED_SCHEMA = [
    'startpartner_pilot_onboarding_items' => ['id','pilot_id','item_key','item_type','status','is_required','is_hard_blocker','evidence_json','blocker_reason','operator_name','completed_at'],
    'startpartner_pilot_content_links' => ['id','pilot_id','organizer_id','submission_id','content_type','publication_status','reporting_target_type','reporting_target_id','approved_at'],
    'startpartner_pilot_measurement_preflights' => ['id','pilot_id','organizer_id','content_link_id','reporting_target_type','reporting_target_id','status','checked_at','checked_by','evidence_json','blocker_reason'],
    'startpartner_pilot_distribution_commitments' => ['id','pilot_id','channel','planned_at','target_reference','status','evidence_json','blocker_reason','operator_name'],
    'startpartner_pilot_usage' => ['id','pilot_id','entitlement_id','content_link_id','usage_kind','pilot_month_index','units','consumed_at'],
    'startpartner_pilot_activation_records' => ['id','pilot_id','content_link_id','operation_id','activation_date_local','timezone_name','activated_at_utc','planned_end_date','before_candidate_revision','after_candidate_revision','before_pilot_revision','after_pilot_revision','actor_reference','evidence_json'],
    'startpartner_pilot_operations' => ['id','operation_id','pilot_id','operation_type','payload_hash','expected_candidate_revision','expected_pilot_revision','status','result_json','error_text','completed_at'],
];

const BE_STARTPARTNER_GATE4_REQUIRED_ITEM_KEYS = [
    'terms_confirmed','organizer_linked','contact_confirmed','portal_access_tested',
    'pilot_entitlement_readback','service_scope_confirmed','source_captured',
    'maintenance_path_agreed','content_rights_confirmed','first_content_prepared',
    'editorial_review_ready','measurement_ready','distribution_ready','activation_target_set',
];

function be_startpartner_gate4_schema_gaps(PDO $pdo): array
{
    be_startpartner_gate3_require_schema($pdo);
    $database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($database === '') return ['database' => ['No database selected.']];
    $stmt = $pdo->prepare('SELECT TABLE_NAME, COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=:schema');
    $stmt->execute(['schema' => $database]);
    $present = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $present[(string)$row['TABLE_NAME']][(string)$row['COLUMN_NAME']] = true;
    }
    $gaps = [];
    foreach (BE_STARTPARTNER_GATE4_REQUIRED_SCHEMA as $table => $columns) {
        if (!isset($present[$table])) { $gaps[$table] = ['table missing']; continue; }
        foreach ($columns as $column) if (!isset($present[$table][$column])) $gaps[$table][] = $column;
    }
    return $gaps;
}

function be_startpartner_gate4_require_schema(PDO $pdo): void
{
    $gaps = be_startpartner_gate4_schema_gaps($pdo);
    if ($gaps !== []) throw new RuntimeException('STARTPARTNER_GATE4_SCHEMA_MISSING: ' . json_encode($gaps, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function be_startpartner_gate4_uuid(mixed $value, string $field): string
{
    $uuid = strtolower(trim((string)$value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $uuid)) {
        throw new InvalidArgumentException("{$field} must be a UUID v4.");
    }
    return $uuid;
}

function be_startpartner_gate4_text(mixed $value, string $field, int $max = 191): string
{
    $text = trim((string)$value);
    if ($text === '' || mb_strlen($text) > $max) throw new InvalidArgumentException("{$field} is required and must not exceed {$max} characters.");
    return $text;
}

function be_startpartner_gate4_json(mixed $value, string $field): string
{
    if (!is_array($value)) throw new InvalidArgumentException("{$field} must be an object or array.");
    return json_encode($value, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}

function be_startpartner_gate4_planned_end_date(string $activationDateLocal): string
{
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $activationDateLocal, new DateTimeZone('Europe/Berlin'));
    if (!$start || $start->format('Y-m-d') !== $activationDateLocal) throw new InvalidArgumentException('activation_date_local is invalid.');
    $day = (int)$start->format('d');
    $targetMonth = $start->modify('first day of this month')->modify('+6 months');
    $lastDay = (int)$targetMonth->format('t');
    return $targetMonth->setDate((int)$targetMonth->format('Y'), (int)$targetMonth->format('m'), min($day, $lastDay))->format('Y-m-d');
}

function be_startpartner_gate4_now_for_local_date(string $activationDateLocal): string
{
    $zone = new DateTimeZone('Europe/Berlin');
    $now = new DateTimeImmutable('now', $zone);
    if ($now->format('Y-m-d') !== $activationDateLocal) {
        throw new DomainException('activation_date_local must equal the current Europe/Berlin date.');
    }
    return $now->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function be_startpartner_gate4_payload_hash(array $payload): string
{
    ksort($payload);
    return hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
}

function be_startpartner_gate4_pilot_for_update(PDO $pdo, string $pilotId): array
{
    $stmt = $pdo->prepare('SELECT p.*, c.revision AS candidate_revision FROM startpartner_pilots p INNER JOIN startpartner_candidates c ON c.id=p.candidate_id WHERE p.id=:id LIMIT 1 FOR UPDATE');
    $stmt->execute(['id'=>$pilotId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new DomainException('Startpartner pilot not found.');
    return $row;
}

function be_startpartner_gate4_entitlement_for_update(PDO $pdo, string $pilotId): array
{
    $stmt = $pdo->prepare('SELECT * FROM startpartner_pilot_entitlements WHERE pilot_id=:pilot_id LIMIT 1 FOR UPDATE');
    $stmt->execute(['pilot_id'=>$pilotId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) throw new DomainException('Pilot entitlement not found.');
    return $row;
}

function be_startpartner_gate4_readiness(PDO $pdo, string $pilotId): array
{
    $stmt = $pdo->prepare('SELECT item_key,status,is_required,is_hard_blocker,blocker_reason,evidence_json,completed_at FROM startpartner_pilot_onboarding_items WHERE pilot_id=:pilot_id ORDER BY item_key');
    $stmt->execute(['pilot_id'=>$pilotId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $byKey = [];
    $blockers = [];
    foreach ($items as $item) {
        $key=(string)$item['item_key']; $byKey[$key]=$item;
        if ((int)$item['is_required']===1 && !in_array((string)$item['status'], ['complete','not_applicable'], true)) $blockers[]="onboarding:{$key}";
        if ((int)$item['is_hard_blocker']===1 && (string)$item['status']==='blocked') $blockers[]="blocked:{$key}";
    }
    foreach (BE_STARTPARTNER_GATE4_REQUIRED_ITEM_KEYS as $key) if (!isset($byKey[$key])) $blockers[]="missing:{$key}";

    $contentStmt=$pdo->prepare("SELECT pcl.*,s.status AS submission_status FROM startpartner_pilot_content_links pcl INNER JOIN submissions s ON s.id=pcl.submission_id WHERE pcl.pilot_id=:pilot_id AND pcl.publication_status='editorial_ready' ORDER BY pcl.id LIMIT 2");
    $contentStmt->execute(['pilot_id'=>$pilotId]);
    $contents=$contentStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($contents)!==1) $blockers[]='first_content_not_unique_or_not_editorial_ready';

    $measurement=null;
    if (count($contents)===1) {
        $m=$pdo->prepare("SELECT * FROM startpartner_pilot_measurement_preflights WHERE content_link_id=:content_link_id AND status='ready' LIMIT 1");
        $m->execute(['content_link_id'=>(int)$contents[0]['id']]);
        $measurement=$m->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($measurement===null) $blockers[]='measurement_not_ready';
    }

    $d=$pdo->prepare("SELECT COUNT(*) FROM startpartner_pilot_distribution_commitments WHERE pilot_id=:pilot_id AND status IN ('ready','completed')");
    $d->execute(['pilot_id'=>$pilotId]);
    if ((int)$d->fetchColumn()<1) $blockers[]='distribution_not_ready';

    $portal=$pdo->prepare("SELECT COUNT(*) FROM organizer_portal_sessions s INNER JOIN startpartner_pilots p ON p.organizer_id=s.organizer_id WHERE p.id=:pilot_id AND s.revoked_at IS NULL AND s.expires_at>UTC_TIMESTAMP()");
    $portal->execute(['pilot_id'=>$pilotId]);
    if ((int)$portal->fetchColumn()<1) $blockers[]='portal_access_not_proven';

    $entitlement=$pdo->prepare("SELECT COUNT(*) FROM startpartner_pilot_entitlements WHERE pilot_id=:pilot_id AND status='pending_activation' AND starts_at IS NULL AND ends_at IS NULL");
    $entitlement->execute(['pilot_id'=>$pilotId]);
    if ((int)$entitlement->fetchColumn()!==1) $blockers[]='pending_entitlement_missing';

    $blockers=array_values(array_unique($blockers));
    return ['ready'=>$blockers===[],'blockers'=>$blockers,'items'=>$items,'content'=>$contents[0]??null,'measurement'=>$measurement];
}

function be_startpartner_gate4_upsert_onboarding_item(PDO $pdo, string $pilotId, array $input): array
{
    $key=be_startpartner_gate4_text($input['item_key']??null,'item_key',64);
    if (!in_array($key, BE_STARTPARTNER_GATE4_REQUIRED_ITEM_KEYS, true)) throw new InvalidArgumentException('item_key is not part of the Gate-4 checklist.');
    $status=strtolower(be_startpartner_gate4_text($input['status']??null,'status',32));
    if (!in_array($status,['open','complete','blocked','not_applicable'],true)) throw new InvalidArgumentException('status is invalid.');
    $operator=be_startpartner_gate4_text($input['operator_name']??null,'operator_name');
    $type=strtolower(be_startpartner_gate4_text($input['item_type']??str_replace('_tested','',$key),'item_type',64));
    $blocker=trim((string)($input['blocker_reason']??''));
    if ($status==='blocked' && $blocker==='') throw new InvalidArgumentException('blocker_reason is required for blocked items.');
    $evidence=is_array($input['evidence']??null)?json_encode($input['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):null;
    $stmt=$pdo->prepare("INSERT INTO startpartner_pilot_onboarding_items (pilot_id,item_key,item_type,status,is_required,is_hard_blocker,evidence_json,blocker_reason,operator_name,completed_at) VALUES (:pilot_id,:item_key,:item_type,:status,1,1,:evidence_json,:blocker_reason,:operator_name,CASE WHEN :status_complete='complete' THEN UTC_TIMESTAMP() ELSE NULL END) ON DUPLICATE KEY UPDATE item_type=VALUES(item_type),status=VALUES(status),evidence_json=VALUES(evidence_json),blocker_reason=VALUES(blocker_reason),operator_name=VALUES(operator_name),completed_at=CASE WHEN VALUES(status)='complete' THEN COALESCE(completed_at,UTC_TIMESTAMP()) ELSE NULL END");
    $stmt->execute(['pilot_id'=>$pilotId,'item_key'=>$key,'item_type'=>$type,'status'=>$status,'evidence_json'=>$evidence,'blocker_reason'=>$blocker!==''?$blocker:null,'operator_name'=>$operator,'status_complete'=>$status]);
    return be_startpartner_gate4_readiness($pdo,$pilotId);
}

function be_startpartner_gate4_activate(PDO $pdo, array $input): array
{
    be_startpartner_gate4_require_schema($pdo);
    $pilotId=be_startpartner_gate4_uuid($input['pilot_id']??null,'pilot_id');
    $operationId=be_startpartner_gate4_uuid($input['operation_id']??null,'operation_id');
    $expectedCandidate=(int)($input['expected_candidate_revision']??0);
    $expectedPilot=(int)($input['expected_pilot_revision']??0);
    $actor=be_startpartner_gate4_text($input['operator_name']??null,'operator_name');
    $activationDate=be_startpartner_gate4_text($input['activation_date_local']??null,'activation_date_local',10);
    $payload=['pilot_id'=>$pilotId,'activation_date_local'=>$activationDate,'expected_candidate_revision'=>$expectedCandidate,'expected_pilot_revision'=>$expectedPilot,'operator_name'=>$actor];
    $hash=be_startpartner_gate4_payload_hash($payload);

    $pdo->beginTransaction();
    try {
        $existing=$pdo->prepare('SELECT * FROM startpartner_pilot_operations WHERE operation_id=:operation_id LIMIT 1 FOR UPDATE');
        $existing->execute(['operation_id'=>$operationId]);
        $operation=$existing->fetch(PDO::FETCH_ASSOC);
        if (is_array($operation)) {
            if (!hash_equals((string)$operation['payload_hash'],$hash)) throw new DomainException('operation_id payload conflict.');
            if ((string)$operation['status']==='completed' && !empty($operation['result_json'])) {
                $result=json_decode((string)$operation['result_json'],true,512,JSON_THROW_ON_ERROR); $pdo->commit(); return $result+['idempotent_replay'=>true];
            }
            throw new DomainException('operation_id is already processing or failed.');
        }

        $pilot=be_startpartner_gate4_pilot_for_update($pdo,$pilotId);
        if ((int)$pilot['candidate_revision']!==$expectedCandidate || (int)$pilot['revision']!==$expectedPilot) throw new DomainException('stale candidate or pilot revision.');
        if ((string)$pilot['status']!=='onboarding' && (string)$pilot['status']!=='activation_ready') throw new DomainException('pilot must be onboarding or activation_ready.');
        $entitlement=be_startpartner_gate4_entitlement_for_update($pdo,$pilotId);
        if ((string)$entitlement['status']!=='pending_activation') throw new DomainException('pilot entitlement is not pending_activation.');
        $readiness=be_startpartner_gate4_readiness($pdo,$pilotId);
        if (!$readiness['ready']) throw new DomainException('pilot is not activation ready: '.implode(', ',$readiness['blockers']));
        $content=$readiness['content'];
        $submissionId=(int)$content['submission_id'];
        $submission=$pdo->prepare('SELECT * FROM submissions WHERE id=:id LIMIT 1 FOR UPDATE');
        $submission->execute(['id'=>$submissionId]);
        $submissionRow=$submission->fetch(PDO::FETCH_ASSOC);
        if (!is_array($submissionRow) || (int)$submissionRow['organizer_id']!==(int)$pilot['organizer_id']) throw new DomainException('linked submission does not match pilot organizer.');
        if (!in_array((string)$submissionRow['status'],['paid','in_review'],true)) throw new DomainException('linked submission is not editorially approvable.');

        $reservation=$pdo->prepare("SELECT * FROM startpartner_candidate_reservations WHERE id=:id AND candidate_id=:candidate_id AND status='active' LIMIT 1 FOR UPDATE");
        $reservation->execute(['id'=>(int)$pilot['reservation_id'],'candidate_id'=>(string)$pilot['candidate_id']]);
        if (!is_array($reservation->fetch(PDO::FETCH_ASSOC))) throw new DomainException('active candidate reservation is missing.');

        $insertOp=$pdo->prepare("INSERT INTO startpartner_pilot_operations (operation_id,pilot_id,operation_type,payload_hash,expected_candidate_revision,expected_pilot_revision,status) VALUES (:operation_id,:pilot_id,'activate',:payload_hash,:candidate_revision,:pilot_revision,'processing')");
        $insertOp->execute(['operation_id'=>$operationId,'pilot_id'=>$pilotId,'payload_hash'=>$hash,'candidate_revision'=>$expectedCandidate,'pilot_revision'=>$expectedPilot]);

        $activatedAt=be_startpartner_gate4_now_for_local_date($activationDate);
        $endDate=be_startpartner_gate4_planned_end_date($activationDate);
        $endUtc=(new DateTimeImmutable($endDate.' 00:00:00',new DateTimeZone('Europe/Berlin')))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $pdo->prepare("UPDATE submissions SET status='approved',review_started_at=COALESCE(review_started_at,UTC_TIMESTAMP()),approved_at=COALESCE(approved_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id")->execute(['id'=>$submissionId]);
        $pdo->prepare("UPDATE startpartner_pilot_content_links SET publication_status='approved',approved_at=COALESCE(approved_at,UTC_TIMESTAMP()) WHERE id=:id")->execute(['id'=>(int)$content['id']]);
        $pdo->prepare("UPDATE startpartner_pilot_scopes SET status='active' WHERE pilot_id=:pilot_id AND status='planned'")->execute(['pilot_id'=>$pilotId]);
        $pdo->prepare("UPDATE startpartner_pilot_entitlements SET status='active',starts_at=:starts_at,ends_at=:ends_at,revision=revision+1,audit_json=JSON_SET(audit_json,'$.activated_by',:actor,'$.activation_operation_id',:operation_id) WHERE id=:id AND status='pending_activation'")->execute(['starts_at'=>$activatedAt,'ends_at'=>$endUtc,'actor'=>$actor,'operation_id'=>$operationId,'id'=>(string)$entitlement['id']]);
        $pdo->prepare("UPDATE startpartner_pilots SET status='active',health='green',activation_ready_at=COALESCE(activation_ready_at,UTC_TIMESTAMP()),activated_at=:activated_at,starts_at=:starts_at,ends_at=:ends_at,revision=revision+1 WHERE id=:id")->execute(['activated_at'=>$activatedAt,'starts_at'=>$activatedAt,'ends_at'=>$endUtc,'id'=>$pilotId]);
        $pdo->prepare("UPDATE startpartner_candidate_reservations SET status='converted_to_pilot',released_at=UTC_TIMESTAMP(),release_reason='gate4_activation' WHERE id=:id AND status='active'")->execute(['id'=>(int)$pilot['reservation_id']]);
        $pdo->prepare("UPDATE startpartner_candidates SET revision=revision+1,updated_at=UTC_TIMESTAMP() WHERE id=:id AND revision=:revision")->execute(['id'=>(string)$pilot['candidate_id'],'revision'=>$expectedCandidate]);

        $usageKind=(string)$content['content_type']==='activity'?'activity_publication':'event_publication';
        $pdo->prepare("INSERT INTO startpartner_pilot_usage (pilot_id,entitlement_id,content_link_id,usage_kind,pilot_month_index,units) VALUES (:pilot_id,:entitlement_id,:content_link_id,:usage_kind,1,1)")->execute(['pilot_id'=>$pilotId,'entitlement_id'=>(string)$entitlement['id'],'content_link_id'=>(int)$content['id'],'usage_kind'=>$usageKind]);

        $record=$pdo->prepare("INSERT INTO startpartner_pilot_activation_records (pilot_id,content_link_id,operation_id,activation_date_local,activated_at_utc,planned_end_date,before_candidate_revision,after_candidate_revision,before_pilot_revision,after_pilot_revision,actor_reference,evidence_json) VALUES (:pilot_id,:content_link_id,:operation_id,:activation_date_local,:activated_at_utc,:planned_end_date,:before_candidate,:after_candidate,:before_pilot,:after_pilot,:actor,:evidence)");
        $evidence=json_encode(['readiness'=>$readiness,'submission_id'=>$submissionId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $record->execute(['pilot_id'=>$pilotId,'content_link_id'=>(int)$content['id'],'operation_id'=>$operationId,'activation_date_local'=>$activationDate,'activated_at_utc'=>$activatedAt,'planned_end_date'=>$endDate,'before_candidate'=>$expectedCandidate,'after_candidate'=>$expectedCandidate+1,'before_pilot'=>$expectedPilot,'after_pilot'=>$expectedPilot+1,'actor'=>$actor,'evidence'=>$evidence]);
        $pdo->prepare("INSERT INTO startpartner_pilot_events (pilot_id,event_type,actor_reference,payload_json) VALUES (:pilot_id,'activated',:actor,:payload)")->execute(['pilot_id'=>$pilotId,'actor'=>$actor,'payload'=>json_encode(['operation_id'=>$operationId,'activation_date_local'=>$activationDate,'planned_end_date'=>$endDate,'submission_id'=>$submissionId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)]);

        $result=['pilot_id'=>$pilotId,'status'=>'active','activation_date_local'=>$activationDate,'planned_end_date'=>$endDate,'activated_at_utc'=>$activatedAt,'submission_id'=>$submissionId,'submission_status'=>'approved','candidate_revision'=>$expectedCandidate+1,'pilot_revision'=>$expectedPilot+1,'reservation_status'=>'converted_to_pilot','capacity_delta'=>0];
        $pdo->prepare("UPDATE startpartner_pilot_operations SET status='completed',result_json=:result,completed_at=UTC_TIMESTAMP() WHERE operation_id=:operation_id")->execute(['result'=>json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'operation_id'=>$operationId]);
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
}
