import { state, els, escapeHtml, clean, setStatus, appPath, api, openDialog, closeDialog, reviewGroup, allReviewCases } from './shared.js?v=2026-07-15-control-center-editorial-v1';
import { configureReview, renderReview } from './review.js?v=2026-07-15-control-center-editorial-v1';
import { renderManage } from './manage.js?v=2026-07-15-control-center-editorial-v1';
import { renderDevelopment } from './development.js?v=2026-07-15-control-center-editorial-v1';

async function initializeSharedSession() {
  return api('/api/control-center/auth.php', { method:'POST', body:JSON.stringify({ password:state.password }), timeoutMs:12000 });
}

async function logout(callServer = true) {
  if (callServer) {
    try { await fetch(appPath('/api/control-center/auth.php'), { method:'DELETE', cache:'no-store' }); } catch (_) {}
  }
  state.password = '';
  sessionStorage.removeItem('be_cc_password');
  els.content.hidden = true;
  els.auth.hidden = false;
  closeDialog();
}

function compactSummary(title, count, detail, view, label) {
  return `<article class="cc-summary-card"><div><span class="cc-summary-label">${escapeHtml(title)}</span><strong>${escapeHtml(count)}</strong><p>${escapeHtml(detail)}</p></div>${view ? `<button class="cc-button cc-button--primary" data-go-view="${view}">${escapeHtml(label)}</button>` : ''}</article>`;
}

function renderOverview() {
  const reviews = allReviewCases();
  const urgent = reviews.filter(item => item.bucket === 'now' || item.overdue || item.state === 'blocked');
  const groups = reviews.reduce((result, item) => {
    const key = reviewGroup(item);
    result[key] = (result[key] || 0) + 1;
    return result;
  }, {});
  const labels = { new_content:'Neue Inhalte', quality:'Qualität', provider:'Anbieter', approvals:'Freigaben', system:'System', other:'Sonstige' };
  const groupText = Object.entries(groups).map(([key,count]) => `${count} ${labels[key] || 'Sonstige'}`).join(' · ') || 'Keine offenen Prüfungen';
  const quality = state.development?.content_quality || {};
  const process = state.development?.automation?.process_health || {};
  const developmentText = `${quality.scope_label || 'Aktive Eventbasis'} ${quality.coverage_percent ?? 0} % vollständig · ${['attention','unknown'].includes(process.status) ? 'Prozessprüfung erforderlich' : 'Prozesse ohne bekannten Fehler'}`;
  const counts = state.backlog?.counts || { total:0, open:0, completed:0 };
  const backlogText = state.backlog?.status === 'error'
    ? 'Kanonische Roadmap konnte nicht geladen werden'
    : `${counts.open} offen · ${counts.completed} abgeschlossen · alle sichtbar`;

  els.view.innerHTML = `<section class="cc-overview-grid">
    ${compactSummary('Jetzt erforderlich', urgent.length, urgent.length ? urgent.slice(0,2).map(item => item.title).join(' · ') : 'Aktuell ist nichts dringend.', urgent.length ? 'review' : '', 'Jetzt prüfen')}
    ${compactSummary('Zu prüfen', reviews.length, groupText, reviews.length ? 'review' : '', 'Prüfen öffnen')}
    ${compactSummary('Backlog', counts.total, backlogText, 'backlog', 'Roadmap öffnen')}
    <article class="cc-summary-card cc-summary-card--quiet"><div><span class="cc-summary-label">Entwicklung</span><h2>${escapeHtml(state.development?.operator_status || state.development?.summary?.label || 'Projektstatus')}</h2><p>${escapeHtml(developmentText)}</p></div><button class="cc-button cc-button--secondary" data-go-view="development">Entwicklung öffnen</button></article>
  </section>`;
  document.querySelectorAll('[data-go-view]').forEach(button => button.addEventListener('click', () => setView(button.dataset.goView)));
}

function roadmapCopy(label, value) {
  const text = clean(value);
  return text ? `<div class="cc-roadmap-copy"><strong>${escapeHtml(label)}</strong><p>${escapeHtml(text)}</p></div>` : '';
}

