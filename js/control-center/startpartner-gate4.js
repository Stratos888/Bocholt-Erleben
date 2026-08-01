import {
  escapeHtml, clean, asArray, formatDate, formatDateTime, api, openDialog,
  closeDialog, dialogMessage, field, textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';

const itemLabels = {
  terms_confirmed: 'Pilotbedingungen bestätigt',
  organizer_linked: 'Organizer verknüpft',
  contact_confirmed: 'Ansprechpartner bestätigt',
  portal_access_tested: 'Portalzugang getestet',
  pilot_entitlement_readback: 'Pilotberechtigung zurückgelesen',
  service_scope_confirmed: 'Serviceumfang bestätigt',
  sources_recorded: 'Quellen erfasst',
  maintenance_path_agreed: 'Pflegeweg vereinbart',
  content_rights_cleared: 'Inhalts- und Bildrechte geklärt',
  first_content_ready: 'Erster Inhalt vorbereitet',
  editorial_review_ready: 'Redaktionelle Prüfung möglich',
  measurement_ready: 'Messzuordnung technisch geprüft',
  distribution_ready: 'Partner-Reichweitenstart vorbereitet',
  activation_target_set: 'Aktivierungszieltermin festgelegt',
};

const statusLabels = {
  pending: 'Offen',
  complete: 'Belegt',
  blocked: 'Blockiert',
  not_applicable: 'Nicht anwendbar',
};

const contentStatusLabels = {
  draft: 'Eingereicht',
  editorial_ready: 'Redaktionell bereit',
  approved: 'Veröffentlicht',
  rejected: 'Abgelehnt',
  withdrawn: 'Zurückgezogen',
};

export function gate4PhaseLabel(phase) {
  return ({
    onboarding: 'Pilot-Onboarding',
    activation_ready: 'Aktivierungsbereit',
    active: 'Pilot aktiv',
  })[phase] || 'Pilot-Onboarding';
}

export function gate4PriorityMessage(gate4 = {}) {
  if (gate4.active) {
    return 'Pilot, Pilotberechtigung und erster Inhalt sind aktiv. Die sechsmonatige Laufzeit wurde gestartet.';
  }
  if (gate4.activation_ready) {
    return 'Alle Aktivierungsbedingungen sind technisch und fachlich belegt. Die Aktivierung ist noch nicht ausgeführt.';
  }
  return gate4.blockers?.[0]?.message || 'Der nächste verbindliche Onboardingpunkt ist noch offen.';
}

function itemRow(row, active) {
  const key = clean(row.item_key);
  const status = clean(row.status) || 'pending';
  const canEdit = Number(row.is_manual || 0) === 1 && !active;
  const evidence = clean(row.evidence_text || row.evidence_reference);
  return `<article class="cc-startpartner-dimension cc-startpartner-dimension--${escapeHtml(status)}" data-gate4-item="${escapeHtml(key)}">
    <header>
      <strong>${escapeHtml(itemLabels[key] || key)}</strong>
      <span>${escapeHtml(statusLabels[status] || status)}</span>
    </header>
    ${evidence ? `<small>${escapeHtml(evidence)}</small>` : '<small>Noch kein belastbarer Nachweis.</small>'}
    ${canEdit ? `<div class="cc-actions">
      <button class="cc-button cc-button--secondary" data-review-action="gate4:item:${escapeHtml(key)}:complete">Als belegt speichern</button>
      <button class="cc-button cc-button--secondary" data-review-action="gate4:item:${escapeHtml(key)}:blocked">Blocker erfassen</button>
    </div>` : ''}
  </article>`;
}

function contentRows(rows = [], active = false) {
  if (!rows.length) {
    return '<p class="cc-muted">Noch kein Pilotinhalt eingereicht.</p>';
  }
  return `<div class="cc-stack">${rows.map(row => `<article class="cc-startpartner-state-card">
    <span class="cc-kicker">${escapeHtml(row.content_type === 'activity' ? 'Aktivität' : 'Veranstaltung')}</span>
    <strong>${escapeHtml(row.title || `Einreichung ${row.submission_id || ''}`)}</strong>
    <p>${escapeHtml(contentStatusLabels[row.status] || row.status || 'In Bearbeitung')}${row.start_date ? ` · ${escapeHtml(formatDate(row.start_date))}` : ''}</p>
    ${row.status === 'draft' && !active ? `<button class="cc-button cc-button--secondary" data-review-action="gate4:content:${escapeHtml(row.id)}">Redaktionell bereit markieren</button>` : ''}
  </article>`).join('')}</div>`;
}

function readinessSummary(gate4) {
  const measurement = gate4.ready_measurement;
  const distribution = gate4.ready_distribution;
  return `<section class="cc-startpartner-grid">
    <section class="cc-startpartner-panel">
      <span class="cc-kicker">Messpreflight</span>
      <h3>${measurement ? 'Technisch belegt' : 'Noch offen'}</h3>
      <p>${measurement
        ? `Messdaten-Owner ${escapeHtml(measurement.metrics_owner || 'value_metric_daily')} · geprüft ${escapeHtml(formatDateTime(measurement.checked_at) || '–')}`
        : 'Vor der Aktivierung wird der kanonische Messdaten-Owner read-only zurückgelesen.'}</p>
      ${!gate4.active ? '<button class="cc-button cc-button--secondary" data-review-action="gate4:measurement">Messpreflight bearbeiten</button>' : ''}
    </section>
    <section class="cc-startpartner-panel">
      <span class="cc-kicker">Partnerdistribution</span>
      <h3>${distribution ? 'Konkret vorbereitet' : 'Noch offen'}</h3>
      <p>${distribution
        ? `${escapeHtml(distribution.channel || 'Kanal')} · ${escapeHtml(formatDate(distribution.planned_at) || '–')}`
        : 'Kanal, Termin, Zielreferenz und Nachweis müssen konkret dokumentiert sein.'}</p>
      ${!gate4.active ? '<button class="cc-button cc-button--secondary" data-review-action="gate4:distribution">Distribution bearbeiten</button>' : ''}
    </section>
  </section>`;
}

export function renderGate4Panel(candidate = {}) {
  const gate4 = candidate.gate4 || {};
  const pilot = gate4.pilot;
  if (!pilot) return '';

  const onboarding = gate4.onboarding || {};
  const first = gate4.first_content || {};
  const stateAction = gate4.activation_ready
    ? '<button class="cc-button cc-button--primary cc-button--large" data-review-action="gate4:activate">Pilot verbindlich aktivieren</button>'
    : gate4.active
      ? '<div class="cc-notice cc-notice--success"><strong>Sechsmonatige Pilotphase läuft</strong><span>Aktivierung, Pilotberechtigung und erster Inhalt sind konsistent zurückgelesen.</span></div>'
      : `<div class="cc-notice cc-notice--info"><strong>Nächster Blocker</strong><span>${escapeHtml(gate4PriorityMessage(gate4))}</span></div>`;

  return `<section class="cc-startpartner-panel" data-gate4-panel>
    <header>
      <div>
        <span class="cc-kicker">Onboarding, Inhalt und Aktivierung</span>
        <h3>${escapeHtml(gate4PhaseLabel(gate4.phase))}</h3>
      </div>
      <span class="cc-pill">${escapeHtml(`${Number(onboarding.completed_count || 0)} / ${Number(onboarding.total_count || 14)} belegt`)}</span>
    </header>
    <dl class="cc-startpartner-facts">
      <div><dt>Aktivierung</dt><dd>${escapeHtml(pilot.activation_date_local ? formatDate(pilot.activation_date_local) : 'Noch nicht gestartet')}</dd></div>
      <div><dt>Geplantes Ende</dt><dd>${escapeHtml(pilot.planned_end_date ? formatDate(pilot.planned_end_date) : 'Noch offen')}</dd></div>
      <div><dt>Erster Inhalt</dt><dd>${escapeHtml(contentStatusLabels[first.status] || first.status || 'Noch nicht bereit')}</dd></div>
      <div><dt>Pilot-Revision</dt><dd>${escapeHtml(String(pilot.revision || 1))}</dd></div>
    </dl>
    ${stateAction}
    <details class="cc-disclosure">
      <summary>Onboarding-Checkliste</summary>
      <div class="cc-startpartner-qualification-group">
        <div>${asArray(onboarding.items).map(row => itemRow(row, gate4.active)).join('')}</div>
      </div>
    </details>
    <details class="cc-disclosure">
      <summary>Pilotinhalte</summary>
      ${contentRows(gate4.content_links, gate4.active)}
    </details>
    ${readinessSummary(gate4)}
  </section>`;
}

async function latest(item) {
  return api(`/api/control-center/case.php?id=${encodeURIComponent(item.id)}`, {timeoutMs: 15000});
}

function berlinToday() {
  const parts = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Europe/Berlin',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(new Date());
  const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
  return `${values.year}-${values.month}-${values.day}`;
}

function addCalendarMonths(valueText, months = 6) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(clean(valueText));
  if (!match) return '';
  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const monthIndex = year * 12 + month - 1 + months;
  const targetYear = Math.floor(monthIndex / 12);
  const targetMonth = monthIndex % 12;
  const lastDay = new Date(Date.UTC(targetYear, targetMonth + 1, 0)).getUTCDate();
  return `${targetYear}-${String(targetMonth + 1).padStart(2, '0')}-${String(Math.min(day, lastDay)).padStart(2, '0')}`;
}

