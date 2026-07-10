(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const esc = value => String(value ?? '').replace(/[&<>'"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;' }[c]));
  const clean = value => String(value ?? '').trim();
  const dialog = document.querySelector('#cc-dialog');
  const body = document.querySelector('#cc-dialog-body');
  const status = document.querySelector('#cc-status');

  async function api(path, options = {}) {
    const response = await fetch(path, {
      ...options,
      headers: { 'Content-Type':'application/json', 'X-BE-Review-Password':password(), ...(options.headers || {}) },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    return payload.data;
  }

  function open(html) { body.innerHTML = html; dialog.showModal(); }
  function close() { if (dialog.open) dialog.close(); body.innerHTML = ''; }
  function field(id, label, value, type = 'text') { return `<label class="cc-field"><span>${esc(label)}</span><input id="${id}" type="${type}" value="${esc(value || '')}"></label>`; }

  async function handleReviewApprove(button) {
    const caseId = button.closest('[data-case-id]')?.dataset.caseId || '';
    if (!caseId) return;
    const detail = await api(`/api/control-center/case.php?id=${encodeURIComponent(caseId)}`);
    if (!String(detail.case_kind || '').includes('correction')) {
      await api('/api/control-center/action.php', { method:'POST', body:JSON.stringify({ case_id:caseId, action:'approve', payload:{} }) });
      window.location.reload();
      return;
    }
    const p = detail.source_payload || {};
    open(`<h2>${esc(detail.title)}</h2><p>${esc(detail.reason || '')}</p><div class="cc-stack">${field('src-url','Offizielle Quelle',p.suggested_url || p.source_url,'url')}${field('src-title','Titel',p.title)}${field('src-date','Startdatum',p.date,'date')}${field('src-end','Enddatum',p.endDate || p.end_date,'date')}${field('src-time','Uhrzeit',p.time)}${field('src-city','Stadt',p.city)}${field('src-location','Ort',p.location)}<label class="cc-field"><span>Beschreibung</span><textarea id="src-description">${esc(p.description || p.current_description || '')}</textarea></label><button type="button" class="cc-button cc-button--primary" id="src-save">Korrigieren und übernehmen</button></div>`);
    document.querySelector('#src-save').addEventListener('click', async () => {
      const v = id => clean(document.querySelector(id)?.value);
      const source = v('#src-url');
      const updates = { title:v('#src-title'), date:v('#src-date'), endDate:v('#src-end'), end_date:v('#src-end'), time:v('#src-time'), city:v('#src-city'), location:v('#src-location'), description:v('#src-description') };
      if (source) { updates.source_url = source; updates.url = source; updates.event_url = source; }
      Object.keys(updates).forEach(key => { if (!updates[key]) delete updates[key]; });
      close(); status.textContent = 'Korrektur wird gespeichert …';
      try {
        await api('/api/control-center/action.php', { method:'POST', body:JSON.stringify({ case_id:caseId, action:'approve', payload:{ event_updates:updates, note:'Über Steuerzentrale korrigiert und fachlich geprüft.' } }) });
        window.location.reload();
      } catch (error) { status.textContent = error.message; }
    });
  }

  async function handleBacklogEdit(button) {
    const caseId = button.closest('[data-case-id]')?.dataset.caseId || '';
    if (!caseId) return;
    const detail = await api(`/api/control-center/case.php?id=${encodeURIComponent(caseId)}`);
    const priority = detail.priority === 'high' ? 'hoch' : detail.priority === 'low' ? 'niedrig' : 'mittel';
    open(`<h2>Backlog bearbeiten</h2><div class="cc-stack">${field('bl-title','Titel',detail.title)}<label class="cc-field"><span>Beschreibung</span><textarea id="bl-description">${esc(detail.reason || '')}</textarea></label><label class="cc-field"><span>Priorität</span><select id="bl-priority"><option value="hoch" ${priority === 'hoch' ? 'selected' : ''}>Hoch</option><option value="mittel" ${priority === 'mittel' ? 'selected' : ''}>Mittel</option><option value="niedrig" ${priority === 'niedrig' ? 'selected' : ''}>Niedrig</option></select></label><button type="button" class="cc-button cc-button--primary" id="bl-save">Speichern</button></div>`);
    document.querySelector('#bl-save').addEventListener('click', async () => {
      const payload = { title:clean(document.querySelector('#bl-title')?.value), description:clean(document.querySelector('#bl-description')?.value), priority:clean(document.querySelector('#bl-priority')?.value) };
      close(); status.textContent = 'Backlog wird gespeichert …';
      try {
        await api('/api/control-center/action.php', { method:'POST', body:JSON.stringify({ case_id:caseId, action:'edit_source', payload }) });
        window.location.reload();
      } catch (error) { status.textContent = error.message; }
    });
  }

  async function handleActivityEdit(button) {
    const id = clean(button.dataset.manageEdit);
    const data = await api(`/api/control-center/content.php?type=activities&id=${encodeURIComponent(id)}`);
    const item = data.item || {};
    if (!item.editable) {
      open(`<h2>${esc(item.title || 'Aktivität')}</h2><p>${esc(item.edit_notice || 'Die Aktivitätsbearbeitung ist noch nicht freigeschaltet.')}</p><a class="cc-button cc-button--secondary" href="${esc(item.public_url || '/aktivitaeten/')}" target="_blank" rel="noopener">Vorschau öffnen</a>`);
      return;
    }
    open(`<h2>Aktivität bearbeiten</h2><div class="cc-stack">${field('ca-title','Titel',item.title)}${field('ca-category','Kategorie',item.category)}${field('ca-location','Ort',item.location)}${field('ca-address','Adresse / Kartenabfrage',item.address)}${field('ca-source','Offizielle Quelle',item.source_url,'url')}${field('ca-duration','Dauer',item.duration)}${field('ca-mode','Modus',item.mode)}${field('ca-price','Preis',item.price)}${field('ca-season','Saison',item.season)}${field('ca-visual','Visual Key',item.visual_key)}<label class="cc-field"><span>Beschreibung</span><textarea id="ca-description">${esc(item.description || '')}</textarea></label><button type="button" class="cc-button cc-button--primary" id="ca-save" data-content-type="activities" data-content-id="${esc(item.id)}">Speichern und Aktualisierung starten</button></div>`);
  }

  document.addEventListener('click', event => {
    const activityEdit = event.target.closest('[data-manage-edit]');
    const activitiesActive = document.querySelector('[data-manage-type="activities"].is-active');
    if (activityEdit && activitiesActive) {
      event.preventDefault();
      event.stopImmediatePropagation();
      handleActivityEdit(activityEdit).catch(error => { status.textContent = error.message; });
      return;
    }
    const approve = event.target.closest('[data-review-action="approve"]');
    const edit = event.target.closest('[data-labor-action="edit_source"]');
    if (!approve && !edit) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    (approve ? handleReviewApprove(approve) : handleBacklogEdit(edit)).catch(error => { status.textContent = error.message; });
  }, true);
})();
