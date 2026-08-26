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
$activationWindow = be_startpartner_gate4_activation_window($activationDate);
$currentMonthIndex = be_startpartner_gate4_pilot_month_index(
    $activationDate,
    $today->format('Y-m-d'),
    $activationWindow['planned_end_date']
);
if ($currentMonthIndex === null) {
    fwrite(STDERR, "Synthetic active-pilot date window is invalid.\n");
    exit(2);
}
$yesterday = $today->modify('-1 day')->format('Y-m-d');
$twoDaysAgo = $today->modify('-2 days')->format('Y-m-d');

$lockedBefore = [
    (int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn(),
];

$seedActivePilot = static function(int $seed) use (
    $pdo,
    $exec,
    $activationDate,
    $activationWindow,
    $yesterday
): array {
    $candidate = sprintf('3442%04d-0000-4000-8000-%012d', $seed, $seed);
    $pilot = sprintf('3440%04d-0000-4000-8000-%012d', $seed, $seed);
    $entitlement = sprintf('3441%04d-0000-4000-8000-%012d', $seed, $seed);
    $firstContent = sprintf('3443%04d-0000-4000-8000-%012d', $seed, $seed);
    $measurement = sprintf('3444%04d-0000-4000-8000-%012d', $seed, $seed);
    $distribution = sprintf('3445%04d-0000-4000-8000-%012d', $seed, $seed);
    $email = "lifecycle-{$seed}@example.invalid";
    $sessionToken = hash('sha256', "lifecycle-session-{$seed}");
    $organization = "Lifecycle Verein {$seed}";

    $exec(
        "INSERT INTO organizers(organization_name,contact_name,email,email_normalized)
         VALUES(:organization,'Lifecycle Test',:email,:email)",
        ['organization'=>$organization,'email'=>$email]
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
            'id'=>$candidate,'organization'=>$organization,'normalized'=>strtolower($organization),
            'identity'=>hash('sha256',"lifecycle-identity-{$seed}"),
            'idem'=>hash('sha256',"lifecycle-idem-{$seed}"),
        ]
    );
    $exec(
        "INSERT INTO startpartner_candidate_contacts(candidate_id,contact_name,email,email_normalized,is_primary)
         VALUES(:candidate,'Lifecycle Test',:email,:email,1)",
        ['candidate'=>$candidate,'email'=>$email]
    );
    $exec(
        "INSERT INTO startpartner_candidate_decisions(
            candidate_id,result,reason,operator_reference,candidate_revision,
            qualification_snapshot_json,capacity_snapshot_json,is_current
         ) VALUES(:candidate,'accepted_pending_terms','Synthetic fixture','Lifecycle Contract',1,JSON_OBJECT(),JSON_OBJECT(),1)",
        ['candidate'=>$candidate]
    );
    $decision = (int)$pdo->lastInsertId();

    $reservationStart = (new DateTimeImmutable($activationDate . ' 00:00:00', new DateTimeZone('Europe/Berlin')))
        ->modify('-5 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $reservationEnd = (new DateTimeImmutable($activationDate . ' 00:00:00', new DateTimeZone('Europe/Berlin')))
        ->modify('+5 days')->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $exec(
        "INSERT INTO startpartner_candidate_reservations(
            candidate_id,decision_id,status,starts_at,ends_at,capacity_snapshot_json,operator_reference,
            released_at,release_reference
         ) VALUES(
            :candidate,:decision,'released',:starts_at,:ends_at,JSON_OBJECT(),'Lifecycle Contract',
            :released_at,'synthetic-activation'
         )",
        [
            'candidate'=>$candidate,'decision'=>$decision,'starts_at'=>$reservationStart,
            'ends_at'=>$reservationEnd,'released_at'=>$activationWindow['starts_at_utc'],
        ]
    );
    $reservation = (int)$pdo->lastInsertId();
    $exec(
        "INSERT INTO startpartner_pilot_terms_acceptances(
            candidate_id,decision_id,terms_version,terms_reference,terms_digest,accepting_person,
            accepting_organization,accepted_at,confirmation_channel,service_scope_json,source_care_json,
            reach_contribution_json,no_automatic_paid_renewal,operator_reference
         ) VALUES(
            :candidate,:decision,'v1','repo://gate4-lifecycle',:digest,'Lifecycle Test',:organization,
            :accepted_at,'operator_recorded',
            JSON_OBJECT('desired_content_scope','both','target_plan_keys',JSON_ARRAY('active','activity_basic')),
            JSON_OBJECT('description','Synthetic source'),JSON_OBJECT('description','Synthetic reach'),1,'Lifecycle Contract'
         )",
        [
            'candidate'=>$candidate,'decision'=>$decision,'digest'=>hash('sha256',"lifecycle-terms-{$seed}"),
            'organization'=>$organization,'accepted_at'=>$reservationStart,
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
            'Lifecycle Contract','Lifecycle Test',:email,:activated_at,:activation_date,:planned_end,:starts_at,:ends_at,1
         )",
        [
            'id'=>$pilot,'candidate'=>$candidate,'organizer'=>$organizer,'terms'=>$terms,
            'reservation'=>$reservation,'email'=>$email,'activated_at'=>$activationWindow['starts_at_utc'],
            'activation_date'=>$activationDate,'planned_end'=>$activationWindow['planned_end_date'],
            'starts_at'=>$activationWindow['starts_at_utc'],'ends_at'=>$activationWindow['ends_at_utc'],
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
        [
            'id'=>$entitlement,'pilot'=>$pilot,'organizer'=>$organizer,
            'starts_at'=>$activationWindow['starts_at_utc'],'ends_at'=>$activationWindow['ends_at_utc'],
        ]
    );
    $exec(
        "INSERT INTO organizer_portal_sessions(organizer_id,session_token_hash,expires_at,last_seen_at)
         VALUES(:organizer,:hash,DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY),UTC_TIMESTAMP())",
        ['organizer'=>$organizer,'hash'=>hash('sha256',$sessionToken)]
    );
    $exec(
        "INSERT INTO submissions(
            organizer_id,submission_kind,status,requested_model_key,payment_kind,payment_reference_key,
            organization_name_snapshot,contact_name_snapshot,email_snapshot,title,start_date,location_name,
            location_public_confirmed,review_started_at,approved_at
         ) VALUES(
            :organizer,'event','approved','active','startpartner_pilot',:payment_reference,
            :organization,'Lifecycle Test',:email,'Historischer erster Pilotinhalt',:start_date,'Bocholt',1,
            :approved_at,:approved_at
         )",
        [
            'organizer'=>$organizer,'payment_reference'=>sprintf('sp344-first-%08d', $seed),
            'organization'=>$organization,'email'=>$email,'start_date'=>$activationDate,
            'approved_at'=>$activationWindow['starts_at_utc'],
        ]
    );
    $firstSubmission = (int)$pdo->lastInsertId();
    $targetId = be_startpartner_gate4_reporting_target_id($organizer);
    $exec(
        "INSERT INTO startpartner_pilot_content_links(
            id,pilot_id,organizer_id,submission_id,content_type,status,reporting_target_type,
            reporting_target_id,source_reference,editorial_ready_at,approved_at
         ) VALUES(
            :id,:pilot,:organizer,:submission,'event','approved','organizer',:target,'synthetic:first',
            :approved_at,:approved_at
         )",
        [
            'id'=>$firstContent,'pilot'=>$pilot,'organizer'=>$organizer,'submission'=>$firstSubmission,
            'target'=>$targetId,'approved_at'=>$activationWindow['starts_at_utc'],
        ]
    );
    $exec(
        "INSERT INTO startpartner_pilot_usages(
            pilot_id,pilot_entitlement_id,content_link_id,submission_id,content_type,pilot_month_index,units,consumed_at
         ) VALUES(:pilot,:entitlement,:content,:submission,'event',1,1,:consumed_at)",
        [
            'pilot'=>$pilot,'entitlement'=>$entitlement,'content'=>$firstContent,
            'submission'=>$firstSubmission,'consumed_at'=>$activationWindow['starts_at_utc'],
        ]
    );
    $exec(
        "INSERT INTO startpartner_pilot_measurement_preflights(
            id,pilot_id,organizer_id,content_link_id,status,metrics_owner,reporting_target_type,
            reporting_target_id,evidence_json,checked_by
         ) VALUES(:id,:pilot,:organizer,:content,'ready','value_metric_daily','organizer',:target,
            JSON_OBJECT('synthetic',true),'Lifecycle Contract')",
        [
            'id'=>$measurement,'pilot'=>$pilot,'organizer'=>$organizer,'content'=>$firstContent,'target'=>$targetId,
        ]
    );
    $exec(
        "INSERT INTO startpartner_pilot_distribution_commitments(
            id,pilot_id,channel,planned_at,target_reference,status,evidence_text,operator_reference
         ) VALUES(:id,:pilot,'newsletter',:planned_at,'https://example.invalid/lifecycle','ready',
            'Synthetic agreement','Lifecycle Contract')",
        ['id'=>$distribution,'pilot'=>$pilot,'planned_at'=>$yesterday . ' 08:00:00']
    );
    return compact(
        'candidate','pilot','entitlement','organizer','sessionToken','firstContent',
        'firstSubmission','targetId','distribution','organization','email'
    );
};

