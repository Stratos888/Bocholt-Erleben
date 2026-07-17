<?php
declare(strict_types=1);

require_once __DIR__ . '/_sources.php';
require_once __DIR__ . '/_editorial_contracts.php';
require_once __DIR__ . '/_inbox_tab.php';
require_once __DIR__ . '/_inbox_schema.php';

function be_cc_sync_sheet_inbox(): array
{
    $tab = be_cc_inbox_tab_name();
    $response = be_google_sheets_values_get(be_cc_inbox_range('A:ZZ'));
    $values = is_array($response['values'] ?? null) ? $response['values'] : [];
    if (count($values) < 2) return ['seen'=>0,'upserted'=>0,'reconciled'=>0,'source'=>'sheet','tab'=>$tab];

    // Fail closed before any source case is updated. A partially valid header
    // must never turn structural Sheet data into misleading editorial tasks.
    $table = be_cc_inbox_table_from_values($values, $tab);
    $index = (array)$table['index'];
    $cell = static function(array $row, array $names) use ($index): string {
        foreach ($names as $name) {
            $position = $index[be_cc_unicode_lower($name)] ?? null;
            if (!is_int($position)) continue;
            $value = trim((string)(($row['_raw'] ?? [])[$position] ?? ''));
            if ($value !== '') return $value;
        }
        return '';
    };

    $seen = $upserted = $reconciled = 0;
    foreach ((array)$table['rows'] as $row) {
        if (!is_array($row)) continue;
        $seen++;
        $rowNumber = (int)($row['row_number'] ?? 0);
        $status = be_cc_source_state_token($cell($row, ['status']) ?: 'review');
        $title = $cell($row, ['title']);
        $reference = $cell($row, ['id_suggestion','id','event_id','source_url','url']);
        if ($reference === '') $reference = 'sheet-row-' . (string)$rowNumber;
        $terminal = be_cc_source_terminal_target('inbox_feed', $status);
        if ($terminal !== null) {
            $reconciled += be_cc_reconcile_source_case(be_db(), 'inbox_feed', $reference, $terminal, [
                'source_status'=>$status,'sheet_row'=>$rowNumber,'sheet_tab'=>$tab,
            ]);
            continue;
        }
        if (!in_array($status, ['review','open','new','pending','später prüfen','spaeter pruefen','snoozed'], true) || $title === '') continue;

        $payload = $row;
        unset($payload['_raw']);
        $payload['sheet_tab'] = $tab;
        $review = be_cc_event_candidate_review_contract($payload);
        $openCount = count((array)($review['decision_gate']['blockers'] ?? []));
        be_cc_upsert_source_case([
            'type'=>'intake','state'=>'decision_required','priority'=>'normal','title'=>$title,
            'reason'=>$cell($row, ['description','notes']) ?: 'Neuer Kandidat aus dem führenden Inbox-Tab wartet auf Prüfung.',
            'next_action'=>$openCount > 0 ? ($openCount === 1 ? 'Einen offenen Prüfpunkt klären.' : $openCount . ' offene Prüfpunkte klären.') : 'Kompakte Gesamtfassung prüfen und übernehmen.',
            'object_type'=>'event_candidate','object_id'=>$reference,'object_title'=>$title,
            'source_system'=>'inbox_feed','source_reference'=>$reference,
            'source_payload'=>$payload,'decision_ready'=>!empty($review['decision_gate']['ready']),
        ]);
        $upserted++;

        if (in_array($status, ['später prüfen','spaeter pruefen','snoozed'], true)) {
            $until = $cell($row, ['next_review_at','next_check_at','snoozed_until','recheck_at']);
            $reconciled += be_cc_reconcile_source_snooze(be_db(), 'inbox_feed', $reference, $until, [
                'source_status'=>$status,'sheet_row'=>$rowNumber,'sheet_tab'=>$tab,
            ]);
        }
    }

    return [
        'seen'=>$seen,'upserted'=>$upserted,'reconciled'=>$reconciled,
        'source'=>'sheet','tab'=>$tab,'schema'=>'valid','header_row'=>(int)$table['header_row'],
    ];
}
