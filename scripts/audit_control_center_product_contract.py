#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
html = (ROOT / 'steuerzentrale/index.html').read_text(encoding='utf-8')
js = (ROOT / 'js/control-center.js').read_text(encoding='utf-8')
css = (ROOT / 'css/control-center.css').read_text(encoding='utf-8')
sources = (ROOT / 'api/control-center/_sources.php').read_text(encoding='utf-8')
writeback = (ROOT / 'api/control-center/_writeback.php').read_text(encoding='utf-8')
domain = (ROOT / 'api/control-center/_domain.php').read_text(encoding='utf-8')
errors = []

required_nav = ['Übersicht', 'Bearbeiten', 'Arbeit', 'Verwaltung', 'Menü']
for label in required_nav:
    if f'<span>{label}</span>' not in html:
        errors.append(f'Navigation fehlt: {label}')

for forbidden in ['<span>Eingang</span>', '<span>Inhalte</span>', '<span>Aufgaben</span>', '<span>Mehr</span>']:
    if forbidden in html:
        errors.append(f'Veralteter Haupttab vorhanden: {forbidden}')

if '.cc-nav{' not in css or 'position:fixed' not in css:
    errors.append('Bottom-Navigation ist nicht dauerhaft fixed.')
if '@media(min-width:760px)' in css and '.cc-nav{position:sticky' in css:
    errors.append('Bottom-Navigation wird auf breiten Viewports sticky.')

contracts = {
    'verdichtete Übersicht': 'cc-summary-card',
    'fokussierter Bearbeitungsbereich': 'cc-work-detail',
    'Desktop Queue': 'cc-queue',
    'manuelle Aufgabe': "openCreateDialog('task')",
    'Ideenerfassung': "openCreateDialog('idea')",
    'Backlog-Erfassung': 'openBacklogCreateDialog',
    'Arbeitslebenszyklus': 'laborModes' if 'laborModes' in js else 'laborLabels',
    'Verwaltungs-API': '/api/control-center/content.php',
    'Korrekturformular': 'cc-save-correction',
    'deutscher Präsentationsvertrag': 'display_status',
}
for label, marker in contracts.items():
    if marker not in js:
        errors.append(f'Produktvertrag fehlt: {label}')

backend_contracts = {
    'Growth-Backlog-Sync': 'be_cc_sync_growth_backlog',
    'Growth-Backlog-Quelle': "'source_system' => 'growth_backlog'",
    'Growth-Backlog-Writeback': 'be_cc_writeback_growth_backlog',
    'Umwandlung ohne Doppelkarte': "'open' => 'open'",
}
for label, marker in backend_contracts.items():
    haystack = sources + writeback + domain
    if marker not in haystack:
        errors.append(f'Backendvertrag fehlt: {label}')

if '/data/events.json' in js:
    errors.append('Verwaltung verwendet nicht vorhandenen statischen Eventpfad.')
if 'decision required' in js.lower():
    errors.append('Technischer englischer Status ist sichtbar codiert.')
if 'JSON.stringify(sync)' in js and '<summary>Technische Details</summary>' not in js:
    errors.append('Systemstatus zeigt Rohdaten ohne eingeklappte technische Details.')

if errors:
    print('=== Control Center Product Contract: FAILED ===')
    for error in errors:
        print('-', error)
    sys.exit(1)

print('=== Control Center Product Contract: OK ===')
