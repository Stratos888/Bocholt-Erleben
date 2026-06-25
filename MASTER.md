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

<!-- === BEGIN BLOCK: MASTER_CURRENT_CONTROL_2026_06_25 | Zweck: konsolidiert strategischen Steuerungsstand fuer nachhaltige Content-Sicherung; Umfang: Content-Quality-V1, KI-Faktencheck-Cache, naechster Feedback-Loop-Workpack, keine Growth-Arbeiten vor Stabilisierung === -->
### Aktueller Steuerungsstand: Content-Sicherung vor weiterem Wachstum

Stand: 2026-06-25.

Aktuell abgeschlossen und nicht erneut öffnen ohne konkretes Symptom:

- `/angebote/` ist nur noch Legacy-Redirect auf `/aktivitaeten/`; die Aktivitätspräsenz läuft kanonisch unter `/aktivitaeten/sichtbar-werden/...`.
- Öffentliche Neben-/Funnel-Seiten nutzen den zentralen Footer/Shell-Abschluss.
- Reporting-/Tracking-Hardening ist technisch abgeschlossen und live bewiesen.
- CSS-Governance ist eingeführt.
- Event-Visual-Motif-Fit ist für den bisherigen Sheet-Stand technisch abgeschlossen.
- Content-Quality-Grundsystem existiert: Workflow, Script, Google-Sheet-Audittab und `/inbox/`-Arbeitsbereich.
- Content Quality Guard V2 ist auf Staging als Prozessgrundlage belegt: Audit-Report mit Prozesskategorien, Arbeitspaketen und Correction Ownern; `/inbox/` zeigt Repo-Datenpatch, Quellencheck, Faktencheck, Beobachten/Retry und Visual-Fit getrennt.
- Content Quality AI Verification Fallback mit Prüfcache ist live belegt: `ai_verification_candidate`, Budgetlimit, strukturiertes Kandidaten-Artefakt, Acceptance-Tab, Cache-Writeback, Cache-Hit und bessere Runtime-Logs funktionieren.

Aktueller strategischer Zielzustand:

- Der Content Quality Guard ist ein automatischer Prüf- und Vorentscheidungsprozess, nicht eine wöchentliche manuelle Vollprüfung.
- Jedes regelmäßige Audit prüft die relevanten Event- und Activity-Daten erneut:
  1. Google-Sheet `Events` für redaktionelle Events.
  2. DB/API für freigegebene Veranstalter-Events.
  3. `data/offers.json` für Activities.
- Sichere technische Fälle werden automatisch behandelt oder still protokolliert, zum Beispiel abgelaufene ausgeblendete Events, harmlose Canonical-/Sprach-Redirects oder einzelne temporäre Netzwerkaussetzer.
- In der Content-Prüfung erscheinen nur Fälle, bei denen wirklich etwas zu tun ist und die weder durch das Prüfskript noch durch einen frischen KI-Faktencheck sicher geklärt werden können.
- Für Quellen, die das GitHub-/HTTP-Prüfskript blockieren, schwer lesbar sind oder Fakten nicht eindeutig maschinell bestätigen, gilt der Hybridmodus: billiges Audit-Skript zuerst, gezielter KI-Faktencheck nur als Fallback.
- KI-Faktenchecks werden gecacht: Wenn Quelle und Inhalt unverändert sind und `verified_until` noch gilt, wird nicht jede Woche erneut teuer geprüft.
- Die private `/inbox/` ist die Arbeitsoberfläche für Content-Prüfung und Korrektur. Der Nutzer soll nicht direkt in technischen Google-Sheet-Spalten arbeiten müssen.
- Source Ownership bleibt erhalten:
  - Sheet-Events werden aus der Content-Prüfung heraus kontrolliert ins Google Sheet `Events` zurückgeschrieben.
  - Anbieter-/DB-Events werden über Review-/Adminlogik bzw. DB-Owner korrigiert.
  - Activities bleiben repo-/JSON-owned und werden in V1 als Patch-Kandidaten gesammelt; später kann daraus ein PR-/Patchpaket-Workflow werden.
