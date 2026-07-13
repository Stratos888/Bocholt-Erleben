(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  const dialogBody = document.querySelector('#cc-dialog-body');
  const view = document.querySelector('#cc-view');
  let selectedManagementId = '';
  let enhancedEditorId = '';
  let enhancementPending = false;
  let managementMapPromise = null;
  let developmentPromise = null;
  let overviewPromise = null;
  let scheduled = false;

  async function api(path) {
    const controller = new AbortController();
    const timer = window.setTimeout(() => controller.abort(), 15000);
    try {
      const response = await fetch(path, { headers: { 'X-BE-Review-Password': password() }, cache: 'no-store', signal: controller.signal });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(payload.message || 'Integrationsdaten konnten nicht geladen werden.');
      return payload.data || {};
    } finally { window.clearTimeout(timer); }
  }

  const formatDate = value => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(clean(value));
    if (!match) return clean(value);
    return `${match[3]}.${match[2]}.${match[1]}`;
  };

  function formatDateRange(item) {
    const start = formatDate(item?.date);
    const end = formatDate(item?.end_date || item?.endDate);
    if (!start) return '';
    if (!end || end === start) return start;
    const startParts = start.split('.');
    const endParts = end.split('.');
    if (startParts[1] === endParts[1] && startParts[2] === endParts[2]) return `${startParts[0]}.–${end}`;
    if (startParts[2] === endParts[2]) return `${startParts[0]}.${startParts[1]}.–${end}`;
    return `${start}–${end}`;
  }

  function statusLabel(value) {
    const status = clean(value).toLowerCase();
    return ({ published:'Veröffentlicht', approved:'Freigegeben', draft:'Entwurf', pending:'Ausstehend', in_review:'In Prüfung' })[status] || clean(value) || 'Veröffentlicht';
  }

  function processList(processHealth) {
    const items = processHealth?.items || [];
    if (!items.length) return '<div class="cc-empty">Noch keine belastbaren Prozessläufe vorhanden.</div>';
    return `<div class="cc-link-list">${items.map(item => {
      const content = `<strong>${esc(item.label)}</strong><span>${esc(item.message || '')}${item.last_run_at ? ` · ${esc(item.last_run_at)}` : ''}</span>`;
      return item.run_url
        ? `<a class="cc-link-card" href="${esc(item.run_url)}" target="_blank" rel="noopener">${content}</a>`
        : `<div class="cc-link-card">${content}</div>`;
    }).join('')}</div>`;
  }

  async function managementMap() {
    if (managementMapPromise) return managementMapPromise;
    managementMapPromise = Promise.allSettled([
      api('/api/control-center/content.php?type=events&source=sheet'),
      api('/api/control-center/content.php?type=events&source=submissions'),
      api('/api/control-center/content.php?type=activities'),
    ]).then(results => {
      const map = new Map();
      results.forEach(result => {
        if (result.status !== 'fulfilled') return;
        (result.value.items || []).forEach(item => map.set(String(item.id), item));
      });
      return map;
    });
    return managementMapPromise;
  }

  async function enhanceManagementRows() {
    const buttons = [...document.querySelectorAll('[data-manage-edit]')];
    if (!buttons.length) return;
    const map = await managementMap();
    buttons.forEach(button => {
      const row = button.closest('.cc-manage-row');
      const item = map.get(clean(button.dataset.manageEdit));
      if (!row || !item) return;
      const meta = row.querySelector('.cc-manage-meta');
      if (meta) {
        const spans = meta.querySelectorAll('span');
        const source = clean(item.source_label) || (clean(item.id).startsWith('submission-') ? 'Anbieter-Event' : (item.date ? 'Redaktionelles Event' : 'Aktivität'));
        if (spans[0]) spans[0].textContent = source;
        if (spans[1]) spans[1].textContent = statusLabel(item.status);
      }
      const detail = [item.date ? formatDateRange(item) : '', item.location, item.city].filter(Boolean).join(' · ');
      const paragraph = row.querySelector('h3 + p');
      if (paragraph && detail) paragraph.textContent = detail;
    });
  }

  async function developmentData() {
    if (!developmentPromise) developmentPromise = api('/api/control-center/development.php');
    return developmentPromise;
  }

  async function overviewData() {
    if (!overviewPromise) overviewPromise = api('/api/control-center/overview.php');
    return overviewPromise;
  }

  async function enhanceStatusTruth() {
    const data = await developmentData();
    const quality = data.content_quality || {};
    const process = data.automation?.process_health || {};
    const openQuality = Number(quality.open_quality_reviews || 0);
    const processNeedsCheck = ['attention','unknown'].includes(process.status);

    document.querySelectorAll('.cc-summary-card').forEach(card => {
      const label = clean(card.querySelector('.cc-summary-label')?.textContent);
      if (label !== 'Entwicklung') return;
      const heading = card.querySelector('h2');
      const paragraph = card.querySelector('p');
      if (heading) heading.textContent = openQuality || processNeedsCheck ? 'Entwicklung benötigt Aufmerksamkeit' : 'Entwicklung ist aktuell';
      if (paragraph) {
        const parts = [quality.coverage_percent >= 100 ? 'Event-Datenbasis vollständig' : `Event-Datenbasis ${quality.coverage_percent || 0} %`];
        if (openQuality) parts.push(`${openQuality} Qualitätsprüfungen offen`);
        if (processNeedsCheck) parts.push('1 Prozessprüfung erforderlich');
        else parts.push('Prozesse aktuell bestätigt');
        paragraph.textContent = parts.join(' · ');
      }
    });

    document.querySelectorAll('.cc-metric').forEach(card => {
      const label = clean(card.querySelector('span')?.textContent);
      if (label !== 'Content') return;
      const value = card.querySelector('strong');
      const text = card.querySelector('p');
      if (value) value.textContent = quality.coverage_percent >= 100 ? 'Datenbasis vollständig' : `${quality.coverage_percent || 0} % Datenbasis`;
      if (text) text.textContent = openQuality ? `${openQuality} Qualitätsprüfungen offen` : 'Keine offene Qualitätsprüfung';
    });
  }

  async function enhanceSystemReview() {
    const detail = document.querySelector('.cc-work-detail');
    if (!detail) return;
    const kicker = clean(detail.querySelector('.cc-kicker')?.textContent);
    if (!kicker.startsWith('System')) return;
    const status = clean(detail.querySelector('.cc-pill')?.textContent);
    const primary = detail.querySelector('[data-review-action="resume"]');
    if (primary && status === 'Blockiert') primary.textContent = 'Als behoben bestätigen';
    detail.querySelector('[data-review-action="wait"]')?.remove();
    if (detail.querySelector('[data-process-run-link]')) return;
    const overview = await overviewData();
    const title = clean(detail.querySelector('h2')?.textContent).toLowerCase();
    const processes = overview.system?.processes || overview.system?.process_health?.items || [];
    const process = processes.find(item => {
      const label = clean(item.label).toLowerCase();
      return label && (title.includes(label) || label.includes(title.replace(/^prozess prüfen:\s*/, '')));
    });
    if (!process?.run_url) return;
    const link = document.createElement('a');
    link.className = 'cc-button cc-button--secondary cc-button--large';
    link.dataset.processRunLink = '1';
    link.href = process.run_url;
    link.target = '_blank';
    link.rel = 'noopener';
    link.textContent = 'Letzten Lauf prüfen';
    detail.querySelector('.cc-action-primary')?.before(link);
  }

  function enhanceSeoFrame() {
    const frame = document.querySelector('.cc-seo-frame');
    if (!frame) return;
    if (!frame.dataset.embedUrl) {
      const url = new URL(frame.getAttribute('src') || '', window.location.href);
      url.searchParams.set('embed', '1');
      frame.dataset.embedUrl = '1';
      frame.src = url.pathname + url.search;
      return;
    }
    if (frame.dataset.embedBound) return;
    frame.dataset.embedBound = '1';
    frame.addEventListener('load', () => {
      try {
        const doc = frame.contentDocument;
        if (!doc || doc.getElementById('be-cc-seo-embed-style')) return;
        const style = doc.createElement('style');
        style.id = 'be-cc-seo-embed-style';
        style.textContent = `
          html,body{background:transparent!important}
          body{padding-bottom:28px!important}
          .wrap{width:100%!important;padding:0 4px 28px!important}
          .topbar>div:first-child,.topbar .logout{display:none!important}
          .topbar{display:block!important;margin:0 0 8px!important}
          .topActions{justify-content:flex-start!important}
          .compactGuidance{margin-bottom:10px!important}
          @media(max-width:860px){.wrap{width:100%!important;padding:0 2px 32px!important}.card{border-radius:14px!important}.topActions .softButton{width:100%!important}}
        `;
        doc.head.appendChild(style);
      } catch (_) {}
    });
  }

  async function enhanceEventEditor(id) {
    if (!id || enhancedEditorId === id || enhancementPending) return;
    if (!dialogBody?.querySelector('#ce-location')) return;
    enhancementPending = true;
    try {
      const data = await api(`/api/control-center/content.php?type=events&id=${encodeURIComponent(id)}`);
      if (selectedManagementId !== id || !dialogBody.querySelector('#ce-location')) return;
      const item = data.item || {};
      const location = dialogBody.querySelector('#ce-location')?.closest('label');
      if (!location) return;
      const heading = dialogBody.querySelector('h2');
      if (heading) heading.textContent = item.source_label ? `${item.source_label} bearbeiten` : 'Veranstaltung bearbeiten';
      if (item.source_label && !dialogBody.querySelector('[data-content-source-label]')) {
        const note = document.createElement('p');
        note.dataset.contentSourceLabel = '1';
        note.className = 'cc-hint';
        note.textContent = `Führende Quelle: ${item.source_label}`;
        heading?.after(note);
      }
      if (!dialogBody.querySelector('#ce-address')) {
        const label = document.createElement('label');
        label.className = 'cc-field';
        label.innerHTML = `<span>Adresse</span><input id="ce-address" type="text" value="${esc(item.address || '')}">`;
        location.after(label);
      }
      if (item.source_system === 'submission_db' && !dialogBody.querySelector('[data-provider-field-note]')) {
        ['#ce-end', '#ce-city', '#ce-category'].forEach(selector => {
          const field = dialogBody.querySelector(selector);
          if (!field) return;
          field.disabled = true;
          field.closest('label')?.classList.add('is-disabled');
        });
        const note = document.createElement('p');
        note.className = 'cc-hint';
        note.dataset.providerFieldNote = '1';
        note.textContent = 'Enddatum, Stadt und Kategorie werden für Anbieter-Events nicht als eigene Felder geführt.';
        dialogBody.querySelector('#ce-category')?.closest('label')?.after(note);
      }
      enhancedEditorId = id;
    } catch (_) {
      // Der Basiseditor bleibt nutzbar; die Ergänzung wird beim nächsten Öffnen erneut versucht.
    } finally { enhancementPending = false; }
  }

  function maybeEnhanceCurrentEditor() {
    if (!selectedManagementId || !dialogBody?.querySelector('#ce-location')) return;
    enhanceEventEditor(selectedManagementId);
  }

  function scheduleEnhancements() {
    if (scheduled) return;
    scheduled = true;
    window.setTimeout(async () => {
      scheduled = false;
      await Promise.allSettled([enhanceManagementRows(), enhanceStatusTruth(), enhanceSystemReview()]);
      enhanceSeoFrame();
    }, 20);
  }

  document.addEventListener('click', event => {
    const edit = event.target.closest('[data-manage-edit]');
    if (edit) {
      selectedManagementId = clean(edit.dataset.manageEdit);
      enhancedEditorId = '';
      setTimeout(maybeEnhanceCurrentEditor, 0);
    }
    const system = event.target.closest('#cc-system');
    if (system) {
      setTimeout(async () => {
        try {
          const overview = await overviewData();
          if (!dialogBody || dialogBody.querySelector('#cc-process-health')) return;
          const section = document.createElement('section');
          section.id = 'cc-process-health';
          section.innerHTML = `<h3>Automatisierte Prozesskette</h3>${processList({ items: overview.system?.processes || [] })}`;
          dialogBody.appendChild(section);
        } catch (_) {}
      }, 120);
    }
    setTimeout(scheduleEnhancements, 0);
  }, true);

  if (view) new MutationObserver(scheduleEnhancements).observe(view, { childList: true, subtree: true });

  const dialogObserver = new MutationObserver(() => {
    if (!dialogBody?.querySelector('#ce-location')) { enhancedEditorId = ''; return; }
    maybeEnhanceCurrentEditor();
  });
  if (dialogBody) dialogObserver.observe(dialogBody, { childList: true, subtree: true });

  scheduleEnhancements();
})();
