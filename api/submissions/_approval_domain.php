<?php
declare(strict_types=1);

function be_submission_approval_fetch_for_update(PDO $pdo, int $submissionId): array
{
    if ($submissionId < 1) {
        throw new InvalidArgumentException('submission_id must be a positive integer.');
    }

    $statement = $pdo->prepare(
        'SELECT
            id,
            organizer_id,
            submission_kind,
            status,
            requested_model_key,
            payment_kind,
            intake_origin,
            title,
            start_date,
            location_name,
            location_public_confirmed,
            paid_at,
            stripe_checkout_session_id,
            stripe_customer_id,
            stripe_subscription_id,
            stripe_price_id
         FROM submissions
         WHERE id = :submission_id
         LIMIT 1
         FOR UPDATE'
    );
    $statement->execute(['submission_id' => $submissionId]);
    $submission = $statement->fetch(PDO::FETCH_ASSOC);

    if (!is_array($submission)) {
        throw new DomainException('linked submission was not found.');
    }

    return $submission;
}

function be_submission_approval_assert_required_content(array $submission): void
{
    $kind = trim((string)($submission['submission_kind'] ?? ''));
    if (!in_array($kind, ['event', 'activity'], true)) {
        throw new DomainException('linked submission kind is not supported.');
    }
    if (trim((string)($submission['title'] ?? '')) === '') {
        throw new DomainException('linked submission title is missing.');
    }
    if ($kind === 'event' && trim((string)($submission['start_date'] ?? '')) === '') {
        throw new DomainException('linked event date is missing.');
    }
    if (trim((string)($submission['location_name'] ?? '')) === '') {
        throw new DomainException('linked submission location is missing.');
    }
    if ((int)($submission['location_public_confirmed'] ?? 0) !== 1) {
        throw new DomainException('linked submission location is not approved for public display.');
    }
}

function be_submission_approval_assert_pilot_path(
    array $submission,
    array $contentLink,
    array $pilot
): void {
    if ((int)($submission['organizer_id'] ?? 0) !== (int)($pilot['organizer_id'] ?? 0)) {
        throw new DomainException('linked submission does not match pilot organizer.');
    }
    if ((int)($contentLink['submission_id'] ?? 0) !== (int)($submission['id'] ?? 0)) {
        throw new DomainException('pilot content link does not match submission.');
    }
    if ((string)($contentLink['publication_status'] ?? '') !== 'editorial_ready') {
        throw new DomainException('pilot content is not editorially ready.');
    }
    if ((string)($submission['status'] ?? '') !== 'in_review') {
        throw new DomainException('pilot submission is not in editorial review.');
    }
    if ((string)($submission['requested_model_key'] ?? '') !== 'startpartner_pilot'
        || (string)($submission['payment_kind'] ?? '') !== 'pilot'
        || (string)($submission['intake_origin'] ?? '') !== 'startpartner_pilot') {
        throw new DomainException('linked submission is not a dedicated Startpartner pilot submission.');
    }
    if (!empty($submission['paid_at'])
        || !empty($submission['stripe_checkout_session_id'])
        || !empty($submission['stripe_customer_id'])
        || !empty($submission['stripe_subscription_id'])
        || !empty($submission['stripe_price_id'])) {
        throw new DomainException('pilot submission must not contain payment or Stripe state.');
    }

    be_submission_approval_assert_required_content($submission);
}

function be_submission_approval_mark_pilot_approved(PDO $pdo, int $submissionId): void
{
    $statement = $pdo->prepare(
        "UPDATE submissions SET status='approved',review_started_at=COALESCE(review_started_at,UTC_TIMESTAMP()),approved_at=COALESCE(approved_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP() WHERE id=:id AND status='in_review' AND requested_model_key='startpartner_pilot' AND payment_kind='pilot' AND intake_origin='startpartner_pilot'"
    );
    $statement->execute(['id' => $submissionId]);

    if ($statement->rowCount() !== 1) {
        throw new DomainException('pilot submission approval did not update exactly one row.');
    }
}