- Keine KI- oder Website-Textauswertung darf fachliche Inhalte blind überschreiben. Semantische Änderungen brauchen eine explizite geprüfte Aktion.
- Eine 0%-Fehlerquote wird nicht behauptet. Der Qualitätsstandard lautet: definierte Event-/Activity-Kerndaten regelmäßig prüfen, sichere Fälle automatisch abräumen, keine bekannten ungeprüften kritischen Fehler online lassen und nur echte Restaufgaben zur Bewertung vorlegen.

Aktueller strategischer Ausbau: KI-Suchlauf Feedback Loop / Self-Improving Search

- Die Content-Prüfung erzeugt Lernsignale für den KI-Suchlauf, damit wiederkehrende Fehler nicht nur korrigiert, sondern die Such-/Prompt-/Quellenlogik verbessert wird.
- Inbox-Ablehnungsgründe, Audit-Findings, KI-Faktencheck-Ergebnisse und Cache-/Bestätigungssignale werden nicht manuell in Suchregeln umgeschrieben, sondern strukturiert typisiert und begrenzt an den nächsten Suchlauf übergeben.
- Finale Prozessarchitektur: Content-Audit verdichtet typisierte Findings in `content-search-feedback.json` und schreibt daraus `Content_Search_Feedback(_Staging)`; der Weekly-KI-Suchlauf kombiniert diesen Tab mit realer Inbox-/Archiv-Ablehnungshistorie und baut daraus einen gedeckelten `CONTENT_SEARCH_FEEDBACK`-Promptkontext.
- Regel-Bloat ist technisch begrenzt: Feedback wird nach Fehlerklassen gruppiert, duplizierte Einzelfälle werden gezählt statt angehängt, alte Einzelablehnungen laufen aus, und der Suchprompt erhält nur die priorisierten Top-Regeln.
- Ziel ist keine unkontrolliert selbsttrainierende KI, sondern ein kontrollierter, belegbarer Prozess: Fehlerklassifikation -> Feedback-Artefakt/Ablehnungshistorie -> begrenzter Suchlauf-Kontext -> Diagnose -> Messung, ob dieselbe Fehlerklasse seltener wiederkommt.

Separater strategischer Punkt:

- KI-Visual-Key-/Bild-Fit-Qualität wird als eigener Workstream geführt.
- Die Event-Suche muss passende `visual_key`/`visual_motif`-Zuordnungen liefern oder fehlende/unsichere Bildpassung als Gap ausgeben.
- Geprüft werden muss nicht nur, ob ein Bild existiert, sondern ob das konkrete Bild bzw. Motiv zum Event passt und nicht zu oft im gleichen Kontext wiederholt wird.
- Dieser Punkt wird nicht mit der Datenwahrheitsprüfung vermischt.

Bewusst geparkt:

- Weiterer SEO-/Content-Ausbau.
- Anbieter-Akquise mit Qualitätsversprechen.
- Neue Event-Visual-Produktion ohne konkreten Gap.
- Pauschale manuelle Vollprüfung aller Inhalte in der Inbox.
- Pauschaler KI-Prüflauf über alle Inhalte ohne Kandidatenfilter, Cache und Budgetgrenze.
- Manuelle periodische KI-Regelpflege aus Ablehnungslisten ohne Automations-/Feedback-Mechanik.
<!-- === END BLOCK: MASTER_CURRENT_CONTROL_2026_06_25 === -->

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
- Current next workpack: deploy and prove the final Content Quality Feedback Loop / Self-Improving Search process patch on staging, then decide main promotion.
- The feedback-loop workpack connects typed audit findings, Inbox/rejection-reason defaults and KI-facts outcomes back into the KI search workflow without requiring the user to manually maintain search rules every few weeks.
- Content Quality Guard V2 and the AI verification cache are no longer basic-introduction workpacks; further changes need a concrete finding from current reports or from the feedback-loop design.
- Manual-KI-Intake / Visual-Key-Handoff and Activity-Premium-Visuals remain separate workstreams and must only be reopened when the current Content-Quality/Visual-Fit package produces a concrete need.
- Keep page-specific changes minimal unless a current roadmap block names a concrete owner and acceptance proof.

<!-- === END CANONICAL MASTER FILE === -->
