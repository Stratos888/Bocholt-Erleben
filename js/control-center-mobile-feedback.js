(() => {
  'use strict';

  const view = document.querySelector('#cc-view');
  const status = document.querySelector('#cc-status');
  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  let casesCache = null;
  let casesPromise = null;

  async function api(path) {
    const response = await fetch(path, {
      headers: { 'X-BE-Review-Password': password() },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    return payload.data;
  }

  async function cases() {
    if (casesCache) return casesCache;
    if (!casesPromise) casesPromise = api('/api/control-center/cases.php').then(data => (casesCache = data.items || []));
    return casesPromise;
  }

  const active = item => !['done','rejected','parked','information'].includes(item.state);
  const backlog = item => item.case_kind === 'backlog_item' && active(item);
  const task = item => item.type === 'task';

  function workCounts(items) {
    return {
      now: items.filter(i => task(i) && active(i) && !['waiting','blocked','snoozed'].includes(i.state) && (i.overdue || i.bucket === 'now' || i.state === 'in_progress')).length,
      next: items.filter(i => task(i) && active(i) && !['waiting','blocked'].includes(i.state) && !i.overdue && i.bucket !== 'now').length,
      waiting: items.filter(i => task(i) && ['waiting','snoozed'].includes(i.state)).length,
      blocked: items.filter(i => task(i) && i.state === 'blocked').length,
      backlog: items.filter(backlog).length,
      archive: items.filter(i => ['done','rejected','parked'].includes(i.state) && (task(i) || i.case_kind === 'backlog_item')).length,
    };
  }

  async function enhanceLabor() {
    const toolbar = view.querySelector('.cc-toolbar');
    const row = toolbar?.querySelector('.cc-filter-row--contained');
    if (!toolbar || !row || toolbar.querySelector('#cc-labor-select')) return;
    row.querySelector('[data-labor-mode="ideas"]')?.remove();
    const items = await cases();
    const counts = workCounts(items);
    const labels = { now:'Jetzt', next:'Als Nächstes', waiting:'Wartet', blocked:'Blockiert', backlog:'Backlog', archive:'Archiv' };
    const current = row.querySelector('.is-active')?.dataset.laborMode || 'now';
    const select = document.createElement('label');
    select.className = 'cc-work-filter';
    select.innerHTML = `<span>Ansicht</span><select id="cc-labor-select">${Object.entries(labels).map(([key,label]) => `<option value="${key}" ${key === current ? 'selected' : ''}>${esc(label)} (${counts[key] || 0})</option>`).join('')}</select>`;
    row.hidden = true;
    toolbar.insertBefore(select, toolbar.firstChild);
    select.querySelector('select').addEventListener('change', event => {
      row.querySelector(`[data-labor-mode="${CSS.escape(event.target.value)}"]`)?.click();
    });
  }

  async function enhanceReview() {
    const card = view.querySelector('.cc-work-detail[data-case-id]');
    if (!card || card.dataset.comparisonLoaded === '1') return;
    card.dataset.comparisonLoaded = '1';
    try {
      const detail = await api(`/api/control-center/case.php?id=${encodeURIComponent(card.dataset.caseId)}`);
      if (detail.case_kind !== 'content_description_correction') return;
      const context = detail.decision_context || {};
      const current = clean(context.current_description || detail.source_payload?.current_description);
      const suggested = clean(context.suggested_description || detail.source_payload?.suggested_description);
      const target = card.querySelector('.cc-action-primary');
      if (!target) return;
      target.insertAdjacentHTML('beforebegin', `<section class="cc-copy-compare cc-copy-compare--inline"><div><span class="cc-kicker">Aktuell</span><p>${current ? esc(current) : '<em>Keine Beschreibung vorhanden.</em>'}</p></div><div class="cc-copy-compare__proposal"><span class="cc-kicker">Vorschlag</span><p>${suggested ? esc(suggested) : '<em>Kein Vorschlag verfügbar.</em>'}</p></div></section>`);
    } catch (error) {
      status.textContent = error.message;
    }
  }

  function removeIdeaCreation() {
    document.querySelector('[data-new-kind="idea"]')?.remove();
  }

  let scheduled = false;
  const enhance = () => {
    if (scheduled) return;
    scheduled = true;
    queueMicrotask(async () => {
      scheduled = false;
      removeIdeaCreation();
      await Promise.allSettled([enhanceLabor(), enhanceReview()]);
    });
  };

  new MutationObserver(enhance).observe(document.body, { childList:true, subtree:true });
  document.addEventListener('click', event => {
    if (event.target.closest('#cc-refresh')) { casesCache = null; casesPromise = null; }
  });
  enhance();
})();
