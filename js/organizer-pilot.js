(() => {
  'use strict';

  const card = document.getElementById('organizer-dashboard-pilot-card');
  if (!card) return;

  const safe = value => String(value ?? '').trim();
  const escape = value => safe(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
  let pendingClientReference = '';

  function syncDashboardPrimaryActionOwner(gate4) {
    const dashboardPrimaryCta = document.getElementById('organizer-dashboard-primary-cta');
    if (!dashboardPrimaryCta) return;

    const phase = safe(gate4?.phase);
    const pilotOwnsPrimaryAction = ['onboarding', 'activation_ready', 'active', 'paused', 'closing'].includes(phase);
    const dashboardActions = dashboardPrimaryCta.closest('.content-actions');

    if (dashboardActions) {
      dashboardActions.hidden = pilotOwnsPrimaryAction;
      if (pilotOwnsPrimaryAction) {
        dashboardActions.dataset.startpartnerPrimaryOwner = 'pilot';
      } else {
        delete dashboardActions.dataset.startpartnerPrimaryOwner;
      }
      return;
    }

    dashboardPrimaryCta.hidden = pilotOwnsPrimaryAction;
    if (pilotOwnsPrimaryAction) {
      dashboardPrimaryCta.dataset.startpartnerPrimaryOwner = 'pilot';
    } else {
      delete dashboardPrimaryCta.dataset.startpartnerPrimaryOwner;
    }
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      cache: 'no-store',
      ...options,
      headers: {
        Accept: 'application/json',
        ...(options.headers || {}),
      },
    });
    const data = await response.json().catch(() => null);
    if (!response.ok) {
      const error = new Error(data?.message || 'Anfrage fehlgeschlagen.');
      error.status = response.status;
      error.code = data?.code || '';
      throw error;
    }
    return data?.data || data;
  }

  function phaseLabel(phase) {
    return ({
      onboarding: 'Pilot wird eingerichtet',
      activation_ready: 'Pilot ist startbereit',
      active: 'Pilotphase läuft',
      paused: 'Pilot ist pausiert',
      closing: 'Pilot wird abgeschlossen',
      ended_without_conversion: 'Pilot ist abgeschlossen',
      terminated: 'Pilot wurde beendet',
      converted: 'Pilot ist abgeschlossen',
    })[phase] || 'Pilot wird eingerichtet';
  }

  function contentStatus(status) {
    return ({
      draft: 'Zur Prüfung eingereicht', editorial_ready: 'Redaktionell vorbereitet',
      approved: 'Freigegeben', rejected: 'Nicht freigegeben', withdrawn: 'Zurückgezogen',
    })[status] || 'In Bearbeitung';
  }

  function formatDate(value) {
    const text = safe(value).slice(0, 10);
    if (!/^\d{4}-\d{2}-\d{2}$/.test(text)) return text || 'Noch offen';
    const [year, month, day] = text.split('-');
    return `${day}.${month}.${year}`;
  }

  function scopeText(rows = []) {
    return rows
      .filter(row => ['events', 'activities'].includes(row.scope_key))
      .map(row => {
        const label = row.scope_key === 'events' ? 'Veranstaltungen' : 'Aktivitäten';
        const amount = row.is_unlimited ? 'vereinbarter Rahmen' : row.limit_value || '–';
        const period = row.period_unit === 'pilot_month' ? ' pro Monat'
          : row.period_unit === 'concurrent' ? ' gleichzeitig' : '';
        return `${label}: ${amount}${period}`;
      })
      .join(' · ');
  }

  function usageText(gate4) {
    const parts = [];
    const event = gate4.limits?.event;
    const activity = gate4.limits?.activity;
    if (event?.available) {
      parts.push(event.is_unlimited
        ? 'Veranstaltungen: vereinbarter Rahmen'
        : `Veranstaltungen: ${Number(event.used || 0)} von ${Number(event.limit || 0)} in diesem Monat`);
    }
    if (activity?.available) {
      parts.push(`Aktivitäten: ${Number(activity.used || 0)} von ${Number(activity.limit || 0)} gleichzeitig belegt`);
    }
    return parts.join(' · ');
  }

  function contentRow(row) {
    return `<div class="content-link"><span>
      <strong>${escape(row.title || `${row.content_type === 'activity' ? 'Aktivität' : 'Veranstaltung'} ${row.submission_id || ''}`)}</strong>
      <small>${escape(contentStatus(row.status))}${row.start_date ? ` · ${escape(formatDate(row.start_date))}` : ''}</small>
    </span></div>`;
  }

  function contentList(rows = []) {
    if (!rows.length) return '<p class="content-note">Noch keine Inhalte eingereicht.</p>';
    const newest = rows.slice().reverse();
    const visible = newest.slice(0, 3);
    const older = newest.slice(3);
    return `<div class="content-links">${visible.map(contentRow).join('')}</div>${older.length ? `
      <details class="content-disclosure"><summary>Weitere Inhalte anzeigen (${older.length})</summary>
        <div class="content-links">${older.map(contentRow).join('')}</div>
      </details>` : ''}`;
  }

  function allowedTypes(gate4) {
    const eventScope = (gate4.scopes || []).some(row => row.scope_key === 'events');
    const activityScope = (gate4.scopes || []).some(row => row.scope_key === 'activities');
    const eventFull = Boolean(gate4.active && gate4.limits?.event?.full);
    const activityFull = Boolean(gate4.active && gate4.limits?.activity?.full);
    return {
      event: eventScope && !eventFull,
      activity: activityScope && !activityFull,
    };
  }

  function submitCopy(gate4, preferredType = '') {
    const allowed = allowedTypes(gate4);
    if (preferredType === 'event' && allowed.event) {
      return {title: 'Nächste Veranstaltung einreichen', cta: 'Veranstaltung einreichen', preferredType: 'event'};
    }
    if (preferredType === 'activity' && allowed.activity) {
      return {title: 'Nächste Aktivität einreichen', cta: 'Aktivität einreichen', preferredType: 'activity'};
    }
    if (allowed.event && !allowed.activity) {
      return {title: 'Nächste Veranstaltung einreichen', cta: 'Veranstaltung einreichen', preferredType: 'event'};
    }
    if (allowed.activity && !allowed.event) {
      return {title: 'Nächste Aktivität einreichen', cta: 'Aktivität einreichen', preferredType: 'activity'};
    }
    return {title: 'Weiteren Inhalt einreichen', cta: 'Inhalt einreichen', preferredType: preferredType || 'event'};
  }

  function nextStep(gate4) {
    const rows = gate4.content_links || [];
    const first = rows[0] || null;
    const projected = gate4.next_action || {};

    if (gate4.phase === 'paused' || projected.code === 'paused') {
      return {title: 'Aktuell nichts zu tun', text: 'Während der Pause sind keine neuen Einreichungen möglich. Bocholt erleben klärt mit dir das weitere Vorgehen.', action: 'wait'};
    }
    if (gate4.phase === 'closing' || projected.code === 'closeout') {
      return {title: 'Aktuell nichts zu tun', text: 'Der Pilot wird abgeschlossen. Neue Einreichungen sind nicht mehr vorgesehen.', action: 'wait'};
    }
    if (['ended_without_conversion', 'terminated', 'converted'].includes(gate4.phase) || projected.code === 'none') {
      return {title: 'Keine weitere Aktion erforderlich', text: 'Der Pilot ist abgeschlossen. Es entsteht keine automatische kostenpflichtige Verlängerung.', action: 'wait'};
    }
    if (projected.code === 'closeout_due') {
      return {title: 'Aktuell nichts zu tun', text: 'Die sechsmonatige Laufzeit ist beendet. Bocholt erleben klärt mit dir den Abschluss.', action: 'wait'};
    }
    if (projected.code === 'event_limit_full') {
      return {title: 'Aktuell keine weitere Veranstaltung möglich', text: 'Die vereinbarten Veranstaltungen für diesen Monat sind bereits ausgeschöpft.', action: 'wait'};
    }
    if (projected.code === 'activity_limit_full') {
      return {title: 'Aktuell keine weitere Aktivität möglich', text: 'Der vereinbarte Aktivitätsplatz ist belegt. Sobald er wieder frei ist, kannst du eine weitere Aktivität einreichen.', action: 'wait'};
    }

    if (gate4.active && projected.code === 'submit_content') {
      const copy = submitCopy(gate4, projected.content_type || '');
      return {
        ...copy,
        text: 'Wir prüfen deine Angaben redaktionell vor jeder Veröffentlichung.',
        action: 'submit',
      };
    }
    if (!first) {
      const copy = submitCopy(gate4, projected.content_type || '');
      return {
        ...copy,
        title: copy.title.replace('Nächste', 'Erste').replace('Weiteren', 'Ersten'),
        text: 'Reiche den ersten Inhalt ein. Wir prüfen und bereiten ihn redaktionell vor; dadurch startet der Pilot noch nicht.',
        action: 'submit',
      };
    }
    if (first.status === 'draft') {
      return {title: 'Aktuell nichts zu tun', text: 'Deine Einreichung ist angekommen und wird redaktionell geprüft.', action: 'wait'};
    }
    if (first.status === 'editorial_ready' || gate4.activation_ready) {
      return {title: 'Aktuell nichts zu tun', text: 'Dein erster Inhalt ist vorbereitet. Bocholt erleben prüft die letzten Voraussetzungen und startet den Pilot anschließend ausdrücklich.', action: 'wait'};
    }
    if (first.status === 'rejected' || first.status === 'withdrawn') {
      const copy = submitCopy(gate4, projected.content_type || '');
      return {
        ...copy,
        title: copy.title.replace('Nächste', 'Neue').replace('Weiteren', 'Neuen'),
        text: 'Für den Pilot wird ein geeigneter Inhalt benötigt. Reiche bitte einen neuen Inhalt ein.',
        action: 'submit',
      };
    }
    return {title: 'Aktuell nichts zu tun', text: 'Sobald etwas von dir benötigt wird, erscheint es hier als nächster Schritt.', action: 'wait'};
  }

  function contentForm(gate4, preferredType = '') {
    const allowed = allowedTypes(gate4);
    if (!allowed.event && !allowed.activity) return '<p class="content-note">Für den aktuellen Pilotstand ist keine weitere Einreichung verfügbar.</p>';
    const preferred = preferredType === 'activity' && allowed.activity ? 'activity' : allowed.event ? 'event' : 'activity';
    const typeField = allowed.event && allowed.activity ? `
          <label class="content-field"><span class="content-field__label">Was möchtest du einreichen?</span>
            <select class="content-field__control" name="content_type">
              <option value="event"${preferred === 'event' ? ' selected' : ''}>Veranstaltung</option>
              <option value="activity"${preferred === 'activity' ? ' selected' : ''}>Aktivität</option>
            </select>
          </label>` : `<input name="content_type" type="hidden" value="${allowed.event ? 'event' : 'activity'}">`;
    return `
      <form class="content-form organizer-pilot-content-form" id="organizer-pilot-content-form" data-preferred-type="${escape(preferred)}">
        <div class="content-form-grid">
          ${typeField}
          <label class="content-field"><span class="content-field__label" data-pilot-title-label>Titel der Veranstaltung *</span><input class="content-field__control" name="title" required maxlength="255"></label>
          <label class="content-field"><span class="content-field__label" data-pilot-location-label>Veranstaltungsort / Location *</span><input class="content-field__control" name="location_name" required maxlength="255"></label>
          <label class="content-field" data-pilot-event-date><span class="content-field__label">Datum *</span><input class="content-field__control" name="start_date" type="date"></label>
          <label class="content-field" data-pilot-event-time><span class="content-field__label">Uhrzeit</span><input class="content-field__control" name="time_text" maxlength="64"></label>
          <label class="content-field content-field--full"><span class="content-field__label" data-pilot-address-label>Straße, Hausnummer / offizieller Treffpunkt</span><input class="content-field__control" name="location_address" maxlength="500"></label>
          <label class="content-field content-field--full"><span class="content-field__label" data-pilot-url-label>Link zur Veranstaltungsseite</span><input class="content-field__control" name="event_url" type="url" inputmode="url"></label>
          <label class="content-field content-field--full" data-pilot-ticket-url><span class="content-field__label">Ticket- oder Anmeldelink</span><input class="content-field__control" name="ticket_url" type="url" inputmode="url"></label>
          <label class="content-field content-field--full"><span class="content-field__label" data-pilot-description-label>Kurze Beschreibung / Hinweise</span><textarea class="content-field__control" name="description_text" rows="4" maxlength="20000"></textarea></label>
          <label class="content-field content-field--full content-field--checkbox"><span class="content-field__hint"><input name="location_public_confirmed" type="checkbox" required> Ich bestätige, dass ich berechtigt bin, diesen Inhalt einzureichen, und dass der Ort öffentlich genannt werden darf.</span></label>
        </div>
        <div class="content-actions content-actions--inline"><button class="content-cta content-cta--primary" type="submit">Zur Prüfung einreichen</button></div>
        <p class="content-note" data-pilot-submit-status aria-live="polite">Die Einreichung ist kostenlos und löst keine Zahlung aus. Veröffentlichung erst nach redaktioneller Freigabe.</p>
      </form>`;
  }

  function operationalNote(gate4) {
    const distribution = gate4.distribution || {};
    const measurement = gate4.measurement || {};
    const notes = [];
    if (distribution.status === 'due') notes.push('Der vereinbarte Reichweitenbeitrag ist fällig und wird von Bocholt erleben nachgehalten.');
    if (distribution.status === 'blocked') notes.push('Beim vereinbarten Reichweitenbeitrag besteht aktuell Klärungsbedarf.');
    if (measurement.status === 'query_or_attribution_problem') notes.push('Die technische Erfolgsmessung wird aktuell geprüft; daraus wird keine Nullnutzung abgeleitet.');
    if (measurement.status === 'no_data_yet_or_too_short') notes.push('Für eine belastbare Nutzungsbewertung liegen noch nicht genügend abgeschlossene Messdaten vor.');
    return notes.length ? `<p class="content-note">${escape(notes.join(' '))}</p>` : '';
  }

  function render(data, successMessage = '') {
    const gate4 = data.gate4 || {};
    const pilot = gate4.pilot || {};
    const step = nextStep(gate4);
    syncDashboardPrimaryActionOwner(gate4);
    const submitBlock = step.action === 'submit' ? `
      <div class="content-actions content-actions--inline">
        <button class="content-cta content-cta--primary" id="organizer-pilot-open-form" type="button">${escape(step.cta)}</button>
      </div>
      <div id="organizer-pilot-form-shell" hidden>${contentForm(gate4, step.preferredType)}</div>` : '';
    card.hidden = false;
    card.innerHTML = `
      <div class="content-form-section-head"><div><span class="content-kicker">Startpartner · 6 Monate kostenlos</span><h2>${escape(phaseLabel(gate4.phase))}</h2></div></div>
      ${successMessage ? `<div class="content-note" role="status">${escape(successMessage)}</div>` : ''}
      <section class="organizer-pilot-next-step" aria-label="Nächster Schritt">
        <span class="content-kicker">Nächster Schritt</span><h3>${escape(step.title)}</h3><p class="content-note">${escape(step.text)}</p>${submitBlock}
      </section>
      <section class="organizer-pilot-content-area" aria-label="Inhalte im Pilot"><h3>Deine Inhalte</h3>${contentList(gate4.content_links)}</section>
      ${operationalNote(gate4)}
      <details class="content-disclosure"><summary>Pilotdetails</summary>
        <dl class="organizer-tariff-table">
          <div><dt>Vereinbarter Rahmen</dt><dd>${escape(scopeText(gate4.scopes) || 'Wird eingerichtet')}</dd></div>
          ${usageText(gate4) ? `<div><dt>Aktuelle Nutzung</dt><dd>${escape(usageText(gate4))}</dd></div>` : ''}
          <div><dt>Pilotstart</dt><dd>${escape(formatDate(pilot.activation_date_local))}</dd></div>
          <div><dt>Geplantes Ende</dt><dd>${escape(formatDate(pilot.planned_end_date))}</dd></div>
          <div><dt>Freigegebene Inhalte</dt><dd>${escape(String((gate4.content_links || []).filter(row => row.status === 'approved').length))}</dd></div>
        </dl>
        <p class="content-note">Keine Zahlungsart erforderlich und keine automatische kostenpflichtige Verlängerung. Veröffentlichungen bleiben redaktionell geprüft.</p>
      </details>`;
    bindForm();
  }

  function friendlyError(error) {
    if (error?.status === 401) return 'Dein Veranstalterzugang ist abgelaufen. Bitte fordere einen neuen Zugangslink an.';
    if (error?.status === 409) return error.message || 'Diese Einreichung wurde bereits mit anderen Angaben gespeichert. Bitte prüfe den aktuellen Stand.';
    if (error?.status === 422) return error.message || 'Bitte prüfe die angegebenen Daten und den aktuellen Pilotstand.';
    return 'Die Einreichung konnte gerade nicht eindeutig bestätigt werden. Du kannst erneut senden; derselbe Versuch wird nicht doppelt angelegt.';
  }

  async function load(successMessage = '') {
    const data = await requestJson('/api/organizer-portal/pilot.php');
    render(data, successMessage);
  }

  function newClientReference() {
    return `gate4-344-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;
  }

  function bindForm() {
    const openButton = document.getElementById('organizer-pilot-open-form');
    const formShell = document.getElementById('organizer-pilot-form-shell');
    const form = document.getElementById('organizer-pilot-content-form');
    if (openButton && formShell && form) {
      openButton.addEventListener('click', () => {
        openButton.hidden = true;
        formShell.hidden = false;
        form.querySelector('select:not([hidden]), input:not([type="hidden"])')?.focus();
      });
    }
    if (!form) return;
    const type = form.elements.content_type;
    const dateRow = form.querySelector('[data-pilot-event-date]');
    const timeRow = form.querySelector('[data-pilot-event-time]');
    const ticketRow = form.querySelector('[data-pilot-ticket-url]');
    const titleLabel = form.querySelector('[data-pilot-title-label]');
    const locationLabel = form.querySelector('[data-pilot-location-label]');
    const addressLabel = form.querySelector('[data-pilot-address-label]');
    const urlLabel = form.querySelector('[data-pilot-url-label]');
    const descriptionLabel = form.querySelector('[data-pilot-description-label]');
    const sync = () => {
      const isEvent = type.value === 'event';
      dateRow.hidden = !isEvent; timeRow.hidden = !isEvent; ticketRow.hidden = !isEvent;
      form.elements.start_date.required = isEvent;
      titleLabel.textContent = isEvent ? 'Titel der Veranstaltung *' : 'Name der Aktivität *';
      locationLabel.textContent = isEvent ? 'Veranstaltungsort / Location *' : 'Name des Standorts *';
      addressLabel.textContent = isEvent ? 'Straße, Hausnummer / offizieller Treffpunkt' : 'Adresse / offizieller Treffpunkt';
      urlLabel.textContent = isEvent ? 'Link zur Veranstaltungsseite' : 'Website / Buchungslink';
      descriptionLabel.textContent = isEvent ? 'Kurze Beschreibung / Hinweise' : 'Kurzbeschreibung der Aktivität';
      if (!isEvent) {
        form.elements.start_date.value = ''; form.elements.time_text.value = ''; form.elements.ticket_url.value = '';
      }
    };
    type.addEventListener('change', sync); sync();

    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const button = form.querySelector('button[type="submit"]');
      const status = form.querySelector('[data-pilot-submit-status]');
      button.disabled = true;
      status.textContent = 'Einreichung wird gespeichert …';
      if (!pendingClientReference) pendingClientReference = newClientReference();
      const values = Object.fromEntries(new FormData(form).entries());
      const payload = {
        ...values,
        location_public_confirmed: form.elements.location_public_confirmed.checked,
        client_reference: pendingClientReference,
      };
      try {
        const result = await requestJson('/api/startpartner/content.php', {
          method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload),
        });
        const id = result.submission_id || result.content_link?.submission_id || '';
        pendingClientReference = '';
        form.reset(); sync();
        await load(`Einreichung ${id} wurde gespeichert und wird redaktionell geprüft.`);
      } catch (error) {
        status.textContent = friendlyError(error);
      } finally {
        button.disabled = false;
      }
    });
  }

  load().catch(error => {
    if (![401, 404].includes(error.status)) {
      card.hidden = false;
      card.innerHTML = '<p class="content-note">Der Startpartner-Pilot konnte gerade nicht geladen werden.</p>';
    }
  });
})();