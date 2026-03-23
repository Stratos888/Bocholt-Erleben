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

1. Subpage system quality and consistency
2. `/info/` as the reference-quality explanation + navigation hub
3. Global design-system consolidation after subpage quality is stable

---

## PERMANENT PRODUCT DECISIONS

- Product type: event website / PWA, not a city app
- Mobile-first, quiet modern UI
- Existing design tokens must be reused before new tokens are added
- All overlays render in a dedicated overlay root directly under `body`
- Deploy must fail fast on broken asset references

### Organizer / location model

- No free tariff
- Monthly volume-based pricing model:
  - Starter: 9,99 € / month, up to 3 events
  - Aktiv: 19,99 € / month, up to 8 events
  - Dauerhaft: 39,99 € / month, unlimited events
- No paid visual prioritization
- No ads
- No design favoritism
- Monthly cancellation should remain possible

### Information architecture

- `/info/` is a hybrid of explanation + direct CTA + navigation
- `/events-veroeffentlichen/` is the only organizer funnel page
- `/ueber/` exists for trust
- `/fuer-veranstalter/` is removed; useful content is integrated elsewhere
- The locations modal is final as an explanation / entry layer, not as a pricing table

---

## NEXT WORKPACK

- Finish remaining subpage alignments against the current UI DNA
- Then consolidate the global design system with CSS tokens + component mapping
- Keep page-specific changes minimal during design-system consolidation

<!-- === END CANONICAL MASTER FILE === -->
