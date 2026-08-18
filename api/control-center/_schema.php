<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/_bootstrap.php';

const BE_CC_REQUIRED_SCHEMA = [
    'control_cases' => [
        'id', 'case_type', 'state', 'priority', 'title', 'source_system',
        'source_reference', 'decision_ready', 'created_at', 'updated_at',
    ],
    'control_case_events' => ['id', 'case_id', 'action', 'actor', 'created_at'],
    'control_content_changes' => [
        'id', 'object_type', 'object_id', 'updates_json', 'publication_state',
        'created_at', 'updated_at',
    ],
    'control_development_snapshots' => ['id', 'metrics_json', 'created_at'],
    'control_operations' => [
        'operation_id', 'case_id', 'action', 'payload_hash', 'status',
        'result_json', 'created_at', 'updated_at',
    ],
    'control_editorial_feedback' => [
        'id', 'case_id', 'final_text', 'decision_class', 'status', 'created_at',
    ],
];

function be_cc_schema_gaps(PDO $pdo): array
{
    $databaseName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseName === '') {
        return ['database' => ['No database selected.']];
    }

    $statement = $pdo->prepare(
        'SELECT TABLE_NAME, COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :schema_name'
    );
    $statement->execute(['schema_name' => $databaseName]);

    $present = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $table = (string)($row['TABLE_NAME'] ?? '');
        $column = (string)($row['COLUMN_NAME'] ?? '');
        if ($table !== '' && $column !== '') {
            $present[$table][$column] = true;
        }
    }

    $gaps = [];
    foreach (BE_CC_REQUIRED_SCHEMA as $table => $columns) {
        if (!isset($present[$table])) {
            $gaps[$table] = ['table missing'];
            continue;
        }
        foreach ($columns as $column) {
            if (!isset($present[$table][$column])) {
                $gaps[$table][] = $column;
            }
        }
    }

    return $gaps;
}

function be_cc_assert_schema_contract(PDO $pdo): void
{
    $gaps = be_cc_schema_gaps($pdo);
    if ($gaps !== []) {
        throw new RuntimeException(
            'CONTROL_CENTER_SCHEMA_MISSING: ' . json_encode(
                $gaps,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            )
        );
    }
}

function be_cc_ensure_schema(): void
{
    be_cc_assert_schema_contract(be_db());
}