function roadmapItem(item) {
  const completed = item.status === 'completed';
  const statusLabel = completed ? 'Abgeschlossen' : 'Offen';
  const orderPrefix = completed ? '' : `${escapeHtml(item.recommended_order || '–')}. `;
  const meta = [statusLabel, item.priority, item.type].filter(Boolean).map(escapeHtml).join(' · ');
  const preview = clean(item.recommended_action || item.why_relevant || item.short_reason || item.expected_benefit) || 'Details öffnen';
  const completion = completed
    ? `${roadmapCopy('Abschluss', item.decision_note)}${roadmapCopy('Abgeschlossen am', item.closed_at)}`
    : '';
  return `<details class="cc-labor-row cc-roadmap-row ${completed ? 'cc-roadmap-row--completed' : ''}">
    <summary><span><strong>${orderPrefix}${escapeHtml(item.title || 'Backlogpunkt')}</strong><small>${meta}</small></span><span class="cc-labor-summary">${escapeHtml(preview)}</span></summary>
    <div class="cc-labor-detail">
      ${roadmapCopy('Warum relevant', item.why_relevant || item.short_reason)}
      ${roadmapCopy('Empfohlener nächster Schritt', item.recommended_action)}
      ${roadmapCopy('Erwarteter Nutzen', item.expected_benefit)}
      ${completion}
    </div>
  </details>`;
}

function roadmapSection(title, items, emptyText) {
  return `<section class="cc-roadmap-section"><div class="cc-roadmap-section__head"><h2>${escapeHtml(title)}</h2><span>${items.length}</span></div>${items.length ? `<div class="cc-roadmap-list">${items.map(roadmapItem).join('')}</div>` : `<div class="cc-empty">${escapeHtml(emptyText)}</div>`}</section>`;
}

function renderBacklog() {
  if (state.backlog?.status === 'error') {
    els.view.innerHTML = `<div class="cc-notice cc-notice--attention"><strong>Roadmap konnte nicht geladen werden.</strong><br>${escapeHtml(state.backlog.message || 'Der kanonische Growth-Backlog ist derzeit nicht erreichbar.')}</div>`;
    return;
  }
  const items = Array.isArray(state.backlog?.items) ? state.backlog.items : [];
  const open = items.filter(item => item.status === 'open');
  const completed = items.filter(item => item.status === 'completed');
  const counts = state.backlog?.counts || { total:items.length, open:open.length, completed:completed.length };
  els.view.innerHTML = `<section class="cc-roadmap-summary">
    <span class="cc-kicker">Informations- und Reihenfolgeansicht</span>
    <h2>${escapeHtml(counts.total)} Punkte insgesamt</h2>
    <p><strong>${escapeHtml(counts.open)} offen</strong> · ${escapeHtml(counts.completed)} abgeschlossen</p>
    <small>Alle kanonischen Punkte bleiben sichtbar. Offene Punkte stehen in der empfohlenen Reihenfolge; dies ist keine Arbeits- oder Prüfqueue.</small>
  </section>
  ${roadmapSection('Offen · empfohlene Reihenfolge', open, 'Aktuell ist kein offener Roadmappunkt vorhanden.')}
  ${roadmapSection('Abgeschlossen', completed, 'Noch kein Roadmappunkt ist abgeschlossen.')}`;
}

function updateBadges() {
  els.badgeReview.hidden = allReviewCases().length === 0;
  els.badgeReview.textContent = allReviewCases().length > 99 ? '99+' : String(allReviewCases().length);
  els.badgeBacklog.hidden = true;
  els.badgeBacklog.textContent = '';
}

export async function load() {
  setStatus('Daten werden geladen …');
  try {
    const [overview, cases, development, backlogResult] = await Promise.all([
      api('/api/control-center/overview.php'),
      api('/api/control-center/cases.php'),
      api('/api/control-center/development.php'),
      api('/api/growth-backlog/list.php').then(data => ({ ok:true, data })).catch(error => ({ ok:false, error })),
    ]);
    state.overview = overview;
    state.cases = cases.items || [];
    state.development = development;
    state.backlog = backlogResult.ok
      ? { status:'ok', label:backlogResult.data.label || 'Growth-Backlog', items:backlogResult.data.items || [], counts:backlogResult.data.counts || { total:0, open:0, completed:0 }, message:'' }
      : { status:'error', label:'Growth-Backlog', items:[], counts:{ total:0, open:0, completed:0 }, message:backlogResult.error?.message || 'Roadmap konnte nicht geladen werden.' };
    updateBadges();
    render();
    setStatus('');
  } catch (error) {
    if (error.status === 401) logout(false);
    setStatus(error.message, 'attention');
  }
}

