(() => {
  'use strict';

  const state = {
    password: sessionStorage.getItem('be_cc_password') || '',
    view: 'overview',
    cases: [],
    overview: null,
  };

  const els = {
    auth: document.querySelector('#cc-auth'),
    authForm: document.querySelector('#cc-auth-form'),
    password: document.querySelector('#cc-password'),
    content: document.querySelector('#cc-content'),
    view: document.querySelector('#cc-view'),
    title: document.querySelector('#cc-title'),
    status: document.querySelector('#cc-status'),
    refresh: document.querySelector('#cc-refresh'),
    nav: [...document.querySelectorAll('.cc-nav [data-view]')],
    badgeInbox: document.querySelector('#cc-badge-inbox'),
    badgeTasks: document.querySelector('#cc-badge-tasks'),
    dialog: document.querySelector('#cc-dialog'),
    dialogBody: document.querySelector('#cc-dialog-body'),
  };

  const rejectReasons = [
    'Termin liegt in der Vergangenheit',
    'Doppelt / bereits abgedeckt',
    'Terminangaben unklar',
    'Nicht öffentlich zugänglich',
    'Quelle / Angaben reichen nicht',
    'Nicht lokal genug',
    'Redaktionell nicht passend',
  ];

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  }

  function formatDate(value) {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? '' : new Intl.DateTimeFormat('de-DE', {dateStyle:'medium'}).format(date);
  }

  async function api(path, options = {}) {
    const response = await fetch(path, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-BE-Review-Password': state.password,
        ...(options.headers || {}),
      },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
      if (response.status === 401) logout();
      throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    }
    return payload.data;
  }

  function setStatus(message = '') { els.status.textContent = message; }

  function logout() {
    state.password = '';
    sessionStorage.removeItem('be_cc_password');
    els.content.hidden = true;
    els.auth.hidden = false;
  }

  function pill(label, modifier = '') {
    return `<span class="cc-pill${modifier ? ` cc-pill--${modifier}` : ''}">${escapeHtml(label)}</span>`;
  }

  function actionLabel(item) {
    if (item.type === 'task') return item.state === 'waiting' ? 'Status prüfen' : 'Erledigen';
    if (item.source?.system === 'submission_db') return 'Nächsten Schritt ausführen';
    if (item.state === 'decision_required' || item.decision_ready) return 'Freigeben';
    return 'Bearbeiten';
  }

  function caseCard(item) {
    const meta = [];
    if (item.object?.title) meta.push(escapeHtml(item.object.title));
    if (item.due_at) meta.push(`${item.overdue ? 'Überfällig' : 'Fällig'} ${escapeHtml(formatDate(item.due_at))}`);
    if (item.state === 'blocked' && item.blocked_reason) meta.push(`Blockiert: ${escapeHtml(item.blocked_reason)}`);

    const mainAction = item.type === 'task'
      ? `<button class="cc-button cc-button--primary" data-action="${item.state === 'waiting' ? 'details' : 'complete'}">${escapeHtml(actionLabel(item))}</button>`
      : item.state === 'decision_required' || item.decision_ready
        ? `<button class="cc-button cc-button--primary" data-action="approve">${escapeHtml(actionLabel(item))}</button>`
        : '<button class="cc-button cc-button--primary" data-action="start">Bearbeiten</button>';

    const secondary = item.type === 'intake' && !item.decision_ready
      ? '<button class="cc-button cc-button--secondary" data-action="convert_to_task">Als Aufgabe</button>'
      : '<button class="cc-button cc-button--secondary" data-action="snooze">Zurückstellen</button>';
    const reject = item.type === 'intake' && (item.state === 'decision_required' || item.decision_ready)
      ? '<button class="cc-button cc-button--secondary" data-action="reject">Ablehnen</button>'
      : '';

    return `<article class="cc-card ${item.priority === 'critical' ? 'cc-card--critical' : ''} ${item.state === 'blocked' ? 'cc-card--blocked' : ''}" data-case-id="${escapeHtml(item.id)}">
      <div class="cc-card__top">
        <h3>${escapeHtml(item.title)}</h3>
        ${pill(item.priority === 'critical' ? 'Kritisch' : item.priority === 'high' ? 'Hoch' : item.state.replaceAll('_',' '), item.priority)}
      </div>
      ${item.reason ? `<p>${escapeHtml(item.reason)}</p>` : ''}
      ${item.next_action ? `<p class="cc-next">Nächster Schritt: ${escapeHtml(item.next_action)}</p>` : ''}
      ${meta.length ? `<div class="cc-meta">${meta.map(value => `<span>${value}</span>`).join('')}</div>` : ''}
      <div class="cc-actions">${mainAction}${secondary}${reject}<button class="cc-button cc-button--secondary" data-action="details">Details</button></div>
    </article>`;
  }

  function section(title, items, emptyText, grid = false) {
    return `<section class="cc-section">
      <div class="cc-section__head"><h2>${escapeHtml(title)}</h2><span class="cc-count">${items.length}</span></div>
      <div class="cc-list ${grid ? 'cc-list--grid' : ''}">
        ${items.length ? items.map(caseCard).join('') : `<div class="cc-empty">${escapeHtml(emptyText)}</div>`}
      </div>
    </section>`;
  }

  function renderOverview() {
    const groups = state.overview?.groups || {now:[],next:[],inbox:[],information:[]};
    els.view.innerHTML = [
      section('Jetzt erforderlich', groups.now, 'Aktuell ist nichts dringend.'),
      section('Neuer Eingang', groups.inbox, 'Keine ungeklärten neuen Fälle.'),
      section('Als Nächstes', groups.next, 'Keine weiteren aktiven Aufgaben.'),
      `<section class="cc-section"><div class="cc-section__head"><h2>Systemzustand</h2></div><div class="cc-empty">${escapeHtml(state.overview?.system?.message || 'Keine Statusinformation verfügbar.')}</div></section>`,
    ].join('');
  }

  function renderFiltered(type, title, emptyText) {
    const items = state.cases.filter(item => item.type === type && !['done','rejected','parked','information'].includes(item.state));
    els.view.innerHTML = section(title, items, emptyText, true);
  }

  function renderContent() {
    els.view.innerHTML = `<section class="cc-section"><div class="cc-section__head"><h2>Inhalte</h2></div><div class="cc-list">
      <a class="cc-link-card" href="/events/"><strong>Veranstaltungen</strong><span>Öffentliche Darstellung und Eventbestand öffnen</span></a>
      <a class="cc-link-card" href="/aktivitaeten/"><strong>Aktivitäten</strong><span>Öffentliche Darstellung und Aktivitätsbestand öffnen</span></a>
      <a class="cc-link-card" href="/inbox/"><strong>Technische Altansicht</strong><span>Nur noch für Diagnose und noch nicht überführte Sonderfunktionen</span></a>
    </div></section>`;
  }

  function renderMore() {
    const ideas = state.cases.filter(item => item.type === 'idea' && !['rejected','done'].includes(item.state));
    els.view.innerHTML = `${section('Ideen', ideas, 'Keine gesammelten Ideen.')}
      <section class="cc-section"><div class="cc-section__head"><h2>Weitere Bereiche</h2></div><div class="cc-list">
        <a class="cc-link-card" href="/fuer-veranstalter/dashboard/"><strong>Anbieterbereich</strong><span>Einreichungen und Anbieterwirkung</span></a>
        <button class="cc-link-card" type="button" id="cc-logout"><strong>Abmelden</strong><span>Sitzungszugang entfernen</span></button>
      </div></section>`;
    document.querySelector('#cc-logout')?.addEventListener('click', logout);
  }

  function render() {
    const titles = {overview:'Übersicht',inbox:'Eingang',content:'Inhalte',tasks:'Aufgaben',more:'Mehr'};
    els.title.textContent = titles[state.view] || 'Steuerzentrale';
    els.nav.forEach(button => button.classList.toggle('is-active', button.dataset.view === state.view));
    if (state.view === 'overview') renderOverview();
    if (state.view === 'inbox') renderFiltered('intake', 'Eingang', 'Keine ungeklärten Fälle.');
    if (state.view === 'tasks') renderFiltered('task', 'Aufgaben', 'Keine offenen Aufgaben.');
    if (state.view === 'content') renderContent();
    if (state.view === 'more') renderMore();
    bindCaseActions();
  }

  function updateBadges() {
    const inbox = state.cases.filter(item => item.type === 'intake' && ['new','decision_required'].includes(item.state)).length;
    const tasks = state.cases.filter(item => item.type === 'task' && !['done','rejected','parked'].includes(item.state)).length;
    [[els.badgeInbox,inbox],[els.badgeTasks,tasks]].forEach(([element,count]) => {
      element.hidden = count === 0;
      element.textContent = count > 99 ? '99+' : String(count);
    });
  }

  async function load() {
    setStatus('Daten werden geladen …');
    try {
      const [overview, cases] = await Promise.all([
        api('/api/control-center/overview.php'),
        api('/api/control-center/cases.php?active=1'),
      ]);
      state.overview = overview;
      state.cases = cases.items || [];
      updateBadges();
      render();
      setStatus('');
    } catch (error) {
      setStatus(error.message);
    }
  }

  function auditEditHtml(item) {
    const p = item.source_payload || {};
    if (item.source?.system !== 'content_audit' || String(p.content_type || '') !== 'event') return '';
    const field = (id, label, value, type = 'text') => `<label class="cc-field"><span>${escapeHtml(label)}</span><input id="${id}" type="${type}" value="${escapeHtml(value || '')}"></label>`;
    return `<div class="cc-stack">
      <h3>Eventdaten korrigieren</h3>
      ${field('cc-edit-source','Offizielle Quelle',p.suggested_url || p.source_url,'url')}
      ${field('cc-edit-title','Titel',p.title)}
      ${field('cc-edit-date','Startdatum',p.date)}
      ${field('cc-edit-end','Enddatum',p.endDate || p.end_date)}
      ${field('cc-edit-time','Uhrzeit',p.time)}
      ${field('cc-edit-city','Stadt',p.city)}
      ${field('cc-edit-location','Ort',p.location)}
      ${field('cc-edit-key','Bildtyp',p.suggested_visual_key || p.visual_key)}
      ${field('cc-edit-motif','Motiv',p.suggested_visual_motif || p.visual_motif)}
      <button class="cc-button cc-button--primary" type="button" id="cc-save-audit">Korrigieren und abschließen</button>
    </div>`;
  }

  async function showDetails(item) {
    try {
      const detail = await api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`);
      els.dialogBody.innerHTML = `<h2>${escapeHtml(detail.title)}</h2>
        ${detail.reason ? `<p>${escapeHtml(detail.reason)}</p>` : ''}
        <dl><dt>Status</dt><dd>${escapeHtml(detail.state)}</dd><dt>Quelle</dt><dd>${escapeHtml(detail.source?.system)} · ${escapeHtml(detail.source?.reference)}</dd>${detail.object?.title ? `<dt>Inhalt</dt><dd>${escapeHtml(detail.object.title)}</dd>` : ''}</dl>
        ${auditEditHtml(detail)}`;
      els.dialog.showModal();
      document.querySelector('#cc-save-audit')?.addEventListener('click', async () => {
        const value = id => document.querySelector(id)?.value.trim() || '';
        const event_updates = {
          source_url: value('#cc-edit-source'), url: value('#cc-edit-source'), event_url: value('#cc-edit-source'),
          title: value('#cc-edit-title'), date: value('#cc-edit-date'), endDate: value('#cc-edit-end'), end_date: value('#cc-edit-end'),
          time: value('#cc-edit-time'), city: value('#cc-edit-city'), location: value('#cc-edit-location'),
          visual_key: value('#cc-edit-key'), visual_motif: value('#cc-edit-motif'),
        };
        Object.keys(event_updates).forEach(key => { if (!event_updates[key]) delete event_updates[key]; });
        els.dialog.close();
        await performAction(detail, 'approve', {event_updates, note:'Über Steuerzentrale korrigiert und fachlich geprüft.'});
      });
    } catch (error) {
      setStatus(error.message);
    }
  }

  function askSnooze(item) {
    const date = new Date();
    date.setDate(date.getDate() + 7);
    els.dialogBody.innerHTML = `<h2>Zurückstellen</h2><p>${escapeHtml(item.title)}</p><label class="cc-field"><span>Wiedervorlage</span><input id="cc-snooze-date" type="date" value="${date.toISOString().slice(0,10)}" required></label><div class="cc-actions"><button class="cc-button cc-button--primary" type="button" id="cc-confirm-snooze">Zurückstellen</button></div>`;
    els.dialog.showModal();
    document.querySelector('#cc-confirm-snooze')?.addEventListener('click', async () => {
      const until = document.querySelector('#cc-snooze-date')?.value;
      els.dialog.close();
      await performAction(item, 'snooze', {until});
    });
  }

  function askReject(item) {
    els.dialogBody.innerHTML = `<h2>Ablehnen</h2><p>${escapeHtml(item.title)}</p><label class="cc-field"><span>Grund</span><select id="cc-reject-reason">${rejectReasons.map(reason => `<option>${escapeHtml(reason)}</option>`).join('')}</select></label><div class="cc-actions"><button class="cc-button cc-button--primary" type="button" id="cc-confirm-reject">Ablehnen</button></div>`;
    els.dialog.showModal();
    document.querySelector('#cc-confirm-reject')?.addEventListener('click', async () => {
      const reason = document.querySelector('#cc-reject-reason')?.value || 'Redaktionell nicht passend';
      els.dialog.close();
      await performAction(item, 'reject', {reason});
    });
  }

  async function performAction(item, action, payload = {}) {
    setStatus('Änderung wird gespeichert …');
    try {
      await api('/api/control-center/action.php', {method:'POST', body:JSON.stringify({case_id:item.id,action,payload})});
      await load();
    } catch (error) {
      setStatus(error.message);
    }
  }

  function bindCaseActions() {
    els.view.querySelectorAll('[data-case-id]').forEach(card => {
      const item = state.cases.find(entry => entry.id === card.dataset.caseId);
      if (!item) return;
      card.querySelectorAll('[data-action]').forEach(button => button.addEventListener('click', () => {
        const action = button.dataset.action;
        if (action === 'details') return showDetails(item);
        if (action === 'snooze') return askSnooze(item);
        if (action === 'reject') return askReject(item);
        return performAction(item, action);
      }));
    });
  }

  els.authForm.addEventListener('submit', async event => {
    event.preventDefault();
    state.password = els.password.value;
    sessionStorage.setItem('be_cc_password', state.password);
    els.auth.hidden = true;
    els.content.hidden = false;
    await load();
  });

  els.nav.forEach(button => button.addEventListener('click', () => {
    state.view = button.dataset.view;
    render();
  }));
  els.refresh.addEventListener('click', load);

  if (state.password) {
    els.auth.hidden = true;
    els.content.hidden = false;
    load();
  }
})();
