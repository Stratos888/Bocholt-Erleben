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

<!-- === BEGIN BLOCK: MASTER_CURRENT_CONTROL_2026_06_22 | Zweck: konsolidiert aktuellen Live-/Staging-Stand fuer Folgechats; Umfang: abgeschlossene Stabilisierung, geparkte Punkte, naechster operativer Beweis === -->
### Aktueller Steuerungsstand: stabilisierte Live-Basis, Content-Sicherung vor weiterem Wachstum

Stand: 2026-06-22.

Aktuell abgeschlossen und nicht erneut öffnen ohne konkretes Symptom:

- `/angebote/` ist nur noch Legacy-Redirect auf `/aktivitaeten/`; die Aktivitätspräsenz läuft kanonisch unter `/aktivitaeten/sichtbar-werden/...`.
- Öffentliche Neben-/Funnel-Seiten nutzen den zentralen Footer/Shell-Abschluss.
- Reporting-/Tracking-Hardening ist technisch abgeschlossen und live bewiesen: `/aktivitaeten/sichtbar-werden/` zählt live als Anbieter-/CTA-Klick im internen Nutzwert-Dashboard.
- CSS-Governance ist eingeführt: `style.css` ist nur CSS-Entry-Point, CSS-Audit ist im Deploy verankert, ZIP-first-Webupload ist als Fallback dokumentiert.
- Aktivitätspräsenz-Funnel und Stripe-Rücksprunglogik sind statisch gegen die neue Route geprüft.
- Event-Visual-Motif-Fit ist für den aktuellen Sheet-Stand abgeschlossen; es gibt keine offenen `gap_to_produce`-, `candidate_to_integrate`- oder `review_rules`-Motive.

Aktueller nächster Haupt-Workpack:

- Content Quality Guard einführen, bevor weiterer SEO-/Content-Ausbau oder Anbieter-Akquise gestartet wird.
- Der Guard prüft die echten aktuellen Quellen des Projekts:
  1. Google-Sheet-Tab `Events` für redaktionelle Events.
  2. `/api/events/public.php` für freigegebene DB-/Veranstalter-Events.
  3. `data/offers.json` für Activities.
- Prüfergebnisse werden in den Google-Sheet-Tab `Content_Audit` geschrieben; auf `staging` in `Content_Audit_Staging`.
- Fachliche Inhalte werden nicht blind automatisch überschrieben; sichere technische Abfangfälle, Warnungen und Review-Fälle werden klar getrennt.

Bewusst geparkt:

- Den nächsten automatischen KI-Suchlauf auf `main` bzw. den nächsten tatsächlichen Manual-KI-Intake-Lauf prüfen.
- Geplanter Kontrollzeitpunkt dafür bleibt: Dienstag, 2026-06-23, 11:00 Uhr.
- Activity-Visual-Restschuld / `fallback` / `needs_review` nur gezielt öffnen, wenn der Content Quality Guard konkrete Review-Fälle bestätigt.
- 28-/30-Tage-Reporting-Datenlauf abwarten, bevor Akquise- oder Feedbackberichte als belastbar bewertet werden.
- Keine weitere KI-/Inbox-Prozesshärtung vor dem echten Main-Handoff-Beweis.

Bis zur Einführung und ersten Bewertung des Content Quality Guard nicht starten:

- keine SEO-Themenseiten,
- kein größerer Activity-/Content-Ausbau,
- kein neuer breiter UI-/Feature-Workpack,
- kein erneuter Event-Visual-Produktionslauf ohne neuen Sheet-Bedarf,
- keine pauschale Activity-Öffnungsstatus-Massenpflege,
- keine CSS-/Doku-Großkonsolidierung ohne konkreten aktuellen Steuerungsgewinn.
<!-- === END BLOCK: MASTER_CURRENT_CONTROL_2026_06_22 === -->

---

## PERMANENT PRODUCT DECISIONS

- Product type: event website / PWA, not a city app
- Mobile-first, quiet modern UI
- Existing design tokens must be reused before new tokens are added
- All overlays render in a dedicated overlay root directly under `body`
- Deploy must fail fast on broken asset references

