import {
  escapeHtml, clean, asArray, formatDate, formatDateTime, api, openDialog,
  closeDialog, dialogMessage, field, textarea, value, operationId, setStatus,
} from './shared.js?v=2026-07-16-e2e-state-v5';

const itemLabels = {
  terms_confirmed: 'Pilotbedingungen bestätigt',
  organizer_linked: 'Veranstalterzugang zugeordnet',
  contact_confirmed: 'Ansprechperson bestätigt',
  portal_access_tested: 'Veranstalterzugang getestet',
  pilot_entitlement_readback: 'Pilotfreigabe geprüft',
  service_scope_confirmed: 'Vereinbarter Umfang bestätigt',
  sources_recorded: 'Inhaltsquellen hinterlegt',
  maintenance_path_agreed: 'Laufende Pflege geklärt',
  content_rights_cleared: 'Inhalts- und Bildrechte geklärt',
  first_content_ready: 'Erster Inhalt vorbereitet',
  editorial_review_ready: 'Redaktionelle Prüfung vorbereitet',
  measurement_ready: 'Erfolgsmessung eingerichtet',
  distribution_ready: 'Reichweitenbeitrag vorbereitet',
  activation_target_set: 'Starttermin festgelegt',
};

const statusLabels = {
  pending: 'Offen',
  complete: 'Erledigt',
  blocked: 'Klärung nötig',
  not_applicable: 'Nicht erforderlich',
};

const contentStatusLabels = {
  draft: 'Zur Prüfung eingereicht',
  editorial_ready: 'Für den Start vorbereitet',
  approved: 'Veröffentlicht',
  rejected: 'Nicht veröffentlicht',
  withdrawn: 'Zurückgezogen',
};

export function gate4PhaseLabel(phase) {
  return ({
    onboarding: 'Piloteinrichtung',
    activation_ready: 'Bereit zum Start',
    active: 'Pilotphase läuft',
  })[phase] || 'Piloteinrichtung';
}

