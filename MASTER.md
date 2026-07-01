<!-- === BEGIN BLOCK: MASTER_ACTIVITY_FAVORITES_STATUS_2026_06_30 | Zweck: aktueller Projektstatus fuer Activity-Favoriten und Browser-Smoke-Abschluss; Umfang: Einstiegspunkt fuer naechste Chats === -->
## Aktueller Stand – Activity-Favoriten und Card-Parity

P1 Browser-Smoke ist auf Main abgenommen: 21/21 OK, 0 Fehler, 0 Warnungen.

Der folgende Produktreife-Schritt ist lokale Activity-Favoriten mit Herz, ohne Login, ohne Cookies und ohne Backend. Favorisierte Aktivitaeten werden in der Aktivitaetenliste still bevorzugt; sie sind kein Schnellfilter-Pill und erzeugen keine eigene Listen-Section. Events erhalten keine Favoritenlogik; dort bleibt die bestehende Kalender-/Terminaktion massgeblich.

Mobile Activity-Cards werden in der Bildgeometrie an Event-Cards angeglichen, damit der Wechsel zwischen Events und Aktivitaeten ruhiger wirkt.

Mobile Schnellfilterleisten werden auf Mobile als einzeilige horizontale Chip-Rail gefuehrt; Desktop bleibt beim bestehenden Wrap-/Grid-Verhalten.
<!-- === END BLOCK: MASTER_ACTIVITY_FAVORITES_STATUS_2026_06_30 === -->

<!-- === BEGIN BLOCK: MASTER_BROWSER_SMOKE_CONTROL_V1_2026_06_29 | Zweck: legt Browser-Smoke als naechsten Produktreife-Sicherheitsgurt fest; Umfang: Betriebsentscheidung, Fehlerreaktion, Abgrenzung zu Content/KI === -->
## Browser-Smoke — strategischer Kontrollstand

Stand: 2026-06-29.

Der Browser-Smoke ist ein kleiner Sicherheitsgurt fuer zentrale Nutzerwege. Er wird nicht als Volltestframework betrieben.

Regeln:

- Automatisch nach Staging-/Main-Deploy pruefen.
- Manuell ohne Redeploy ueber GitHub Actions `Browser Smoke` pruefbar machen.
- Read-only bleiben: keine echten Formulare absenden, keine Zahlungen, keine E-Mails.
- Fehler muessen mit Route, Profil, Screenshot und Summary sichtbar werden.
- Automatische Reparatur oder Rollback ist nicht Teil von V1.
- Content-, KI- und Audit-Probleme bleiben eigene Workstreams.

P1 gilt erst als abgeschlossen, wenn der Deploy-Smoke und der manuelle Smoke diese Regeln erfuellen.
<!-- === END BLOCK: MASTER_BROWSER_SMOKE_CONTROL_V1_2026_06_29 === -->

<!-- === BEGIN BLOCK: MASTER_INBOX_CONTENT_ROUTING_CONTROL_2026_06_26 | Zweck: aktualisiert den strategischen Steuerungsstand fuer Inbox, Content-Pruefung und Visual-Backlog-Trennung; Umfang: Produkt-/Prozessregel, kein Implementierungsdetail === -->
### Inbox und Content-Pruefung – strategische Steuerungsregel

Stand: 2026-06-26.

Die private `/inbox/` ist keine technische Fehlerliste, sondern eine mobile-first Ausnahme-Arbeitsansicht.

Grundregeln:

- Neue Events und bestehende Content-Prueffaelle bleiben getrennte Queues.
- In der Content-Pruefung erscheinen nur Faelle, bei denen eine menschliche Entscheidung oder Bestaetigung wirklich noetig ist.
- Automatisch ableitbare, rein technische oder backlog-faehige Hinweise sollen nicht als einzelne manuelle Karten erscheinen.
- Activity-Visual-Gaps, z. B. fehlender `visual_key` bei ansonsten nutzbarer Activity mit vorhandener Quelle/Bildbasis, werden als Visual-/Bildbedarf aggregiert und nicht als Content-Sofortaktion behandelt.
- Offizielle Quellen haben Vorrang vor Ticketportalen als primaere Eventquelle. Ticketportale sollen nur bewusst behalten oder als Buchungslink gefuehrt werden.
- Die UI soll mobile-first, knapp und handlungsorientiert bleiben: kurze Labels, klare Hauptaktion, technische Details nur nachrangig bzw. im Debug-Kontext.

Dieser Stand ist vorerst eingefroren und wird durch reale mobile Nutzung validiert, bevor weitere UI-/Prozessumbauten erfolgen.
<!-- === END BLOCK: MASTER_INBOX_CONTENT_ROUTING_CONTROL_2026_06_26 === -->

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

<!-- === BEGIN BLOCK: MASTER_PRODUCT_MATURITY_CONTROL_2026_06_29 | Zweck: strategische Steuerung fuer nicht-contentbezogene Produktreife; Umfang: Datenschutz/Tracking, Smoke-Tests, Nutzerbindung, Standort, Anbieter-/Rechtsreife, Konsolidierung === -->
### Produktreife-Roadmap ausserhalb Content-Operation

