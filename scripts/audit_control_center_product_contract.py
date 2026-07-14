#!/usr/bin/env python3
from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
read = lambda path: (ROOT / path).read_text(encoding='utf-8')
html = read('steuerzentrale/index.html')
environment_js = read('js/control-center-environment.js')
js = read('js/control-center.js')
source_js = read('js/control-center-source-editors.js')
bridge_js = read('js/control-center-final-bridge.js')
seo_embed_js = read('js/control-center-seo-embed.js')
stability_js = read('js/control-center-stability.js')
publication_js = read('js/control-center-publication.js')
development_js = read('js/control-center-development.js')
integration_js = read('js/control-center-integrations.js')
style_entry = read('css/style.css')
css = read('css/control-center.css') + read('css/control-center-final.css')
auth = read('api/control-center/auth.php')
sources = read('api/control-center/_sources.php')
content_source = read('api/control-center/_content_source.php')
sheet_inbox = read('api/control-center/_sheet_inbox_source.php')
content_ops = read('api/control-center/_content_ops.php')
process_chain = read('api/control-center/_process_chain.php')
submission_content = read('api/control-center/_submission_content.php')
writeback = read('api/control-center/_writeback.php')
domain = read('api/control-center/_domain.php')
workflow_contracts = read('api/control-center/_workflow_contracts.php')
presentation = read('api/control-center/_presentation.php')
content_api = read('api/control-center/content.php')
case_api = read('api/control-center/case.php')
cases_api = read('api/control-center/cases.php')
action_api = read('api/control-center/action.php')
publication_api = read('api/control-center/publication.php')
content_history = read('api/control-center/_content_history.php')
contracts = read('api/control-center/_contracts.php')
github_repo = read('api/control-center/_github_repo.php')
development_api = read('api/control-center/development.php')
overview_api = read('api/control-center/overview.php')
deploy_api = read('api/control-center/deploy.php')
schema = read('api/control-center/_schema.php')
public_events = read('api/events/public.php')
seo_dashboard = read('intern/seo-dashboard/index.php')
manifest = read('data/control_center_repo_workpacks.json')
errors = []

for label in ['Übersicht', 'Prüfen', 'Backlog', 'Verwaltung', 'Entwicklung']:
    if f'<span>{label}</span>' not in html:
        errors.append(f'Navigation fehlt: {label}')
for forbidden in ['<span>Arbeit</span>', '<span>Eingang</span>', '<span>Aufgaben</span>', '<span>Mehr</span>']:
    if forbidden in html:
        errors.append(f'Veralteter Haupttab vorhanden: {forbidden}')
if 'control-center-mobile-feedback.js' in html:
    errors.append('Veralteter UI-Überlagerungscontroller wird weiterhin geladen.')
if '../css/style.css?v=2026-06-22-css-governance-v1' not in html:
    errors.append('Steuerzentrale lädt den CSS-Entry-Point nicht umgebungsrelativ.')
if './control-center-final.css?v=2026-06-22-css-governance-v1' not in style_entry:
    errors.append('Finale responsive Steuerzentralen-CSS fehlt in der Governance-Importkette.')
for asset in ['control-center-environment.js','control-center-final-bridge.js','control-center-seo-embed.js','control-center-stability.js']:
    if asset not in html:
        errors.append(f'Erforderlicher Integrationscontroller fehlt: {asset}')
if 'id="cc-dialog-close" type="button"' not in html:
    errors.append('Dialog-Schließen ist nicht von Pflichtfeldvalidierung entkoppelt.')