export function gate4PriorityMessage(gate4 = {}) {
  if (gate4.active) {
    return 'Der Pilot und der erste Inhalt sind aktiv. Die sechsmonatige Pilotphase läuft.';
  }
  if (gate4.activation_ready) {
    return 'Alle Voraussetzungen sind erfüllt. Der Pilot kann jetzt gestartet werden.';
  }
  return gate4.blockers?.[0]?.message || 'Der nächste Schritt der Piloteinrichtung ist noch offen.';
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
    ${evidence ? `<small>${escapeHtml(evidence)}</small>` : '<small>Noch kein Nachweis hinterlegt.</small>'}
    ${canEdit ? `<div class="cc-actions">
      <button class="cc-button cc-button--secondary" data-review-action="gate4:item:${escapeHtml(key)}:complete">Als erledigt speichern</button>
      <button class="cc-button cc-button--secondary" data-review-action="gate4:item:${escapeHtml(key)}:blocked">Klärungsbedarf erfassen</button>
    </div>` : ''}
  </article>`;
}

function contentRows(rows = [], active = false) {
  if (!rows.length) {
    return '<p class="cc-muted">Noch kein Inhalt für den Pilot eingereicht.</p>';
  }
  return `<div class="cc-stack">${rows.map(row => `<article class="cc-startpartner-state-card">
    <span class="cc-kicker">${escapeHtml(row.content_type === 'activity' ? 'Aktivität' : 'Veranstaltung')}</span>
    <strong>${escapeHtml(row.title || `Einreichung ${row.submission_id || ''}`)}</strong>
    <p>${escapeHtml(contentStatusLabels[row.status] || row.status || 'In Bearbeitung')}${row.start_date ? ` · ${escapeHtml(formatDate(row.start_date))}` : ''}</p>
    ${row.status === 'draft' && !active ? `<button class="cc-button cc-button--secondary" data-review-action="gate4:content:${escapeHtml(row.id)}">Für den Pilotstart vorbereiten</button>` : ''}
  </article>`).join('')}</div>`;
}

function readinessSummary(gate4) {
  const measurement = gate4.ready_measurement;
  const distribution = gate4.ready_distribution;
  return `<section class="cc-startpartner-grid">
    <section class="cc-startpartner-panel">
      <span class="cc-kicker">Erfolgsmessung</span>
      <h3>${measurement ? 'Eingerichtet' : 'Noch offen'}</h3>
      <p>${measurement
        ? `Zuordnung geprüft ${escapeHtml(formatDateTime(measurement.checked_at) || '–')}`
        : 'Vor dem Pilotstart prüft das System, ob die Erfolgsmessung für diesen Veranstalter richtig verbunden ist.'}</p>
      ${!gate4.active ? '<button class="cc-button cc-button--secondary" data-review-action="gate4:measurement">Erfolgsmessung prüfen</button>' : ''}
    </section>
    <section class="cc-startpartner-panel">
      <span class="cc-kicker">Reichweitenbeitrag</span>
      <h3>${distribution ? 'Vorbereitet' : 'Noch offen'}</h3>
      <p>${distribution
        ? `${escapeHtml(distribution.channel || 'Kanal')} · ${escapeHtml(formatDate(distribution.planned_at) || '–')}`
        : 'Kanal, Termin und geplanter Beitrag werden vor dem Pilotstart festgehalten.'}</p>
      ${!gate4.active ? '<button class="cc-button cc-button--secondary" data-review-action="gate4:distribution">Reichweitenbeitrag planen</button>' : ''}
    </section>
  </section>`;
}

function scopeRepairBlockers(gate4 = {}) {
  return asArray(gate4.blockers).filter(blocker => blocker?.code === 'scope_target_plan_mismatch');
}

export function renderGate4Panel(candidate = {}) {
  const gate4 = candidate.gate4 || {};
  const pilot = gate4.pilot;
  if (!pilot) return '';

  const onboarding = gate4.onboarding || {};
  const first = gate4.first_content || {};
  const scopeMismatches = scopeRepairBlockers(gate4);
  const stateAction = scopeMismatches.length && !gate4.active
    ? `<div class="cc-notice cc-notice--attention"><strong>Zielmodell-Zuordnung blockiert</strong><span>Die persistierte Event-/Aktivitätszuordnung stimmt nicht mit dem gebundenen Pilotvertrag überein. Vor weiteren Pilotschritten ist eine revisionsgesicherte Reparatur erforderlich.</span></div>
       <button class="cc-button cc-button--primary" data-review-action="gate4:repair-scope">Zielmodell-Zuordnung reparieren</button>`
    : gate4.activation_ready
      ? '<button class="cc-button cc-button--primary cc-button--large" data-review-action="gate4:activate">Pilot jetzt starten</button>'
      : gate4.active
        ? '<div class="cc-notice cc-notice--success"><strong>Sechsmonatige Pilotphase läuft</strong><span>Der Pilot und der erste Inhalt sind aktiv. Der aktuelle Stand wurde vollständig geprüft.</span></div>'
        : `<div class="cc-notice cc-notice--info"><strong>Nächster offener Punkt</strong><span>${escapeHtml(gate4PriorityMessage(gate4))}</span></div>`;

  return `<section class="cc-startpartner-panel" data-gate4-panel>
    <header>
      <div>
        <span class="cc-kicker">Einrichtung, Inhalte und Start</span>
        <h3>${escapeHtml(gate4PhaseLabel(gate4.phase))}</h3>
      </div>
      <span class="cc-pill">${escapeHtml(`${Number(onboarding.completed_count || 0)} von ${Number(onboarding.total_count || 14)} erledigt`)}</span>
    </header>
    <dl class="cc-startpartner-facts">
      <div><dt>Pilotstart</dt><dd>${escapeHtml(pilot.activation_date_local ? formatDate(pilot.activation_date_local) : 'Noch nicht gestartet')}</dd></div>
      <div><dt>Geplantes Ende</dt><dd>${escapeHtml(pilot.planned_end_date ? formatDate(pilot.planned_end_date) : 'Noch offen')}</dd></div>
      <div><dt>Erster Inhalt</dt><dd>${escapeHtml(contentStatusLabels[first.status] || first.status || 'Noch nicht vorbereitet')}</dd></div>
    </dl>
    ${stateAction}
    <details class="cc-disclosure">
      <summary>Schritte der Piloteinrichtung</summary>
      <div class="cc-startpartner-qualification-group">
        <div>${asArray(onboarding.items).map(row => itemRow(row, gate4.active)).join('')}</div>
      </div>
    </details>
    <details class="cc-disclosure">
      <summary>Inhalte im Pilot</summary>
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
    dialogMessage(error.message || 'Die Änderung konnte nicht gespeichert werden.');
    return null;
  }
}

function confirmButton(label, tone = 'primary') {
  return `<button type="button" class="cc-button cc-button--${tone}" id="gate4-confirm">${escapeHtml(label)}</button>`;
}

