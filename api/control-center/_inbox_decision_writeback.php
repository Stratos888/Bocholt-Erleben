<?php
declare(strict_types=1);

/**
 * Direct, verified status writeback for Inbox decisions that do not require
 * the legacy Apps Script publication workflow.
 */

function be_cc_inbox_direct_updates(array $expected): array
{
    return [
        'status' => trim((string)($expected['status'] ?? '')),
        'ablehnungsgrund' => trim((string)($expected['reason'] ?? '')),
    ];
}

function be_cc_writeback_inbox_decision_direct(array $case, string $action, array $decision): array
{
    if (!in_array($action, ['reject', 'snooze'], true)) {
        throw new InvalidArgumentException('Direkter Inbox-Writeback unterstützt nur Ablehnen und Zurückstellen.');
    }

    $source = be_cc_editorial_payload($case);
    $table = be_cc_sheet_table('Inbox!A:AZ');
    $resolution = be_cc_resolve_inbox_row($table['rows'], be_cc_inbox_identity($source));
    if (($resolution['status'] ?? '') !== 'resolved') {
        throw new DomainException((string)($resolution['message'] ?? 'Inbox-Datensatz ist nicht eindeutig.'));
    }

    $expected = be_cc_inbox_expected_writeback($action, $decision);
    $updates = be_cc_inbox_direct_updates($expected);
    if ($updates['status'] === '') {
        throw new RuntimeException('Kanonischer Inbox-Status fehlt.');
    }

    be_cc_update_sheet_row_by_header(
        'Inbox',
        (int)$table['header_row'],
        (int)$resolution['row_number'],
        $updates
    );

    $verification = be_cc_verify_inbox_writeback($source, $expected);
    return [
        'transport' => 'google_sheets_direct',
        'resolution' => $resolution,
        'written_fields' => array_keys($updates),
        'expected' => $expected,
        'verification' => $verification,
    ];
}
