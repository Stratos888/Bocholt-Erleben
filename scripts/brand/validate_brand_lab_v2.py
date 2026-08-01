#!/usr/bin/env python3
from pathlib import Path
import json, re, struct, sys

ROOT=Path(__file__).resolve().parents[2]
LAB=ROOT/'docs/brand/lab-v2'
C=LAB/'candidates/open-impulse'
SIZES=(16,24,32,48,64)
files=[
 ROOT/'docs/brand/AI_BRAND_WORKPACK_B.md',ROOT/'docs/brand/AI_BRAND_REFINEMENT_REPORT.md',
 LAB/'README.md',LAB/'SOURCE.md',LAB/'PROVENANCE.json',LAB/'index.html',
 C/'wordmark.svg',C/'wordmark-mono.svg',C/'compact.svg',C/'compact-mono.svg',
 C/'small-mark.svg',C/'small-mark-mono.svg',C/'small-mark-inverse.svg',
 C/'app-icon.svg',C/'app-icon-mono.svg',
 *[C/'sizes'/f'{n}.png' for n in SIZES],
]
errors=[f'missing: {p.relative_to(ROOT)}' for p in files if not p.is_file()]
if not errors:
 html=(LAB/'index.html').read_text()
 for marker in ('<meta name="robots" content="noindex,nofollow">','Offener Impuls','91/100','Eine Richtung hat das interne Gate erreicht.','Echte Rastergrößen','App-Masken','Product-Owner-Gate'):
  if marker not in html: errors.append(f'missing lab marker: {marker}')
 if html.count('class="candidate-card"')!=1: errors.append('lab must present exactly one candidate')
 if re.search(r'(?:src|href|url\()[^>\n]*https?://',html,re.I): errors.append('lab contains external dependency')
 refs=['wordmark.svg','wordmark-mono.svg','compact.svg','compact-mono.svg','small-mark.svg','small-mark-mono.svg','small-mark-inverse.svg',*[f'sizes/{n}.png' for n in SIZES]]
 for ref in refs:
  if f'candidates/open-impulse/{ref}' not in html: errors.append(f'lab does not reference: {ref}')
 for p in C.glob('*.svg'):
  text=p.read_text(); rel=p.relative_to(ROOT)
  if '<svg' not in text or 'viewBox=' not in text or '<path' not in text: errors.append(f'invalid SVG: {rel}')
  if '<text' in text.lower(): errors.append(f'SVG contains text: {rel}')
  if re.search(r'(?:href|src)=["\']https?://|filter=|lineargradient|radialgradient',text,re.I): errors.append(f'SVG has external or forbidden effect: {rel}')
 for n in SIZES:
  p=C/'sizes'/f'{n}.png'; data=p.read_bytes(); rel=p.relative_to(ROOT)
  if data[:8]!=b'\x89PNG\r\n\x1a\n' or len(data)<24: errors.append(f'invalid PNG: {rel}'); continue
  if struct.unpack('>II',data[16:24])!=(n,n): errors.append(f'wrong PNG size: {rel}')
 try: provenance=json.loads((LAB/'PROVENANCE.json').read_text())
 except json.JSONDecodeError as exc: errors.append(f'invalid provenance JSON: {exc}'); provenance={}
 flags={'candidate_id':'open-impulse','score':91,'external_designer_used':False,'image_generation_used_for_final_assets':False,'automatic_tracing_used':False,'assets_are_path_based':True,'legal_clearance_claimed':False}
 for key,value in flags.items():
  if provenance.get(key)!=value: errors.append(f'provenance {key} must equal {value!r}')
 score=provenance.get('scorecard',{})
 if sum(score.values())!=91: errors.append('scorecard must sum to 91')
 work=(ROOT/'docs/brand/AI_BRAND_WORKPACK_B.md').read_text(); report=(ROOT/'docs/brand/AI_BRAND_REFINEMENT_REPORT.md').read_text()
 for marker in ('Offener Impuls — 91/100','eine Grundrichtung qualifiziert'):
  if marker not in work: errors.append(f'workpack missing marker: {marker}')
 for marker in ('B2 – Offener Impuls','**Gesamt** | **91/100**','neun Prinzipien geprüft'):
  if marker not in report: errors.append(f'report missing marker: {marker}')
if errors:
 print('Brand Lab v2 validation: FAIL',file=sys.stderr)
 for e in errors: print(f'- {e}',file=sys.stderr)
 raise SystemExit(1)
print('Brand Lab v2 validation: OK (1 qualified direction, 91/100, isolated path assets, 16–64 px rasters)')