combined_js = environment_js + js + source_js + bridge_js + seo_embed_js + stability_js + publication_js + development_js + integration_js
ui_contracts = {
    'dynamische Prüfkategorien': 'visibleReviewGroups',
    'mobiles Prüfdropdown': 'cc-review-select',
    'Systemfälle in Prüfen': "item.type === 'task'",
    'reines Backlog': 'renderBacklog',
    'direkte Backlog-Neuerfassung': 'openBacklogDialog',
    'Backlog-Editor-Brücke': 'data-backlog-action="edit_source"',
    'getrennte Verwaltungsquellen': 'Promise.allSettled',
    'Verwaltungs-Timeout': 'timeoutMs:15000',
    'kompakte Verwaltungskarten': 'cc-manage-meta',
    'Projektstatus-Umschalter': 'Projektstatus',
    'SEO-Umschalter': 'SEO & Reichweite',
    'bestehendes SEO-Dashboard eingebettet': '/intern/seo-dashboard/',
    'SEO-Embed ohne doppelte Abmeldung': '.logout{display:none',
    'SEO-Embed ohne verschachteltes Scrollen': 'ResizeObserver',
    'SEO-Staging-Isolation': 'mapDocumentUrl',
    'wahrheitsgemäßer Betreiberstatus': 'operator_status',
    'Baustein-Wirkungsansicht': 'Automatisierte Verbesserung',
    'Baustein-Trendstatus': 'cc-component-trend',
    'aufklappbare Wirkungsdetails': 'Messwerte anzeigen',
    'Qualitätsvergleich': 'cc-copy-compare',
    'direkte Vorschlagsübernahme': 'Vorschlag übernehmen',
    'umgebungsabhängige Pfadauflösung': 'beControlCenterPath',
}
for label, marker in ui_contracts.items():
    if marker not in combined_js and marker not in css and marker not in development_api:
        errors.append(f'Produktvertrag fehlt: {label}')
for forbidden in ['Als Nächstes', 'Wartet', 'Blockiert', 'Ideen', 'Archiv']:
    if forbidden in js:
        errors.append(f'Veralteter sichtbarer Arbeitsfilter vorhanden: {forbidden}')
if 'Speichern und veröffentlichen' in combined_js:
    errors.append('UI behauptet eine Veröffentlichung vor öffentlicher Bestätigung.')
if 'data-review-action="details"' in js:
    errors.append('Prüfen enthält weiterhin redundante Details-Aktion.')
if 'SEO & Projektwirkung' in combined_js:
    errors.append('Altes nachgebautes SEO-Minidashboard ist weiterhin aktiv.')

security_contracts = {
    'gemeinsame SEO-Session': 'BE_INTERNAL_SEO_DASHBOARD',
    'SEO-Freigabemarker': 'be_internal_seo_dashboard_unlocked',
    'Session-Fixation-Schutz': 'session_regenerate_id',
    'HttpOnly-Cookie': "'httponly' => true",
    'SameSite-Cookie': "'samesite' => 'Lax'",
    'kein Passwort in iframe-URL': 'src="/intern/seo-dashboard/"',
}
for label, marker in security_contracts.items():
    if marker not in auth + js:
        errors.append(f'Sicherheitsvertrag fehlt: {label}')

if 'be_cc_event_is_current_or_future' not in content_source or 'if (!be_cc_event_is_current_or_future($date, $endDate)) continue;' not in content_source:
    errors.append('Verwaltung filtert vergangene redaktionelle Events nicht aus.')
if 'be_cc_event_is_current_or_future($date, $endDate)' not in development_api:
    errors.append('Entwicklungsmetriken verwenden nicht dieselbe aktive Eventbasis wie die Verwaltung.')
if "'scope_label'=>'Aktive Eventbasis'" not in development_api:
    errors.append('Content-Vollständigkeit benennt ihren aktiven Scope nicht eindeutig.')
if "new DateTimeImmutable('-7 days')" not in development_api:
    errors.append('Bausteintrends verwenden keinen belastbaren Sieben-Tage-Vergleich.')
for marker in ['be_cc_component_trend', "'component_improvement'", "'learning_recurrences'", "'search_selected_rate'", "'growth_gsc_rows'"]:
    if marker not in development_api:
        errors.append(f'Baustein-Wirkungsvertrag fehlt: {marker}')
if "count($evidence) >= 2" not in development_api or "'insufficient'" not in development_api:
    errors.append('Bausteintrends verhindern keine Scheingenauigkeit bei zu kleiner Datenbasis.')
if 'be_cc_content_audit_row' not in writeback:
    errors.append('Qualitätsaktionen verwenden keine stabile Audit-Zeilenauflösung.')
