import { state, els, escapeHtml, clean, api, openDialog, closeDialog, dialogMessage, field, textarea, value } from './shared.js?v=2026-07-15-control-center-editorial-v1';

let reloadApplication = async () => {};

export function configureBacklog(options = {}) {
  if (typeof options.reload === 'function') reloadApplication = options.reload;
}

function roadmapCopy(label, valueToRender) {
  const text = clean(valueToRender);
  return text ? `<div class="cc-roadmap-copy"><strong>${escapeHtml(label)}</strong><p>${escapeHtml(text)}</p></div>` : '';
}

function selectField(id, label, selected, options) {
  const values = options.map(([optionValue, optionLabel]) => `<option value="${escapeHtml(optionValue)}" ${optionValue === selected ? 'selected' : ''}>${escapeHtml(optionLabel)}</option>`).join('');
  return `<label class="cc-field"><span>${escapeHtml(label)}</span><select id="${id}">${values}</select></label>`;
}

function roadmapItem(item) {
  const completed = item.status === 'completed';
  const statusLabel = completed ? 'Abgeschlossen' : 'Offen';
  const orderPrefix = completed ? '' : `${escapeHtml(item.recommended_order || '–')}. `;
  const meta = [statusLabel, item.priority, item.type].filter(Boolean).map(escapeHtml).join(' · ');
  const preview = clean(item.recommended_action || item.why_relevant || item.short_reason || item.expected_benefit) || 'Keine Kurzbeschreibung hinterlegt';
  const completion = completed
    ? `${roadmapCopy('Abschluss', item.decision_note)}${roadmapCopy('Abgeschlossen am', item.closed_at)}`
    : '';
  return `<details class="cc-labor-row cc-roadmap-row ${completed ? 'cc-roadmap-row--completed' : ''}">
    <summary>
      <span><strong>${orderPrefix}${escapeHtml(item.title || 'Backlogpunkt')}</strong><small>${meta}</small></span>
      <span class="cc-labor-summary">${escapeHtml(preview)}</span>
      <span class="cc-roadmap-affordance">Details &amp; Bearbeiten</span>
    </summary>
    <div class="cc-labor-detail">
      ${roadmapCopy('Warum relevant', item.why_relevant || item.short_reason)}
      ${roadmapCopy('Empfohlener nächster Schritt', item.recommended_action)}
      ${roadmapCopy('Erwarteter Nutzen', item.expected_benefit)}
      ${completion}
      <div class="cc-roadmap-actions">
        <button class="cc-button cc-button--primary" type="button" data-backlog-edit="${escapeHtml(item.id)}">Bearbeiten</button>
        <button class="cc-button cc-button--secondary" type="button" data-backlog-status="${escapeHtml(item.id)}">${completed ? 'Wieder öffnen' : 'Als abgeschlossen markieren'}</button>
      </div>
    </div>
  </details>`;
}

function roadmapSection(title, items, emptyText) {
  return `<section class="cc-roadmap-section"><div class="cc-roadmap-section__head"><h2>${escapeHtml(title)}</h2><span>${items.length}</span></div>${items.length ? `<div class="cc-roadmap-list">${items.map(roadmapItem).join('')}</div>` : `<div class="cc-empty">${escapeHtml(emptyText)}</div>`}</section>`;
}

function itemById(id) {
  return (state.backlog?.items || []).find(item => String(item.id) === String(id)) || null;
}

function editorMarkup(item) {
  const isNew = !item;
  return `<h2>${isNew ? 'Neuen Backlogpunkt anlegen' : 'Backlogpunkt bearbeiten'}</h2>
    <p>${isNew ? 'Der neue Punkt wird offen in die kanonische Roadmap aufgenommen.' : 'Änderungen werden direkt in der kanonischen Roadmap gespeichert.'}</p>
    <form id="cc-backlog-editor" class="cc-form-stack">
      ${field('cc-backlog-title', 'Titel', item?.title || '', 'text', 'required maxlength="240"')}
      ${selectField('cc-backlog-priority', 'Priorität', clean(item?.priority || 'mittel').toLowerCase(), [['hoch','Hoch'],['mittel','Mittel'],['niedrig','Niedrig']])}
      ${field('cc-backlog-type', 'Themenbereich', item?.type || 'Sonstiges', 'text', 'required maxlength="120"')}
      ${textarea('cc-backlog-why', 'Warum relevant', item?.why_relevant || item?.short_reason || '', 'rows="5"')}
      ${textarea('cc-backlog-action', 'Empfohlener nächster Schritt', item?.recommended_action || '', 'rows="4"')}
      ${textarea('cc-backlog-benefit', 'Erwarteter Nutzen', item?.expected_benefit || '', 'rows="4"')}
      <div id="cc-dialog-message"></div>
      <div class="cc-dialog-actions">
        <button class="cc-button cc-button--primary" type="submit">Speichern</button>
        <button class="cc-button cc-button--secondary" type="button" id="cc-backlog-cancel">Abbrechen</button>
      </div>
    </form>`;
}

