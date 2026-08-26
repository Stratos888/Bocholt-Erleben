<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/api/startpartner/_gate4_domain.php';

$dsn = getenv('STARTPARTNER_TEST_DSN') ?: '';
$user = getenv('STARTPARTNER_TEST_USER') ?: '';
$password = getenv('STARTPARTNER_TEST_PASSWORD') ?: '';
if ($dsn === '') {
    fwrite(STDERR, "STARTPARTNER_TEST_DSN is required.\n");
    exit(2);
}
$pdo = new PDO($dsn, $user, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$failures = [];
$assert = static function(bool $ok, string $message) use (&$failures): void {
    if (!$ok) $failures[] = $message;
};
$exec = static function(string $sql, array $params = []) use ($pdo): void {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
};
$expectDomain = static function(callable $callback, string $message) use (&$failures): void {
    try {
        $callback();
        $failures[] = $message;
    } catch (DomainException|InvalidArgumentException|BeStartpartnerConflictException $expected) {
    }
};

$pdo->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS value_metric_daily (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    metric_date DATE NOT NULL,
    metric_key VARCHAR(64) NOT NULL,
    entity_type VARCHAR(40) NOT NULL DEFAULT '',
    entity_id VARCHAR(191) NOT NULL DEFAULT '',
    entity_title VARCHAR(255) NULL,
    destination_url VARCHAR(1024) NULL,
    reporting_target_type VARCHAR(40) NOT NULL DEFAULT '',
    reporting_target_id VARCHAR(191) NOT NULL DEFAULT '',
    reporting_target_title VARCHAR(255) NULL,
    page_path VARCHAR(255) NULL,
    source_context VARCHAR(64) NOT NULL DEFAULT '',
    bucket_hash CHAR(64) NOT NULL,
    count_value INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_value_metric_daily_bucket (bucket_hash),
    KEY idx_value_metric_daily_reporting_target (metric_date, reporting_target_type, reporting_target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);

$timezone = new DateTimeZone('Europe/Berlin');
$today = new DateTimeImmutable('today', $timezone);
$activationDate = $today->modify('-160 days')->format('Y-m-d');
$window = be_startpartner_gate4_activation_window($activationDate);
$yesterday = $today->modify('-1 day')->format('Y-m-d');
$twoDaysAgo = $today->modify('-2 days')->format('Y-m-d');

$seedActivePilot = static function(int $seed) use ($pdo, $exec, $activationDate, $window, $yesterday): array {
    $candidate = sprintf('3442%04d-0000-4000-8000-%012d', $seed, $seed);
    $pilot = sprintf('3440%04d-0000-4000-8000-%012d', $seed, $seed);
    $entitlement = sprintf('3441%04d-0000-4000-8000-%012d', $seed, $seed);
    $contentLink = sprintf('3443%04d-0000-4000-8000-%012d', $seed, $seed);
    $email = "lifecycle-{$seed}@example.invalid";
    $token = hash('sha256', "lifecycle-session-{$seed}");

    $exec(
        "INSERT INTO organizers(organization_name,contact_name,email,email_normalized)
         VALUES(:organization,'Lifecycle Test',:email,:email)",
        ['organization' => "Lifecycle Verein {$seed}", 'email' => $email]
    );
    $organizer = (int)$pdo->lastInsertId();
    $exec(
        "INSERT INTO startpartner_candidates(
            id,source,organization_name,organization_name_normalized,desired_content_scope,status,status_reason,
            identity_key,idempotency_key_hash,form_version,retention_review_at,revision,assigned_to,status_changed_at
         ) VALUES(
            :id,'targeted_outreach',:organization,:normalized,'both','accepted_pending_terms','Synthetic active lifecycle',
            :identity,:idem,'gate4-344-test',DATE_ADD(UTC_TIMESTAMP(),INTERVAL 90 DAY),1,'Lifecycle Contract',UTC_TIMESTAMP()
         )",
        [
            'id' => $candidate,
            'organization' => "Lifecycle Verein {$seed}",
            'normalized' => "lifecycle verein {$seed}",
            'identity' => hash('sha256', "lifecycle-identity-{$seed}"),
            'idem' => hash('sha256', "lifecycle-idem-{$seed}"),
        ]
    );
    $exec(
        "INSERT INTO startpartner_candidate_contacts(candidate_id,contact_name,email,email_normalized,is_primary)
         VALUES(:candidate,'Lifecycle Test',:email,:email,1)",
        ['candidate' => $candidate, 'email' => $email]
    );
    $exec(
        "INSERT INTO startpartner_candidate_decisions(
            candidate_id,result,reason,operator_reference,candidate_revision,
            qualification_snapshot_json,capacity_snapshot_json,is_current
         ) VALUES(:candidate,'accepted_pending_terms','Synthetic fixture','Lifecycle Contract',1,JSON_OBJECT(),JSON_OBJECT(),1)",
        ['candidate' => $candidate]
    );
    $decision = (int)$pdo->lastInsertId();
    $exec(
        "INSERT INTO startpartner_candidate_reservations(
            candidate_id,decision_id,status,starts_at,ends_at,capacity_snapshot_json,operator_reference,
            released_at,release_reference
         ) VALUES(
            :candidate,:decision,'released',DATE_SUB(UTC_TIMESTAMP(),INTERVAL 180 DAY),DATE_ADD(UTC_TIMESTAMP(),INTERVAL 20 DAY),
            JSON_OBJECT(),'Lifecycle Contract',UTC_TIMESTAMP(),'synthetic-activation'
         )",
        ['candidate' => $candidate, 'decision' => $decision]
    );
    $reservation = (int)$pdo->lastInsertId();
    $exec(
        "INSERT INTO startpartner_pilot_terms_acceptances(
            candidate_id,decision_id,terms_version,terms_reference,terms_digest,accepting_person,
            accepting_organization,accepted_at,confirmation_channel,service_scope_json,source_care_json,
            reach_contribution_json,no_automatic_paid_renewal,operator_reference
         ) VALUES(
            :candidate,:decision,'v1','repo://gate4-lifecycle',:digest,'Lifecycle Test',:organization,
            DATE_SUB(UTC_TIMESTAMP(),INTERVAL 170 DAY),'operator_recorded',
            JSON_OBJECT('desired_content_scope','both','target_plan_keys',JSON_ARRAY('active','activity_basic')),
            JSON_OBJECT('description','Synthetic source'),JSON_OBJECT('description','Synthetic reach'),1,'Lifecycle Contract'
         )",
        [
            'candidate' => $candidate,
            'decision' => $decision,
            'digest' => hash('sha256', "lifecycle-terms-{$seed}"),
            'organization' => "Lifecycle Verein {$seed}",
        ]
    );
    $terms = (int)$pdo->lastInsertId();
    $exec(
        "INSERT INTO startpartner_pilots(
            id,candidate_id,organizer_id,terms_acceptance_id,reservation_id,cohort_key,status,
            target_plan_keys_json,internal_owner,partner_contact_name_snapshot,partner_contact_email_snapshot,
            activated_at,activation_date_local,planned_end_date,starts_at,ends_at,revision
         ) VALUES(
            :id,:candidate,:organizer,:terms,:reservation,'gate4-344','active',JSON_ARRAY('active','activity_basic'),
            'Lifecycle Contract','Lifecycle Test',:email,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 160 DAY),
            :activation_date,:planned_end,:starts_at,:ends_at,1
         )",
        [
            'id' => $pilot, 'candidate' => $candidate, 'organizer' => $organizer,
            'terms' => $terms, 'reservation' => $reservation, 'email' => $email,
            'activation_date' => $activationDate, 'planned_end' => $window['planned_end_date'],
            'starts_at' => $window['starts_at_utc'], 'ends_at' => $window['ends_at_utc'],
        ]
    );
    foreach ([
        ['events','events','active',8,0,'pilot_month'],
        ['activities','activities','activity_basic',1,0,'concurrent'],
        ['automatic-source','automatic_source',null,null,0,'not_applicable'],
        ['maintenance-service','maintenance_service',null,null,0,'not_applicable'],
        ['provider-portal','provider_portal',null,null,0,'not_applicable'],
        ['measurement','measurement',null,null,0,'not_applicable'],
        ['reach-contribution','reach_contribution',null,null,0,'not_applicable'],
    ] as $scope) {
        $exec(
            "INSERT INTO startpartner_pilot_scopes(
                pilot_id,scope_key,scope_type,status,target_plan_key,limit_value,is_unlimited,period_unit,details_json
             ) VALUES(:pilot,:key,:type,'active',:plan,:limit,:unlimited,:period,JSON_OBJECT('synthetic',true))",
            [
                'pilot'=>$pilot,'key'=>$scope[0],'type'=>$scope[1],'plan'=>$scope[2],
                'limit'=>$scope[3],'unlimited'=>$scope[4],'period'=>$scope[5],
            ]
        );
    }
    $exec(
        "INSERT INTO startpartner_pilot_entitlements(
            id,pilot_id,organizer_id,source_reference,status,starts_at,ends_at,target_plan_keys_json,
            event_limit_per_pilot_month,activity_concurrent_limit,is_event_unlimited,source_scope_json,audit_json,revision
         ) VALUES(
            :id,:pilot,:organizer,:pilot,'active',:starts_at,:ends_at,JSON_ARRAY('active','activity_basic'),
            8,1,0,JSON_OBJECT(),JSON_OBJECT('synthetic',true),1
         )",
        ['id'=>$entitlement,'pilot'=>$pilot,'organizer'=>$organizer,'starts_at'=>$window['starts_at_utc'],'ends_at'=>$window['ends_at_utc']]
    );
    $exec(
        "INSERT INTO organizer_portal_sessions(organizer_id,session_token_hash,expires_at,last_seen_at)
         VALUES(:organizer,:hash,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP())",
        ['organizer'=>$organizer,'hash'=>hash('sha256',$token)]
    );
    $exec(
        "INSERT INTO submissions(
            organizer_id,submission_kind,status,requested_model_key,payment_kind,payment_reference_key,
            organization_name_snapshot,contact_name_snapshot,email_snapshot,title,start_date,location_name,
            location_public_confirmed,review_started_at,approved_at
         ) VALUES(
            :organizer,'event','approved','active','startpartner_pilot',:payment_reference,
            :organization,'Lifecycle Test',:email,'Historischer erster Pilotinhalt',:start_date,'Bocholt',1,
            DATE_SUB(UTC_TIMESTAMP(),INTERVAL 160 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 160 DAY)
         )",
        [
            'organizer'=>$organizer,'payment_reference'=>hash('sha256',"payment-{$seed}"),
            'organization'=>"Lifecycle Verein {$seed}",'email'=>$email,'start_date'=>$activationDate,
        ]
    );
    $submission = (int)$pdo->lastInsertId();
    $targetId = be_startpartner_gate4_reporting_target_id($organizer);
    $exec(
        "INSERT INTO startpartner_pilot_content_links(
            id,pilot_id,organizer_id,submission_id,content_type,status,reporting_target_type,
            reporting_target_id,source_reference,editorial_ready_at,approved_at
         ) VALUES(
            :id,:pilot,:organizer,:submission,'event','approved','organizer',:target,'synthetic:first',
            DATE_SUB(UTC_TIMESTAMP(),INTERVAL 160 DAY),DATE_SUB(UTC_TIMESTAMP(),INTERVAL 160 DAY)
         )",
        ['id'=>$contentLink,'pilot'=>$pilot,'organizer'=>$organizer,'submission'=>$submission,'target'=>$targetId]
    );
    $exec(
        "INSERT INTO startpartner_pilot_usages(
            pilot_id,pilot_entitlement_id,content_link_id,submission_id,content_type,pilot_month_index,units,consumed_at
         ) VALUES(:pilot,:entitlement,:content,:submission,'event',1,1,DATE_SUB(UTC_TIMESTAMP(),INTERVAL 160 DAY))",
        ['pilot'=>$pilot,'entitlement'=>$entitlement,'content'=>$contentLink,'submission'=>$submission]
    );
    $exec(
        "INSERT INTO startpartner_pilot_measurement_preflights(
            id,pilot_id,organizer_id,content_link_id,status,metrics_owner,reporting_target_type,
            reporting_target_id,evidence_json,checked_by
         ) VALUES(:id,:pilot,:organizer,:content,'ready','value_metric_daily','organizer',:target,JSON_OBJECT('synthetic',true),'Lifecycle Contract')",
        [
            'id'=>sprintf('3444%04d-0000-4000-8000-%012d',$seed,$seed),
            'pilot'=>$pilot,'organizer'=>$organizer,'content'=>$contentLink,'target'=>$targetId,
        ]
    );
    $exec(
        "INSERT INTO startpartner_pilot_distribution_commitments(
            id,pilot_id,channel,planned_at,target_reference,status,evidence_text,operator_reference
         ) VALUES(:id,:pilot,'newsletter',:planned_at,'https://example.invalid/lifecycle','ready','Synthetic agreement','Lifecycle Contract')",
        [
            'id'=>sprintf('3445%04d-0000-4000-8000-%012d',$seed,$seed),
            'pilot'=>$pilot,'planned_at'=>$yesterday . ' 08:00:00',
        ]
    );
    return compact('candidate','pilot','entitlement','organizer','token','contentLink','submission','targetId');
};

