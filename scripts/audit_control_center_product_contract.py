#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
html = (ROOT / 'steuerzentrale/index.html').read_text(encoding='utf-8')
js = (ROOT / 'js/control-center.js').read_text(encoding='utf-8')
source_js = (ROOT / 'js/control-center-source-editors.js').read_text(encoding='utf-8')
publication_js = (ROOT / 'js/control-center-publication.js').read_text(encoding='utf-8')
development_js = (ROOT / 'js/control-center-development.js').read_text(encoding='utf-8')
css = (ROOT / 'css/control-center.css').read_text(encoding='utf-8')
sources = (ROOT / 'api/control-center/_sources.php').read_text(encoding='utf-8')
writeback = (ROOT / 'api/control-center/_writeback.php').read_text(encoding='utf-8')
domain = (ROOT / 'api/control-center/_domain.php').read_text(encoding='utf-8')
presentation = (ROOT / 'api/control-center/_presentation.php').read_text(encoding='utf-8')
content_api = (ROOT / 'api/control-center/content.php').read_text(encoding='utf-8')
publication_api = (ROOT / 'api/control-center/publication.php').read_text(encoding='utf-8')
content_history = (ROOT / 'api/control-center/_content_history.php').read_text(encoding='utf-8')
contracts = (ROOT / 'api/control-center/_contracts.php').read_text(encoding='utf-8')
development_api = (ROOT / 'api/control-center/development.php').read_text(encoding='utf-8')
deploy_api = (ROOT / 'api/control-center/deploy.php').read_text(encoding='utf-8')
schema = (ROOT / 'api/control-center/_schema.php').read_text(encoding='utf-8')
manifest = (ROOT / 'data/control_center_repo_workpacks.json').read_text(encoding='utf-8')
errors = []

required_nav = ['Übersicht', 'Prüfen', 'Arbeit', 'Verwaltung', 'Entwicklung']
for label in required_nav:
    if f'<span>{label}</span>' not in html:
        errors.append(f'Navigation fehlt: {label}')

for forbidden in ['<span>Eingang</span>', '<span>Inhalte</span>', '<span>Aufgaben</span>', '<span>Mehr</span>', '<span>Menü</span>', '<span>Bearbeiten</span>']:
    if forbidden in html:
        errors.append(f'Veralteter Haupttab vorhanden: {forbidden}')

if '.cc-nav{' not in css or 'position:fixed' not in css:
    errors.append('Bottom-Navigation ist nicht dauerhaft fixed.')
if 'id="cc-dialog-close" type="button"' not in html:
    errors.append('Dialog-Schließen ist nicht von Pflichtfeldvalidierung entkoppelt.')

combined_js = js + source_js + publication_js + development_js
contracts_ui = {
    'verdichtete Übersicht': 'cc-summary-card',
    'fokussierter Prüfbereich': 'renderReviewDetail',
    'Desktop Queue': 'cc-queue',
    'gemeinsame Neuerfassung': 'openNewWorkMenu',
    'kompakte Arbeitszeilen': 'cc-labor-row',
    'Arbeitslebenszyklus': 'laborLabels',
    'Live-Verwaltung': 'openContentEditor',
    'ehrlicher Speichervorgang': 'Speichern und Aktualisierung starten',
    'öffentliche Feed-Verifikation': 'verifyPublication',
    'Entwicklungsbereich': 'renderDevelopment',
    'Entwicklungstrends': 'content_coverage_delta',
    'technische SEO-Basis': 'Technische SEO-Basis',
    'Header-Menü': 'openHeaderMenu',
    'verständlicher Systemstatus': 'cc-system-list',
}
for label, marker in contracts_ui.items():
    if marker not in combined_js and marker not in css:
        errors.append(f'Produktvertrag fehlt: {label}')

backend_contracts = {
    'Growth-Backlog-Sync': 'be_cc_sync_growth_backlog',
    'Repo-Workpack-Sync': 'be_cc_sync_repo_workpacks',
    'Growth-Backlog-Writeback': 'be_cc_writeback_growth_backlog',
    'Umwandlung ohne Doppelkarte': "'open' => 'open'",
    'Wartegrund-Persistenz': "in_array($action, ['wait', 'block']",
    'Event-Livewriteback': 'be_cc_update_event_fields',
    'atomare Event-Updateplanung': 'be_cc_validate_event_update',
    'Deploy nach Speichern': "'action' => 'deploy'",
    'dauerhafter Änderungsverlauf': 'control_content_changes',
    'öffentliche Wirkung': 'be_cc_verify_public_change',
    'Teilfehler als Arbeit': 'be_cc_upsert_publication_issue',
    'Entwicklungs-Snapshots': 'control_development_snapshots',
    'technische SEO-Prüfung': 'be_cc_technical_seo_metrics',
    'Search-Console-Transparenz': 'search_console_connected',
}
backend = sources + writeback + domain + presentation + content_api + publication_api + content_history + contracts + development_api + deploy_api + schema
for label, marker in backend_contracts.items():
    if marker not in backend:
        errors.append(f'Backendvertrag fehlt: {label}')

if 'no_automatic_doc_scraping' not in manifest:
    errors.append('Repo-Workpack-Manifest dokumentiert keine kuratierte Importgrenze.')
if '/data/events.json' in js:
    errors.append('Verwaltung verwendet direkt den öffentlichen Eventfeed statt der führenden Quelle.')
if 'decision required' in combined_js.lower():
    errors.append('Technischer englischer Status ist sichtbar codiert.')
if 'search_console_connected' not in development_api or 'false' not in development_api:
    errors.append('Nicht angebundene Search-Console-Daten werden nicht transparent markiert.')
if 'Speichern und veröffentlichen' in combined_js:
    errors.append('UI behauptet eine Veröffentlichung, bevor die öffentliche Wirkung bestätigt ist.')

if errors:
    print('=== Control Center Product Contract: FAILED ===')
    for error in errors:
        print('-', error)
    sys.exit(1)

print('=== Control Center Product Contract: OK ===')