<!-- === BEGIN BLOCK: MASTER_PREMIUM_VISUAL_CONTRACT_2026_06_01 | Zweck: verankert die dauerhafte Bild-/Visual-Produktentscheidung; Umfang: Event- und Activity-Card-Bilder, Today Home, Feed-Cards, Premium-Qualitaet === -->
### Premium visual contract

- Event- und Activity-Card-Bilder werden kuenftig als kuratierte 16:9-WebP-Card-Assets verstanden, nicht als beliebige Rohbilder, die im Layout gerettet werden.
- Bevorzugte Quellenhierarchie: eigene/exklusive Premium-Echtfotos, vom Veranstalter bzw. Rechteinhaber freigegebene Premium-Echtfotos, sonstige rechtlich einwandfreie und qualitativ starke Fotos, danach selbst erzeugte symbolische KI-Premium-Visuals.
- Rechtlich einwandfrei bedeutet: Quelle, Lizenz/Rechtebasis, Urheber-/Credit-Angaben und ggf. Nutzungserlaubnis sind belegbar; unklare, nur scheinbar freie oder nicht sauber zuordenbare Bilder gelten nicht als `ready`.
- Wenn kein rechtlich einwandfreies Premium-Echtfoto verfuegbar ist, ist ein selbst erzeugtes symbolisches KI-Premium-Visual der bevorzugte Standard-Fallback.
- Prominente Flaechen wie Today Home duerfen nur `ready`-Visuals oder bewusst freigegebene `fallback`-Visuals nutzen.
- Schwache Bilder werden ersetzt, zurueckgestuft oder aus prominenten Flaechen ausgeschlossen; sie werden nicht dauerhaft per CSS, Crop-Rateversuchen oder Einzel-Focal-Point-Hotfixes kaschiert.
- Fuer Visuals gelten die Statuswerte `ready`, `usable`, `fallback`, `needs_review` und `blocked`.
- CSS liefert den stabilen Rahmen fuer Bildausspielung, ist aber nicht das Rettungssystem fuer ungeeignete Motive, schlechte Ausschnitte oder zu grosse Rohdateien.
- Perspektivischer Zielzustand ist ein internes Visual-Audit bzw. Vorschau-Raster, das Bilder in echten Card-Kontexten prueft: Today Mobile, Today Desktop, Events Feed, Activities Feed und spaeter Detail-/Hero-Kontexte.

<!-- === END BLOCK: MASTER_PREMIUM_VISUAL_CONTRACT_2026_06_01 === -->

### Product governance

- `Produktvertrag.md` is the only canonical source for:
  - organizer membership model
  - tariff names
  - pricing
  - token / event quota logic
  - event submission and approval rules
- `MASTER.md` may define strategic direction, but must not redefine canonical product mechanics from `Produktvertrag.md`.

### Information architecture

- `/` is the canonical Today/Home recommendation entry and current public home.
- `/events/` is the canonical event search and browsing route.
- `/aktivitaeten/` is the canonical activities search and browsing route.
- `/angebote/` is a legacy redirect to `/aktivitaeten/` and must not contain independent activities content.
- `/aktivitaeten/sichtbar-werden/` is the canonical activity-presence decision page.
- `/aktivitaeten/sichtbar-werden/einreichen/` is the canonical activity-presence submission page.
- `/aktivitaeten/sichtbar-werden/erfolg/` is the canonical activity-presence success/status page.
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
- Current next workpack: introduce and validate the Content Quality Guard against the real Sheet-/DB-/Repo content sources.
- Manual-KI-Intake / Visual-Key-Handoff remains parked until the scheduled real `main` check; do not treat a `staging` workflow attempt or chat simulation as the final proof.
- Activity-Premium-Visuals continue as a separate workstream and must not reopen the frozen Event-Visual-Duplicate-Cleanup without a concrete symptom or Content-Quality-Guard finding.
- Keep page-specific changes minimal unless a current roadmap block names a concrete owner and acceptance proof.

<!-- === END CANONICAL MASTER FILE === -->
