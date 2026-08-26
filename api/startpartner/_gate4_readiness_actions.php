<?php
declare(strict_types=1);

function be_startpartner_gate4_measurement_readback(PDO $pdo, array $content): array
{
    $organizerId = (int)($content['organizer_id'] ?? 0);
    $targetType = trim((string)($content['reporting_target_type'] ?? ''));
    $targetId = trim((string)($content['reporting_target_id'] ?? ''));
    $expectedTargetId = be_startpartner_gate4_reporting_target_id($organizerId);
    if ($targetType !== 'organizer' || !hash_equals($expectedTargetId, $targetId)) {
        throw new DomainException('Die Erfolgsmessung ist nicht dem richtigen Veranstalter zugeordnet.');
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
        throw new DomainException('Die Erfolgsmessung ist technisch noch nicht vollständig eingerichtet.');
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
        throw new DomainException('Die Erfolgsmessung konnte nicht geprüft werden.');
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

function be_startpartner_gate4_persist_measurement_preflight(
    PDO $pdo,
    array $pilot,
    array $content,
    string $status,
    string $evidenceText,
    string $checkedBy,
    string $operationId
): array {
    if (!in_array($status, ['ready', 'blocked'], true)) {
        throw new InvalidArgumentException('Die Erfolgsmessung muss als eingerichtet oder als klärungsbedürftig gespeichert werden.');
    }
    if (!in_array((string)($content['status'] ?? ''), ['editorial_ready', 'approved'], true)) {
        throw new DomainException('Für die Erfolgsmessung muss zuerst ein Inhalt für den Pilotstart vorbereitet sein.');
    }

    $technicalReadback = $status === 'ready'
        ? be_startpartner_gate4_measurement_readback($pdo, $content)
        : null;
    $id = be_startpartner_gate4_uuid();
    $statement = $pdo->prepare(
        "INSERT INTO startpartner_pilot_measurement_preflights (
            id, pilot_id, organizer_id, content_link_id, status, metrics_owner,
            reporting_target_type, reporting_target_id, evidence_json, checked_by
         ) VALUES (
            :id, :pilot_id, :organizer_id, :content_link_id, :status, 'value_metric_daily',
            :reporting_target_type, :reporting_target_id, :evidence_json, :checked_by
         )
         ON DUPLICATE KEY UPDATE
            status = VALUES(status), metrics_owner = VALUES(metrics_owner),
            reporting_target_type = VALUES(reporting_target_type),
            reporting_target_id = VALUES(reporting_target_id),
            evidence_json = VALUES(evidence_json), checked_by = VALUES(checked_by),
            checked_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP"
    );
    $statement->execute([
        'id' => $id,
        'pilot_id' => (string)$pilot['id'],
        'organizer_id' => (int)$pilot['organizer_id'],
        'content_link_id' => (string)$content['id'],
        'status' => $status,
        'reporting_target_type' => (string)$content['reporting_target_type'],
        'reporting_target_id' => (string)$content['reporting_target_id'],
        'evidence_json' => json_encode([
            'evidence_text' => $evidenceText,
            'technical_readback' => $technicalReadback,
            'operation_id' => $operationId,
            'pilot_id' => (string)$pilot['id'],
            'content_link_id' => (string)$content['id'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        'checked_by' => $checkedBy,
    ]);
    $item = $pdo->prepare(
        "UPDATE startpartner_pilot_onboarding_items
         SET status = :item_status, evidence_text = :evidence_text,
             evidence_reference = :evidence_reference, operator_reference = :operator,
             completed_at = CASE WHEN :is_ready = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
             revision = revision + 1
         WHERE pilot_id = :pilot_id AND item_key = 'measurement_ready'"
    );
    $item->execute([
        'item_status' => $status === 'ready' ? 'complete' : 'blocked',
        'evidence_text' => $evidenceText,
        'evidence_reference' => (string)$content['id'],
        'operator' => $checkedBy,
        'is_ready' => $status === 'ready' ? 1 : 0,
        'pilot_id' => (string)$pilot['id'],
    ]);

    return [
        'status' => $status,
        'technical_readback' => $technicalReadback,
    ];
}

function be_startpartner_gate4_auto_measurement_preflight(
    PDO $pdo,
    array $pilot,
    array $content,
    string $operationId
): array {
    try {
        return be_startpartner_gate4_persist_measurement_preflight(
            $pdo,
            $pilot,
            $content,
            'ready',
            'Automatische technische Prüfung nach redaktioneller Vorbereitung des ersten Pilotinhalts.',
            'automatic-measurement-readback',
            $operationId
        );
    } catch (DomainException $error) {
        $result = be_startpartner_gate4_persist_measurement_preflight(
            $pdo,
            $pilot,
            $content,
            'blocked',
            'Automatische technische Prüfung nicht erfolgreich: ' . $error->getMessage(),
            'automatic-measurement-readback',
            $operationId
        );
        $result['error_message'] = $error->getMessage();
        return $result;
    }
}

function be_startpartner_gate4_distribution_input(array $input): array
{
    $channel = be_startpartner_gate4_required_text($input['channel'] ?? null, 64, 'channel');
    $targetReference = be_startpartner_gate4_required_text($input['target_reference'] ?? null, 2048, 'target_reference');
    $plannedLocalDate = be_startpartner_gate4_validate_local_date($input['planned_at'] ?? null);
    $status = strtolower(be_startpartner_gate4_required_text($input['status'] ?? null, 16, 'status'));
    if (!in_array($status, ['ready', 'blocked'], true)) {
        throw new InvalidArgumentException('Der Reichweitenbeitrag muss als vereinbart oder als klärungsbedürftig gespeichert werden.');
    }
    $timezone = new DateTimeZone('Europe/Berlin');
    $plannedLocal = new DateTimeImmutable($plannedLocalDate . ' 00:00:00', $timezone);
    $todayLocal = new DateTimeImmutable('today', $timezone);
    if ($status === 'ready' && $plannedLocal < $todayLocal) {
        throw new DomainException('Der vereinbarte Zieltermin für den Reichweitenbeitrag darf nicht in der Vergangenheit liegen.');
    }
    return [
        'channel' => $channel,
        'target_reference' => $targetReference,
        'planned_date_local' => $plannedLocalDate,
        'planned_at_utc' => $plannedLocal->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
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

function be_startpartner_gate4_mark_content_ready(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.content.editorial_ready',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $statement = $pdo->prepare(
                "UPDATE startpartner_pilot_content_links pcl
                 INNER JOIN submissions s ON s.id = pcl.submission_id
                 SET pcl.status = 'editorial_ready', pcl.editorial_ready_at = CURRENT_TIMESTAMP,
                     s.status = 'in_review', s.review_started_at = COALESCE(s.review_started_at, CURRENT_TIMESTAMP)
                 WHERE pcl.id = :id AND pcl.pilot_id = :pilot_id
                   AND pcl.status IN ('draft','editorial_ready')
                   AND s.status IN ('pending_review','in_review')"
            );
            $statement->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            if ($statement->rowCount() < 1) {
                $check = $pdo->prepare(
                    'SELECT status FROM startpartner_pilot_content_links WHERE id = :id AND pilot_id = :pilot_id'
                );
                $check->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
                if ((string)$check->fetchColumn() !== 'editorial_ready') {
                    throw new DomainException('Der Inhalt konnte nicht für den Pilotstart vorbereitet werden.');
                }
            }
            foreach (['first_content_ready', 'editorial_review_ready'] as $itemKey) {
                $item = $pdo->prepare(
                    "UPDATE startpartner_pilot_onboarding_items
                     SET status = 'complete', evidence_text = :evidence_text,
                         evidence_reference = :evidence_reference, operator_reference = :operator,
                         completed_at = CURRENT_TIMESTAMP, revision = revision + 1
                     WHERE pilot_id = :pilot_id AND item_key = :item_key"
                );
                $item->execute([
                    'evidence_text' => 'Der verknüpfte Inhalt ist redaktionell für den Pilotstart vorbereitet.',
                    'evidence_reference' => $contentLinkId,
                    'operator' => $operator,
                    'pilot_id' => (string)$pilot['id'],
                    'item_key' => $itemKey,
                ]);
            }

            $contentQuery = $pdo->prepare(
                'SELECT * FROM startpartner_pilot_content_links WHERE id = :id AND pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
            );
            $contentQuery->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            $content = $contentQuery->fetch(PDO::FETCH_ASSOC);
            if (!is_array($content)) {
                throw new DomainException('Der vorbereitete Pilotinhalt konnte nicht zurückgelesen werden.');
            }
            $measurement = be_startpartner_gate4_auto_measurement_preflight(
                $pdo,
                $pilot,
                $content,
                $operationId
            );

            return [
                'status_reason' => ($measurement['status'] ?? '') === 'ready'
                    ? 'Der erste Inhalt ist für den Pilotstart vorbereitet; die Erfolgsmessung wurde automatisch geprüft.'
                    : 'Der erste Inhalt ist vorbereitet. Die automatische Prüfung der Erfolgsmessung benötigt noch Klärung.',
                'content_link_id' => $contentLinkId,
                'measurement_status' => $measurement['status'] ?? 'blocked',
                'measurement_error' => $measurement['error_message'] ?? null,
            ];
        }
    );
}

function be_startpartner_gate4_set_measurement(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.measurement.set',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $contentLinkId = be_startpartner_gate4_required_text($input['content_link_id'] ?? null, 36, 'content_link_id');
            $status = strtolower(be_startpartner_gate4_required_text($input['status'] ?? null, 16, 'status'));
            if (!in_array($status, ['ready', 'blocked'], true)) {
                throw new InvalidArgumentException('Die Erfolgsmessung muss als eingerichtet oder als klärungsbedürftig gespeichert werden.');
            }
            $link = $pdo->prepare(
                'SELECT * FROM startpartner_pilot_content_links
                 WHERE id = :id AND pilot_id = :pilot_id LIMIT 1 FOR UPDATE'
            );
            $link->execute(['id' => $contentLinkId, 'pilot_id' => (string)$pilot['id']]);
            $content = $link->fetch(PDO::FETCH_ASSOC);
            if (!is_array($content)) {
                throw new DomainException('Der ausgewählte Pilotinhalt wurde nicht gefunden.');
            }
            $evidenceText = be_startpartner_gate4_required_text($input['evidence_text'] ?? null, 5000, 'evidence_text');
            $result = be_startpartner_gate4_persist_measurement_preflight(
                $pdo,
                $pilot,
                $content,
                $status,
                $evidenceText,
                $operator,
                $operationId
            );
            return [
                'status_reason' => $status === 'ready'
                    ? 'Die Erfolgsmessung wurde erneut technisch geprüft und ist eingerichtet.'
                    : 'Für die Erfolgsmessung wurde ein offener Punkt gespeichert.',
                'content_link_id' => $contentLinkId,
                'technical_readback' => $result['technical_readback'] ?? null,
            ];
        }
    );
}

function be_startpartner_gate4_set_distribution(PDO $pdo, string $candidateId, array $input): array
{
    return be_startpartner_gate4_run_operation(
        $pdo,
        $candidateId,
        'gate4.distribution.set',
        $input,
        static function(PDO $pdo, array $candidate, array $pilot, string $operator, string $operationId, array $input): array {
            $distribution = be_startpartner_gate4_distribution_input($input);
            be_startpartner_gate4_supersede_distribution($pdo, (string)$pilot['id'], $operationId);

            $id = be_startpartner_gate4_uuid();
            $statement = $pdo->prepare(
                'INSERT INTO startpartner_pilot_distribution_commitments (
                    id, pilot_id, channel, planned_at, target_reference, status,
                    evidence_text, operator_reference
                 ) VALUES (
                    :id, :pilot_id, :channel, :planned_at, :target_reference, :status,
                    :evidence_text, :operator_reference
                 )'
            );
            $statement->execute([
                'id' => $id,
                'pilot_id' => (string)$pilot['id'],
                'channel' => $distribution['channel'],
                'planned_at' => $distribution['planned_at_utc'],
                'target_reference' => $distribution['target_reference'],
                'status' => $distribution['status'],
                'evidence_text' => $distribution['evidence_text'],
                'operator_reference' => $operator,
            ]);
            $item = $pdo->prepare(
                "UPDATE startpartner_pilot_onboarding_items
                 SET status = :item_status, evidence_text = :evidence_text,
                     evidence_reference = :evidence_reference, operator_reference = :operator,
                     completed_at = CASE WHEN :is_ready = 1 THEN CURRENT_TIMESTAMP ELSE NULL END,
                     revision = revision + 1
                 WHERE pilot_id = :pilot_id AND item_key = 'distribution_ready'"
            );
            $item->execute([
                'item_status' => $distribution['status'] === 'ready' ? 'complete' : 'blocked',
                'evidence_text' => $distribution['evidence_text'],
                'evidence_reference' => $id,
                'operator' => $operator,
                'is_ready' => $distribution['status'] === 'ready' ? 1 : 0,
                'pilot_id' => (string)$pilot['id'],
            ]);
            return [
                'status_reason' => $distribution['status'] === 'ready'
                    ? 'Der Reichweitenbeitrag wurde als mit dem Partner vereinbart gespeichert.'
                    : 'Für den Reichweitenbeitrag wurde ein offener Punkt gespeichert.',
                'distribution_id' => $id,
                'planned_date_local' => $distribution['planned_date_local'],
            ];
        }
    );
}
