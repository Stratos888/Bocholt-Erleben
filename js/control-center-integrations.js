(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  const dialogBody = document.querySelector('#cc-dialog-body');
  let selectedManagementId = '';
  let enhancedEditorId = '';
  let enhancementPending = false;

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

  function labelManagementSources() {
    document.querySelectorAll('[data-manage-edit]').forEach(button => {
      const row = button.closest('.cc-manage-row');
      const kicker = row?.querySelector('.cc-kicker');
      if (!kicker) return;
      const target = clean(button.dataset.manageEdit).startsWith('submission-')
        ? 'Anbieter-Event · veröffentlicht'
        : (kicker.textContent.includes('Aktivität') ? kicker.textContent : 'Redaktionelles Event · veröffentlicht');
      if (kicker.textContent !== target) kicker.textContent = target;
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

  document.addEventListener('click', event => {
    const manageButton = event.target.closest('[data-view="manage"], [data-manage-type]');
    if (manageButton) setTimeout(labelManagementSources, 100);
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
          const overview = await api('/api/control-center/overview.php');
          if (!dialogBody || dialogBody.querySelector('#cc-process-health')) return;
          const section = document.createElement('section');
          section.id = 'cc-process-health';
          section.innerHTML = `<h3>Automatisierte Prozesskette</h3>${processList({ items: overview.system?.processes || [] })}`;
          dialogBody.appendChild(section);
        } catch (_) {}
      }, 120);
    }
  }, true);

  const viewObserver = new MutationObserver(labelManagementSources);
  const view = document.querySelector('#cc-view');
  if (view) viewObserver.observe(view, { childList: true, subtree: true });

  const dialogObserver = new MutationObserver(() => {
    if (!dialogBody?.querySelector('#ce-location')) { enhancedEditorId = ''; return; }
    maybeEnhanceCurrentEditor();
  });
  if (dialogBody) dialogObserver.observe(dialogBody, { childList: true, subtree: true });
})();
