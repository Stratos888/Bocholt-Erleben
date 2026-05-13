<?php
declare(strict_types=1);
/* === BEGIN FILE: api/submissions/approve.php | Zweck: gibt eine bezahlte Submission frei, bucht genau eine Consumption und reduziert das passende Entitlement; Umfang: komplette Datei === */

require dirname(__DIR__) . '/_bootstrap.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    be_json_response(405, [
        'status' => 'error',
        'message' => 'Method not allowed.',
    ]);
}

/* === BEGIN BLOCK: SUBMISSION_REVIEW_ENDPOINT_ACCESS_GUARD_V1 | Zweck: verhindert öffentlichen Zugriff auf DB-Review-Endpunkt; Umfang: prüft Review-Passwort nach Method-Check === */
be_require_review_access();
/* === END BLOCK: SUBMISSION_REVIEW_ENDPOINT_ACCESS_GUARD_V1 === */

function sap_read_json_body(): array
{
    $raw = file_get_contents('php://input');

    if (!is_string($raw) || trim($raw) === '') {
        throw new InvalidArgumentException('Request body is empty.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new InvalidArgumentException('Request body must be valid JSON.');
    }

    return $decoded;
}

function sap_required_positive_int(array $input, string $key, string $label): int
{
    $value = $input[$key] ?? null;

    if (!is_numeric($value)) {
        throw new InvalidArgumentException($label . ' is required.');
    }

    $intValue = (int)$value;
    if ($intValue <= 0) {
        throw new InvalidArgumentException($label . ' must be a positive integer.');
    }

    return $intValue;
}

function sap_fetch_submission(PDO $pdo, int $submissionId): array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            payment_reference_key,
            email_snapshot,
            title,
            start_date,
            location_name,
            location_public_confirmed,
            approved_at,
            paid_at
         FROM submissions
         WHERE id = :submission_id
         LIMIT 1'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        throw new InvalidArgumentException('Submission wurde nicht gefunden.');
    }

    return $row;
}

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_APPROVAL_ASSERT_V1 | Zweck: erlaubt finale Freigabe fuer bezahlte Events und Aktivitaetspraesenzen mit passenden Pflichtdaten; Umfang: ersetzt sap_assert_submission_approvable === */
function sap_assert_submission_approvable(array $submission): void
{
    $status = trim((string)($submission['status'] ?? ''));
    $submissionKind = (string)($submission['submission_kind'] ?? 'event');

    if (!in_array($status, ['paid', 'in_review', 'approved'], true)) {
        throw new InvalidArgumentException('Submission ist aktuell nicht freigabefähig.');
    }

    if (trim((string)($submission['paid_at'] ?? '')) === '') {
        throw new InvalidArgumentException('Submission ist noch nicht bezahlt.');
    }

    if (!in_array($submissionKind, ['event', 'activity'], true)) {
        throw new InvalidArgumentException('Diese Submission-Art kann nicht veröffentlicht werden.');
    }

    if (trim((string)($submission['title'] ?? '')) === '') {
        throw new InvalidArgumentException($submissionKind === 'activity'
            ? 'Vor der Veröffentlichung fehlt der Aktivitätsname.'
            : 'Vor der Veröffentlichung fehlt der Veranstaltungstitel.');
    }

    if ($submissionKind === 'event' && trim((string)($submission['start_date'] ?? '')) === '') {
        throw new InvalidArgumentException('Vor der Veröffentlichung fehlt das Veranstaltungsdatum.');
    }

    if (trim((string)($submission['location_name'] ?? '')) === '') {
        throw new InvalidArgumentException($submissionKind === 'activity'
            ? 'Vor der Veröffentlichung fehlt Anbieter oder Ort der Aktivität.'
            : 'Vor der Veröffentlichung fehlt der Veranstaltungsort.');
    }

    if ((int)($submission['location_public_confirmed'] ?? 0) !== 1) {
        throw new InvalidArgumentException('Vor der Veröffentlichung muss die öffentliche Ortsnennung bestätigt sein.');
    }
}
/* === END BLOCK: ACTIVITY_PRESENCE_APPROVAL_ASSERT_V1 === */