async function scopeRepairDialog(item, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const mismatches = scopeRepairBlockers(data.gate4 || {});
  if (!mismatches.length) {
    setStatus('Die Zielmodell-Zuordnung ist bereits konsistent. Es wurde nichts geändert.', 'success');
    return;
  }
  const rows = mismatches.map(mismatch => `<li><strong>${escapeHtml(mismatch.scope_key || 'Scope')}</strong>: ${escapeHtml(mismatch.actual_target_plan_key || 'ohne Zuordnung')} → ${escapeHtml(mismatch.expected_target_plan_key || 'erwartetes Zielmodell')}</li>`).join('');
  openDialog(`<h2>Zielmodell-Zuordnung reparieren</h2>
    <p>Die gebundenen Pilotbedingungen bleiben unverändert. Korrigiert wird ausschließlich die falsche technische Zielmodell-Zuordnung der noch nicht aktivierten Event-/Aktivitäts-Scopes.</p>
    <ul>${rows}</ul>
    <div class="cc-notice cc-notice--info"><strong>Keine externe Wirkung</strong><span>Diese Reparatur versendet keine Mail oder Magic Link, legt keinen Inhalt an, veröffentlicht nichts, verändert keine Zahlung und startet den Pilot nicht.</span></div>
    <div id="cc-dialog-message"></div>
    ${confirmButton('Zielmodell-Zuordnung reparieren')}`,'cc-dialog--wide');
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'repair_scope_target_plans',
    }, reload, 'Zielmodell-Zuordnung repariert und aktueller Pilotstand vollständig neu geprüft.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function manualItemDialog(item, key, status, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const label = itemLabels[key] || key;
  const blocked = status === 'blocked';
  openDialog(`<h2>${escapeHtml(label)}</h2>
    <p>${blocked ? 'Beschreibe, was noch geklärt werden muss und was als Nächstes passiert.' : 'Hinterlege den Nachweis, dass dieser Schritt abgeschlossen ist.'}</p>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      ${textarea('gate4-evidence', blocked ? 'Offener Punkt und nächster Schritt' : 'Nachweis', '', 'required')}
      ${confirmButton(blocked ? 'Klärungsbedarf speichern' : 'Als erledigt speichern', blocked ? 'danger' : 'primary')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'update_item',
      item_key: key,
      status,
      evidence_text: value('#gate4-evidence'),
    }, reload, blocked ? 'Klärungsbedarf gespeichert. Der Gesamtstand wurde neu geprüft.' : 'Schritt als erledigt gespeichert. Der Gesamtstand wurde neu geprüft.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function contentReadyDialog(item, contentLinkId, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const row = asArray(data.gate4?.content_links).find(entry => String(entry.id) === contentLinkId);
  openDialog(`<h2>Inhalt für den Pilotstart vorbereiten</h2>
    <p>${escapeHtml(row?.title || 'Ausgewählter Inhalt')}</p>
    <div class="cc-notice cc-notice--info"><strong>Noch nicht veröffentlicht</strong><span>Der Inhalt wird für den Start vorbereitet. Veröffentlicht wird er erst, wenn der Pilot gestartet wird.</span></div>
    <div id="cc-dialog-message"></div>
    ${confirmButton('Für den Pilotstart vorbereiten')}`);
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'mark_content_ready',
      content_link_id: contentLinkId,
    }, reload, 'Der Inhalt ist für den Pilotstart vorbereitet. Der Gesamtstand wurde neu geprüft.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function measurementDialog(item, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const rows = asArray(data.gate4?.content_links).filter(row => ['editorial_ready', 'approved'].includes(row.status));
  if (!rows.length) {
    setStatus('Für die Erfolgsmessung muss zuerst ein Inhalt für den Pilotstart vorbereitet sein.', 'attention');
    return;
  }
  const current = data.gate4?.ready_measurement;
  openDialog(`<h2>Erfolgsmessung prüfen</h2>
    <p>Beim Speichern prüft das System, ob die Erfolgsmessung für diesen Veranstalter richtig verbunden ist. Es werden keine Testdaten angelegt.</p>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      <label class="cc-field"><span>Inhalt</span><select id="gate4-measure-content">${rows.map(row => `<option value="${escapeHtml(row.id)}" ${String(current?.content_link_id || '') === String(row.id) ? 'selected' : ''}>${escapeHtml(row.title || row.id)}</option>`).join('')}</select></label>
      <label class="cc-field"><span>Ergebnis</span><select id="gate4-measure-status"><option value="ready">Eingerichtet</option><option value="blocked">Klärung nötig</option></select></label>
      ${textarea('gate4-measure-evidence', 'Notiz zur Prüfung oder zum offenen Punkt', current?.evidence?.evidence_text || '', 'required')}
      ${confirmButton('Erfolgsmessung speichern')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/onboarding.php', data, {
      action: 'set_measurement',
      status: value('#gate4-measure-status'),
      content_link_id: value('#gate4-measure-content'),
      evidence_text: value('#gate4-measure-evidence'),
    }, reload, 'Erfolgsmessung gespeichert und geprüft.');
    if (!result) event.currentTarget.disabled = false;
  });
}

