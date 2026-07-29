(() => {
  "use strict";

  const root = document.querySelector('[data-organizer-page="dashboard"]');
  if (!root) return;

  if (!document.querySelector('link[data-organizer-gate4-style]')) {
    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = '/css/organizer-gate4.css?v=2026-07-29-startpartner-gate4-v1';
    link.dataset.organizerGate4Style = 'true';
    document.head.appendChild(link);
  }

  const card = document.getElementById('organizer-dashboard-startpartner-card');
  const body = document.getElementById('organizer-dashboard-startpartner-body');
  if (!card || !body) return;

  const safe = (value) => String(value ?? '').trim();
  const escapeHtml = (value) => String(value ?? '')
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&#39;');
  const date = (value) => {
    const text = safe(value);
    if (!text) return '–';
    const parsed = new Date(text.includes('T') ? text : `${text}T00:00:00`);
    if (Number.isNaN(parsed.getTime())) return text;
    return new Intl.DateTimeFormat('de-DE', { day: '2-digit', month: '2-digit', year: 'numeric' }).format(parsed);
  };
  const statusLabel = (status) => ({
    onboarding: 'Onboarding läuft',
    activation_ready: 'Aktivierung vorbereitet',
    active: 'Pilot aktiv',
    paused: 'Pilot pausiert',
    closing: 'Pilotabschluss wird vorbereitet',
  })[safe(status)] || safe(status) || 'Pilotstatus offen';
  const entitlementLabel = (status) => ({
    pending_activation: 'Noch nicht aktiv', active: 'Aktiv', paused: 'Pausiert', ended: 'Beendet', revoked: 'Widerrufen',
  })[safe(status)] || safe(status) || '–';

  function scopeText(scope) {
    const name = scope.scope_type === 'activities' ? 'Aktivitäten' : scope.scope_type === 'events' ? 'Veranstaltungen' : safe(scope.scope_key);
    const limit = Number(scope.is_unlimited) === 1 ? 'vereinbarter umfangreicher Rahmen' : safe(scope.limit_value) || '–';
    const suffix = scope.period_unit === 'pilot_month' ? ' je Pilotmonat' : scope.period_unit === 'concurrent' ? ' gleichzeitig' : '';
    return `${name}: ${limit}${suffix}`;
  }

  function render(pilot) {
    if (!pilot) return;
    const readiness = pilot.readiness || {};
    const blockers = Array.isArray(readiness.blockers) ? readiness.blockers : [];
    const content = Array.isArray(pilot.content) ? pilot.content : [];
    const activation = pilot.activation || {};
    card.hidden = false;
    body.innerHTML = `
      <div class="organizer-startpartner-status">
        <div>
          <span class="content-note">Kostenloser Startpartner-Pilot</span>
          <h3>${escapeHtml(statusLabel(pilot.status))}</h3>
          <p>${pilot.status === 'active'
            ? `Laufzeit bis ${escapeHtml(date(activation.planned_end_date || pilot.ends_at))}. Keine automatische kostenpflichtige Verlängerung.`
            : blockers.length
              ? `${escapeHtml(String(blockers.length))} Aktivierungspunkt${blockers.length === 1 ? '' : 'e'} noch offen.`
              : 'Alle technischen Aktivierungspunkte sind vorbereitet. Die Freigabe erfolgt kontrolliert.'}</p>
        </div>
        <span class="organizer-startpartner-badge">${escapeHtml(entitlementLabel(pilot.entitlement?.status))}</span>
      </div>
      <dl class="organizer-startpartner-facts">
        <div><dt>Pilotstatus</dt><dd>${escapeHtml(statusLabel(pilot.status))}</dd></div>
        <div><dt>Aktivierung</dt><dd>${escapeHtml(date(activation.activation_date_local || pilot.starts_at))}</dd></div>
        <div><dt>Geplantes Ende</dt><dd>${escapeHtml(date(activation.planned_end_date || pilot.ends_at))}</dd></div>
        <div><dt>Veröffentlichte Inhalte</dt><dd>${escapeHtml(String(content.filter(item => item.publication_status === 'approved').length))}</dd></div>
      </dl>
      ${Array.isArray(pilot.scopes) && pilot.scopes.length ? `<ul class="organizer-startpartner-scopes">${pilot.scopes.map(scope => `<li>${escapeHtml(scopeText(scope))}</li>`).join('')}</ul>` : ''}
      ${blockers.length ? `<details class="organizer-startpartner-blockers"><summary>Offene Aktivierungspunkte</summary><ul>${blockers.map(item => `<li>${escapeHtml(item)}</li>`).join('')}</ul></details>` : ''}
    `;
  }

  fetch('/api/organizer-portal/pilot.php', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
    .then(async (response) => {
      if (response.status === 401 || response.status === 404) return null;
      const data = await response.json().catch(() => null);
      if (!response.ok) throw new Error(safe(data?.message) || 'pilot_state_failed');
      return data?.data?.pilot || null;
    })
    .then(render)
    .catch((error) => console.warn('Organizer portal: Startpartner pilot state unavailable.', error));
})();