$refresh = static function(array $fixture) use ($pdo): array {
    $detail = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    return [(int)$detail['revision'], (int)$detail['gate4']['pilot']['revision'], $detail];
};

$mutate = static function(array $fixture, callable|string $writer, array $payload, ?string $operationId = null) use ($pdo, $refresh): array {
    [$candidateRevision, $pilotRevision] = $refresh($fixture);
    $payload += [
        'operation_id' => $operationId ?? ('gate4:344:mysql:' . bin2hex(random_bytes(8))),
        'operator_name' => 'Lifecycle Contract',
        'expected_revision' => $candidateRevision,
        'expected_pilot_revision' => $pilotRevision,
    ];
    return $writer($pdo, $fixture['candidate'], $payload);
};

try {
    $fixture = $seedActivePilot(1);
    $_COOKIE['be_organizer_portal_session'] = $fixture['token'];
    $session = be_startpartner_gate4_portal_session($pdo);
    $initial = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert($initial['gate4']['phase'] === 'active' && $initial['gate4']['effective_active'] === true, 'Synthetic reference pilot must start effectively active.');
    $assert((int)$initial['gate4']['capacity']['occupied_slots'] === 1, 'Active synthetic pilot must occupy exactly one capacity slot.');
    $assert(($initial['gate4']['measurement_runtime']['status'] ?? '') === 'no_data_yet_or_too_short', 'No metric rows must be no_data_yet_or_too_short, never zero usage.');
    $assert(($initial['gate4']['distribution_runtime']['status'] ?? '') === 'due', 'Past ready distribution must be due, not completed.');

    $replayInput = [
        'content_type'=>'event','client_reference'=>'gate4-344-replay-1','title'=>'Replay Event',
        'start_date'=>$today->modify('+10 days')->format('Y-m-d'),'location_name'=>'Bocholt',
        'location_address'=>'Testweg 1','location_public_confirmed'=>true,
    ];
    $firstReplayWrite = be_startpartner_gate4_create_portal_submission($pdo, $session, $replayInput);
    $sameReplay = be_startpartner_gate4_create_portal_submission($pdo, $session, $replayInput);
    $assert(($sameReplay['idempotent_replay'] ?? false) === true, 'Same partner client_reference and same payload must replay.');
    $changedReplay = $replayInput;
    $changedReplay['title'] = 'Changed replay payload';
    $expectDomain(
        static fn() => be_startpartner_gate4_create_portal_submission($pdo, $session, $changedReplay),
        'Same partner client_reference with changed payload must fail closed.'
    );

    $eventLinks = [];
    for ($index = 1; $index <= 9; $index++) {
        $created = be_startpartner_gate4_create_portal_submission($pdo, $session, [
            'content_type'=>'event','client_reference'=>"gate4-344-event-{$index}",
            'title'=>"Pilot Event {$index}",'start_date'=>$today->modify('+' . (20 + $index) . ' days')->format('Y-m-d'),
            'location_name'=>'Bocholt','location_address'=>'Testweg 2','location_public_confirmed'=>true,
        ]);
        $eventLinks[] = (string)$created['content_link']['id'];
    }
    $firstApprovalReplay = null;
    $firstApprovalPayload = null;
    for ($index = 0; $index < 8; $index++) {
        $mutate($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$eventLinks[$index]]);
        [$candidateRevision, $pilotRevision] = $refresh($fixture);
        $operationId = 'gate4:344:mysql:event-approval-' . $index;
        $payload = [
            'operation_id'=>$operationId,'operator_name'=>'Lifecycle Contract',
            'expected_revision'=>$candidateRevision,'expected_pilot_revision'=>$pilotRevision,
            'content_link_id'=>$eventLinks[$index],
        ];
        $approval = be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], $payload);
        if ($index === 0) {
            $firstApprovalReplay = be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], $payload);
            $firstApprovalPayload = $payload;
        }
        $assert(($approval['meta']['usage_units'] ?? null) === 1, "Event approval " . ($index + 1) . ' must write one usage.');
    }
    $assert(($firstApprovalReplay['idempotent_replay'] ?? false) === true, 'Identical approval operation retry must replay without second usage.');
    if (is_array($firstApprovalPayload)) {
        $changedOperation = $firstApprovalPayload;
        $changedOperation['unexpected_change'] = 'different-payload';
        $expectDomain(
            static fn() => be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], $changedOperation),
            'Same operation_id with changed payload must conflict.'
        );
    }
    $mutate($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$eventLinks[8]]);
    $expectDomain(
        static function() use ($pdo, $fixture, $eventLinks, $refresh): void {
            [$cr, $pr] = $refresh($fixture);
            be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], [
                'operation_id'=>'gate4:344:mysql:event-nine-blocked','operator_name'=>'Lifecycle Contract',
                'expected_revision'=>$cr,'expected_pilot_revision'=>$pr,'content_link_id'=>$eventLinks[8],
            ]);
        },
        'Ninth event approval in one pilot month must fail closed.'
    );
    $monthIndex = be_startpartner_gate4_pilot_month_index($activationDate, $today->format('Y-m-d'), $window['planned_end_date']);
    $usageCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM startpartner_pilot_usages
         WHERE pilot_id='" . $fixture['pilot'] . "' AND content_type='event' AND pilot_month_index=" . (int)$monthIndex
    )->fetchColumn();
    $assert($usageCount === 8, 'Current pilot month must contain exactly eight event usage rows after the blocked ninth approval.');

    $activityLinks = [];
    for ($index = 1; $index <= 2; $index++) {
        $created = be_startpartner_gate4_create_portal_submission($pdo, $session, [
            'content_type'=>'activity','client_reference'=>"gate4-344-activity-{$index}",
            'title'=>"Pilot Activity {$index}",'location_name'=>'Bocholt',
            'location_address'=>'Testweg 3','location_public_confirmed'=>true,
        ]);
        $activityLinks[] = (string)$created['content_link']['id'];
    }
    $mutate($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$activityLinks[0]]);
    $mutate($fixture, 'be_startpartner_gate4_approve_content', ['content_link_id'=>$activityLinks[0]]);
    $mutate($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$activityLinks[1]]);
    $expectDomain(
        static function() use ($pdo, $fixture, $activityLinks, $refresh): void {
            [$cr, $pr] = $refresh($fixture);
            be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], [
                'operation_id'=>'gate4:344:mysql:activity-two-blocked','operator_name'=>'Lifecycle Contract',
                'expected_revision'=>$cr,'expected_pilot_revision'=>$pr,'content_link_id'=>$activityLinks[1],
            ]);
        },
        'Second concurrent activity approval must fail closed.'
    );
    $usageBeforeWithdrawal = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $mutate($fixture, 'be_startpartner_gate4_withdraw_content', ['content_link_id'=>$activityLinks[0]]);
    $mutate($fixture, 'be_startpartner_gate4_approve_content', ['content_link_id'=>$activityLinks[1]]);
    $usageAfterReplacement = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $assert($usageAfterReplacement === $usageBeforeWithdrawal + 1, 'Activity replacement must retain historical usage and add exactly one new usage.');

    foreach (BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS as $key) {
        $mutate($fixture, 'be_startpartner_gate4_complete_checkpoint', [
            'checkpoint_key'=>$key,'evidence_text'=>"Synthetic {$key} checkpoint.",
        ]);
    }
    $checkpointEvents = (int)$pdo->query(
        "SELECT COUNT(*) FROM startpartner_pilot_events WHERE pilot_id='" . $fixture['pilot'] . "' AND event_type='gate4_checkpoint_completed'"
    )->fetchColumn();
    $assert($checkpointEvents === 4, 'All four lifecycle checkpoints must be recorded exactly once.');
    $expectDomain(
        static function() use ($pdo, $fixture, $refresh): void {
            [$cr, $pr] = $refresh($fixture);
            be_startpartner_gate4_complete_checkpoint($pdo, $fixture['candidate'], [
                'operation_id'=>'gate4:344:mysql:duplicate-checkpoint','operator_name'=>'Lifecycle Contract',
                'expected_revision'=>$cr,'expected_pilot_revision'=>$pr,
                'checkpoint_key'=>'day_30','evidence_text'=>'Duplicate must fail.',
            ]);
        },
        'A completed checkpoint must not be completed a second time.'
    );

    $targetId = $fixture['targetId'];
    $exec(
        "INSERT INTO value_metric_daily(metric_date,metric_key,reporting_target_type,reporting_target_id,bucket_hash,count_value)
         VALUES(:date,'detail_view','organizer',:target,:hash,0)",
        ['date'=>$yesterday,'target'=>$targetId,'hash'=>hash('sha256','gate4-344-zero')]
    );
    $zeroState = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert(($zeroState['gate4']['measurement_runtime']['status'] ?? '') === 'zero_usage', 'Explicit completed zero bucket must classify as zero_usage.');
    $exec(
        "INSERT INTO value_metric_daily(metric_date,metric_key,reporting_target_type,reporting_target_id,bucket_hash,count_value)
         VALUES(:date,'detail_view','organizer',:target,:hash,3)",
        ['date'=>$twoDaysAgo,'target'=>$targetId,'hash'=>hash('sha256','gate4-344-positive')]
    );
    $positiveState = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert(($positiveState['gate4']['measurement_runtime']['status'] ?? '') === 'usage_observed', 'Positive completed metric bucket must classify as usage_observed.');
    $exec(
        "UPDATE startpartner_pilot_measurement_preflights SET reporting_target_id='wrong-target'
         WHERE pilot_id=:pilot AND content_link_id=:content",
        ['pilot'=>$fixture['pilot'],'content'=>$fixture['contentLink']]
    );
    $problemState = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert(($problemState['gate4']['measurement_runtime']['status'] ?? '') === 'query_or_attribution_problem', 'Wrong attribution must classify as query_or_attribution_problem.');
    $exec(
        "UPDATE startpartner_pilot_measurement_preflights SET reporting_target_id=:target
         WHERE pilot_id=:pilot AND content_link_id=:content",
        ['target'=>$targetId,'pilot'=>$fixture['pilot'],'content'=>$fixture['contentLink']]
    );

    $distributionState = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $distributionId = (string)$distributionState['gate4']['distribution_runtime']['commitment']['id'];
    $mutate($fixture, 'be_startpartner_gate4_set_distribution_fulfillment', [
        'distribution_id'=>$distributionId,'status'=>'blocked','evidence_text'=>'Synthetic blocker.',
    ]);
    $blockedDistribution = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert(($blockedDistribution['gate4']['distribution_runtime']['status'] ?? '') === 'blocked', 'Ready distribution may become blocked.');
    $mutate($fixture, 'be_startpartner_gate4_set_distribution_fulfillment', [
        'distribution_id'=>$distributionId,'status'=>'completed','evidence_text'=>'Synthetic completion.',
    ]);
    $completedDistribution = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert(($completedDistribution['gate4']['distribution_runtime']['status'] ?? '') === 'completed', 'Resolved blocked distribution may become completed.');
    $expectDomain(
        static function() use ($pdo, $fixture, $distributionId, $refresh): void {
            [$cr, $pr] = $refresh($fixture);
            be_startpartner_gate4_set_distribution_fulfillment($pdo, $fixture['candidate'], [
                'operation_id'=>'gate4:344:mysql:completed-reopen','operator_name'=>'Lifecycle Contract',
                'expected_revision'=>$cr,'expected_pilot_revision'=>$pr,
                'distribution_id'=>$distributionId,'status'=>'blocked','evidence_text'=>'Terminal reopen must fail.',
            ]);
        },
        'Completed distribution must be terminal.'
    );

    $usageBeforeLifecycle = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $mutate($fixture, 'be_startpartner_gate4_transition_lifecycle', ['transition'=>'pause']);
    $paused = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert($paused['gate4']['phase'] === 'paused', 'Pause must project paused state.');
    $assert((string)$paused['gate3']['entitlement']['status'] === 'paused', 'Pause must pause the entitlement.');
    $assert((int)$paused['gate4']['capacity']['occupied_slots'] === 1, 'Paused pilot must keep its capacity slot.');
    $expectDomain(
        static fn() => be_startpartner_gate4_create_portal_submission($pdo, $session, [
            'content_type'=>'event','client_reference'=>'gate4-344-paused-submit','title'=>'Must not persist',
            'start_date'=>$today->modify('+40 days')->format('Y-m-d'),'location_name'=>'Bocholt',
            'location_public_confirmed'=>true,
        ]),
        'Paused pilot must reject new partner content.'
    );
    $mutate($fixture, 'be_startpartner_gate4_transition_lifecycle', ['transition'=>'resume']);
    $resumed = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert($resumed['gate4']['phase'] === 'active', 'Resume must restore active state inside the effective window.');

    $spare = be_startpartner_gate4_create_portal_submission($pdo, $session, [
        'content_type'=>'event','client_reference'=>'gate4-344-spare-draft','title'=>'Spare draft',
        'start_date'=>$today->modify('+50 days')->format('Y-m-d'),'location_name'=>'Bocholt',
        'location_public_confirmed'=>true,
    ]);
    $spareId = (string)$spare['content_link']['id'];
    $mutate($fixture, 'be_startpartner_gate4_transition_lifecycle', ['transition'=>'start_closeout']);
    $closing = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert($closing['gate4']['phase'] === 'closing', 'Closeout must enter closing.');
    $assert((int)$closing['gate4']['capacity']['occupied_slots'] === 1, 'Closing pilot must keep its capacity slot.');
    $mutate($fixture, 'be_startpartner_gate4_transition_lifecycle', ['transition'=>'end_without_conversion']);
    $ended = be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
    $assert($ended['gate4']['phase'] === 'ended_without_conversion', 'Orderly closeout must end without conversion.');
    $assert((int)$ended['gate4']['capacity']['occupied_slots'] === 0, 'Ended pilot must release capacity.');
    $spareStatus = $pdo->prepare('SELECT status FROM startpartner_pilot_content_links WHERE id=:id');
    $spareStatus->execute(['id'=>$spareId]);
    $assert((string)$spareStatus->fetchColumn() === 'withdrawn', 'Orderly end must withdraw only open pilot links.');
    $usageAfterEnd = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $assert($usageAfterEnd === $usageBeforeLifecycle, 'Pause, resume and orderly end must not delete historical usage.');
    $caseState = $pdo->prepare("SELECT state FROM control_cases WHERE source_system='startpartner_candidate' AND source_reference=:candidate");
    $caseState->execute(['candidate'=>$fixture['candidate']]);
    $assert((string)$caseState->fetchColumn() === 'done', 'Terminal pilot must close the existing Control Center projection.');

    $terminatedFixture = $seedActivePilot(2);
    $terminateUsageBefore = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $terminatedFixture['pilot'] . "'")->fetchColumn();
    $mutate($terminatedFixture, 'be_startpartner_gate4_transition_lifecycle', ['transition'=>'terminate']);
    $terminated = be_startpartner_gate4_candidate_detail($pdo, $terminatedFixture['candidate']);
    $assert($terminated['gate4']['phase'] === 'terminated', 'Termination must enter terminal state.');
    $assert((string)$terminated['gate3']['entitlement']['status'] === 'revoked', 'Termination must revoke the pilot entitlement.');
    $assert((int)$terminated['gate4']['capacity']['occupied_slots'] === 0, 'Terminated pilot must release capacity.');
    $terminateUsageAfter = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $terminatedFixture['pilot'] . "'")->fetchColumn();
    $assert($terminateUsageAfter === $terminateUsageBefore, 'Termination must retain historical usage.');

    $lockedBefore = [
        (int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),
        (int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),
        (int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn(),
    ];
    $lockedAfter = [
        (int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),
        (int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),
        (int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn(),
    ];
    $assert($lockedBefore === $lockedAfter, 'Lifecycle contract must not mutate regular subscription/publication entitlement owners.');
} catch (Throwable $error) {
    $failures[] = $error::class . ': ' . $error->getMessage();
}

$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
foreach ([
    'startpartner_candidate_operations','startpartner_pilot_events','startpartner_pilot_usages',
    'startpartner_pilot_measurement_preflights','startpartner_pilot_distribution_commitments',
    'startpartner_pilot_onboarding_items','startpartner_pilot_content_links','organizer_portal_sessions',
    'organizer_magic_links','submissions','startpartner_pilot_entitlements','startpartner_pilot_scopes',
    'startpartner_pilots','startpartner_pilot_terms_acceptances','startpartner_candidate_reservations',
    'startpartner_candidate_decisions','startpartner_candidate_contacts','control_case_events','control_cases',
    'startpartner_candidates','organizers','value_metric_daily',
] as $table) {
    $pdo->exec("DELETE FROM {$table}");
}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
$assert((int)$pdo->query('SELECT COUNT(*) FROM startpartner_candidates')->fetchColumn() === 0, 'Lifecycle cleanup must leave zero synthetic candidates.');
$assert((int)be_startpartner_gate4_capacity($pdo)['occupied_slots'] === 0, 'Lifecycle cleanup must leave capacity at zero.');

if ($failures) {
    fwrite(STDERR, "=== Startpartner Active-Pilot MySQL Contract: FAILED ===\n" . implode("\n", array_map(static fn(string $v): string => '- ' . $v, $failures)) . "\n");
    exit(1);
}
echo "=== Startpartner Active-Pilot MySQL Contract: OK ===\n";
