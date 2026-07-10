#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
read = lambda path: (ROOT / path).read_text(encoding='utf-8')
html = read('steuerzentrale/index.html')
js = read('js/control-center.js')
source_js = read('js/control-center-source-editors.js')
publication_js = read('js/control-center-publication.js')
development_js = read('js/control-center-development.js')
integration_js = read('js/control-center-integrations.js')
css = read('css/control-center.css')
sources = read('api/control-center/_sources.php')
sheet_inbox = read('api/control-center/_sheet_inbox_source.php')
content_ops = read('api/control-center/_content_ops.php')
process_chain = read('api/control-center/_process_chain.php')
submission_content = read('api/control-center/_submission_content.php')
writeback = read('api/control-center/_writeback.php')
domain = read('api/control-center/_domain.php')
workflow_contracts = read('api/control-center/_workflow_contracts.php')
presentation = read('api/control-center/_presentation.php')
content_api = read('api/control-center/content.php')
publication_api = read('api/control-center/publication.php')
content_history = read('api/control-center/_content_history.php')
contracts = read('api/control-center/_contracts.php')
github_repo = read('api/control-center/_github_repo.php')
development_api = read('api/control-center/development.php')
overview_api = read('api/control-center/overview.php')
deploy_api = read('api/control-center/deploy.php')
schema = read('api/control-center/_schema.php')
public_events = read('api/events/public.php')
manifest = read('data/control_center_repo_workpacks.json')
errors = []

for label in ['Übersicht', 'Prüfen', 'Arbeit', 'Verwaltung', 'Entwicklung']:
    if f'<span>{label}</span>' not in html:
        errors.append(f'Navigation fehlt: {label}')
for forbidden in ['<span>Eingang</span>', '<span>Inhalte</span>', '<span>Aufgaben</span>', '<span>Mehr</span>', '<span>Menü</span>', '<span>Bearbeiten</span>']:
    if forbidden in html:
        errors.append(f'Veralteter Haupttab vorhanden: {forbidden}')
if '.cc-nav{' not in css or 'position:fixed' not in css:
    errors.append('Bottom-Navigation ist nicht dauerhaft fixed.')
if 'id="cc-dialog-close" type="button"' not in html:
    errors.append('Dialog-Schließen ist nicht von Pflichtfeldvalidierung entkoppelt.')
if 'control-center-integrations.js' not in html:
    errors.append('Gesamtprojekt-Integrationscontroller wird nicht geladen.')

combined_js = js + source_js + publication_js + development_js + integration_js
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
    'Aktivitätseditor': 'ca-save',
    'Entwicklungsbereich': 'renderDevelopment',
    'Suchlaufwirkung': 'Gefundene Kandidaten',
    'Lernwirkungsanzeige': 'Verhinderte Wiederholungen',
    'Prozesskettenanzeige': 'Automatisierte Prozesskette',
    'Funnelanzeige': 'Nutzer-Funnel und SEO-Wirkung',
    'Header-Menü': 'openHeaderMenu',
    'verständlicher Systemstatus': 'cc-system-list',
}
for label, marker in contracts_ui.items():
    if marker not in combined_js and marker not in css:
        errors.append(f'Produktvertrag fehlt: {label}')
if 'Speichern und veröffentlichen' in combined_js:
    errors.append('UI behauptet eine Veröffentlichung, bevor die öffentliche Wirkung bestätigt ist.')

backend_contracts = {
    'führende Sheet-Inbox': 'be_cc_sync_sheet_inbox',
    'echter Inbox-Fallback': "'status' => 'standby'",
    'isolierte Quellsynchronisation': 'be_cc_safe_source_sync',
    'Growth-Backlog-Sync': 'be_cc_sync_growth_backlog',
    'Repo-Workpack-Sync': 'be_cc_sync_repo_workpacks',
    'Growth-Backlog-Writeback': 'be_cc_writeback_growth_backlog',
    'gemeinsamer Workflow-Vertrag': 'be_cc_transition_target',
    'abhängige KI-Prozesskette': 'be_cc_integrated_process_health',
    'Event-Livewriteback': 'be_cc_update_event_fields',
    'Aktivitäts-Repo-Writeback': 'be_cc_update_activity_in_repo',
    'explizites Aktivitäts-Gate': 'BE_ACTIVITY_WRITEBACK_ENABLED',
    'Anbieter-Event-Verwaltung': 'be_cc_update_submission_event',
    'Anbieter-Event-Quelle': 'be_cc_submission_event_items',
    'öffentliche Anbieter-Sichtbarkeit': 'location_public_confirmed=1',
    'Anbieter-Event-Public-Verifikation': "sourceSystem === 'submission_db'",
    'dauerhafter Änderungsverlauf': 'control_content_changes',
    'öffentliche Wirkung': 'be_cc_verify_public_change',
    'Teilfehler als Arbeit': 'be_cc_upsert_publication_issue',
    'Entwicklungs-Snapshots': 'control_development_snapshots',
    'Content-Ops-Wirkung': 'feedback_rule_effectiveness_daily',
    'Prozessstatus': 'be_cc_process_health',
    'Funnelmetriken': 'growth.gsc_rows',
    'Suchlaufmetriken': 'search.weekly.raw_candidates',
    'kombinierte Eventqualität': 'be_cc_sum_quality',
    'Aktivitätsqualität': 'activity_coverage_percent',
    'technische SEO-Prüfung': 'be_cc_technical_seo_metrics',
}
backend = sources + sheet_inbox + content_ops + process_chain + submission_content + writeback + domain + workflow_contracts + presentation + content_api + publication_api + content_history + contracts + github_repo + development_api + overview_api + deploy_api + schema + public_events
for label, marker in backend_contracts.items():
    if marker not in backend:
        errors.append(f'Backendvertrag fehlt: {label}')

if 'no_automatic_doc_scraping' not in manifest:
    errors.append('Repo-Workpack-Manifest dokumentiert keine kuratierte Importgrenze.')
if '/data/events.json' in js:
    errors.append('Verwaltung verwendet direkt den öffentlichen Eventfeed statt der führenden Quelle.')
if 'decision required' in combined_js.lower():
    errors.append('Technischer englischer Status ist sichtbar codiert.')
if "'available' => $enabled" not in github_repo or "'configured' =>" not in github_repo:
    errors.append('Aktivitätseditor unterscheidet Konfiguration und bewusste Freigabe nicht.')
if "type === 'activities'" not in publication_js or '#ca-save' not in publication_js:
    errors.append('Aktivitätsveröffentlichung ist nicht in den gemeinsamen Verifikationscontroller eingebunden.')
for marker, message in [
    ("'address' => $address", 'Öffentliche Anbieter-Events geben die Adresse nicht verifizierbar aus.'),
    ("'source_url' => $sourceUrl", 'Öffentliche Anbieter-Events geben die offizielle Quelle nicht separat aus.'),
    ("'ticket_url' => $ticketUrl", 'Öffentliche Anbieter-Events geben den Ticketlink nicht separat aus.'),
]:
    if marker not in public_events:
        errors.append(message)
if 'Promise.all' not in integration_js or "api('/api/control-center/overview.php')" not in integration_js:
    errors.append('Entwicklung verwendet nicht denselben integrierten Prozessstatus wie der Systemstatus.')

if errors:
    print('=== Control Center Product Contract: FAILED ===')
    for error in errors:
        print('-', error)
    sys.exit(1)
print('=== Control Center Product Contract: OK ===')