Stand: 2026-06-29.

Dieser Block steuert die naechsten groesseren Arbeiten, wenn Content-Live-Lauf, KI-Suche und Content-Pruefung bewusst ausgeklammert werden.

Validierte Reihenfolge:

1. Datenschutz-/Tracking-Konsistenz herstellen.
   - GA4, internes Nutzwerttracking, LocalStorage, Push, Formspree, Stripe und Anbieterbereich muessen in Technik, Datenschutztext und ggf. Einwilligung konsistent beschrieben bzw. gesteuert sein.
   - Der bisherige Widerspruch `Analytics aktiv` vs. `/datenschutz/` mit `kein Tracking` ist mit dem Consent-/Datenschutz-Patch behoben; nach Upload/Deploy bleibt nur der Live-Smoke.

2. Browser-Smoke-Tests fuer Kernwege einfuehren.
   - Vor groesseren neuen Produktfeatures braucht die App wenige echte Browserpruefungen fuer Home, Events, Activities, Einreichung, Zahlung-starten, Anbieterlogin und Dashboard.
   - Ziel ist ein kleines Sicherheitsnetz, keine Volltestabdeckung.

3. Nutzerbindung produktisieren: Merken / Fuer dich / Erinnern.
   - Vorhandene lokale Profil-/Recommendation-Logik soll als klares Nutzerfeature sichtbar werden.
   - Keine Account-/Sync-Pflicht. Erinnern/Push erst nach Datenschutz-/Einwilligungsentscheidung.

4. Standort-/Karten-/Naehe-Schicht aufbauen.
   - Zuerst Datenmodell fuer Koordinaten/Location-IDs und einheitliche Maps-Ziele, danach erst UI wie Karte oder Naehe-Sortierung.

5. Anbieterbereich und Verkaufsstrecke verkaufsfertig machen.
   - Technischer Anbieterbereich ist vorhanden; naechster Hebel ist Verstaendlichkeit: Nutzen, Status, naechste Aktion, Zahlungs-/Abo-Status und Billing fuer echte Anbieter.

6. Recht-/Verkaufsseiten fuer bezahlte Produkte haerten.
   - Oeffentliche Texte muessen zum `Produktvertrag.md` passen: redaktionelle Pruefung, Zahlung, Ablehnung, Laufzeit, Kuendigung, Widerruf/AGB, Datenschutz.

7. UI-/CSS-/JS-Konsolidierung gezielt fortsetzen.
   - Keine Neuarchitektur; nur owner-file-orientierte Entflechtung bei konkretem Feature-/Bugfix-Kontext.

Abgrenzung:

- Der Content-/KI-/Audit-Strang bleibt separat im bestehenden Current-Control-Block gefuehrt.
- Growth/SEO bleibt datenabhaengig und ist kein Ersatz fuer Produktreife.
- Neue Nutzerfeatures duerfen nicht vor Datenschutz-/Tracking-Konsistenz und minimalem Browser-Smoke-Netz groesser gebaut werden.
<!-- === END BLOCK: MASTER_PRODUCT_MATURITY_CONTROL_2026_06_29 === -->

<!-- === BEGIN BLOCK: MASTER_CURRENT_CONTROL_2026_06_27 | Zweck: konsolidiert aktuellen Projektbesitzer-Steuerungsstand nach Doku-/Roadmap-Abgleich; Umfang: Betrieb statt Featureaufbau, erledigte Grossbloecke, echte naechste Steuerung === -->
### Aktueller Steuerungsstand: Betrieb beweisen, Restluecken gezielt schliessen

Stand: 2026-06-27.

Aktuell nicht erneut als grosse offene Workpacks behandeln:

- `/angebote/` ist nur noch Legacy-Redirect auf `/aktivitaeten/`; die Aktivitätspräsenz läuft kanonisch unter `/aktivitaeten/sichtbar-werden/...`.
- Öffentliche Neben-/Funnel-Seiten nutzen den zentralen Footer/Shell-Abschluss.
- Reporting-/Tracking-Hardening ist technisch abgeschlossen und live bewiesen; belastbare Akquise-Aussagen brauchen nur noch organische 28-/30-Tage-Daten.
- CSS-Governance ist eingeführt.
- Mail-System V1 ist zentral umgesetzt und getestet; weitere Mailarbeit nur bei neuem konkretem Mailtopic oder Zustell-/Darstellungsfehler.
- Aktivitaetspraesenz-/Abo-Livebeweis ist als erledigt zu fuehren; neue Abo-/Billing-Tests nur bei konkreter Flow-Aenderung oder Stripe-Symptom.
- Anbieterbereich und Nutzwert-Metrikpfad sind technisch vorhanden; kein neuer Dashboard-Aufbau ohne Datenbefund.
- Event-Visual-Motif-Fit ist für den bisherigen Sheet-/Matrix-Stand abgeschlossen.
- Badegewaesserstatus-Proof / Guard V2 ist abgehakt: Guard -> `data/bathing_water_status.json` -> Commit -> Deploy -> Frontend-Override -> keine falsche Badeempfehlung.
- Seasonal Activity Highlights V1 ist abgeschlossen; kein geplanter Content-Batch-02 als Pflichtfolge.
- Content-Quality-Grundsystem existiert: Workflow, Script, Google-Sheet-Audittab, Prüfcache, Acceptance-/Feedback-Artefakte und private `/inbox/`-Arbeitsoberfläche.
- KI-Suchlauf -> Manual Inbox -> Visual-Key-Handoff -> Events-/Live-Bildausspielung ist auf `main` mit dem aktuellen Prüflauf bestanden.

