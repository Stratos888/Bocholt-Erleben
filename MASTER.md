# MASTER CONTROL FILE — BOCHOLT ERLEBEN

<!-- === BEGIN CANONICAL MASTER FILE: Strategic project control only === -->

## PROJECT GOAL

Bocholt erleben is a mobile-first production event discovery PWA for Bocholt.

The product must feel:

- trustworthy
- calm
- modern
- stable
- easy to scan

Business goals:

- reliable event discovery
- reliable organizer onboarding
- fair but monetizable event/location publishing

---

## FROZEN AREAS

The following areas are frozen unless a concrete bug is proven:

- Event Feed core UX
- Event Detailpanel
- Event-Card Normal State Polish

The following workpack is intentionally on hold:

- State Transition & Hierarchy Polish

---

## CURRENT FOCUS

1. Live-Messbasis konsolidieren: vorhandene Live-KPIs nicht mehr als fehlend behandeln, sondern Tracking-Qualität, Ziel-/Item-Zuordnung und auswertbare Veranstalter-/Location-Reports herstellen.
2. Monetarisierungs-Readiness absichern: echten Live-Zahlungsfall bewusst testen, kritische Smoke-Tests automatisieren und Review-/Push-Flows gegen stille Ausfälle härten.
3. Veranstalter-Nutzwert sichtbar machen: Anbieterbereich, Akquise-Kommunikation und Feedbackberichte so ausbauen, dass Reichweite, Interesse und konkrete Klicks belegbar werden.
4. Danach Discovery-Ausbau: „Heute in Bocholt“, Aktivitäten und Orte/Locations.

---

## PERMANENT PRODUCT DECISIONS

- Product type: event website / PWA, not a city app
- Mobile-first, quiet modern UI
- Existing design tokens must be reused before new tokens are added
- All overlays render in a dedicated overlay root directly under `body`
- Deploy must fail fast on broken asset references

### Product governance

- `Produktvertrag.md` is the only canonical source for:
  - organizer membership model
  - tariff names
  - pricing
  - token / event quota logic
  - event submission and approval rules
- `MASTER.md` may define strategic direction, but must not redefine canonical product mechanics from `Produktvertrag.md`.

### Information architecture

- `/` remains the current public home until the planned `Heute` recommendation home replaces it.
- `/events/` is the canonical event search and browsing route.
- `/aktivitaeten/` is the canonical activities search and browsing route.
- `/angebote/` remains a legacy/transition route for the activities page until redirect/canonical cleanup is finalized.
- `/angebote/sichtbar-werden/` is the canonical activity-presence decision page.
- `/angebote/sichtbar-werden/einreichen/` is the canonical activity-presence submission page.
- `/angebote/sichtbar-werden/erfolg/` is the canonical activity-presence success/status page.
- `/events-veroeffentlichen/` is the canonical organizer funnel overview.
- `/events-veroeffentlichen/einreichen/` is the canonical single-event submission route.
- `/events-veroeffentlichen/anbindung/` is the canonical automatic-takeover request route.
- `/fuer-veranstalter/` is the canonical organizer membership route.
- `/ueber/` is the canonical trust/explanation page.
- `/veroeffentlichung-erklaert/` is the canonical central explanation route for publication, review, payment/freigabe, fairness and activity-vs-event distinction.
- `/info/` is legacy backup/redirect only and is not the current canonical information hub.
- Success, cancellation, login, dashboard and inbox routes are functional routes, not public SEO landing pages unless explicitly promoted.
- Legacy routes or older pages may still temporarily exist in the repo during migration, but repo presence alone is not canonical information architecture.
- The locations modal is final as an explanation / entry layer, not as a pricing table.

---

## NEXT WORKPACK

- Work from `ROADMAP.md` as the tactical prioritized backlog.
- First target: measured organizer value, not another broad visual-polish loop.
- Keep page-specific changes minimal until the measurement/reporting and monetization smoke-test gaps are closed.

<!-- === END CANONICAL MASTER FILE === -->

<!-- === BEGIN BLOCK: MASTER_TODAY_HOME_PREMIUM_CONTINUATION_2026_06_01 | Zweck: aktueller Projektstand Today Home + Event Visuals ohne Freeze; Umfang: strategischer Fortsetzungspunkt === -->
## Aktueller Fortsetzungspunkt – Today Home Premium Completion + Event Visuals

Stand: 2026-06-01, `staging`, HEAD zuletzt geprüft: `9115d43`.

Wichtig: Today Home wird **nicht** als V1 eingefroren. Ziel bleibt eine vollständig premium-fertige Home-/Today-Erfahrung für Mobile und Desktop.

Bereits sauber umgesetzt und auf Staging geprüft:
- `/` ist als Today-/Home-Einstieg aktiv.
- Today Home zeigt einen kompakten Empfehlungsbereich „Was passt jetzt?“ mit 3 Tipps.
- Recommendation-Mix ist fachlich korrigiert:
  - Events erscheinen auf Today Home nur noch, wenn sie heute starten oder als Mehrtagesevent heute laufen.
  - Zukünftige Events, z. B. ein Termin am 14.06., erscheinen nicht mehr als Heute-Tipp.
  - Wenn keine heutigen Events vorhanden sind, zeigt Today Home 3 Aktivitäten.
- Event-Visual-System ist technisch produktionsnah aufgebaut:
  - `visual_key` wird für Events genutzt.
  - `data/event_visual_pool.json` liefert nur `status: ready`-Assets an die UI.
  - 7 neue bzw. ersetzte Event-Visuals wurden akzeptiert, WebP-komprimiert, committed und auf Staging geprüft.
  - Staging-Coverage: 62/62 aktuelle Events haben ein ready Event-Visual.
- Neue/ersetzte ready Assets:
  - `music-stage-01.webp`
  - `default-city-02.webp`
  - `culture-exhibition-01.webp`
  - `market-food-01.webp`
  - `sport-active-01.webp`
  - `creative-workshop-01.webp`
  - `kids-family-01.webp`

Offen für Premium Completion:
- Today Home nicht nur funktional, sondern visuell/produktlogisch auf Premium-Endzustand bringen.
- Mobile und Desktop separat gegen Premium-Anspruch prüfen.
- Aktivitätsbilder und Eventbilder im echten Home-Kontext konsistent bewerten.
- Keine weiteren Randpolish-Workpacks ohne konkretes sichtbares Symptom; aber klare Premium-Gaps aktiv bearbeiten.
- Nach Today Home Premium Completion direkt das Event-Visual-System auf den normalen Events Feed übertragen.
<!-- === END BLOCK: MASTER_TODAY_HOME_PREMIUM_CONTINUATION_2026_06_01 === -->