function sap_fetch_existing_consumption(PDO $pdo, int $submissionId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            entitlement_id,
            submission_id,
            units,
            consumed_reason,
            consumed_at
         FROM publication_consumptions
         WHERE submission_id = :submission_id
         LIMIT 1'
    );

    $statement->execute([
        ':submission_id' => $submissionId,
    ]);

    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

function sap_fetch_specific_single_entitlement(PDO $pdo, int $organizerId, int $submissionId): ?array
{
    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            source_type,
            source_reference,
            source_submission_id,
            subscription_id,
            plan_key,
            status,
            period_start,
            period_end,
            included_publications,
            consumed_publications,
            is_unlimited
         FROM publication_entitlements
         WHERE organizer_id = :organizer_id
           AND status = :status
           AND source_type = :source_type
           AND source_submission_id = :source_submission_id
           AND (is_unlimited = 1 OR consumed_publications < included_publications)
         LIMIT 1'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
        ':status' => 'active',
        ':source_type' => 'single_submission',
        ':source_submission_id' => $submissionId,
    ]);

    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}

/* === BEGIN BLOCK: ACTIVITY_PRESENCE_APPROVAL_ENTITLEMENT_SCOPE_V1 | Zweck: trennt Event- und Aktivitaetspraesenz-Kontingente bei finaler Freigabe; Umfang: ersetzt sap_fetch_active_subscription_entitlement und ergänzt Plan-Gruppen-Helfer === */
function sap_subscription_plan_keys_for_submission(array $submission): array
{
    $submissionKind = trim((string)($submission['submission_kind'] ?? 'event'));

    if ($submissionKind === 'activity') {
        return ['activity_basic', 'activity_plus'];
    }

    return ['starter', 'active', 'unlimited'];
}

function sap_fetch_active_subscription_entitlement(PDO $pdo, array $submission): ?array
{
    $organizerId = (int)$submission['organizer_id'];
    $planKeys = sap_subscription_plan_keys_for_submission($submission);
    $planPlaceholders = [];
    $params = [
        ':organizer_id' => $organizerId,
        ':status' => 'active',
        ':source_type' => 'subscription',
    ];

    foreach ($planKeys as $index => $planKey) {
        $placeholder = ':plan_key_' . $index;
        $planPlaceholders[] = $placeholder;
        $params[$placeholder] = $planKey;
    }

    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            source_type,
            source_id,
            plan_key,
            status,
            included_publications,
            consumed_publications,
            is_unlimited,
            period_start,
            period_end
         FROM publication_entitlements
         WHERE organizer_id = :organizer_id
           AND status = :status
           AND source_type = :source_type
           AND plan_key IN (' . implode(', ', $planPlaceholders) . ')
           AND (period_start IS NULL OR period_start <= UTC_TIMESTAMP())
           AND (period_end IS NULL OR period_end >= UTC_TIMESTAMP())
           AND (is_unlimited = 1 OR consumed_publications < included_publications)
         ORDER BY
            is_unlimited ASC,
            period_end ASC,
            id ASC
         LIMIT 1'
    );

    $statement->execute($params);

    $row = $statement->fetch();
    return is_array($row) ? $row : null;
}
/* === END BLOCK: ACTIVITY_PRESENCE_APPROVAL_ENTITLEMENT_SCOPE_V1 === */

function sap_pick_entitlement(PDO $pdo, array $submission): array
{
    $organizerId = (int)$submission['organizer_id'];
    $submissionId = (int)$submission['id'];

    $singleEntitlement = sap_fetch_specific_single_entitlement($pdo, $organizerId, $submissionId);
    if (is_array($singleEntitlement)) {
        return $singleEntitlement;
    }

$subscriptionEntitlement = sap_fetch_active_subscription_entitlement($pdo, $submission);
    if (is_array($subscriptionEntitlement)) {
        return $subscriptionEntitlement;
    }

    throw new RuntimeException('Kein aktives Veröffentlichungs-Kontingent für diese Freigabe gefunden.');
}