Aktueller strategischer Zielzustand:

- P0 Datenschutz-/Tracking-Konsistenz ist mit diesem Patch umgesetzt: Statistik erst nach aktiver Zustimmung, Datenschutzseite aktualisiert, interne Nutzwertmetriken serverseitig consent-geschuetzt. Nach Upload/Deploy ist nur ein kurzer Live-Smoke noetig.
- Naechster nicht-contentbezogener Workpack danach: P1 Browser-Smoke-Tests fuer Kernwege.

- Der Content Quality Guard ist ein automatischer Prüf- und Vorentscheidungsprozess, nicht eine wöchentliche manuelle Vollprüfung.
- In der privaten `/inbox/` erscheinen nur Fälle, bei denen wirklich eine menschliche Entscheidung oder Bestätigung nötig ist.
- Activity-Visual-Gaps mit nutzbarem Übergangsbild sind kleine Visual-Pruefpunkte / To-dos, aber keine normale Content-Sofortaktion.
- Google-Sheet-Events, Anbieter-/DB-Events und repo-owned Activities behalten ihre jeweiligen Owner; keine KI darf fachliche Inhalte blind überschreiben.
- Eine 0%-Fehlerquote wird nicht behauptet. Ziel ist: kritische Kernfelder regelmäßig prüfen, sichere Fälle automatisch abräumen, echte Restentscheidungen knapp vorlegen.
- KI-Suchlauf-Feedback bleibt kontrolliert: typisierte Fehlerklassen und Ablehnungshistorie werden begrenzt in den nächsten Suchlauf gegeben; kein unkontrolliertes Selbsttraining und kein Rohdump alter Einzelfälle.

Aktuell echte nächste Steuerungspunkte:

1. Geplanten Dienstag-/Mittwoch-Liveprozess beobachten und danach als Beweis dokumentieren.
2. Inbox-Owner-UX für `visual_key` verbessern: sichtbar, verständlich, vor Übernahme korrigierbar.
3. Feedback-Loop-Livewirkung belegen: Suchlauf liest Regeln, Logs zeigen angewandte Feedbackklassen, Wiederholfehler sinken.
4. Drei Activity-Visual-Reste als kleinen Pruefpunkt / To-do behalten (`buergerpark-rhede`, `suderwicker-maerchenspielplatz`, `waldlehrpfad-am-vossenpand`), aber nicht als Content-Blocker oder normale Inbox-Aktion.
5. Roadmap und Teststatus current-first halten, damit erledigte Spezialworkstreams nicht wieder als neue Grossbaustellen gelesen werden.

Bewusst geparkt:

- Weiterer SEO-/Content-Ausbau bis belastbare GSC-/GA4-/Nutzwertdaten vorliegen.
- Anbieter-Akquise mit Qualitätsversprechen bis Reportingdaten belastbar sind.
- Neue Event-Visual-Produktion ohne konkreten Gap.
- Pauschale manuelle Vollprüfung aller Inhalte in der Inbox.
- Pauschaler KI-Prüflauf über alle Inhalte ohne Kandidatenfilter, Cache und Budgetgrenze.
- Mail-System-Neuentwicklung ohne konkreten neuen Mailfall.
- Badegewaesser-Neu-Proof; Guard V2 / Badegewaesserstatus-Proof ist abgehakt, nicht neu zu entwerfen.
<!-- === END BLOCK: MASTER_CURRENT_CONTROL_2026_06_27 === -->

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
- Current next workpack: planned Tuesday/Wednesday live process beobachten, danach Beweisstatus in `TEST_STATUS.md` dokumentieren.
- If the live process is green, the next implementation workpack is narrowly scoped: Inbox visual-key owner UX, so event candidates show the proposed image type and can be corrected before takeover.
- Do not reopen Mail-System, Anbieter-Dashboard, Badegewaesser-Proof, Activity-Presence/Abo live proof, Event-Visual-Motif-Fit or broad Content-Quality architecture without a concrete new symptom.
- Activity-Visual residuals remain a small visual check / todo, but are not content blockers.
- Keep page-specific changes minimal unless a current roadmap block names a concrete owner and acceptance proof.

<!-- === END CANONICAL MASTER FILE === -->
