#!/usr/bin/env python3
from pathlib import Path
import json
import re
import sys
import struct

ROOT = Path(__file__).resolve().parents[2]
LAB = ROOT / 'docs/brand/lab'

required = [
    ROOT / 'docs/brand/AI_BRAND_WORKPACK_A.md',
    ROOT / 'docs/brand/AI_BRAND_EXPLORATION_REPORT.md',
    LAB / 'index.html',
    LAB / 'PROVENANCE.json',
]
for candidate in ('a', 'b'):
    required += [
        LAB / f'candidates/{candidate}/lockup.svg',
        LAB / f'candidates/{candidate}/lockup-mono.svg',
    ]
    for size in (16, 24, 32, 48):
        required.append(LAB / f'candidates/{candidate}/sizes/{size}.png')
required += [
    LAB / 'candidates/a/icon.svg',
    LAB / 'candidates/a/icon-mono.svg',
    LAB / 'candidates/a/icon-inverse.svg',
    LAB / 'candidates/b/app-icon.svg',
    LAB / 'candidates/b/app-icon-mono.svg',
    LAB / 'candidates/b/small-mark.svg',
    LAB / 'candidates/b/small-mark-mono.svg',
]

errors = []
for path in required:
    if not path.is_file():
        errors.append(f'missing: {path.relative_to(ROOT)}')

if not errors:
    html = (LAB / 'index.html').read_text(encoding='utf-8')
    if '<meta name="robots" content="noindex,nofollow">' not in html:
        errors.append('lab must be noindex,nofollow')
    if html.count('<section class="candidate ') != 2:
        errors.append('lab must contain exactly two candidates')
    for marker in (
        'Offener Takt',
        'Direktwort',
        '81/100',
        '83/100',
        'Echte Rastergrößen',
        'App-Masken',
    ):
        if marker not in html:
            errors.append(f'missing lab marker: {marker}')

    external_patterns = (
        r'<script[^>]+src=',
        r'<link[^>]+href=["\']https?://',
        r'<img[^>]+src=["\']https?://',
    )
    for pattern in external_patterns:
        if re.search(pattern, html, re.IGNORECASE):
            errors.append(f'external dependency: {pattern}')

    for candidate in ('a', 'b'):
        for size in (16, 24, 32, 48):
            path = LAB / f'candidates/{candidate}/sizes/{size}.png'
            data = path.read_bytes()
            if data[:8] != b"\x89PNG\r\n\x1a\n" or len(data) < 24:
                errors.append(f'invalid PNG: {path.relative_to(ROOT)}')
                continue
            width, height = struct.unpack('>II', data[16:24])
            if (width, height) != (size, size):
                errors.append(
                    f'wrong PNG size: {path.relative_to(ROOT)}={(width, height)}'
                )

    for path in LAB.rglob('*.svg'):
        text = path.read_text(encoding='utf-8')
        if '<svg' not in text or 'viewBox=' not in text:
            errors.append(f'invalid SVG shell: {path.relative_to(ROOT)}')

    provenance = json.loads((LAB / 'PROVENANCE.json').read_text(encoding='utf-8'))
    if provenance.get('external_designer_used') is not False:
        errors.append('external designer flag must be false')
    if provenance.get('image_generation_used_for_final_assets') is not False:
        errors.append('final assets must not use image generation')

if errors:
    print('Brand Lab validation: FAIL', file=sys.stderr)
    for error in errors:
        print(f'- {error}', file=sys.stderr)
    raise SystemExit(1)

print('Brand Lab validation: OK (2 candidates, isolated assets, vector masters, true raster sizes)')
