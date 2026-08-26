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
$assert = static function(bool $condition, string $message) use (&$failures): void {
    if (!$condition) {
        $failures[] = $message;
    }
};
$exec = static function(string $sql, array $params = []) use ($pdo): void {
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
};

$email = 'gate4-340-v2@example.invalid';
$acceptedAt = '2026-08-25 12:00:00';
$digest = hash('sha256', 'gate4-340-v2-terms');

$exec(
    "INSERT INTO organizers(organization_name, contact_name, email, email_normalized)
     VALUES('Gate 4 V2 Verein', 'Erika V2', :email, :email)",
    ['email' => $email]
);
$organizerId = (int)$pdo->lastInsertId();

$gate3 = [
    'pilot' => [
        'organizer_id' => $organizerId,
        'partner_contact_email_snapshot' => $email,
    ],
    'terms_acceptance' => [
        'id' => 340,
        'terms_version' => BE_STARTPARTNER_GATE4_TERMS_V2,
        'terms_reference' => BE_STARTPARTNER_GATE4_TERMS_REFERENCE_V2,
        'terms_digest' => $digest,
        'accepted_at' => $acceptedAt,
        'confirmation_channel' => 'email_reply',
        'no_automatic_paid_renewal' => 1,
    ],
];

$assert(
    be_startpartner_gate4_terms_v2_accepted($gate3) === true,
    'A bound v2 acceptance from an explicit partner evidence channel must be recognized.'
);

$operatorRecorded = $gate3;
$operatorRecorded['terms_acceptance']['confirmation_channel'] = 'operator_recorded';
$assert(
    be_startpartner_gate4_terms_v2_accepted($operatorRecorded) === false,
    'operator_recorded alone must not become the production v2 partner-acceptance shortcut.'
);

$wrongReference = $gate3;
$wrongReference['terms_acceptance']['terms_reference'] = 'system://startpartner/pilot-terms/not-v2';
$assert(
    be_startpartner_gate4_terms_v2_accepted($wrongReference) === false,
    'v2 derived rights require the canonical bound v2 terms reference.'
);

$insertMagicLink = static function(
    string $tokenSeed,
    string $emailSnapshot,
    string $intendedAction,
    string $consumedAt,
    string $createdAt
) use ($pdo, $organizerId): int {
    $statement = $pdo->prepare(
        'INSERT INTO organizer_magic_links (
            organizer_id, token_hash, intended_action, email_snapshot,
            expires_at, consumed_at, created_at, updated_at
         ) VALUES (
            :organizer_id, :token_hash, :intended_action, :email_snapshot,
            :expires_at, :consumed_at, :created_at, :created_at
         )'
    );
    $statement->execute([
        'organizer_id' => $organizerId,
        'token_hash' => hash('sha256', $tokenSeed),
        'intended_action' => $intendedAction,
        'email_snapshot' => $emailSnapshot,
        'expires_at' => '2026-08-30 12:00:00',
        'consumed_at' => $consumedAt,
        'created_at' => $createdAt,
    ]);
    return (int)$pdo->lastInsertId();
};

$insertSession = static function(
    string $tokenSeed,
    int $magicLinkId,
    string $createdAt
) use ($pdo, $organizerId): int {
    $statement = $pdo->prepare(
        'INSERT INTO organizer_portal_sessions (
            organizer_id, session_token_hash, issued_from_magic_link_id,
            expires_at, last_seen_at, created_at, updated_at
         ) VALUES (
            :organizer_id, :session_token_hash, :magic_link_id,
            :expires_at, :last_seen_at, :created_at, :created_at
         )'
    );
    $statement->execute([
        'organizer_id' => $organizerId,
        'session_token_hash' => hash('sha256', $tokenSeed),
        'magic_link_id' => $magicLinkId,
        'expires_at' => '2026-08-30 12:00:00',
        'last_seen_at' => $createdAt,
        'created_at' => $createdAt,
    ]);
    return (int)$pdo->lastInsertId();
};

$oldLink = $insertMagicLink(
    'gate4-340-old-link',
    $email,
    'portal_login',
    '2026-08-25 11:00:00',
    '2026-08-25 10:59:00'
);
$insertSession('gate4-340-old-session', $oldLink, '2026-08-25 11:00:01');
$assert(
    be_startpartner_gate4_portal_access_readback($pdo, $gate3) === null,
    'A portal session from before the current v2 acceptance must not satisfy portal_access_tested.'
);

