(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const clean = value => String(value ?? '').trim();
  const status = document.querySelector('#cc-status');
  const dialog = document.querySelector('#cc-dialog');
  const dialogBody = document.querySelector('#cc-dialog-body');
  let selectedEventId = '';

  async function api(path, options = {}, allowAccepted = false) {
    const response = await fetch(path, {
      ...options,
      headers: { 'Content-Type': 'application/json', 'X-BE-Review-Password': password(), ...(options.headers || {}) },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok && !(allowAccepted && response.status === 202)) throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    return payload.data || {};
  }

  const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));
  function closeDialog() { if (dialog?.open) dialog.close(); if (dialogBody) dialogBody.innerHTML = ''; }
  const value = selector => clean(document.querySelector(selector)?.value);

  function eventUpdates() {
    return {
      title: value('#ce-title'), date: value('#ce-date'), end_date: value('#ce-end'), time: value('#ce-time'),
      location: value('#ce-location'), address: value('#ce-address'), city: value('#ce-city'), category: value('#ce-category'),
      source_url: value('#ce-source'), ticket_url: value('#ce-ticket'), description: value('#ce-description'),
    };
  }

  function activityUpdates() {
    return {
      title: value('#ca-title'), kategorie: value('#ca-category'), location: value('#ca-location'), maps_query: value('#ca-address'),
      url: value('#ca-source'), duration: value('#ca-duration'), mode: value('#ca-mode'), price: value('#ca-price'),
      season: value('#ca-season'), visual_key: value('#ca-visual'), description: value('#ca-description'),
    };
  }

  async function verifyPublication(changeId, type) {
    const deadline = Date.now() + 5 * 60 * 1000;
    let attempt = 0;
    while (Date.now() < deadline) {
      attempt += 1;
      status.textContent = `Änderung gespeichert. Öffentliche Aktualisierung wird geprüft (${attempt}) …`;
      try {
        const data = await api(`/api/control-center/publication.php?id=${encodeURIComponent(changeId)}`, {}, true);
        if (data.verified) {
          status.textContent = `Änderung ist im öffentlichen ${type === 'activities' ? 'Aktivitätsbestand' : 'Eventbestand'} bestätigt. Ansicht wird aktualisiert …`;
          await sleep(900);
          window.location.reload();
          return true;
        }
      } catch (error) {
        status.textContent = error.message;
        return false;
      }
      await sleep(5000);
    }
    status.textContent = 'Die öffentliche Aktualisierung ist noch nicht bestätigt. Ein ausstehender Vorgang bleibt unter Arbeit nachvollziehbar gespeichert.';
    return false;
  }

  async function saveContent(button) {
    const type = button.dataset.contentType || 'events';
    const id = clean(button.dataset.contentId || button.dataset.eventId || selectedEventId);
    if (!id) throw new Error('Inhalts-ID fehlt.');
    const updates = type === 'activities' ? activityUpdates() : eventUpdates();
    closeDialog();
    status.textContent = 'Änderung wird validiert und gespeichert …';
    const result = await api('/api/control-center/content.php', {
      method: 'POST',
      body: JSON.stringify({ type, id, updates, deploy: true }),
    });
    status.textContent = result.message || 'Änderung gespeichert. Aktualisierung wurde gestartet.';
    if (result.change_id) await verifyPublication(result.change_id, type);
  }

  document.addEventListener('click', event => {
    const edit = event.target.closest('[data-manage-edit]');
    if (edit) selectedEventId = clean(edit.dataset.manageEdit);
    const button = event.target.closest('#ce-save, #ca-save');
    if (!button) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    button.disabled = true;
    saveContent(button).catch(error => { status.textContent = error instanceof Error ? error.message : String(error); });
  }, true);

  const observer = new MutationObserver(() => {
    const button = document.querySelector('#ce-save');
    if (!button || button.dataset.publicationEnhanced === '1') return;
    button.dataset.publicationEnhanced = '1';
    button.dataset.contentType = 'events';
    button.dataset.contentId = button.dataset.eventId || selectedEventId;
    button.textContent = 'Speichern und Aktualisierung starten';
  });
  if (dialogBody) observer.observe(dialogBody, { childList: true, subtree: true });
})();
