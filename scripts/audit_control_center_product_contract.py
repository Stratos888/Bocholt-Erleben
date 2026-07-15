#!/usr/bin/env python3
from pathlib import Path
import sys
ROOT=Path(__file__).resolve().parents[1]
def read(path): return (ROOT/path).read_text(encoding='utf-8')
def require(text,marker,message,errors):
    if marker not in text: errors.append(message)
errors=[]
html=read('steuerzentrale/index.html')
loader=read('js/control-center.js')
modules='\n'.join(read(f'js/control-center/{name}.js') for name in ['shared','app','review','review-render','review-actions','manage','manage-render','manage-actions','development'])
environment=read('js/control-center-environment.js')
seo_embed=read('js/control-center-seo-embed.js')
style=read('css/style.css')
css=read('css/control-center.css')+read('css/control-center-final.css')+read('css/control-center-editorial.css')
auth=read('api/control-center/auth.php')
presentation=read('api/control-center/_presentation.php')
case_api=read('api/control-center/case.php')
action=read('api/control-center/action.php')
schema=read('api/control-center/_schema.php')
editorial_runtime=read('api/control-center/_editorial_runtime.php')
editorial_contracts=read('api/control-center/_editorial_contracts.php')
decision_contract=read('api/control-center/_decision_contract.php')
sheet_identity=read('api/control-center/_sheet_identity.php')
operations=read('api/control-center/_operations.php')
feedback=read('api/control-center/_editorial_feedback.php')
overview=read('api/control-center/overview.php')
cases=read('api/control-center/cases.php')
content_source=read('api/control-center/_content_source.php')
content_api=read('api/control-center/content.php')
history=read('api/control-center/_content_history.php')
publication=read('api/control-center/publication.php')
development=read('api/control-center/development.php')
sources=read('api/control-center/_sources.php')
writeback=read('api/control-center/_writeback.php')
process_chain=read('api/control-center/_process_chain.php')
content_ops=read('api/control-center/_content_ops.php')
github_repo=read('api/control-center/_github_repo.php')
public_events=read('api/events/public.php')
seo_dashboard=read('intern/seo-dashboard/index.php')
manifest=read('data/control_center_repo_workpacks.json')
for label in ['Übersicht','Prüfen','Backlog','Verwaltung','Entwicklung']:
    require(html,f'<span>{label}</span>',f'Navigation fehlt: {label}',errors)
for old in ['<span>Arbeit</span>','<span>Eingang</span>','<span>Aufgaben</span>','<span>Mehr</span>']:
    if old in html: errors.append(f'Veralteter Haupttab vorhanden: {old}')
for asset in ['control-center-environment.js','control-center.js','control-center-seo-embed.js']:
    require(html,asset,f'Erforderlicher Controller fehlt: {asset}',errors)
for overlay in ['control-center-source-editors.js','control-center-final-bridge.js','control-center-stability.js','control-center-publication.js','control-center-development.js','control-center-integrations.js']:
    if overlay in html: errors.append(f'Überlagerungscontroller wird geladen: {overlay}')