if 'suggested_description' not in case_api or 'current_description' not in case_api:
    errors.append('Qualitätsfälle liefern keinen aktuellen Text und Vorschlag.')
if 'be_cc_presented_cases' not in cases_api or "source_system'] ?? '') !== 'growth_backlog'" not in cases_api:
    errors.append('Backlog ist nicht auf die kanonische Growth-Backlog-Quelle begrenzt.')
if 'be_cc_case_priority_rank' not in cases_api:
    errors.append('Backlog wird nicht stabil nach Priorität sortiert.')
if "source_system='repo_workpack'" not in overview_api or "'repo_workpacks'" in overview_api:
    errors.append('Repo-Workpacks werden weiterhin als operativer Backlog synchronisiert.')
if "be_cc_action('recheck', 'Erneut prüfen')" not in presentation:
    errors.append('Systemfälle besitzen keine fachliche Neuprüfung.')
if 'be_cc_recheck_source_case' not in action_api or "be_cc_apply_action($caseId, 'complete'" not in action_api:
    errors.append('Systemfälle werden nicht erst nach belegter Neuprüfung geschlossen.')
if 'BE_INTERNAL_SEO_DASHBOARD' not in seo_dashboard:
    errors.append('Bestehendes SEO-Dashboard verwendet nicht die erwartete Session.')

backend_contracts = {
    'führende Sheet-Inbox': 'be_cc_sync_sheet_inbox',
    'echter Inbox-Fallback': "'status' => 'standby'",
    'isolierte Quellsynchronisation': 'be_cc_safe_source_sync',
    'Growth-Backlog-Sync': 'be_cc_sync_growth_backlog',
    'Growth-Backlog-Writeback': 'be_cc_writeback_growth_backlog',
    'gemeinsamer Workflow-Vertrag': 'be_cc_transition_target',
    'abhängige KI-Prozesskette': 'be_cc_integrated_process_health',
    'Event-Livewriteback': 'be_cc_update_event_fields',
    'getrennte Eventlistenquellen': 'be_cc_event_items_by_source',
    'Anbieter-Event-Verwaltung': 'be_cc_update_submission_event',
    'dauerhafter Änderungsverlauf': 'control_content_changes',
    'öffentliche Wirkung': 'be_cc_verify_public_change',
    'Content-Ops-Wirkung': 'feedback_rule_effectiveness_daily',
    'Funnelmetriken': 'growth.gsc_rows',
    'Suchlaufmetriken': 'search.weekly.raw_candidates',
    'technische SEO-Prüfung': 'be_cc_technical_seo_metrics',
}
backend = sources + content_source + sheet_inbox + content_ops + process_chain + submission_content + writeback + domain + workflow_contracts + presentation + content_api + case_api + cases_api + action_api + publication_api + content_history + contracts + github_repo + development_api + overview_api + deploy_api + schema + public_events
for label, marker in backend_contracts.items():
    if marker not in backend:
        errors.append(f'Backendvertrag fehlt: {label}')

if 'no_automatic_doc_scraping' not in manifest:
    errors.append('Repo-Workpack-Manifest dokumentiert keine kuratierte Importgrenze.')
if '/data/events.json' in js:
    errors.append('Verwaltung verwendet direkt den öffentlichen Eventfeed.')
if "'available' => $enabled" not in github_repo or "'configured' =>" not in github_repo:
    errors.append('Aktivitätseditor unterscheidet Konfiguration und Freigabe nicht.')
for marker, message in [
    ("'address' => $address", 'Öffentliche Anbieter-Events geben die Adresse nicht aus.'),
    ("'source_url' => $sourceUrl", 'Öffentliche Anbieter-Events geben die Quelle nicht separat aus.'),
    ("'ticket_url' => $ticketUrl", 'Öffentliche Anbieter-Events geben den Ticketlink nicht separat aus.'),
]:
    if marker not in public_events:
        errors.append(message)

if errors:
    print('=== Control Center Product Contract: FAILED ===')
    for error in errors:
        print('-', error)
    sys.exit(1)
print('=== Control Center Product Contract: OK ===')
