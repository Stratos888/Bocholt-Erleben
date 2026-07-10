(() => {
  'use strict';

  const password = () => sessionStorage.getItem('be_cc_password') || '';
  const clean = value => String(value ?? '').trim();
  const status = document.querySelector('#cc-status');
  const dialog = document.querySelector('#cc-dialog');
  const dialogBody = document.querySelector('#cc-dialog-body');

  async function api(path, options = {}, allowAccepted = false) {
    const response = await fetch(path, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-BE-Review-Password': password(),
        ...(options.headers || {}),
      },
      cache: 'no-store',
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok && !(allowAccepted && response.status === 202)) {
      throw new Error(payload.message || 'Anfrage fehlgeschlagen.');
    }
    return payload.data || {};
  }

  const sleep = milliseconds => new Promise(resolve => setTimeout(resolve, milliseconds));

  function closeDialog() {
    if (dialog?.open) dialog.close();
    if (dialogBody) dialogBody.innerHTML = '';
  }

  function collectUpdates() {
    const value = selector => clean(document.querySelector(selector)?.value);
    return {
      title: value('#ce-title'),
      date: value('#ce-date'),
      end_date: value('#ce-end'),
      time: value('#ce-time'),
      location: value('#ce-location'),
      city: value('#ce-city'),
      category: value('#ce-category'),
      source_url: value('#ce-source'),
      ticket_url: value('#ce-ticket'),
      description: value('#ce-description'),
    };
  }

  async function verifyPublication(changeId) {
    const deadline = Date.now() + 5 * 60 * 1000;
    let attempt = 0;
    while (Date.now() < deadline) {
      attempt += 1;
      status.textContent = `Änderung gespeichert. Öffentliche Aktualisierung wird geprüft (${attempt}) …`;
      try {
        const data = await api(`/api/control-center/publication.php?id=${encodeURIComponent(changeId)}`, {}, true);
        if (data.verified) {
          status.textContent = 'Änderung ist im öffentlichen Eventbestand bestätigt.';
          return true;
        }
      } catch (error) {
        status.textContent = error.message;
        return false;
      }
      await sleep(5000);
    }
    status.textContent = 'Die öffentliche Aktualisierung ist noch nicht bestätigt. Ein ausstehender Vorgang bleibt nachvollziehbar gespeichert.';
    return false;
  }

  async function saveEvent(button) {
    const eventId = clean(button.dataset.eventId);
    if (!eventId) throw new Error('Event-ID fehlt.');
    const updates = collectUpdates();
    closeDialog();
    status.textContent = 'Änderung wird validiert und gespeichert …';
    const result = await api('/api/control-center/content.php', {
      method: 'POST',
      body: JSON.stringify({ type: 'events', id: eventId, updates, deploy: true }),
    });
    status.textContent = result.message || 'Änderung gespeichert. Aktualisierung wurde gestartet.';
    if (result.change_id) await verifyPublication(result.change_id);
  }

  document.addEventListener('click', event => {
    const button = event.target.closest('#ce-save');
    if (!button) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    button.disabled = true;
    saveEvent(button).catch(error => {
      status.textContent = error instanceof Error ? error.message : String(error);
    });
  }, true);

  const observer = new MutationObserver(() => {
    const button = document.querySelector('#ce-save');
    if (!button || button.dataset.publicationEnhanced === '1') return;
    button.dataset.publicationEnhanced = '1';
    button.textContent = 'Speichern und Aktualisierung starten';
    const title = document.querySelector('#ce-title');
    const eventId = new URLSearchParams(button.dataset.context || '').get('id');
    if (eventId) button.dataset.eventId = eventId;
    if (!button.dataset.eventId) {
      const heading = dialogBody?.querySelector('[data-event-id]');
      if (heading) button.dataset.eventId = heading.dataset.eventId;
    }
    if (title && !button.dataset.eventId) button.dataset.eventId = title.dataset.eventId || '';
  });
  if (dialogBody) observer.observe(dialogBody, { childList: true, subtree: true });
})();