async function distributionDialog(item, reload) {
  const detail = await latest(item);
  const data = detail.startpartner_candidate;
  const current = data.gate4?.ready_distribution || asArray(data.gate4?.distribution_commitments)[0] || {};
  const planned = clean(current.planned_at).slice(0, 10);
  openDialog(`<h2>Reichweitenbeitrag planen</h2>
    <p>Halte fest, über welchen Kanal und zu welchem Termin der Partner den Pilot bekannt macht. Eine neue Planung ersetzt die vorherige offene Fassung.</p>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      ${field('gate4-channel', 'Kanal', current.channel || '', 'text', 'required')}
      ${field('gate4-target', 'Ziel-Link oder Kampagne', current.target_reference || '', 'text', 'required')}
      ${field('gate4-date', 'Geplanter Termin', planned || berlinToday(), 'date', 'required')}
      <label class="cc-field"><span>Ergebnis</span><select id="gate4-distribution-status"><option value="ready">Vorbereitet</option><option value="blocked">Klärung nötig</option></select></label>
      ${textarea('gate4-distribution-evidence', 'Notiz oder Nachweis', current.evidence_text || '', 'required')}
      ${confirmButton('Planung speichern')}
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
    }, reload, 'Reichweitenbeitrag gespeichert. Der Gesamtstand wurde neu geprüft.');
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
  openDialog(`<h2>Startpartner-Pilot starten</h2>
    <div class="cc-notice cc-notice--info"><strong>Was beim Start passiert</strong><span>Der erste Inhalt wird veröffentlicht und die Pilotfreigabe wird aktiviert. Die Reservierung endet, ohne einen weiteren Platz zu belegen. Es wird keine Zahlung ausgelöst.</span></div>
    <div id="cc-dialog-message"></div>
    <div class="cc-stack">
      ${field('gate4-activation-date', 'Startdatum', defaultDate, 'date', 'required')}
      <dl class="cc-startpartner-facts"><div><dt>Geplantes Ende</dt><dd id="gate4-end-preview">${escapeHtml(formatDate(addCalendarMonths(defaultDate)))}</dd></div></dl>
      ${confirmButton('Pilot jetzt starten')}
    </div>`, 'cc-dialog--wide');
  document.querySelector('#gate4-activation-date')?.addEventListener('change', event => {
    const preview = document.querySelector('#gate4-end-preview');
    if (preview) preview.textContent = formatDate(addCalendarMonths(event.target.value));
  });
  document.querySelector('#gate4-confirm')?.addEventListener('click', async event => {
    event.currentTarget.disabled = true;
    const result = await mutate('/api/startpartner/activation.php', data, {
      activation_date_local: value('#gate4-activation-date'),
    }, reload, 'Der Pilot ist gestartet. Der erste Inhalt wurde veröffentlicht und der aktuelle Stand neu geladen.');
    if (!result) event.currentTarget.disabled = false;
  });
}

export async function handleGate4Action(item, action, reload) {
  try {
    const parts = String(action || '').split(':');
    if (parts[1] === 'repair-scope') return scopeRepairDialog(item, reload);
    if (parts[1] === 'item') return manualItemDialog(item, parts[2], parts[3], reload);
    if (parts[1] === 'content') return contentReadyDialog(item, parts[2], reload);
    if (parts[1] === 'measurement') return measurementDialog(item, reload);
    if (parts[1] === 'distribution') return distributionDialog(item, reload);
    if (parts[1] === 'activate') return activationDialog(item, reload);
  } catch (error) {
    setStatus(error.message || 'Die Piloteinrichtung konnte nicht geöffnet werden.', 'attention');
  }
}