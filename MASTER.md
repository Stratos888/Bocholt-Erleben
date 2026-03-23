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

### Product governance

- `Produktvertrag.md` is the only canonical source for:
  - organizer membership model
  - tariff names
  - pricing
  - token / event quota logic
  - event submission and approval rules
- `MASTER.md` may define strategic direction, but must not redefine canonical product mechanics from `Produktvertrag.md`.

### Information architecture

- `/info/` is a hybrid of explanation + direct CTA + navigation
- `/events-veroeffentlichen/` is the canonical organizer funnel page
- `/ueber/` exists for trust
- Legacy routes or older pages may still temporarily exist in the repo during migration, but repo presence alone is not canonical information architecture
- The locations modal is final as an explanation / entry layer, not as a pricing table

---

## NEXT WORKPACK

- Finish remaining subpage alignments against the current UI DNA
- Then consolidate the global design system with CSS tokens + component mapping
- Keep page-specific changes minimal during design-system consolidation

<!-- === END CANONICAL MASTER FILE === -->