$detail = static function(array $fixture) use ($pdo): array {
    return be_startpartner_gate4_candidate_detail($pdo, $fixture['candidate']);
};
$operationPayload = static function(array $fixture, array $payload, ?string $operationId = null) use ($detail): array {
    $current = $detail($fixture);
    return $payload + [
        'operation_id'=>$operationId ?? ('gate4:344:mysql:' . bin2hex(random_bytes(8))),
        'operator_name'=>'Lifecycle Contract',
        'expected_revision'=>(int)$current['revision'],
        'expected_pilot_revision'=>(int)$current['gate4']['pilot']['revision'],
    ];
};
$write = static function(array $fixture, callable|string $writer, array $payload, ?string $operationId = null) use (
    $pdo,
    $operationPayload
): array {
    return $writer($pdo, $fixture['candidate'], $operationPayload($fixture, $payload, $operationId));
};
$transition = static function(array $fixture, string $transition) use ($pdo, $operationPayload): array {
    $payload = $operationPayload($fixture, []);
    return be_startpartner_gate4_transition_lifecycle($pdo, $fixture['candidate'], $transition, $payload);
};
$insertApprovedUsage = static function(array $fixture, string $contentType, int $monthIndex, int $ordinal) use ($pdo, $exec): string {
    $contentId = sprintf('346%05d-0000-4000-8000-%012d', $ordinal, $ordinal);
    $requestedModel = $contentType === 'activity' ? 'activity_basic' : 'active';
    $exec(
        "INSERT INTO submissions(
            organizer_id,submission_kind,status,requested_model_key,payment_kind,payment_reference_key,
            organization_name_snapshot,contact_name_snapshot,email_snapshot,title,start_date,location_name,
            location_public_confirmed,review_started_at,approved_at
         ) VALUES(
            :organizer,:kind,'approved',:model,'startpartner_pilot',:payment_reference,
            :organization,'Lifecycle Test',:email,:title,:start_date,'Bocholt',1,UTC_TIMESTAMP(),UTC_TIMESTAMP()
         )",
        [
            'organizer'=>$fixture['organizer'],'kind'=>$contentType,'model'=>$requestedModel,
            'payment_reference'=>sprintf('sp344-use-%08d', $ordinal),
            'organization'=>$fixture['organization'],'email'=>$fixture['email'],
            'title'=>"Synthetic approved {$contentType} {$ordinal}",
            'start_date'=>$contentType === 'event' ? gmdate('Y-m-d', strtotime('+30 days')) : null,
        ]
    );
    $submission = (int)$pdo->lastInsertId();
    $exec(
        "INSERT INTO startpartner_pilot_content_links(
            id,pilot_id,organizer_id,submission_id,content_type,status,reporting_target_type,
            reporting_target_id,source_reference,editorial_ready_at,approved_at
         ) VALUES(
            :id,:pilot,:organizer,:submission,:kind,'approved','organizer',:target,:source,UTC_TIMESTAMP(),UTC_TIMESTAMP()
         )",
        [
            'id'=>$contentId,'pilot'=>$fixture['pilot'],'organizer'=>$fixture['organizer'],
            'submission'=>$submission,'kind'=>$contentType,'target'=>$fixture['targetId'],
            'source'=>"synthetic:approved:{$ordinal}",
        ]
    );
    $exec(
        "INSERT INTO startpartner_pilot_usages(
            pilot_id,pilot_entitlement_id,content_link_id,submission_id,content_type,pilot_month_index,units
         ) VALUES(:pilot,:entitlement,:content,:submission,:kind,:month_index,1)",
        [
            'pilot'=>$fixture['pilot'],'entitlement'=>$fixture['entitlement'],'content'=>$contentId,
            'submission'=>$submission,'kind'=>$contentType,'month_index'=>$monthIndex,
        ]
    );
    return $contentId;
};