function operator(data) {
  return clean(data.assigned_to) || 'Steuerzentrale';
}

async function mutate(path, data, payload, reload, success) {
  try {
    const result = await api(path, {
      method: 'POST',
      body: JSON.stringify({
        candidate_id: data.id,
        operation_id: `gate4:241:${operationId().replace(/^cc:/, '')}`,
        expected_revision: Number(data.revision),
        expected_pilot_revision: Number(data.gate4?.pilot?.revision || 0),
        operator_name: operator(data),
        ...payload,
      }),
      timeoutMs: 70000,
    });
    await reload({throwOnError: true});
    closeDialog();
    setStatus(success, 'success');
    return result;
  } catch (error) {
    if (error.status === 409) {
      await reload({throwOnError: true}).catch(() => {});
      dialogMessage('Zwischenzeitlich geändert. Die Ansicht wurde neu geladen; bitte prüfe den aktuellen Stand.');
      return null;
    }
    dialogMessage(error.message || 'Die Gate-4-Aktion konnte nicht gespeichert werden.');
    return null;
  }
}

function confirmButton(label, tone = 'primary') {
  return `<button type="button" class="cc-button cc-button--${tone}" id="gate4-confirm">${escapeHtml(label)}</button>`;
}

async function manualItemDialog(item, key, status, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const label = itemLabels[key] || key;
  const blocked = status === 'blocked';
  openDialog(`<h2>${escapeHtml(label)}</h2>
    <p>${blocked ? 'Dokumentiere den konkreten Blocker und den nächsten Klärungsschritt.' : 'Dokumentiere den belastbaren Nachweis für diesen manuellen Onboardingpunkt.'}</p>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      ${textarea('gate4-evidence', blocked ? 'Blocker und nächster Schritt' : 'Nachweis', '', 'required')}
      ${confirmButton(blocked ? 'Blocker speichern' : 'Als belegt speichern', blocked ? 'danger' : 'primary')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'update_item',
      item_key: key,
      status,
      evidence_text: value('#gate4-evidence'),
    }, reload, blocked ? 'Blocker gespeichert und Gesamtzustand neu bewertet.' : 'Nachweis gespeichert und Gesamtzustand neu bewertet.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function contentReadyDialog(item, contentLinkId, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const row = asArray(data.gate4?.content_links).find(entry => String(entry.id) === contentLinkId);
  openDialog(`<h2>Pilotinhalt redaktionell bereit markieren</h2>
    <p>${escapeHtml(row?.title || 'Ausgewählter Pilotinhalt')}</p>
    <div class="cc-notice cc-notice--info"><strong>Noch keine Veröffentlichung</strong><span>Der Inhalt wird in den redaktionell bereiten Zustand versetzt. Die Veröffentlichung erfolgt erst atomar mit der Pilotaktivierung.</span></div>
    <div id="cc-dialog-message"></div>
    ${confirmButton('Redaktionell bereit markieren')}`);
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'mark_content_ready',
      content_link_id: contentLinkId,
    }, reload, 'Pilotinhalt ist redaktionell bereit; der Gesamtzustand wurde neu bewertet.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function measurementDialog(item, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const rows = asArray(data.gate4?.content_links).filter(row => ['editorial_ready', 'approved'].includes(row.status));
  if (!rows.length) {
    setStatus('Für den Messpreflight muss zuerst ein Pilotinhalt redaktionell bereit sein.', 'attention');
    return;
  }
  const current = data.gate4?.ready_measurement;
  openDialog(`<h2>Messpreflight prüfen</h2>
    <p>Beim Speichern als „bereit“ wird der kanonische Messdaten-Owner read-only abgefragt. Es werden keine Testmetriken geschrieben.</p>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      <label class="cc-field"><span>Pilotinhalt</span><select id="gate4-measure-content">${rows.map(row => `<option value="${escapeHtml(row.id)}" ${String(current?.content_link_id || '') === String(row.id) ? 'selected' : ''}>${escapeHtml(row.title || row.id)}</option>`).join('')}</select></label>
      <label class="cc-field"><span>Status</span><select id="gate4-measure-status"><option value="ready">Technisch bereit</option><option value="blocked">Blockiert</option></select></label>
      ${textarea('gate4-measure-evidence', 'Prüfnachweis oder Blocker', current?.evidence?.evidence_text || '', 'required')}
      ${confirmButton('Messpreflight speichern')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'set_measurement',
      status: value('#gate4-measure-status'),
      content_link_id: value('#gate4-measure-content'),
      evidence_text: value('#gate4-measure-evidence'),
    }, reload, 'Messpreflight gespeichert und technisch zurückgelesen.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function distributionDialog(item, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const current = data.gate4?.ready_distribution || asArray(data.gate4?.distribution_commitments)[0] || {};
  const planned = clean(current.planned_at).slice(0, 10);
  openDialog(`<h2>Partner-Reichweitenstart dokumentieren</h2>
    <p>Eine neue Fassung ersetzt frühere offene Vereinbarungen. Abgeschlossene Nachweise bleiben im Audit erhalten.</p>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      ${field('gate4-channel', 'Kanal', current.channel || '', 'text', 'required')}
      ${field('gate4-target', 'Ziel-Link oder Kampagnenreferenz', current.target_reference || '', 'text', 'required')}
      ${field('gate4-date', 'Geplantes lokales Datum', planned || berlinToday(), 'date', 'required')}
      <label class="cc-field"><span>Status</span><select id="gate4-distribution-status"><option value="ready">Konkret vorbereitet</option><option value="blocked">Blockiert</option></select></label>
      ${textarea('gate4-distribution-evidence', 'Konkreter Nachweis oder Blocker', current.evidence_text || '', 'required')}
      ${confirmButton('Distribution speichern')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'set_distribution',
      status: value('#gate4-distribution-status'),
      channel: value('#gate4-channel'),
      target_reference: value('#gate4-target'),
      planned_at: value('#gate4-date'),
      evidence_text: value('#gate4-distribution-evidence'),
    }, reload, 'Partnerdistribution gespeichert und Gesamtzustand neu bewertet.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function activationDialog(item, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  if (!data.gate4?.activation_ready) {
    setStatus(gate4PriorityMessage(data.gate4 || {}), 'attention');
    return;
  }
  const defaultDate = berlinToday();
  openDialog(`<h2>Startpartner-Pilot verbindlich aktivieren</h2>
    <div class="cc-notice cc-notice--info"><strong>Atomare Aktivierung</strong><span>Der erste Inhalt wird freigegeben, die dedizierte Pilotberechtigung aktiviert und die Reservierung ohne zusätzliche Kapazitätsbelegung beendet. Mail, Zahlung und Stripe bleiben unverändert.</span></div>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      ${field('gate4-activation-date', 'Lokales Aktivierungsdatum', defaultDate, 'date', 'required')}
      <dl class="cc-startpartner-facts"><div><dt>Geplantes Ende</dt><dd id="gate4-end-preview">${escapeHtml(formatDate(addCalendarMonths(defaultDate)))}</dd></div></dl>
      ${confirmButton('Pilot verbindlich aktivieren')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-activation-date')?.addEventListener('change', event => {
    const preview = document.querySelector('#gate4-end-preview');
    if (preview) preview.textContent = formatDate(addCalendarMonths(event.target.value));
  });
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/activation.php', data, {
      activation_date_local: value('#gate4-activation-date'),
    }, reload, 'Pilot aktiviert, erster Inhalt freigegeben und vollständiger Zustand zurückgelesen.');
    if (!result) event.currentTarget.disabled = false;
  });
}

export async function handleGate4Action(item, action, reload) {
  try {
    const parts = String(action || '').split(':');
    if (parts[1] === 'item') return manualItemDialog(item, parts[2], parts[3], reload);
    if (parts[1] === 'content') return contentReadyDialog(item, parts[2], reload);
    if (parts[1] === 'measurement') return measurementDialog(item, reload);
    if (parts[1] === 'distribution') return distributionDialog(item, reload);
    if (parts[1] === 'activate') return activationDialog(item, reload);
  } catch (error) {
    setStatus(error.message || 'Gate 4 konnte nicht geöffnet werden.', 'attention');
  }
}