function openBacklogEditor(item = null) {
  openDialog(editorMarkup(item), 'cc-dialog--wide');
  document.querySelector('#cc-backlog-cancel')?.addEventListener('click', closeDialog);
  document.querySelector('#cc-backlog-editor')?.addEventListener('submit', async event => {
    event.preventDefault();
    const title = value('#cc-backlog-title');
    const type = value('#cc-backlog-type');
    if (!title || !type) return dialogMessage('Titel und Themenbereich sind erforderlich.');
    const payload = {
      title,
      priority: value('#cc-backlog-priority'),
      type,
      why_relevant: value('#cc-backlog-why'),
      recommended_action: value('#cc-backlog-action'),
      expected_benefit: value('#cc-backlog-benefit'),
    };
    if (item) {
      payload.id = item.id;
      payload.action = 'edit';
      payload.expected_updated_at = item.updated_at || '';
    }
    const submit = event.submitter;
    if (submit) submit.disabled = true;
    dialogMessage('Änderung wird gespeichert …', 'info');
    try {
      await api(item ? '/api/growth-backlog/update.php' : '/api/growth-backlog/create.php', { method:'POST', body:JSON.stringify(payload) });
      closeDialog();
      await reloadApplication();
    } catch (error) {
      dialogMessage(error.message || 'Backlogpunkt konnte nicht gespeichert werden.');
      if (submit) submit.disabled = false;
    }
  });
}

function openBacklogStatus(item) {
  const completed = item.status === 'completed';
  const action = completed ? 'reopen' : 'complete';
  openDialog(`<h2>${completed ? 'Backlogpunkt wieder öffnen' : 'Backlogpunkt abschließen'}</h2>
    <p><strong>${escapeHtml(item.title || 'Backlogpunkt')}</strong></p>
    ${completed ? '<p>Der Punkt wird wieder als offen einsortiert.</p>' : textarea('cc-backlog-decision-note', 'Abschlussnotiz (optional)', item.decision_note || '', 'rows="4"')}
    <div id="cc-dialog-message"></div>
    <div class="cc-dialog-actions">
      <button class="cc-button cc-button--primary" type="button" id="cc-backlog-status-confirm">${completed ? 'Wieder öffnen' : 'Abschließen'}</button>
      <button class="cc-button cc-button--secondary" type="button" id="cc-backlog-status-cancel">Abbrechen</button>
    </div>`);
  document.querySelector('#cc-backlog-status-cancel')?.addEventListener('click', closeDialog);
  document.querySelector('#cc-backlog-status-confirm')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    dialogMessage('Status wird gespeichert …', 'info');
    try {
      await api('/api/growth-backlog/update.php', {
        method:'POST',
        body:JSON.stringify({
          id:item.id,
          action,
          decision_note: completed ? '' : value('#cc-backlog-decision-note'),
          expected_updated_at:item.updated_at || '',
        }),
      });
      closeDialog();
      await reloadApplication();
    } catch (error) {
      dialogMessage(error.message || 'Status konnte nicht gespeichert werden.');
      button.disabled = false;
    }
  });
}

function bindBacklogActions() {
  document.querySelector('#cc-backlog-new')?.addEventListener('click', () => openBacklogEditor());
  document.querySelectorAll('[data-backlog-edit]').forEach(button => button.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();
    const item = itemById(button.dataset.backlogEdit);
    if (item) openBacklogEditor(item);
  }));
  document.querySelectorAll('[data-backlog-status]').forEach(button => button.addEventListener('click', event => {
    event.preventDefault();
    event.stopPropagation();
    const item = itemById(button.dataset.backlogStatus);
    if (item) openBacklogStatus(item);
  }));
}

export function renderBacklog() {
  if (state.backlog?.status === 'error') {
    els.view.innerHTML = `<div class="cc-notice cc-notice--attention"><strong>Backlog konnte nicht geladen werden.</strong><br>${escapeHtml(state.backlog.message || 'Der kanonische Growth-Backlog ist derzeit nicht erreichbar.')}</div>`;
    return;
  }
  const items = Array.isArray(state.backlog?.items) ? state.backlog.items : [];
  const open = items.filter(item => item.status === 'open');
  const completed = items.filter(item => item.status === 'completed');
  els.view.innerHTML = `<div class="cc-backlog-toolbar"><button class="cc-button cc-button--primary" type="button" id="cc-backlog-new">+ Neuer Punkt</button></div>
    ${roadmapSection('Offen · empfohlene Reihenfolge', open, 'Aktuell ist kein offener Backlogpunkt vorhanden.')}
    ${roadmapSection('Abgeschlossen', completed, 'Noch kein Backlogpunkt ist abgeschlossen.')}`;
  bindBacklogActions();
}
