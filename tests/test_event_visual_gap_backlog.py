#!/usr/bin/env python3
import importlib.util
import sys
import types
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
feedback_stub=types.ModuleType('content_ops_visual_feedback')
feedback_stub.load_visual_feedback_contract=lambda:{}
feedback_stub.classify_visual_issue=lambda row,contract=None:{'problem_type':row.get('visual_problem_type','visual_review'),'decision_class':'needs_visual_fix','followup_route':'visual_review','requires_task':True}
sys.modules['content_ops_visual_feedback']=feedback_stub
stub=types.ModuleType('event_visual_motifs')
stub.infer_event_visual_fit=lambda **kwargs:{}
stub.load_event_visual_pool=lambda:{}
sys.modules['event_visual_motifs']=stub
path=ROOT/'scripts'/'build-event-visual-gap-backlog.py'
spec=importlib.util.spec_from_file_location('visual_gap_builder',path)
module=importlib.util.module_from_spec(spec); assert spec.loader; spec.loader.exec_module(module)
pool={'pools':{'art_exhibition_gallery':{'images':[{'id':'motif-gap-art-market-01','src':'/assets/event-visuals/motif-gap-art-market-01.webp','status':'ready','visual_motif':'art_market'}]}}}
module.load_event_visual_pool=lambda:pool
module.infer_event_visual_fit=lambda **kwargs:{'visual_key':'art_exhibition_gallery','visual_motif':'art_market','visual_asset_status':'ok'}
assert module.FIELDS[:10]==['status','priority','event_title','event_date','visual_key','visual_motif','problem','recommended_action','source_url','notes']
item={'source':'fixture','id':'cityart-2026','title':'Kunstmarkt CityArt','date':'2026-08-30','description':'','category':'','location':'','visual_key':'art_exhibition_gallery','visual_motif':'art_market','visual_asset_id':'','visual_gap_id':'visual-gap-existing','url':'https://example.org'}
rows=module.build_rows([item])
assert len(rows)==1
assert rows[0]['gap_id']=='visual-gap-existing'
assert rows[0]['status']=='candidate_ready'
assert rows[0]['candidate_asset_id']=='motif-gap-art-market-01'
assert rows[0]['decision_class']=='needs_visual_fix'
assert rows[0]['followup_route']=='visual_review'
rows2=module.build_rows([item,item])
assert len(rows2)==1, 'gap_id must be idempotent'
print('=== Event Visual Gap Backlog: OK ===')