configureReview({ reload:load });

function render() {
  if (state.view !== 'development' || state.developmentMode !== 'seo') els.view.classList.remove('cc-view--seo');
  if (state.view === 'overview') renderOverview();
  else if (state.view === 'review') renderReview();
  else if (state.view === 'backlog') renderBacklog();
  else if (state.view === 'manage') renderManage();
  else if (state.view === 'development') renderDevelopment();
}

function setView(view) {
  state.view = view;
  setStatus('');
  const titles = { overview:'Übersicht', review:'Prüfen', backlog:'Backlog', manage:'Verwaltung', development:'Entwicklung' };
  els.title.textContent = titles[view] || 'Steuerzentrale';
  els.nav.forEach(button => button.classList.toggle('is-active', button.dataset.view === view));
  render();
  window.scrollTo({ top:0, behavior:'instant' });
}

function openSystemDialog() {
  const systemCases = allReviewCases().filter(item => reviewGroup(item) === 'system');
  const counts = state.backlog?.counts || { total:0, open:0, completed:0 };
  closeDialog();
  openDialog(`<h2>Systemstatus</h2><p>${escapeHtml(state.overview?.system?.message || 'Keine bekannte Störung mit Auswirkung.')}</p><ul class="cc-system-list"><li><strong>Prüffälle</strong><span>${allReviewCases().length} offen</span></li><li><strong>Systemfälle</strong><span>${systemCases.length} offen</span></li><li><strong>Roadmap</strong><span>${counts.total} insgesamt · ${counts.open} offen</span></li></ul><details class="cc-disclosure"><summary>Technische Details</summary><div>${escapeHtml(JSON.stringify(state.overview?.sync || {}, null, 2))}</div></details>`);
}

function openHeaderMenu() {
  openDialog(`<h2>Menü</h2><div class="cc-link-list"><a class="cc-link-card" href="${escapeHtml(appPath('/fuer-veranstalter/dashboard/'))}"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung</span></a><button class="cc-link-card" id="cc-system"><strong>Systemstatus</strong><span>Prozesse und Auswirkungen prüfen</span></button><button class="cc-link-card" id="cc-deploy"><strong>Deployment starten</strong><span>Datenbestand neu veröffentlichen</span></button><button class="cc-link-card" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button></div>`);
  document.querySelector('#cc-system')?.addEventListener('click', openSystemDialog);
  document.querySelector('#cc-deploy')?.addEventListener('click', async () => {
    closeDialog();
    setStatus('Deployment wird gestartet …', 'info');
    try {
      const result = await api('/api/control-center/deploy.php', { method:'POST', body:'{}' });
      setStatus(result.message, 'success');
    } catch (error) { setStatus(error.message, 'attention'); }
  });
  document.querySelector('#cc-logout')?.addEventListener('click', () => logout(true));
}

els.authForm.addEventListener('submit', async event => {
  event.preventDefault();
  state.password = clean(els.password.value);
  setStatus('Zugang wird geprüft …');
  try {
    await initializeSharedSession();
    sessionStorage.setItem('be_cc_password', state.password);
    els.auth.hidden = true;
    els.content.hidden = false;
    await load();
  } catch (error) {
    state.password = '';
    setStatus(error.message, 'attention');
  }
});

els.refresh.addEventListener('click', () => {
  state.managementLoaded = { events:false, activities:false };
  state.managementWarnings = { events:[], activities:[] };
  state.development = null;
  load();
});
els.menuButton.addEventListener('click', openHeaderMenu);
els.nav.forEach(button => button.addEventListener('click', () => setView(button.dataset.view)));
els.dialogClose.addEventListener('click', closeDialog);
els.dialog.addEventListener('click', event => { if (event.target === els.dialog) closeDialog(); });
if (state.password) {
  els.auth.hidden = true;
  els.content.hidden = false;
  initializeSharedSession().then(load).catch(() => logout(false));
}
