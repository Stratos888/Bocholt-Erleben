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
        const period = row.period_unit === 'pilot_month' ? ' pro Pilotmonat'
          : row.period_unit === 'concurrent' ? ' gleichzeitig' : '';
        return `${label}: ${amount}${period}`;
      })
      .join(' · ');
  }

  function limitText(gate4) {
    const parts = [];
    const event = gate4.limits?.event;
    const activity = gate4.limits?.activity;
    if (event?.available) {
      parts.push(event.is_unlimited
        ? 'Veranstaltungen: vereinbarter Rahmen'
        : `Veranstaltungen: ${Number(event.used || 0)} von ${Number(event.limit || 0)} in diesem Pilotmonat`);
    }
    if (activity?.available) {
      parts.push(`Aktivitäten: ${Number(activity.used || 0)} von ${Number(activity.limit || 0)} gleichzeitig belegt`);
    }
    return parts.join(' · ');
  }

  function contentList(rows = []) {
    if (!rows.length) return '<p class="content-note">Noch kein Inhalt für den Pilot eingereicht.</p>';
    return `<div class="content-links">${rows.slice().reverse().map(row => `
      <div class="content-link"><span>
        <strong>${escape(row.title || `${row.content_type === 'activity' ? 'Aktivität' : 'Veranstaltung'} ${row.submission_id || ''}`)}</strong>
        <small>${escape(contentStatus(row.status))}${row.start_date ? ` · ${escape(formatDate(row.start_date))}` : ''}</small>
      </span></div>
    `).join('')}</div>`;
  }

  function nextStep(gate4) {
    const rows = gate4.content_links || [];
    const first = rows[0] || null;
    const projected = gate4.next_action || {};

    if (gate4.phase === 'paused' || projected.code === 'paused') {
      return {title: 'Pilot ist pausiert', text: 'Aktuell können keine neuen Pilotinhalte eingereicht werden. Bocholt erleben klärt den nächsten Schritt.', action: 'wait'};
    }
    if (gate4.phase === 'closing' || projected.code === 'closeout') {
      return {title: 'Pilot wird abgeschlossen', text: 'Neue Pilotinhalte sind gesperrt. Die bisherigen Inhalte und Nutzungsnachweise bleiben als Verlauf erhalten.', action: 'wait'};
    }
    if (['ended_without_conversion', 'terminated', 'converted'].includes(gate4.phase) || projected.code === 'none') {
      return {title: 'Pilot abgeschlossen', text: 'Für diesen Pilot sind keine weiteren Einreichungen vorgesehen. Es entsteht keine automatische kostenpflichtige Verlängerung.', action: 'wait'};
    }
    if (projected.code === 'closeout_due') {
      return {title: 'Pilotlaufzeit beendet', text: 'Die sechsmonatige Laufzeit ist beendet. Neue Pilotinhalte sind bis zur Abschlussentscheidung gesperrt.', action: 'wait'};
    }
    if (projected.code === 'event_limit_full') {
      return {title: 'Event-Limit erreicht', text: projected.label || 'Für diesen Pilotmonat sind alle vereinbarten Event-Einheiten genutzt.', action: 'wait'};
    }
    if (projected.code === 'activity_limit_full') {
      return {title: 'Aktivitätsplatz belegt', text: 'Die vereinbarte gleichzeitige Aktivitätspräsenz ist vollständig belegt. Nach einem belegten Rückzug kann wieder eine Aktivität freigegeben werden.', action: 'wait'};
    }

    if (gate4.active && projected.code === 'submit_content') {
      return {
        title: projected.label || 'Weiteren Inhalt einreichen',
        text: 'Der Inhalt wird redaktionell geprüft. Erst eine spätere Freigabe zählt zum vereinbarten Pilotverbrauch.',
        action: 'submit', preferredType: projected.content_type || '',
      };
    }
    if (!first) {
      return {
        title: 'Als Nächstes: ersten Inhalt einreichen',
        text: 'Reiche eine Veranstaltung oder Aktivität ein. Wir prüfen und bereiten sie redaktionell vor; dadurch startet der Pilot noch nicht.',
        action: 'submit', preferredType: projected.content_type || '',
      };
    }
    if (first.status === 'draft') {
      return {title: 'Dein erster Inhalt wird geprüft', text: 'Die Einreichung ist angekommen. Du musst aktuell nichts weiter tun; wir bereiten den Inhalt redaktionell vor.', action: 'wait'};
    }
    if (first.status === 'editorial_ready' || gate4.activation_ready) {
      return {title: 'Von dir ist aktuell nichts mehr nötig', text: 'Der erste Inhalt ist vorbereitet. Bocholt erleben prüft die letzten Voraussetzungen und startet den Pilot anschließend ausdrücklich.', action: 'wait'};
    }
    if (first.status === 'rejected' || first.status === 'withdrawn') {
      return {title: 'Als Nächstes: neuen Inhalt einreichen', text: 'Für den Pilot wird ein geeigneter erster Inhalt benötigt. Reiche bitte eine neue Veranstaltung oder Aktivität ein.', action: 'submit', preferredType: projected.content_type || ''};
    }
    return {title: 'Aktueller Pilotstand', text: projected.label || 'Sobald etwas von dir benötigt wird, erscheint es hier als nächster Schritt.', action: 'wait'};
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

  function contentForm(gate4, preferredType = '') {
    const allowed = allowedTypes(gate4);
    if (!allowed.event && !allowed.activity) return '<p class="content-note">Für den aktuellen Pilotstand ist keine weitere Einreichung verfügbar.</p>';
    const preferred = preferredType === 'activity' && allowed.activity ? 'activity' : allowed.event ? 'event' : 'activity';
    return `
      <form class="content-form organizer-pilot-content-form" id="organizer-pilot-content-form" data-preferred-type="${escape(preferred)}">
        <div class="content-form-grid">
          <label class="content-field"><span class="content-field__label">Inhaltstyp</span>
            <select class="content-field__control" name="content_type">
              ${allowed.event ? `<option value="event"${preferred === 'event' ? ' selected' : ''}>Veranstaltung</option>` : ''}
              ${allowed.activity ? `<option value="activity"${preferred === 'activity' ? ' selected' : ''}>Aktivität</option>` : ''}
            </select>
          </label>
          <label class="content-field"><span class="content-field__label">Titel *</span><input class="content-field__control" name="title" required maxlength="255"></label>
          <label class="content-field"><span class="content-field__label">Ort oder Anbieter *</span><input class="content-field__control" name="location_name" required maxlength="255"></label>
          <label class="content-field" data-pilot-event-date><span class="content-field__label">Datum *</span><input class="content-field__control" name="start_date" type="date"></label>
          <label class="content-field" data-pilot-event-time><span class="content-field__label">Uhrzeit oder Zeitraum</span><input class="content-field__control" name="time_text" maxlength="64"></label>
          <label class="content-field content-field--full"><span class="content-field__label">Öffentliche Adresse oder Treffpunkt</span><input class="content-field__control" name="location_address" maxlength="500"></label>
          <label class="content-field content-field--full"><span class="content-field__label">Informationsseite</span><input class="content-field__control" name="event_url" type="url" inputmode="url"></label>
          <label class="content-field content-field--full" data-pilot-ticket-url><span class="content-field__label">Ticket- oder Anmeldelink</span><input class="content-field__control" name="ticket_url" type="url" inputmode="url"></label>
          <label class="content-field content-field--full"><span class="content-field__label">Beschreibung und Hinweise</span><textarea class="content-field__control" name="description_text" rows="4" maxlength="20000"></textarea></label>
          <label class="content-field content-field--full content-field--checkbox"><span class="content-field__hint"><input name="location_public_confirmed" type="checkbox" required> Ich darf die angegebenen Ortsdaten öffentlich nennen und diesen Inhalt einreichen.</span></label>
        </div>
        <div class="content-actions content-actions--inline"><button class="content-cta content-cta--primary" type="submit">Kostenlos zur Prüfung einreichen</button></div>
        <p class="content-note" data-pilot-submit-status aria-live="polite">Die Einreichung ist kostenlos und löst keine Zahlung aus. Eine redaktionelle Freigabe ist weiterhin erforderlich.</p>
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
    card.hidden = false;
    card.innerHTML = `
      <div class="content-form-section-head"><div><span class="content-kicker">Kostenloser Startpartner-Pilot</span><h2>${escape(phaseLabel(gate4.phase))}</h2></div></div>
      <p>${escape(gate4.active ? 'Die sechsmonatige Pilotphase läuft. Es gibt keine automatische kostenpflichtige Verlängerung.' : 'Die Pilotlaufzeit beginnt nur durch den ausdrücklichen Start und wird nicht automatisch kostenpflichtig verlängert.')}</p>
      ${successMessage ? `<div class="content-note" role="status">${escape(successMessage)}</div>` : ''}
      <section class="content-card content-card--primary organizer-pilot-next-step" aria-label="Nächster Schritt"><span class="content-kicker">Nächster Schritt</span><h3>${escape(step.title)}</h3><p>${escape(step.text)}</p></section>
      ${step.action === 'submit' ? contentForm(gate4, step.preferredType) : ''}
      <section class="organizer-pilot-content-area" aria-label="Inhalte im Pilot"><h3>Deine Inhalte</h3>${contentList(gate4.content_links)}</section>
      ${operationalNote(gate4)}
      <details class="content-disclosure"><summary>Pilotumfang und Laufzeit</summary>
        <dl class="organizer-tariff-table">
          <div><dt>Vereinbarter Umfang</dt><dd>${escape(scopeText(gate4.scopes) || 'Wird eingerichtet')}</dd></div>
          ${limitText(gate4) ? `<div><dt>Aktueller Limitstand</dt><dd>${escape(limitText(gate4))}</dd></div>` : ''}
          <div><dt>Pilotstart</dt><dd>${escape(formatDate(pilot.activation_date_local))}</dd></div>
          <div><dt>Geplantes Ende</dt><dd>${escape(formatDate(pilot.planned_end_date))}</dd></div>
          <div><dt>Freigegebene Pilotinhalte</dt><dd>${escape(String((gate4.content_links || []).filter(row => row.status === 'approved').length))}</dd></div>
        </dl>
        <p class="content-note">Der Pilot ist kostenlos und wird nicht automatisch kostenpflichtig verlängert. Veröffentlichungen bleiben redaktionell geprüft.</p>
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
    const form = document.getElementById('organizer-pilot-content-form');
    if (!form) return;
    const type = form.elements.content_type;
    const dateRow = form.querySelector('[data-pilot-event-date]');
    const timeRow = form.querySelector('[data-pilot-event-time]');
    const ticketRow = form.querySelector('[data-pilot-ticket-url]');
    const sync = () => {
      const isEvent = type.value === 'event';
      dateRow.hidden = !isEvent; timeRow.hidden = !isEvent; ticketRow.hidden = !isEvent;
      form.elements.start_date.required = isEvent;
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