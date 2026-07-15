export const state = {
  password: sessionStorage.getItem('be_cc_password') || '',
  view: 'overview', cases: [], overview: null, development: null,
  developmentMode: 'project', reviewGroup: 'all', reviewIndex: 0,
  managementType: 'events', managementQuery: '',
  managementItems: { events: [], activities: [] },
  managementLoaded: { events: false, activities: false },
  managementWarnings: { events: [], activities: [] },
};

export const els = {
  auth: document.querySelector('#cc-auth'), authForm: document.querySelector('#cc-auth-form'),
  password: document.querySelector('#cc-password'), content: document.querySelector('#cc-content'),
  view: document.querySelector('#cc-view'), title: document.querySelector('#cc-title'),
  status: document.querySelector('#cc-status'), refresh: document.querySelector('#cc-refresh'),
  menuButton: document.querySelector('#cc-menu-button'),
  nav: [...document.querySelectorAll('.cc-nav [data-view]')],
  badgeReview: document.querySelector('#cc-badge-review'), badgeBacklog: document.querySelector('#cc-badge-backlog'),
  dialog: document.querySelector('#cc-dialog'), dialogBody: document.querySelector('#cc-dialog-body'),
  dialogClose: document.querySelector('#cc-dialog-close'),
};

export const reviewLabels = {
  all: 'Alle', new_content: 'Neue Inhalte', quality: 'Qualität', provider: 'Anbieter',
  approvals: 'Freigaben', system: 'System', other: 'Sonstige',
};

export const rejectOptions = [
  ['duplicate', 'Doppelt / bereits abgedeckt'],
  ['rejected_not_event', 'Kein konkreter Einzeltermin'],
  ['rejected_not_public', 'Nicht öffentlich zugänglich'],
  ['rejected_not_local', 'Nicht lokal genug'],
  ['rejected_source_weak', 'Quelle oder Angaben reichen nicht'],
  ['rejected_low_value', 'Redaktionell zu schwach'],
  ['rejected_commercial', 'Überwiegend Werbung / Promotion'],
];

export const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, c => ({
  '&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;',
}[c]));
export const clean = value => String(value ?? '').trim();
export const asArray = value => Array.isArray(value) ? value : [];
export const sleep = milliseconds => new Promise(resolve => window.setTimeout(resolve, milliseconds));
export const appPath = value => window.beControlCenterPath ? window.beControlCenterPath(value) : value;

export function formatDate(value) {
  if (!value) return '';
  const text = String(value);
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(text) ? `${text}T00:00:00` : text.replace(' ', 'T');
  const date = new Date(normalized);
  return Number.isNaN(date.getTime()) ? clean(value) : new Intl.DateTimeFormat('de-DE', { dateStyle:'medium' }).format(date);
}
export function formatDateTime(value) {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? clean(value) : new Intl.DateTimeFormat('de-DE', { dateStyle:'medium', timeStyle:'short' }).format(date);
}
export function setStatus(message, tone = '') {
  els.status.textContent = message || '';
  els.status.dataset.tone = tone;
}
export function operationId() {
  if (window.crypto?.randomUUID) return `cc:${window.crypto.randomUUID()}`;
  return `cc:${Date.now().toString(36)}:${Math.random().toString(36).slice(2)}:${Math.random().toString(36).slice(2)}`;
}
export const draftKey = caseId => `be_cc_draft:${caseId}`;
export function readDraft(caseId) {
  try { return JSON.parse(sessionStorage.getItem(draftKey(caseId)) || 'null'); } catch (_) { return null; }
}
export function writeDraft(caseId, value) { sessionStorage.setItem(draftKey(caseId), JSON.stringify(value)); }
export function clearDraft(caseId) { sessionStorage.removeItem(draftKey(caseId)); }

export async function api(path, options = {}) {
  const { timeoutMs = 20000, allowAccepted = false, ...fetchOptions } = options;
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), timeoutMs);
  try {
    const response = await fetch(appPath(path), {
      ...fetchOptions, signal: controller.signal, cache:'no-store',
      headers: { 'Content-Type':'application/json', 'X-BE-Review-Password':state.password, ...(fetchOptions.headers || {}) },
    });
    const payload = await response.json().catch(() => ({}));
    if (!response.ok && !(allowAccepted && response.status === 202)) {
      const error = new Error(payload.message || 'Anfrage fehlgeschlagen.');
      error.status = response.status;
      error.data = payload.data || null;
      throw error;
    }
    return payload.data || {};
  } catch (error) {
    if (error?.name === 'AbortError') throw new Error('Die Datenquelle antwortet nicht rechtzeitig. Bitte erneut laden.');
    throw error;
  } finally { window.clearTimeout(timer); }
}

export function openDialog(html, className = '') {
  els.dialogBody.innerHTML = html;
  els.dialog.className = `cc-dialog ${className}`.trim();
  els.dialog.showModal();
}
export function closeDialog() {
  if (els.dialog.open) els.dialog.close();
  els.dialogBody.innerHTML = '';
  els.dialog.className = 'cc-dialog';
}
export function dialogMessage(message, tone = 'attention') {
  const target = document.querySelector('#cc-dialog-message');
  if (!target) return setStatus(message, tone);
  target.textContent = message || '';
  target.className = message ? `cc-notice cc-notice--${tone}` : '';
}
export function field(id, label, value, type = 'text', extra = '') {
  return `<label class="cc-field"><span>${escapeHtml(label)}</span><input id="${id}" type="${type}" value="${escapeHtml(value || '')}" ${extra}></label>`;
}
export function textarea(id, label, value, extra = '') {
  return `<label class="cc-field"><span>${escapeHtml(label)}</span><textarea id="${id}" ${extra}>${escapeHtml(value || '')}</textarea></label>`;
}
export const value = selector => clean(document.querySelector(selector)?.value);
export function fact(label, content, url = '') {
  const output = url
    ? `<a href="${escapeHtml(url)}" target="_blank" rel="noopener">${escapeHtml(content || url)}</a>`
    : escapeHtml(content || '–');
  return `<div><dt>${escapeHtml(label)}</dt><dd>${output}</dd></div>`;
}

export function activeCases() { return state.cases.filter(item => !['done','rejected','parked','information'].includes(item.state)); }
export function backlogCases() { return activeCases().filter(item => item.case_kind === 'backlog_item'); }
export function reviewGroup(item) { return item.type === 'task' ? 'system' : (item.queue_group || 'other'); }
export function allReviewCases() { return activeCases().filter(item => item.type === 'intake' || (item.type === 'task' && item.case_kind !== 'backlog_item')); }
export function reviewCases() { return allReviewCases().filter(item => state.reviewGroup === 'all' || reviewGroup(item) === state.reviewGroup); }