function sap_increment_entitlement_consumption(PDO $pdo, array $entitlement): void
{
    $statement = $pdo->prepare(
        'UPDATE publication_entitlements
         SET consumed_publications = consumed_publications + 1,
             updated_at = CURRENT_TIMESTAMP
         WHERE id = :id
           AND (
             is_unlimited = 1
             OR consumed_publications < included_publications
           )'
    );

    $statement->execute([
        ':id' => (int)$entitlement['id'],
    ]);

    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('Entitlement konnte nicht belastet werden.');
    }
}

function sap_insert_consumption(PDO $pdo, int $organizerId, int $entitlementId, int $submissionId): int
{
    $statement = $pdo->prepare(
        'INSERT INTO publication_consumptions (
            organizer_id,
            entitlement_id,
            submission_id,
            units,
            consumed_reason
         ) VALUES (
            :organizer_id,
            :entitlement_id,
            :submission_id,
            1,
            :consumed_reason
         )'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
        ':entitlement_id' => $entitlementId,
        ':submission_id' => $submissionId,
        ':consumed_reason' => 'approved_publication',
    ]);

    return (int)$pdo->lastInsertId();
}

function sap_mark_submission_approved(PDO $pdo, int $submissionId): void
{
    $statement = $pdo->prepare(
        'UPDATE submissions
         SET
            status = :status,
            review_started_at = CASE
                WHEN review_started_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE review_started_at
            END,
            approved_at = CASE
                WHEN approved_at IS NULL THEN CURRENT_TIMESTAMP
                ELSE approved_at
            END,
            updated_at = CURRENT_TIMESTAMP
         WHERE id = :submission_id'
    );

    $statement->execute([
        ':status' => 'approved',
        ':submission_id' => $submissionId,
    ]);
}
/* === BEGIN BLOCK: SUBMISSION_APPROVAL_PUBLICATION_MAIL_V1 | Zweck: informiert Einreicher nach finaler Veröffentlichung; Umfang: Mail-Helfer für approve.php === */
/* === BEGIN BLOCK: ACTIVITY_PRESENCE_APPROVAL_PUBLICATION_MAIL_V1 | Zweck: informiert Einreicher nach finaler Veröffentlichung passend fuer Event oder Aktivitaet; Umfang: ersetzt SUBMISSION_APPROVAL_PUBLICATION_MAIL_V1 === */
function sap_send_publication_mail(array $submission): void
{
    $email = trim((string)($submission['email_snapshot'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }

    $title = trim((string)($submission['title'] ?? ''));
    $reference = trim((string)($submission['payment_reference_key'] ?? ''));
    $isActivity = (string)($submission['submission_kind'] ?? 'event') === 'activity';

    $bodyLines = [
        'Hallo,',
        '',
        $isActivity
            ? 'deine Aktivität wurde bei Bocholt erleben veröffentlicht.'
            : 'deine Veranstaltung wurde bei Bocholt erleben veröffentlicht.',
        '',
        ($isActivity ? 'Aktivität: ' : 'Veranstaltung: ') . ($title !== '' ? $title : 'ohne Titel'),
        'Referenz: ' . $reference,
        '',
        $isActivity
            ? 'Sie ist nun im öffentlichen Aktivitätenbereich sichtbar.'
            : 'Sie ist nun im öffentlichen Eventbereich sichtbar.',
        '',
        'Viele Grüße',
        'Bocholt erleben',
    ];

    try {
        be_send_mail(
            $email,
            $isActivity ? 'Deine Aktivität wurde veröffentlicht' : 'Deine Veranstaltung wurde veröffentlicht',
            implode("\n", $bodyLines)
        );
    } catch (Throwable $error) {
        error_log('Submission publication mail failed: ' . $error->getMessage());
    }
}
/* === END BLOCK: ACTIVITY_PRESENCE_APPROVAL_PUBLICATION_MAIL_V1 === */
/* === END BLOCK: ACTIVITY_PRESENCE_APPROVAL_PUBLICATION_MAIL_V1 === */
/* === END BLOCK: SUBMISSION_APPROVAL_PUBLICATION_MAIL_V1 === */
function sap_build_quota_summary(PDO $pdo, int $organizerId): array
{
    $statement = $pdo->prepare(
        'SELECT
            COUNT(*) AS entitlement_count,
            MAX(CASE WHEN is_unlimited = 1 THEN 1 ELSE 0 END) AS has_unlimited,
            COALESCE(SUM(included_publications), 0) AS included_total,
            COALESCE(SUM(consumed_publications), 0) AS consumed_total
         FROM publication_entitlements
         WHERE organizer_id = :organizer_id
           AND status = :status
           AND (period_start IS NULL OR period_start <= UTC_TIMESTAMP())
           AND (period_end IS NULL OR period_end >= UTC_TIMESTAMP())'
    );

    $statement->execute([
        ':organizer_id' => $organizerId,
        ':status' => 'active',
    ]);

    $row = $statement->fetch();
    if (!is_array($row)) {
        return [
            'entitlement_count' => 0,
            'has_unlimited' => false,
            'included_total' => 0,
            'consumed_total' => 0,
            'remaining_total' => 0,
        ];
    }

    $hasUnlimited = ((int)($row['has_unlimited'] ?? 0)) === 1;
    $includedTotal = (int)($row['included_total'] ?? 0);
    $consumedTotal = (int)($row['consumed_total'] ?? 0);

    return [
        'entitlement_count' => (int)($row['entitlement_count'] ?? 0),
        'has_unlimited' => $hasUnlimited,
        'included_total' => $includedTotal,
        'consumed_total' => $consumedTotal,
        'remaining_total' => $hasUnlimited ? null : max(0, $includedTotal - $consumedTotal),
    ];
}

try {
    $input = sap_read_json_body();
    $submissionId = sap_required_positive_int($input, 'submission_id', 'submission_id');

    $pdo = be_db();
    $pdo->beginTransaction();

    $submission = sap_fetch_submission($pdo, $submissionId);
    sap_assert_submission_approvable($submission);

    $existingConsumption = sap_fetch_existing_consumption($pdo, $submissionId);

    if (is_array($existingConsumption)) {
        sap_mark_submission_approved($pdo, $submissionId);
        $quotaSummary = sap_build_quota_summary($pdo, (int)$submission['organizer_id']);
        $pdo->commit();

        be_json_response(200, [
            'status' => 'ok',
            'data' => [
                'submission_id' => $submissionId,
                'submission_status' => 'approved',
                'consumption_id' => (int)$existingConsumption['id'],
                'idempotent' => true,
                'quota' => $quotaSummary,
            ],
        ]);
    }

    $entitlement = sap_pick_entitlement($pdo, $submission);
    sap_increment_entitlement_consumption($pdo, $entitlement);
    $consumptionId = sap_insert_consumption(
        $pdo,
        (int)$submission['organizer_id'],
        (int)$entitlement['id'],
        $submissionId
    );
    sap_mark_submission_approved($pdo, $submissionId);

    $quotaSummary = sap_build_quota_summary($pdo, (int)$submission['organizer_id']);

    $pdo->commit();

    sap_send_publication_mail($submission);

    be_json_response(200, [
        'status' => 'ok',
        'data' => [
            'submission_id' => $submissionId,
            'submission_status' => 'approved',
            'entitlement_id' => (int)$entitlement['id'],
            'consumption_id' => $consumptionId,
            'quota' => $quotaSummary,
            'public_feed' => '/api/events/public.php',
        ],
    ]);
} catch (InvalidArgumentException $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    be_json_response(422, [
        'status' => 'error',
        'message' => $error->getMessage(),
    ]);
} catch (Throwable $error) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    be_json_response(500, [
        'status' => 'error',
        'message' => 'Submission approval failed.',
        'error_class' => get_class($error),
        'error_message' => $error->getMessage(),
    ]);
}

/* === END FILE: api/submissions/approve.php === */
