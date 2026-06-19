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

<!-- === BEGIN BLOCK: MASTER_POST_MAIN_MERGE_2026_06_19 | Zweck: setzt den aktuellen Projektsteuerungsstand nach Main-Merge und Live-Smoke; Umfang: naechster operativer Beweis und bewusste Nicht-Ziele === -->
### Nach Main-Merge: KI-/Inbox-Visual-Key-Handoff beweisen

Stand: 2026-06-19.

Aktueller Zustand:

- `staging` wurde erfolgreich nach `main` gemerged.
- Der Live-Bereich wurde manuell geprüft und wirkt stabil.
- Der Desktop-Alignment-Fehler der drei Today-Cards wurde nach dem Main-Merge behoben und live kontrolliert.
- Der Event-Visual-Motif-Fit-Block ist für den aktuellen Sheet-Stand abgeschlossen; es gibt keine offenen `gap_to_produce`-, `candidate_to_integrate`- oder `review_rules`-Motive.

Nächster operativer Beweis:

- Den nächsten automatischen KI-Suchlauf auf `main` bzw. den nächsten tatsächlichen Manual-KI-Intake-Lauf prüfen.
- Geplanter Kontrollzeitpunkt: Dienstag, 2026-06-23, 11:00 Uhr.

Zu prüfen:

1. `Inbox.visual_key` wird im Google Sheet mit dem KI-Vorschlag befüllt.
2. Das Dropdown für `Inbox.visual_key` enthält die erlaubten Keys aus `data/event_visual_pool.json`.
3. Ein redaktionell geänderter `visual_key` bleibt beim Übernehmen erhalten.
4. `Events.visual_key` wird korrekt geschrieben.
5. Der spätere Build übernimmt den Key in die deployten Eventdaten.
6. Event-Cards erhalten dadurch automatisch passende Bilder aus dem Event-Visual-Pool.

Bis zu diesem Beweis nicht starten:

- kein neuer breiter UI-/Feature-Workpack,
- kein erneuter Event-Visual-Produktionslauf ohne neuen Sheet-Bedarf,
- keine pauschale Activity-Öffnungsstatus-Massenpflege,
- keine Prozesshärtung des KI-/Inbox-Flows vor dem echten Main-Beweis.
<!-- === END BLOCK: MASTER_POST_MAIN_MERGE_2026_06_19 === -->

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
- First operational proof after the successful Main merge and Live smoke: evaluate the next automatic `main` search run for Manual-KI-Intake / Visual-Key-Handoff.
- Do not treat a `staging` workflow attempt or chat simulation as the final proof; the real Google-Sheet chain must be verified on `main`.
- Activity-Premium-Visuals continue as a separate workstream and must not reopen the frozen Event-Visual-Duplicate-Cleanup without a concrete symptom.
- Keep page-specific changes minimal unless a current roadmap block names a concrete owner and acceptance proof.

<!-- === END CANONICAL MASTER FILE === -->

<!-- === BEGIN BLOCK: MASTER_CURRENT_PROJECT_CONTINUATION_2026_06_09 | Zweck: ersetzt veralteten Today-Home-Fortsetzungspunkt durch aktuellen Steuerungsstand; Umfang: KI-Intake, Event-Visual-Freeze, Activity-Visuals, Activity-Opening === -->
## Aktueller Fortsetzungspunkt – KI-Intake Main-Beweis und getrennte Premium-Visual-Workstreams

Stand: 2026-06-09, `staging` ZIP-Snapshot.

Aktuell verbindlich:

- Der nächste operative Pipeline-Beweis ist der Manual-KI-Intake / Visual-Key-Handoff nach dem nächsten automatischen Suchlauf auf `main`.
- Auf `staging` ist die echte Google-Sheet-Kette bewusst nicht vollständig prüfbar, weil der Workflow per Branch-Guard geschützt ist.
- Nach dem Suchlauf ist zu prüfen, ob `Inbox.visual_key`, Dropdown, redaktionelle Key-Änderung, `Events.visual_key`, Build und Eventbild-Ausspielung zusammen funktionieren.
- Event-Visual-Duplicate-Cleanup ist vorerst gefreezt; Folgearbeit dort nur bei konkretem sichtbaren Symptom oder bewusstem neuen Eventdatenstand.
- Activity-Premium-Visuals sind ein eigener Qualitäts-Workstream mit exklusiven Bildern pro Activity und dürfen nicht mit Event-Visuals vermischt werden.
- Activity-Opening-Status ist grundsätzlich umgesetzt; Folgearbeit nur noch gezielt an Saisonlogik, Detailtexten und Sonderfällen.

Nicht als nächstes starten:

- kein weiterer breiter Event-Card-Polish ohne konkretes Symptom
- keine erneute pauschale Activity-Öffnungszeiten-Massenpflege
- keine KI-Chat-Simulation als Ersatz für den späteren `main`-Beweis
- keine Vermischung von Roadmap-Konsolidierung, Bildproduktion und App-Logik in einem Patch
<!-- === END BLOCK: MASTER_CURRENT_PROJECT_CONTINUATION_2026_06_09 === -->
