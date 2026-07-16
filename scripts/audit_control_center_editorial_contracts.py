#!/usr/bin/env python3
from pathlib import Path
import json
import sys
ROOT=Path(__file__).resolve().parents[1]
errors:list[str]=[]
def read(path:str)->str:
    p=ROOT/path
    if not p.is_file() or not p.read_text(encoding='utf-8').strip():
        errors.append(f'Datei fehlt oder ist leer: {path}')
        return ''
    return p.read_text(encoding='utf-8')
editorial=json.loads(read('data/control_center_editorial_contract.json') or '{}')
decisions=json.loads(read('data/content_ops_decision_classes.json') or '{}')
required_files=[
    'api/control-center/_decision_contract.php','api/control-center/_editorial_contracts.php','api/control-center/_event_review_tasks.php',
    'api/control-center/_event_review_writeback.php','api/control-center/_inbox_decision_writeback.php','api/control-center/_sheet_identity.php',
    'api/control-center/_operations.php','api/control-center/_editorial_feedback.php','tests/fixtures/control_center_editorial_cases.json',
    'tests/control_center_editorial_contracts_test.php','tests/control_center_exception_review_runtime_test.php',
    'docs/steuerzentrale-redaktioneller-entscheidungs-und-lernprozess.md',
]
contents={path:read(path) for path in required_files}
if editorial.get('version')!='control_center_editorial_contract_v2':errors.append('Redaktioneller Vertrag hat keine erwartete Version v2.')
classes=set((decisions.get('decision_classes') or {}).keys())
for section in ('approve_classes','reject_classes'):
    for cls in editorial.get('decisions',{}).get(section,[]):
        if cls not in classes:errors.append(f'decision_class fehlt in kanonischer Taxonomie: {cls}')
if editorial.get('decisions',{}).get('snooze_class') not in classes:errors.append('Snooze-Klasse fehlt in kanonischer Taxonomie.')
for required in ('evidence_states','task_states','time_statuses','finding_groups'):
    if not editorial.get('event_candidate',{}).get(required):errors.append(f'Eventvertrag enthält keine {required}.')
markers={
    'api/control-center/_event_review_tasks.php':['review_tasks','task_revision','time_not_published','visual_asset_id','visual_gap_id','field_evidence'],
    'api/control-center/_event_review_writeback.php':['be_cc_resolve_event_review_task','source_verified','be_cc_event_candidate_review_contract','review_task_resolved'],
    'api/control-center/_inbox_decision_writeback.php':['be_cc_inbox_direct_canonical_plan','Inbox!A:ZZ','expectedSourceFingerprint'],
    'api/control-center/_sheet_identity.php':['be_cc_resolve_inbox_row','be_cc_resolve_audit_row','validated_row_hint','superseded_audit_row'],
    'api/control-center/_decision_contract.php':['be_cc_validate_operator_decision','decision_class','suppress_until'],
    'api/control-center/_operations.php':['be_cc_operation_reservation_decision','payload_hash','replay','conflict'],
    'api/control-center/_editorial_feedback.php':['be_cc_editorial_feedback_observation','be_cc_editorial_feedback_aggregate','permanent_rule_change_allowed'],
    'tests/control_center_editorial_contracts_test.php':['CityArt löst konkretes ready-Asset','Prüfpunkt besitzt Revision','Prüfpunkt besitzt Evidenz'],
    'tests/control_center_exception_review_runtime_test.php':['atomar in einem Batch','konkretes CityArt-Asset','Wiedervorlage wird typisiert'],
}
for path,needles in markers.items():
    text=contents.get(path,'')
    for needle in needles:
        if needle not in text:errors.append(f'Vertragsmarker fehlt in {path}: {needle}')
fixture=json.loads(contents.get('tests/fixtures/control_center_editorial_cases.json') or '{}')
if len(fixture.get('event_cases') or [])<7:errors.append('Fixture deckt zu wenige Eventfälle ab.')
if errors:
    print('=== Control Center Editorial Contract Audit: FAILED ===')
    for error in errors:print('-',error)
    sys.exit(1)
print('=== Control Center Editorial Contract Audit: OK ===')