require(loader,'control-center/app.js','Core-Modul-Loader fehlt.',errors)
require(style,'control-center-editorial.css','Redaktionelle CSS-Governance fehlt.',errors)
for label,marker in {
    'vollständige Eventkarte':'cc-fact-grid','Entscheidungsgate':'decision_gate','Bearbeiten und übernehmen':'edit_and_approve',
    'typisierte Ablehnung':'Ablehnungsgrund auswählen','stabile operation_id':'operation_id','konfliktfester Entwurf':'be_cc_draft:',
    'Backlog-Quellenstatus':'Erfolgreich geladen ·','getrennte Verwaltungsquellen':'Promise.allSettled',
    'Verwaltungsdetails':'data-manage-details','exakter Live-Link':'Live öffnen',
    'verständlicher Publikationsstatus':'Öffentliche Wirkung wird geprüft · Versuch',
    'Projektstatus':'operator_status','Wirkungsansicht':'Automatisierte Verbesserung','SEO-Embed':"appPath('/intern/seo-dashboard/?embed=1')",
}.items(): require(modules+css,marker,f'Frontendvertrag fehlt: {label}',errors)
if 'new MutationObserver(' in modules: errors.append('Fachmodule verwenden weiterhin DOM-Überlagerungen.')
if '/data/events.json' in modules: errors.append('Verwaltung liest direkt den öffentlichen Eventfeed.')
if 'Speichern und veröffentlichen' in modules: errors.append('UI behauptet Veröffentlichung vor Bestätigung.')
for label,marker,text in [
    ('Session-Fixation','session_regenerate_id',auth),('HttpOnly',"'httponly' => true",auth),('SameSite',"'samesite' => 'Lax'",auth),
    ('gemeinsame SEO-Session','BE_INTERNAL_SEO_DASHBOARD',auth+seo_dashboard),('Staging-Pfade','BE_CONTROL_CENTER_BASE',environment),
    ('SEO-Pfadabbildung','mapDocumentUrl',seo_embed),('SEO-Höhenanpassung','ResizeObserver',seo_embed),
]: require(text,marker,f'Sicherheits-/Integrationsvertrag fehlt: {label}',errors)
editorial=decision_contract+editorial_contracts+sheet_identity+operations+feedback+editorial_runtime+presentation+case_api+action+schema
for label,marker in {
    'kanonische Entscheidung':'be_cc_validate_operator_decision','Review-Datenvertrag':'be_cc_event_candidate_review_contract',
    'stabile Inbox-Auflösung':'be_cc_resolve_inbox_row','stabile Audit-Auflösung':'be_cc_resolve_audit_row',
    'Operationspersistenz':'control_operations','Feedbackpersistenz':'control_editorial_feedback',
    'serverseitiges Gate':'Event ist nicht entscheidungsreif','Review-Ausgabe':'review_contract',
}.items(): require(editorial,marker,f'Backendvertrag fehlt: {label}',errors)
for label,marker,text in [
    ('aktuelle Eventbasis','be_cc_event_is_current_or_future',content_source+development),
    ('Growth-Backlog-Sync','be_cc_sync_growth_backlog',sources+overview),('Backlog-Quellenstatus',"'sync' => $sync",overview),
    ('reines Backlog',"source_system'] ?? '') !== 'growth_backlog'",cases),('Event-Livewriteback','be_cc_update_event_fields',writeback+editorial_runtime),
    ('Inhaltshistorie','control_content_changes',history+schema),('Publikationsprüfung','be_cc_verify_public_change',publication),
    ('Prozesskette','be_cc_integrated_process_health',process_chain+overview),('Content-Ops-Wirkung','feedback_rule_effectiveness_daily',content_ops),
    ('Aktivitätswriteback','be_cc_update_activity_in_repo',content_api+github_repo),('Aktivitätsfreigabe','BE_ACTIVITY_WRITEBACK_ENABLED',github_repo),
    ('Bausteintrends',"'component_improvement'",development),('Scheingenauigkeitsschutz',"'insufficient'",development),
]: require(text,marker,f'Backendvertrag fehlt: {label}',errors)
for marker,message in [("'address' => $address",'Öffentliche Adresse fehlt.'),("'source_url' => $sourceUrl",'Öffentliche Quelle fehlt.'),("'ticket_url' => $ticketUrl",'Öffentlicher Ticketlink fehlt.')]: require(public_events,marker,message,errors)
require(manifest,'no_automatic_doc_scraping','Kuratierte Repo-Importgrenze fehlt.',errors)
if errors:
    print('=== Control Center Product Contract: FAILED ===')
    for error in errors: print('-',error)
    sys.exit(1)
print('=== Control Center Product Contract: OK ===')