$wrongEmailLink = $insertMagicLink(
    'gate4-340-wrong-email-link',
    'other@example.invalid',
    'portal_login',
    '2026-08-25 12:30:00',
    '2026-08-25 12:29:00'
);
$insertSession('gate4-340-wrong-email-session', $wrongEmailLink, '2026-08-25 12:30:01');
$assert(
    be_startpartner_gate4_portal_access_readback($pdo, $gate3) === null,
    'A fresh session for another email snapshot must not satisfy the current partner access check.'
);

$wrongActionLink = $insertMagicLink(
    'gate4-340-wrong-action-link',
    $email,
    'account_change',
    '2026-08-25 12:40:00',
    '2026-08-25 12:39:00'
);
$insertSession('gate4-340-wrong-action-session', $wrongActionLink, '2026-08-25 12:40:01');
$assert(
    be_startpartner_gate4_portal_access_readback($pdo, $gate3) === null,
    'Only a consumed portal_login Magic Link may prove the partner portal access step.'
);

$freshLink = $insertMagicLink(
    'gate4-340-fresh-link',
    strtoupper($email),
    'portal_login',
    '2026-08-25 13:00:00',
    '2026-08-25 12:59:00'
);
$freshSessionId = $insertSession('gate4-340-fresh-session', $freshLink, '2026-08-25 13:00:01');
$portalReadback = be_startpartner_gate4_portal_access_readback($pdo, $gate3);
$assert(
    is_array($portalReadback) && (int)$portalReadback['portal_session_id'] === $freshSessionId,
    'A post-acceptance portal_login for the bound contact must satisfy the v2 portal access readback.'
);

$persistedRows = [];
foreach (BE_STARTPARTNER_GATE4_ONBOARDING_ITEMS as $key) {
    $persistedRows[] = [
        'item_key' => $key,
        'status' => 'pending',
        'is_required' => 1,
        'is_hard_blocker' => 1,
        'revision' => 0,
    ];
}
$v2Items = array_column(
    be_startpartner_gate4_current_onboarding_items($gate3, $persistedRows, null, null, null, $portalReadback),
    null,
    'item_key'
);
$assert(
    ($v2Items['content_rights_cleared']['status'] ?? '') === 'complete'
        && (int)($v2Items['content_rights_cleared']['is_manual'] ?? 1) === 0,
    'Accepted v2 terms must derive content rights instead of requiring operator copy/paste evidence.'
);
$assert(
    ($v2Items['portal_access_tested']['status'] ?? '') === 'complete'
        && (int)($v2Items['portal_access_tested']['is_manual'] ?? 1) === 0,
    'Current v2 portal access must be system-derived.'
);
$assert(
    ($v2Items['activation_target_set']['status'] ?? '') === 'complete'
        && (int)($v2Items['activation_target_set']['is_manual'] ?? 1) === 0,
    'v2 must not require a redundant pre-activation date-maintenance step.'
);

$legacy = $gate3;
$legacy['terms_acceptance']['terms_version'] = 'startpartner-pilot-2026-08-v1';
$legacy['terms_acceptance']['terms_reference'] = 'system://startpartner/pilot-terms/startpartner-pilot-2026-08-v1';
$legacy['terms_acceptance']['confirmation_channel'] = 'operator_recorded';
$legacyItems = array_column(
    be_startpartner_gate4_current_onboarding_items($legacy, $persistedRows, null, null, null, $portalReadback),
    null,
    'item_key'
);
$assert(
    ($legacyItems['content_rights_cleared']['status'] ?? '') === 'pending'
        && (int)($legacyItems['content_rights_cleared']['is_manual'] ?? 0) === 1,
    'Legacy v1 pilots must retain their historical manual-evidence semantics.'
);
$assert(
    ($legacyItems['portal_access_tested']['status'] ?? '') === 'pending'
        && (int)($legacyItems['portal_access_tested']['is_manual'] ?? 0) === 1,
    'Legacy v1 portal evidence must not be silently reinterpreted.'
);

if ($failures !== []) {
    fwrite(STDERR, "=== Startpartner Gate-4 V2 Journey Contract: FAILED ===\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, '- ' . $failure . "\n");
    }
    exit(1);
}

echo "=== Startpartner Gate-4 V2 Journey Contract: OK ===\n";