try {
    $fixture = $seedActivePilot(1);
    $_COOKIE['be_organizer_portal_session'] = $fixture['sessionToken'];
    $session = be_startpartner_gate4_portal_session($pdo);
    $initial = $detail($fixture);
    $assert($initial['gate4']['phase'] === 'active' && $initial['gate4']['effective_active'] === true, 'Synthetic pilot must start effectively active.');
    $assert((int)$initial['gate4']['capacity']['occupied_slots'] === 1, 'Active pilot must occupy one capacity slot.');
    $assert(($initial['gate4']['measurement_runtime']['status'] ?? '') === 'no_data_yet_or_too_short', 'Missing metric rows must never be interpreted as zero usage.');
    $assert(($initial['gate4']['distribution_runtime']['status'] ?? '') === 'due', 'Past ready distribution must be due.');

    $replayInput = [
        'content_type'=>'event','client_reference'=>'gate4-344-replay-1','title'=>'Replay Event',
        'start_date'=>$today->modify('+10 days')->format('Y-m-d'),'location_name'=>'Bocholt',
        'location_address'=>'Testweg 1','location_public_confirmed'=>true,
    ];
    be_startpartner_gate4_create_portal_submission($pdo, $session, $replayInput);
    $sameReplay = be_startpartner_gate4_create_portal_submission($pdo, $session, $replayInput);
    $assert(($sameReplay['idempotent_replay'] ?? false) === true, 'Same client_reference and payload must replay.');
    $changedReplay = $replayInput;
    $changedReplay['title'] = 'Changed replay payload';
    $expectDomain(
        static fn() => be_startpartner_gate4_create_portal_submission($pdo, $session, $changedReplay),
        'Same client_reference with changed payload must fail closed.'
    );

    for ($i = 1; $i <= 7; $i++) {
        $insertApprovedUsage($fixture, 'event', $currentMonthIndex, $i);
    }
    $event8 = be_startpartner_gate4_create_portal_submission($pdo, $session, [
        'content_type'=>'event','client_reference'=>'gate4-344-event-8','title'=>'Pilot Event 8',
        'start_date'=>$today->modify('+20 days')->format('Y-m-d'),'location_name'=>'Bocholt',
        'location_public_confirmed'=>true,
    ]);
    $event9 = be_startpartner_gate4_create_portal_submission($pdo, $session, [
        'content_type'=>'event','client_reference'=>'gate4-344-event-9','title'=>'Pilot Event 9',
        'start_date'=>$today->modify('+21 days')->format('Y-m-d'),'location_name'=>'Bocholt',
        'location_public_confirmed'=>true,
    ]);
    $event8Id = (string)$event8['content_link']['id'];
    $event9Id = (string)$event9['content_link']['id'];
    $write($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$event8Id]);
    $approvalPayload = $operationPayload(
        $fixture,
        ['content_link_id'=>$event8Id],
        'gate4:344:mysql:event-eight'
    );
    $approval = be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], $approvalPayload);
    $assert(($approval['meta']['usage_units'] ?? null) === 1, 'Eighth event approval must write exactly one usage.');
    $approvalReplay = be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], $approvalPayload);
    $assert(($approvalReplay['idempotent_replay'] ?? false) === true, 'Identical approval retry must replay.');
    $changedApproval = $approvalPayload;
    $changedApproval['changed_payload'] = true;
    $expectDomain(
        static fn() => be_startpartner_gate4_approve_content($pdo, $fixture['candidate'], $changedApproval),
        'Same operation_id with changed approval payload must conflict.'
    );
    $write($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$event9Id]);
    $expectDomain(
        static function() use ($pdo, $fixture, $event9Id, $operationPayload): void {
            be_startpartner_gate4_approve_content(
                $pdo,
                $fixture['candidate'],
                $operationPayload($fixture, ['content_link_id'=>$event9Id], 'gate4:344:mysql:event-nine')
            );
        },
        'Ninth event approval in one pilot month must fail closed.'
    );
    $eventUsage = $pdo->prepare(
        "SELECT COALESCE(SUM(units),0) FROM startpartner_pilot_usages
         WHERE pilot_id=:pilot AND content_type='event' AND pilot_month_index=:month_index"
    );
    $eventUsage->execute(['pilot'=>$fixture['pilot'],'month_index'=>$currentMonthIndex]);
    $assert((int)$eventUsage->fetchColumn() === 8, 'Current pilot month must contain exactly eight event usage units.');

    $activity1 = be_startpartner_gate4_create_portal_submission($pdo, $session, [
        'content_type'=>'activity','client_reference'=>'gate4-344-activity-1','title'=>'Pilot Activity 1',
        'location_name'=>'Bocholt','location_public_confirmed'=>true,
    ]);
    $activity2 = be_startpartner_gate4_create_portal_submission($pdo, $session, [
        'content_type'=>'activity','client_reference'=>'gate4-344-activity-2','title'=>'Pilot Activity 2',
        'location_name'=>'Bocholt','location_public_confirmed'=>true,
    ]);
    $activity1Id = (string)$activity1['content_link']['id'];
    $activity2Id = (string)$activity2['content_link']['id'];
    $write($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$activity1Id]);
    $write($fixture, 'be_startpartner_gate4_approve_content', ['content_link_id'=>$activity1Id]);
    $write($fixture, 'be_startpartner_gate4_mark_content_ready', ['content_link_id'=>$activity2Id]);
    $expectDomain(
        static function() use ($pdo, $fixture, $activity2Id, $operationPayload): void {
            be_startpartner_gate4_approve_content(
                $pdo,
                $fixture['candidate'],
                $operationPayload($fixture, ['content_link_id'=>$activity2Id], 'gate4:344:mysql:activity-two-blocked')
            );
        },
        'Second concurrent activity approval must fail closed.'
    );
    $usageBeforeWithdrawal = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $write($fixture, 'be_startpartner_gate4_withdraw_content', ['content_link_id'=>$activity1Id]);
    $write($fixture, 'be_startpartner_gate4_approve_content', ['content_link_id'=>$activity2Id]);
    $usageAfterReplacement = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $assert($usageAfterReplacement === $usageBeforeWithdrawal + 1, 'Activity withdrawal must retain historical usage and allow exactly one replacement usage.');

    foreach (BE_STARTPARTNER_GATE4_CHECKPOINT_KEYS as $key) {
        $write($fixture, 'be_startpartner_gate4_complete_checkpoint', [
            'checkpoint_key'=>$key,'evidence_text'=>"Synthetic {$key} checkpoint.",
        ]);
    }
    $checkpointCount = (int)$pdo->query(
        "SELECT COUNT(*) FROM startpartner_pilot_events WHERE pilot_id='" . $fixture['pilot'] . "' AND event_type='gate4_checkpoint_completed'"
    )->fetchColumn();
    $assert($checkpointCount === 4, 'All four lifecycle checkpoints must be recorded once.');
    $expectDomain(
        static function() use ($pdo, $fixture, $operationPayload): void {
            be_startpartner_gate4_complete_checkpoint(
                $pdo,
                $fixture['candidate'],
                $operationPayload(
                    $fixture,
                    ['checkpoint_key'=>'day_30','evidence_text'=>'Duplicate must fail.'],
                    'gate4:344:mysql:duplicate-checkpoint'
                )
            );
        },
        'Completed checkpoint must not be completed again.'
    );

    $exec(
        "INSERT INTO value_metric_daily(metric_date,metric_key,reporting_target_type,reporting_target_id,bucket_hash,count_value)
         VALUES(:date,'detail_view','organizer',:target,:hash,0)",
        ['date'=>$yesterday,'target'=>$fixture['targetId'],'hash'=>hash('sha256','gate4-344-zero')]
    );
    $zeroState = $detail($fixture);
    $assert(($zeroState['gate4']['measurement_runtime']['status'] ?? '') === 'zero_usage', 'Explicit completed zero bucket must classify as zero_usage.');
    $exec(
        "INSERT INTO value_metric_daily(metric_date,metric_key,reporting_target_type,reporting_target_id,bucket_hash,count_value)
         VALUES(:date,'detail_view','organizer',:target,:hash,3)",
        ['date'=>$twoDaysAgo,'target'=>$fixture['targetId'],'hash'=>hash('sha256','gate4-344-positive')]
    );
    $usageState = $detail($fixture);
    $assert(($usageState['gate4']['measurement_runtime']['status'] ?? '') === 'usage_observed', 'Positive completed bucket must classify as usage_observed.');
    $exec(
        "UPDATE startpartner_pilot_measurement_preflights SET reporting_target_id='wrong-target'
         WHERE pilot_id=:pilot AND content_link_id=:content",
        ['pilot'=>$fixture['pilot'],'content'=>$fixture['firstContent']]
    );
    $problemState = $detail($fixture);
    $assert(($problemState['gate4']['measurement_runtime']['status'] ?? '') === 'query_or_attribution_problem', 'Wrong attribution must classify as query_or_attribution_problem.');
    $exec(
        "UPDATE startpartner_pilot_measurement_preflights SET reporting_target_id=:target
         WHERE pilot_id=:pilot AND content_link_id=:content",
        ['target'=>$fixture['targetId'],'pilot'=>$fixture['pilot'],'content'=>$fixture['firstContent']]
    );

    $write($fixture, 'be_startpartner_gate4_set_distribution_fulfillment', [
        'distribution_id'=>$fixture['distribution'],'status'=>'blocked','evidence_text'=>'Synthetic blocker.',
    ]);
    $blockedDistribution = $detail($fixture);
    $assert(($blockedDistribution['gate4']['distribution_runtime']['status'] ?? '') === 'blocked', 'Ready distribution may become blocked.');
    $write($fixture, 'be_startpartner_gate4_set_distribution_fulfillment', [
        'distribution_id'=>$fixture['distribution'],'status'=>'completed','evidence_text'=>'Synthetic completion.',
    ]);
    $completedDistribution = $detail($fixture);
    $assert(($completedDistribution['gate4']['distribution_runtime']['status'] ?? '') === 'completed', 'Resolved blocked distribution may become completed.');
    $expectDomain(
        static function() use ($pdo, $fixture, $operationPayload): void {
            be_startpartner_gate4_set_distribution_fulfillment(
                $pdo,
                $fixture['candidate'],
                $operationPayload(
                    $fixture,
                    ['distribution_id'=>$fixture['distribution'],'status'=>'blocked','evidence_text'=>'Terminal reopen must fail.'],
                    'gate4:344:mysql:distribution-reopen'
                )
            );
        },
        'Completed distribution must be terminal.'
    );

    $usageBeforeLifecycle = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $transition($fixture, 'pause');
    $paused = $detail($fixture);
    $assert($paused['gate4']['phase'] === 'paused', 'Pause must project paused state.');
    $assert((string)$paused['gate3']['entitlement']['status'] === 'paused', 'Pause must pause entitlement.');
    $assert((int)$paused['gate4']['capacity']['occupied_slots'] === 1, 'Paused pilot must keep its capacity slot.');
    $expectDomain(
        static fn() => be_startpartner_gate4_create_portal_submission($pdo, $session, [
            'content_type'=>'event','client_reference'=>'gate4-344-paused-submit','title'=>'Must not persist',
            'start_date'=>$today->modify('+40 days')->format('Y-m-d'),'location_name'=>'Bocholt',
            'location_public_confirmed'=>true,
        ]),
        'Paused pilot must reject new partner content.'
    );
    $transition($fixture, 'resume');
    $assert($detail($fixture)['gate4']['phase'] === 'active', 'Resume must restore active state inside effective lifetime.');

    $spare = be_startpartner_gate4_create_portal_submission($pdo, $session, [
        'content_type'=>'event','client_reference'=>'gate4-344-spare','title'=>'Spare draft',
        'start_date'=>$today->modify('+50 days')->format('Y-m-d'),'location_name'=>'Bocholt',
        'location_public_confirmed'=>true,
    ]);
    $spareId = (string)$spare['content_link']['id'];
    $transition($fixture, 'start_closeout');
    $closing = $detail($fixture);
    $assert($closing['gate4']['phase'] === 'closing', 'Closeout must enter closing.');
    $assert((int)$closing['gate4']['capacity']['occupied_slots'] === 1, 'Closing pilot must keep its capacity slot.');
    $transition($fixture, 'end_without_conversion');
    $ended = $detail($fixture);
    $assert($ended['gate4']['phase'] === 'ended_without_conversion', 'Orderly closeout must end without conversion.');
    $assert((int)$ended['gate4']['capacity']['occupied_slots'] === 0, 'Ended pilot must release capacity.');
    $spareStatus = $pdo->prepare('SELECT status FROM startpartner_pilot_content_links WHERE id=:id');
    $spareStatus->execute(['id'=>$spareId]);
    $assert((string)$spareStatus->fetchColumn() === 'withdrawn', 'Orderly end must withdraw open pilot links.');
    $usageAfterEnd = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $fixture['pilot'] . "'")->fetchColumn();
    $assert($usageAfterEnd === $usageBeforeLifecycle, 'Pause/resume/end must not delete historical usage.');
    $case = $pdo->prepare("SELECT state FROM control_cases WHERE source_system='startpartner_candidate' AND source_reference=:candidate");
    $case->execute(['candidate'=>$fixture['candidate']]);
    $assert((string)$case->fetchColumn() === 'done', 'Terminal pilot must close the Control Center projection.');

    $terminatedFixture = $seedActivePilot(2);
    $terminateUsageBefore = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $terminatedFixture['pilot'] . "'")->fetchColumn();
    $transition($terminatedFixture, 'terminate');
    $terminated = $detail($terminatedFixture);
    $assert($terminated['gate4']['phase'] === 'terminated', 'Termination must enter terminated state.');
    $assert((string)$terminated['gate3']['entitlement']['status'] === 'revoked', 'Termination must revoke entitlement.');
    $assert((int)$terminated['gate4']['capacity']['occupied_slots'] === 0, 'Terminated pilot must release capacity.');
    $terminateUsageAfter = (int)$pdo->query("SELECT COUNT(*) FROM startpartner_pilot_usages WHERE pilot_id='" . $terminatedFixture['pilot'] . "'")->fetchColumn();
    $assert($terminateUsageAfter === $terminateUsageBefore, 'Termination must retain historical usage.');
} catch (Throwable $error) {
    $failures[] = $error::class . ': ' . $error->getMessage();
}

$lockedAfter = [
    (int)$pdo->query('SELECT COUNT(*) FROM subscriptions')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM publication_entitlements')->fetchColumn(),
    (int)$pdo->query('SELECT COUNT(*) FROM publication_consumptions')->fetchColumn(),
];
$assert($lockedAfter === $lockedBefore, 'Lifecycle contract must not mutate regular subscription/publication owners.');

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
$assert((int)$pdo->query('SELECT COUNT(*) FROM startpartner_candidates')->fetchColumn() === 0, 'Cleanup must leave zero synthetic candidates.');
$assert((int)be_startpartner_gate4_capacity($pdo)['occupied_slots'] === 0, 'Cleanup must leave capacity at zero.');

if ($failures) {
    fwrite(STDERR, "=== Startpartner Active-Pilot MySQL Contract: FAILED ===\n" . implode("\n", array_map(static fn(string $v): string => '- ' . $v, $failures)) . "\n");
    exit(1);
}
echo "=== Startpartner Active-Pilot MySQL Contract: OK ===\n";