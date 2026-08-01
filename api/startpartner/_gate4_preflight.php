<?php
declare(strict_types=1);

function be_startpartner_gate4_measurement_readback(PDO $pdo, array $content): array
{
    $organizerId = (int)($content['organizer_id'] ?? 0);
    $targetType = trim((string)($content['reporting_target_type'] ?? ''));
    $targetId = trim((string)($content['reporting_target_id'] ?? ''));
    $expectedTargetId = be_startpartner_gate4_reporting_target_id($organizerId);
    if ($targetType !== 'organizer' || !hash_equals($expectedTargetId, $targetId)) {
        throw new DomainException('Die Messzuordnung stimmt nicht mit dem kanonischen Organizer-Ziel überein.');
    }

    $requiredColumns = ['metric_date', 'reporting_target_type', 'reporting_target_id', 'count_value', 'updated_at'];
    $placeholders = implode(',', array_fill(0, count($requiredColumns), '?'));
    $columns = $pdo->prepare(
        "SELECT COUNT(DISTINCT COLUMN_NAME)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'value_metric_daily'
           AND COLUMN_NAME IN ($placeholders)"
    );
    $columns->execute($requiredColumns);
    if ((int)$columns->fetchColumn() !== count($requiredColumns)) {
        throw new DomainException('Der kanonische Messdaten-Owner ist nicht vollständig initialisiert.');
    }

    $readback = $pdo->prepare(
        "SELECT COUNT(*) AS bucket_count,
                COALESCE(SUM(count_value), 0) AS observed_actions,
                MAX(updated_at) AS last_metric_update
         FROM value_metric_daily
         WHERE reporting_target_type = :target_type
           AND reporting_target_id = :target_id"
    );
    $readback->execute(['target_type' => $targetType, 'target_id' => $targetId]);
    $row = $readback->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        throw new DomainException('Der Messdaten-Owner konnte nicht read-only zurückgelesen werden.');
    }

    return [
        'owner' => 'value_metric_daily',
        'query_status' => 'ok',
        'reporting_target_type' => $targetType,
        'reporting_target_id' => $targetId,
        'bucket_count' => (int)($row['bucket_count'] ?? 0),
        'observed_actions' => (int)($row['observed_actions'] ?? 0),
        'last_metric_update' => $row['last_metric_update'] ?? null,
        'checked_at_utc' => gmdate('Y-m-d H:i:s'),
    ];
}

function be_startpartner_gate4_distribution_input(array $input): array
{
    $channel = be_startpartner_gate4_required_text($input['channel'] ?? null, 64, 'channel');
    $targetReference = be_startpartner_gate4_required_text($input['target_reference'] ?? null, 2048, 'target_reference');
    $plannedLocalDate = be_startpartner_gate4_validate_local_date($input['planned_at'] ?? null);
    $plannedAt = (new DateTimeImmutable(
        $plannedLocalDate . ' 00:00:00',
        new DateTimeZone('Europe/Berlin')
    ))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $status = strtolower(be_startpartner_gate4_required_text($input['status'] ?? null, 16, 'status'));
    if (!in_array($status, ['ready', 'blocked'], true)) {
        throw new InvalidArgumentException('distribution status must be ready or blocked.');
    }
    return [
        'channel' => $channel,
        'target_reference' => $targetReference,
        'planned_date_local' => $plannedLocalDate,
        'planned_at_utc' => $plannedAt,
        'status' => $status,
        'evidence_text' => be_startpartner_gate4_required_text($input['evidence_text'] ?? null, 5000, 'evidence_text'),
    ];
}

function be_startpartner_gate4_supersede_distribution(PDO $pdo, string $pilotId, string $operationId): void
{
    $statement = $pdo->prepare(
        "UPDATE startpartner_pilot_distribution_commitments
         SET status = 'cancelled',
             evidence_text = CONCAT(
                 COALESCE(NULLIF(evidence_text, ''), 'Kein früherer Nachweis.'),
                 '\nAbgelöst durch Operation ', :operation_id
             ),
             updated_at = CURRENT_TIMESTAMP
         WHERE pilot_id = :pilot_id
           AND status IN ('planned','ready','blocked')"
    );
    $statement->execute(['operation_id' => $operationId, 'pilot_id' => $pilotId]);
}
