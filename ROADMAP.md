<!-- === BEGIN BLOCK: ROADMAP_CURRENT_OWNER_VIEW_2026_06_27 | Zweck: current-first Status fuer Projektbesitzer nach Doku-Abgleich; Umfang: echte naechste Workpacks, abgeschlossene Punkte, kleine Rest-To-dos, Dokumentationshygiene === -->
## Current Owner Roadmap – Stand 2026-06-27

Dieser Block ist die aktuelle Einstiegsschicht fuer Folgechats. Aeltere Roadmap-Bloecke darunter bleiben als Historie/Beweisarchiv erhalten, sind aber **keine automatisch offenen To-dos**.

### Aktueller Projektzustand

Das Projekt ist nicht mehr im Feature-Aufbau-Grundmodus, sondern im Betriebs-/Qualitaetssicherungsmodus.

Als erledigt bzw. nicht mehr als grosses naechstes To-do zu behandeln:

- Mail-System V1: zentraler Topic-/HTML-/Plaintext-Mailpfad ist umgesetzt und getestet; weitere Mailarbeit nur bei neuem konkretem Mailtopic oder Zustell-/Darstellungsfehler.
- Anbieterbereich/Nutzwertdaten: technische Basis mit Dashboard-/Metrikpfad ist vorhanden; naechste Bewertung erst nach belastbarem 28-/30-Tage-Datenlauf.
- Aktivitaetspraesenz-/Abo-Livebeweis: als erledigt behandeln; Activity-Presence-Funnel, Zahlungslink-/Checkout-Kette und Anbieterbereich-Kontext sind im Teststatus belegt. Weitere Abo-Tests nur bei konkreter Flow-Aenderung oder Stripe-/Billing-Symptom.
- Event-Visual-Motif-Fit: fuer den aktuellen Sheet-/Matrix-Stand abgeschlossen; neue Event-Visual-Arbeit nur bei neuem konkretem Gap oder falscher Bildzuordnung.
- Badegewaesserstatus-Proof / Guard V2: abgehakt. Safe-Writeback ueber `data/bathing_water_status.json`, Deploy und Frontend-Override sind auf Staging belegt; kein neuer Proof-Block und kein aktives To-do.
- Seasonal Activity Highlights V1: abgeschlossen; keine aktive Content-Batch-02-Pflicht.
- Content-Audit/Inbox-Routing: Prozessbasis ist umgesetzt; normale Inbox soll nur echte Entscheidungen zeigen, nicht technische Backloglisten.

### Echte naechste Prioritaeten nach gruenem Dienstag-/Mittwoch-Lauf

1. **Live-Lauf belegen und dokumentieren**
   - Dienstag: Weekly-KI-Suche, Manual-Inbox-Import, Ablehn-/Archivlogik, Visual-Key-Handoff beobachten.
   - Mittwoch: Content-Audit mit echten Sheet-/Runtime-Events pruefen.
   - Ziel: nicht neu bauen, sondern beweisen, dass der Betriebsprozess selbststaendig laeuft.

2. **Inbox: Visual-Key vor Uebernahme komfortabel korrigierbar machen**
   - Der Handoff `Inbox.visual_key -> Events.visual_key -> Runtime-Bild` ist belegt.
   - Offener Rest ist die bessere Owner-Bedienung: sichtbarer Bildtyp, klarer Labeltext und Dropdown/Korrektur vor Uebernahme.
   - Ziel: falsche Live-Bilder frueh verhindern, ohne den Such-/Importprozess neu zu bauen.

3. **Feedback-Loop-Livebeweis dokumentieren**
   - Der Feedback-Loop ist lokal/statisch umgesetzt.
   - Noch zu belegen: Weekly-Suchlauf liest Feedbackregeln/Ablehnungshistorie sichtbar ein und reduziert Wiederholfehler.
   - Ziel: Beweis/Logging/Status, kein neuer Mechanik-Entwurf.

4. **Activity-Visual-Rest als Pruefpunkt / kleines To-do behalten**
   - Der aktuelle Audit nennt noch `buergerpark-rhede`, `suderwicker-maerchenspielplatz` und `waldlehrpfad-am-vossenpand` als `visual_backlog_observation`.
   - Diese Punkte sind **keine Content-Blocker** und sollen nicht in die normale Content-Inbox.
   - Sie bleiben aber bewusst als kleiner Visual-Pruefpunkt offen: pruefen, ob Uebergangsbilder reichen oder ob Premium-Pool-Ergaenzungen sinnvoll sind.

5. **Dokumentationshygiene current-first halten**
   - `MASTER.md` steuert Strategie.
   - `ROADMAP.md` steuert aktuelle taktische Reihenfolge.
   - `TEST_STATUS.md` ist Beweisarchiv und Testindex.
   - Spezialdokumente wie `MAIL_SYSTEM.md`, `BATHING_WATER_*`, `VISUAL_WORKFLOW.md`, `ACTIVITY_VISUAL_*` bleiben als Fachvertraege/Workstream-Historie erhalten.
   - Nicht loeschen: Die Dateien sind redundant wirkend, haben aber unterschiedliche Rollen. Problem ist nicht Dateimenge, sondern veraltete offene Hinweise ohne current-first Klarstellung.

### Nicht als naechstes starten

- keinen manuellen bezahlten KI-Lauf ohne konkreten Testzweck,
- keine grosse UI-Runde ohne echte neue Mobile-Faelle,
- keine neue Event-Bildproduktion ohne konkreten Matrix-/Audit-Gap,
- keine SEO-/Growth-Landingpages vor belastbaren Daten,
- keine Mail-System-Neuentwicklung,
- keinen Badegewaesser-Neu-Proof; Guard V2 / Badegewaesserstatus-Proof ist abgehakt.

<!-- === END BLOCK: ROADMAP_CURRENT_OWNER_VIEW_2026_06_27 === -->

<!-- === BEGIN BLOCK: ROADMAP_INBOX_CONTENT_AUTOMATION_ROUTING_FREEZE_2026_06_26 | Zweck: setzt den aktuellen Inbox-/Content-Quality-Fortsetzungspunkt nach Mobile-Kompaktfreeze; Umfang: naechste Arbeit erst nach echter Nutzung, keine sofortige neue UI-Runde === -->
## Inbox / Content-Pruefung – Mobile-Kompaktstand eingefroren, echte Nutzung abwarten (2026-06-26)

Status: aktueller Arbeitsstand eingefroren.

### Aktuelle Prozessentscheidung

Die Content-Pruefung wird als automatisierter Kreislauf gefuehrt:

`Audit -> Vorentscheidung/Routing -> nur echte Ausnahmefaelle in Inbox -> strukturierte Rueckmeldung -> bessere KI-Suche und weniger Wiederholfehler`.

Die Inbox soll nicht alle Warnungen anzeigen. Sie soll nur Faelle zeigen, in denen der Nutzer wirklich gebraucht wird.

### Routing-Regeln fuer den eingefrorenen Stand

- Neue Event-Kandidaten bleiben in `Neue Events`.
- Echte Quellen-/Fakten-/Korrekturentscheidungen bleiben in `Content-Pruefung`.
- Reine Activity-Visual-Gaps werden nicht als normale Content-Aktion behandelt, sondern aggregiert in den Visual-Backlog bzw. `Content_Visual_Feedback(_Staging)` gegeben.
- Beispiel: `Buergerpark Rhede` mit fehlendem `visual_key`, aber nutzbarer Activity und vorhandener Quelle/Bildbasis, ist kein manueller Sofortfall.
- Ticketportal als primaere Eventquelle ist ein manueller Fall, wenn eine offizielle Quelle vorgeschlagen wird; Normalaktion ist `Offizielle Quelle uebernehmen`.

### UX-Regel

Mobile ist die fuehrende Nutzungsrealitaet fuer die Inbox.

Daher gilt fuer weitere Inbox-Arbeiten:

- keine langen Erklaertexte,
- keine technischen Pflichtlisten im normalen Flow,
- keine gleichrangigen Spezialaktionen, wenn es eine klare Hauptaktion gibt,
- Counts direkt in den Tabs,
- Hilfe nur kurz, kontextuell und moeglichst ueber Label/Title/Status,
- technische Details nur fuer Debug/Diagnose.

### Naechster Schritt

Kein weiterer Umbau sofort.

Erst nach realer Nutzung sammeln:

1. Screenshot eines verbleibenden unklaren Content-Falls,
2. erwartete Aktion aus Nutzersicht,
3. tatsaechlich angebotene Aktionen,
4. ob der Fall automatisch, Backlog oder manuell sein sollte.

Dann gezielt nachschaerfen.
<!-- === END BLOCK: ROADMAP_INBOX_CONTENT_AUTOMATION_ROUTING_FREEZE_2026_06_26 === -->

# ROADMAP — BOCHOLT ERLEBEN

<!-- === BEGIN CANONICAL ROADMAP FILE: Tactical prioritized backlog only === -->

## Rolle dieses Dokuments

Dieses Dokument hält die priorisierten nächsten Arbeitsblöcke fest.

Es ist keine Produktdefinition und keine Engineering-Regeldatei.

Kanonische Rollen:

- `Produktvertrag.md` = Produktlogik, Preise, Modelle, Kontingente
- `MASTER.md` = strategische Projektsteuerung
- `ROADMAP.md` = taktische To-do-Liste und Reihenfolge der nächsten Workpacks
- `ENGINEERING.md` = harte Umsetzungsregeln und Arbeitsmodi
- `TEST_STATUS.md` = geprüfte Funktionsstände und offene Regressionstests

---

<!-- === BEGIN BLOCK: ROADMAP_INBOX_VISUAL_KEY_REVIEW_UI_2026_06_25 | Zweck: definiert den naechsten kleinen Workpack nach bestandenem Main-KI-Suchlauf-Handoff; Umfang: Visual-Key-Anzeige, Dropdown-Korrektur, Lernsignal, keine neue Bildproduktion === -->
## Inbox Review – Visual Key sichtbar und änderbar machen (nächster Workpack)

Status: nächster empfohlener UI-/Prozess-Workpack nach bestandenem Main-Beweis für KI-Suchlauf, Manual Inbox, Visual-Key-Handoff und Live-Bildausspielung.

### Ziel in einem Satz

Die Inbox-PWA soll den von der KI vorgeschlagenen `visual_key` vor der Veröffentlichung sichtbar machen und redaktionell änderbar speichern, damit falsche Bildtypen vor Livegang korrigiert und später als Lernsignal genutzt werden können.

### Auslöser

Im Main-Proof wurde der Fall `Bocholter Gesundheitstage` sichtbar:

- KI-/Fallback-Logik führte zunächst zu `indoor_sport_competition`.
- Live erschien dadurch ein Darts-/Sporthallenbild.
- Fachlich korrekt war `business_messe_info`.
- Nach manueller Korrektur in `Events.visual_key` und Deploy wurde das Bild korrekt ausgetauscht.
- Der technische Handoff ist nach Apps-Script-Fix bestanden; die Review-UI ist aber noch blind gegenüber dem Bildtyp.

### Zielzustand V1

In der Inbox-Karte für neue Events gibt es einen kompakten Bildtyp-Block:

- Aktueller `visual_key` sichtbar.
- Dropdown mit den erlaubten Keys aus `event_visual_pool.json`.
- Änderung wird vor `Übernehmen` im Sheet gespeichert oder beim Übernehmen mitgegeben.
- Der finale Wert landet nach `Übernehmen` in `Events.visual_key`.
- Optional: ursprünglicher KI-Vorschlag bleibt als separates Feld/Notiz auswertbar, damit spätere Suchlauf-Regeln lernen können.

### Akzeptanzkriterien

- Eine KI-Inbox-Zeile mit `visual_key` zeigt den Key in der PWA sichtbar an.
- Der Nutzer kann den Key vor dem Übernehmen ändern.
- Es gibt keine freien Tippfehlerwerte; Auswahl ist auf bekannte Pool-Keys begrenzt.
- Nach `Übernehmen` steht der geänderte Wert in `Events.visual_key`.
- `Deploy jetzt` veröffentlicht danach das passende Bild.
- Der bestehende Apps-Script-Approve-Fix bleibt kompatibel.

### Nicht-Ziel

- Kein neues Event-Visual-System.
- Keine neue Bildproduktion.
- Keine Abschaffung der menschlichen Inbox-Prüfung.
- Kein blindes automatisches Überschreiben von Eventdaten.
- Kein vollständiger Self-Improving-Search-Feedback-Loop; dieser bleibt ein separater Content-Quality-Workpack.

### Verhältnis zum Feedback-Loop

Dieser Workpack ist die redaktionelle Eingabeschicht für spätere Automatisierung:

`KI-Vorschlag -> redaktionell geänderter finaler visual_key -> strukturiertes Lernsignal -> bessere Suchlauf-/Mapping-Regeln`.
<!-- === END BLOCK: ROADMAP_INBOX_VISUAL_KEY_REVIEW_UI_2026_06_25 === -->

---

<!-- === BEGIN BLOCK: ROADMAP_CONTENT_QUALITY_FEEDBACK_LOOP_2026_06_25 | Zweck: definiert den naechsten Workpack nach abgeschlossenem KI-Faktencheck-Cache; Umfang: Audit-/Inbox-Feedback, Ablehnungsgruende, KI-Suchlauf-Regeln, Messbarkeit, keine blinde Selbstmutation === -->
## Content-Prüfung – KI-Suchlauf Feedback Loop / Self-Improving Search finaler Staging-Prozess (2026-06-25)

Dieser Block ist der nächste aktive Content-Quality-Workpack nach dem belegten KI-Faktencheck-Fallback mit Prüfcache. Er überschreibt ältere Formulierungen, nach denen zuerst noch der Cache-Grundprozess zu implementieren wäre.

### Ziel in einem Satz

Die Content-Prüfung soll Fehler nicht nur sichtbar machen, sondern strukturierte Lernsignale für den nächsten KI-Suchlauf erzeugen, damit wiederkehrende Fehler bei Event- und Activity-Daten seltener entstehen.

### Warum dieser Workpack nötig ist

Aktuell sammelt die Inbox bereits Nutzerentscheidungen, Ablehnungsgründe und Korrektursignale. Ohne weiteren Ausbau müsste der Nutzer diese Listen regelmäßig manuell auswerten und daraus Such-/Prompt-Regeln ableiten. Das ist nicht praktikabel.

Ziel ist daher ein kontrollierter Feedback-Prozess:

1. Content Quality Audit erkennt Datenfehler, Unsicherheiten, Quellenprobleme und Zeitlücken.
2. `/inbox/` sammelt Entscheidungen, Ablehnungsgründe und Korrekturgründe.
3. KI-Faktencheck liefert strukturierte Ergebnisse wie `confirmed`, `conflict`, `better_source_found`, `not_found` oder `uncertain`.
4. Ein Feedback-Artefakt fasst diese Signale nach Fehlerklasse, Quelle, Eventtyp und Feld zusammen.
5. Der nächste KI-Suchlauf nutzt dieses Feedback als Kontext für Quellenwahl, Prompt-Regeln und Plausibilitätsprüfungen.
6. Reports zeigen, welches Feedback angewendet wurde und ob wiederkehrende Fehlerklassen abnehmen.

### Fehlerklassen

Mindestens strukturiert unterscheiden:

- Datum falsch erkannt oder altes Jahr übernommen.
- Start-/Enddatum verwechselt oder Enddatum fehlt.
- Uhrzeit übersehen, obwohl Quelle konkrete Zeiten nennt.
- Uhrzeit falsch interpretiert, z. B. Öffnungszeit statt Eventzeit.
- Ort/Adresse falsch oder zu grob übernommen.
- Ticketportal statt offizieller Event-/Veranstalterquelle genutzt.
- Quelle nicht erreichbar, Bot-Schutz, `429`, `503`, Redirect oder schwer lesbare Seite.
- Event verwechselt, ähnliche Titel vermischt oder falscher Termin derselben Reihe genutzt.
- Activity-Öffnungszeiten, Saisonhinweise oder Nutzungsempfehlungen veraltet/unsicher.
- Visual-Key-/Motiv-Fit falsch oder zu generisch, sofern dies aus der getrennten Visual-Fit-Prüfung als Suchfeedback relevant wird.

### Zielartefakte

Der Workpack soll voraussichtlich ein oder mehrere maschinenlesbare Artefakte erzeugen, z. B.:

- `content-search-feedback.json` als komprimierter Lernkontext für den KI-Suchlauf.
- Feedback-Zusammenfassung im Content-Audit-Report.
- Fehlerklassen-Zählung nach Lauf, Quelle und Feld.
- Liste wiederholter Fehler, die trotz Feedback erneut auftreten.

Die Analyse der aktuellen Owner-Flows legt diese finale Datei-/Tab-Struktur fest:

- Audit-Owner: `scripts/content-quality-audit.py` erzeugt `data/content-search-feedback.json` als runtime-/reportfähiges Artefakt.
- Sheet-Handoff: `.github/workflows/content-quality-audit.yml` schreibt daraus `Content_Search_Feedback` auf `main` und `Content_Search_Feedback_Staging` auf `staging`.
- Suchlauf-Owner: `scripts/weekly-ki-websearch-to-manual-inbox.py` exportiert den Feedback-Tab optional, fällt bei fehlendem Tab auf `data/content-search-feedback.json` zurück, liest zusätzlich echte Inbox-/Archiv-Ablehnungen und baut daraus einen gruppierten, gedeckelten `CONTENT_SEARCH_FEEDBACK`-Promptblock.
- Diagnose: `weekly_event_diagnostics.json` enthält Anzahl, Klassen, Quellen und Kurzfassung der im Suchlauf angewendeten Feedbackregeln.
- Automationsgrenze: Feedback ändert keine fachlichen Events/Activities/DB-Daten, schreibt keine Suchregeln dauerhaft selbst um und wird nur als laufbezogener Qualitätsfilter genutzt.

### Regeln und Grenzen

- Keine unkontrollierte Selbstmutation der KI-Regeln.
- Kein automatisches Überschreiben von Event-/Activity-Daten nur wegen eines Feedback-Signals.
- Feedback darf Such-/Prompt-Kontext und Prüfprioritäten verbessern, aber fachliche Änderungen bleiben bestätigungspflichtig.
- Kein pauschaler Vollscan per KI; Budget-, Kandidaten- und Cache-Logik bleiben bestehen.
- Ablehnungsgründe aus der Inbox werden nicht roh in den Prompt übernommen, sondern auf feste Feedbackklassen normalisiert.
- Regel-Bloat ist technisch begrenzt: maximaler Feedback-Pool, maximaler Prompt-Block, Gruppierung nach Fehlerklasse/Feld/Quelle, Duplikatzählung statt Listenwachstum und Decay für alte Einzelablehnungen.
- Der Nutzer soll keine wöchentliche manuelle Regelpflege übernehmen müssen.

### Akzeptanzkriterien

- Audit-/KI-Faktencheck-Signale werden in einem strukturierten Feedback-Artefakt gesammelt; Inbox-Ablehnungen werden zusätzlich aus Inbox/Archiv-Historie typisiert.
- Der KI-Suchlauf kann dieses Artefakt lesen und in Prompt-/Quellenlogik sichtbar berücksichtigen.
- Der Audit-Report zeigt, welche Feedbackklassen erzeugt wurden; der Weekly-Diagnosereport zeigt, welche Feedbackregeln angewendet wurden.
- Wiederholte Fehlerklassen werden erkennbar gezählt, ohne dass Einzelfälle unbegrenzt als neue Promptregeln anwachsen.
- Mindestens eine konkrete Fehlerklasse, z. B. Zeitangaben oder Ticketportal-Primärquellen, wird als Proof durchgängig vom Audit-Finding bis zum KI-Suchlauf-Feedback verfolgt.

### Nicht-Ziel in diesem Workpack

- Kein UI-Polish der Inbox.
- Kein Daten-Cleanup nur zum Schließen aktueller Audit-Fälle.
- Kein neues visuelles Bildsystem.
- Kein Training eines eigenen Modells.
- Keine automatische Live-Änderung aus Feedback ohne Review-/Owner-Pfad.
<!-- === END BLOCK: ROADMAP_CONTENT_QUALITY_FEEDBACK_LOOP_2026_06_25 === -->

---

<!-- === BEGIN BLOCK: ROADMAP_CONTENT_QUALITY_AI_VERIFICATION_CACHE_2026_06_24 | Zweck: dokumentiert den abgeschlossenen KI-Faktencheck-Fallback mit Pruefcache; Umfang: Events, Activities, blockierte Quellen, verified_until, Budget-/Prioritaetslogik, Acceptance-Tab, Cache-Hit === -->
## Content-Prüfung – KI-Faktencheck-Fallback mit Prüfcache abgeschlossen (2026-06-25)

Status: als V1-Prozess auf Staging live belegt.

### Ziel des Workpacks

Der Workpack sollte verhindern, dass blockierte, schwer lesbare oder fachlich unsichere Quellen direkt als Nutzeraufgabe erscheinen oder jede Woche pauschal teuer per KI geprüft werden.

Ziel-Hybridprozess:

1. Das billige Audit-Skript prüft technische und eindeutig maschinelle Regeln.
2. Nur blockierte, schwer lesbare oder fachlich unsichere Fälle werden `ai_verification_candidate`.
3. KI-Faktencheck-Kandidaten werden strukturiert und budgetiert ausgegeben.
4. Ein Prüfcache verhindert Wiederholungsprüfungen, solange Inhalt und Quelle unverändert und `verified_until` gültig ist.
5. In `/inbox/` landen nur Konflikte, fehlende belastbare Quellen, vorgeschlagene Änderungen oder echte Nutzerentscheidungen.

### Belegt umgesetzt

- Audit erzeugt `ai_verification_candidate` für unsichere/blockierte Quellen.
- Report zeigt Cache-Zahlen, KI-Kandidaten, Budgetauswahl und zurückgestellte Kandidaten.
- `content-ai-verification-candidates.json` wird als strukturiertes KI-Fallback-Artefakt erzeugt.
- `Content_Verification_Cache(_Staging)` wird exportiert und befüllt.
- `Content_Verification_Acceptance(_Staging)` dient als robuste Acceptance-Schicht, damit technische Audit-Zeilen nicht manuell bearbeitet werden müssen.
- Bestätigte Acceptance-Zeilen werden in den Prüfcache übernommen.
- Ein bestätigter unveränderter Fall erzeugt im Folgelauf einen Cache-Hit und verschwindet aus den KI-Kandidaten.
- `source_has_time_but_dataset_missing_time` macht sichtbar, wenn eine Quelle konkrete Uhrzeiten nennt, der Datensatz aber keine Uhrzeit enthält.
- Workflow-/Script-Logs sind durch Checkpoint-Ausgaben beobachtbar; kein enger Timeout wurde ergänzt.

### Konkreter Proof

Staging-Proof mit `Farm & Country Fair`:

- Acceptance-Zeile für `verification_key=0c54bf13073014fd79f9b8d7` wurde gelesen.
- Cache-Writeback schrieb einen Cache-Eintrag.
- Folgelauf lud `Cache entries loaded: 1` und meldete `Cache hits: 1`.
- `Farm & Country Fair` erschien danach nicht mehr als KI-Faktencheck-Kandidat.
- Der Fall bleibt weiterhin für andere unabhängige Hinweise sichtbar, z. B. Visual-Fit oder Zeitlücken, falls diese nicht Teil des bestätigten KI-Faktenchecks sind.

### Grenzen

- Das ist keine 100%-Garantie für die gesamte Website.
- Abgesichert ist der definierte V1-Prozess für Event-/Activity-Kerndaten und die aktuell modellierten Prüfregeln.
- KI-/Audit-Ergebnisse überschreiben keine fachlichen Daten automatisch.
- Die Pflege echter Korrekturen bleibt owner- und reviewpflichtig.

### Folgeworkpack

Der nächste Ausbau ist `Content-Prüfung – KI-Suchlauf Feedback Loop / Self-Improving Search`.
<!-- === END BLOCK: ROADMAP_CONTENT_QUALITY_AI_VERIFICATION_CACHE_2026_06_24 === -->

---

<!-- === BEGIN BLOCK: ROADMAP_CONTENT_QUALITY_PROCESS_V2_STATUS_2026_06_24 | Zweck: dokumentiert den aktuellen Zielabgleich nach Content-Facts-/Visual-Fit-Prozesspatches; Umfang: Status, erreichte Prozessfaehigkeiten, offene Workpacks, naechste Reihenfolge === -->
## Content-Prüfung – aktueller Zielabgleich nach Prozess V2 (2026-06-24)

Dieser Block ist die aktuelle taktische Steuerung für die Content-Prüfung. Er konkretisiert den Zielzustand aus `ROADMAP_CONTENT_QUALITY_TARGET_STATE_2026_06_23` und überschreibt ältere offene Content-Quality-To-dos, soweit sie durch den Prozess-V2-Stand erledigt sind.

### Aktueller Reifegrad

Stand: Prozess-V2 plus KI-Faktencheck-/Prüfcache-V1 sind als technische Prozessbasis belegt. Offene Arbeitspakete sind jetzt fachliche Paketbearbeitung, Visual-Fit-Auswertung und der KI-Suchlauf-Feedback-Loop.

Belegt durch den letzten Staging-Audit-Report nach KI-Faktencheck-/Prüfcache-V1:

- `critical`: 0.
- `review_needed`: 4.
- `warning`: 19.
- `auto_fixed` / automatisch erledigt: 118.
- Arbeitspakete: Repo-Datenpatch 3, Sheet-/Quellenkorrektur 1, Quellenprüfung 1, Faktencheck 4, KI-Faktencheck 3, Visual-Fit 11, Automatisch erledigt 118.
- Verification-Fallback: Cache entries loaded 1, Cache hits 1, AI candidates selected 3, Deferred by budget 0.

### Was jetzt erreicht ist

- Der Audit unterscheidet nicht mehr nur Fehler/kein Fehler, sondern Prozesskategorien und Korrektur-Owner:
  - `auto_resolved` / `audit_script`.
  - `repo_data_patch_candidate` / `repo_patch`.
  - `sheet_update_candidate` / `content_inbox_to_sheet`.
  - `source_review_candidate` / `content_inbox_source_check`.
  - `fact_check_candidate` / `content_inbox_fact_check`.
  - `visual_fit_candidate` / `visual_workflow`.
- `/inbox/` bündelt die Content-Prüfung in Arbeitspakete statt in eine pauschale Einzelfallliste.
- Sheet-/Quellenkorrekturen laufen konzeptionell über die Content-Inbox, nicht über direkte Nutzerarbeit im Google Sheet.
- Repo-owned Activities werden nicht unsichtbar in der Inbox überschrieben, sondern als Repo-Datenpatch-Kandidaten gesammelt.
- Quellencheck, Faktencheck, KI-Faktencheck, Retry/Beobachten und Visual-Fit sind getrennte Pakete und werden nicht mehr mit normalen Datenpatches vermischt.
- Für Ticketportal-Fälle kann der Audit eine empfohlene offizielle Quelle aus `data/content_source_suggestions.json` in die Inbox bringen; Speichern bleibt eine bewusste Aktion.
- Faktenprobe V1 prüft bei geeigneten Eventquellen automatisch, ob Titel, Datum, Uhrzeit und Ort ausreichend in der Quelle bestätigt werden.
- Visual-Fit V2 erzeugt für fehlende Event-Visual-Zuordnungen konkrete Vorschläge für `visual_key`, `visual_motif`, Motivrolle und Asset-Status.

### Aktuelle offene Arbeitspakete aus dem letzten Audit

Repo-Datenpatch:

- `Bürgerpark Rhede` – `visual_key` fehlt.
- `Suderwicker Märchenspielplatz` – `visual_key` fehlt.
- `Waldlehrpfad am Vossenpand` – `visual_key` fehlt.

Sheet-/Quellenkorrektur:

- `Borken Open Air - ABBA Gold The Concert Show` – Ticketportal ist als Primärquelle hinterlegt; empfohlene offizielle Quelle ist vorbereitet.

Quellenprüfung:

- `Unterduikmuseum Aalten` – Redirect muss fachlich geprüft werden.

Faktencheck / Zeitlücken:

- `Borkener Darts Trophy` – Quelle nennt Uhrzeit, Datensatz enthält keine Uhrzeit.
- `Musikschulfest der Musikschule Isselburg` – Quelle nennt Uhrzeit, Datensatz enthält keine Uhrzeit.
- `Bocholter Gesundheitstage` – Quelle nennt Uhrzeit, Datensatz enthält keine Uhrzeit.
- `Playfountain - Bocholter Wasserspaß` – Quelle nennt Uhrzeit, Datensatz enthält keine Uhrzeit.

KI-Faktencheck:

- `Pokémon-Tag` – Kernfakten nicht automatisch ausreichend bestätigt.
- `Das schönste Ei der Welt` – Kernfakten nicht automatisch ausreichend bestätigt.
- `Witte Venn Ahaus-Alstätte entdecken` – Activity-Quelle technisch nicht sicher lesbar.

Visual-Fit:

- 11 Fälle, darunter konkrete `visual_key`-/`visual_motif`-Vorschläge. `Farm & Country Fair` ist hier noch sichtbar, aber nicht mehr als KI-Faktencheck-Kandidat.
- Visual-Fit-Fälle sind keine normalen Datenfehler, sondern gehören in den separaten Bild-/Motiv-Workflow.

### Was noch fehlt

- KI-Suchlauf-Feedback-Loop entwerfen, damit Audit-/Inbox-Fehler nicht manuell in Suchregeln übersetzt werden müssen.
- Visual-Fit-Fälle fachlich bewerten: Welche Vorschläge können übernommen werden, welche brauchen neue Bilder, welche brauchen Regelverfeinerung?
- Aus dem Repo-Datenpatch-Paket einen bewussten Sammelpatch erzeugen.
- Quellencheck- und Faktencheck-Pakete fachlich prüfen und erst danach ggf. Sheet-/Repo-Korrekturen ableiten.
- Repo-Patch-Paket/Export später automatischer machen; aktuell ist die Paketkopie für ChatGPT-Patchvorbereitung ausreichend.
- UI/UX der Content-Inbox bleibt bewusst ganz am Schluss.

### Nächste Reihenfolge

1. KI-Suchlauf Feedback Loop / Self-Improving Search analysieren und als Prozesspatch vorbereiten:
   - Audit-Findings, Inbox-Entscheidungen, Ablehnungsgründe und KI-Faktencheck-Ergebnisse als Lernsignale sammeln.
   - Fehlerklassen normalisieren.
   - Feedback-Artefakt für den nächsten KI-Suchlauf erzeugen.
   - Anwendung und Wirkung im Report messbar machen.
2. Danach Visual-Fit-Paket fachlich auswerten und in Gruppen aufteilen:
   - direkt übernehmbarer Visual-Key-/Motif-Vorschlag.
   - neue Bildproduktion / Asset-Gap.
   - Regelverfeinerung nötig.
   - echte Nutzer-Bildentscheidung nötig.
3. Danach Repo-Datenpatch-Paket für die drei Activity-`visual_key`-Lücken vorbereiten.
4. Danach Quellencheck-/Faktencheck-Pakete mit Cache-/KI-Fallbacklogik prüfen.
5. Danach erst UI/UX-Polish der Content-Inbox.

### Harte Grenzen

- Keine fachlichen Daten automatisch überschreiben.
- Keine Daten-Cleanup-Patches nur zum optischen Schließen des Reports.
- Keine neue SEO-/Content-Ausweitung vor Abschluss der offenen Content-Prüfpakete.
- Keine Bildproduktion ohne konkreten Visual-Gap oder bestätigten Visual-Fit-Bedarf.
<!-- === END BLOCK: ROADMAP_CONTENT_QUALITY_PROCESS_V2_STATUS_2026_06_24 === -->

<!-- === BEGIN BLOCK: ROADMAP_CONTENT_QUALITY_TARGET_STATE_2026_06_23 | Zweck: haelt den Zielzustand des automatischen Content-Pruefprozesses und die aktuelle Standortbestimmung fest; Umfang: Events, Activities, Anbieter-Events, Korrekturwege, Visual-Fit-Abgrenzung === -->
## Aktuelle taktische Priorität – Content-Prüfung Zielzustand (2026-06-23)

Dieser Block überschreibt ältere Content-Quality-/Inbox-Formulierungen weiter unten, wenn sie dem aktuellen Zielzustand widersprechen.

### Ziel in einem Satz

Der Content-Prüfprozess prüft regelmäßig alle relevanten Inhalte automatisch, löst sichere technische Fälle selbst oder protokolliert sie still, und zeigt in `/inbox/` nur Fälle, bei denen wirklich etwas zu entscheiden, freizugeben oder gesammelt zu korrigieren ist.

### Was regelmäßig geprüft werden soll

Events:

- Titel, Datum, Startzeit, Endzeit, Ort, Adresse und Quelle.
- Ticketportal vs. offizielle Event-/Veranstalterquelle.
- Absage, Verschiebung oder widersprüchliche Angaben, soweit maschinell erkennbar.
- Dubletten, Serienlogik und abgelaufene Sichtbarkeit.
- `visual_key` / `visual_motif` als Übergabe an den separaten Visual-Fit-Prüfblock.

Activities:

- Titel, Ort, Adresse/Maps, Beschreibung und Quelle.
- Öffnungszeiten, Saisonzeiten, Feiertagslogik, Kostenhinweise und `checked_at`.
- Redirects, Erreichbarkeit, fehlende Pflichtfelder und Bild-/Visual-Key-Grundstatus.

Anbieter-/DB-Events:

- Öffentliche Sichtbarkeit, Pflichtfelder, Quellen, Zahlungs-/Review-Status und technische API-Ausspielung.
- Korrekturweg bleibt DB-/Admin-/Review-geführt, nicht Google-Sheet-geführt.

### Was in der Content-Prüfung erscheinen soll

Anzeigen:

- Quelle kaputt, unklar, zu generisch oder Ticketportal als Primärquelle.
- Datum, Uhrzeit, Ort oder Adresse fehlen bzw. widersprechen der Quelle.
- Activity-Öffnungszeiten, Saison-/Feiertagslogik oder Kosten sind zu alt oder nicht belastbar.
- Pflichtfeld fehlt, `visual_key` fehlt oder ist unbekannt.
- Dubletten, Serienproblem, Absage/Verschiebung oder anderer echter Reviewbedarf.
- Bild-/Motiv-Fit ist unsicher und kann nicht automatisch entschieden werden.

Nicht anzeigen:

- korrekt geprüfte Inhalte ohne Befund.
- harmlose Canonical-, Slash- oder Sprachpfad-Redirects.
- einzelne temporäre Timeouts ohne weiteren Befund.
- abgelaufene Events, die korrekt aus dem öffentlichen Feed fallen.
- pauschale Vollprüfungen ohne konkrete Auffälligkeit.

### Wie korrigiert werden soll

- Der Nutzer soll nicht direkt im Google Sheet arbeiten müssen.
- Sheet-Events werden aus `/inbox/` → Content-Prüfung kontrolliert ins Google Sheet `Events` zurückgeschrieben.
- Anbieter-/DB-Events werden aus der Inbox bzw. Admin-/Reviewlogik in der DB korrigiert.
- Activities bleiben in V1 repo-/JSON-owned: Die Content-Prüfung sammelt Patch-Kandidaten; ChatGPT erstellt daraus später Sammelpatches. Ein späterer PR-/Patchpaket-Workflow ist möglich.
- Fachliche Daten dürfen nicht blind durch KI-Textauswertung überschrieben werden. Semantische Änderungen brauchen eine explizite geprüfte Aktion.

### Separater Visual-Fit-Workstream

Bildprüfung ist ein eigener Qualitätsblock:

- KI-Suche muss passende `visual_key`/`visual_motif`-Werte wählen.
- Wenn kein passendes Motiv existiert, entsteht ein Gap statt einer falschen Bildzuordnung.
- Geprüft werden müssen Bild-Motiv-Fit, zu generische Bilder, irreführende Bilder und zu häufige Wiederholung gleicher Bilder im gleichen Kontext.
- Bildproduktion, Visual-Key-Verfeinerung und Pool-Updates laufen über den Visual Workflow, nicht als Nebenwirkung der Daten-/Quellenprüfung.

### Standortbestimmung aktuell

Erreicht:

- Content-Quality-Grundsystem existiert: Workflow, Script, Audit-Report, Audit-Sheet-Tabs und `/inbox/`-Arbeitsbereich.
- Lärm wurde deutlich reduziert: korrekte bzw. harmlose Fälle sollen nicht mehr als normale Aufgaben auftauchen.
- Sichere technische Auto-Behandlung ist begonnen, z. B. abgelaufene Events im Report als `auto_fixed`.
- Der Audit schreibt Empfehlungen, aber ändert fachliche Quellen nicht blind.

Noch offen:

- Report und Workbench müssen stärker nach Arbeitstyp sortieren: automatisch erledigt, nur beobachtet, Sheet-Korrektur, DB-/Review-Fall, Repo-Patch-Kandidat, Visual-Fit-Kandidat, echte Nutzerentscheidung.
- Die Inbox muss zur zentralen Korrekturoberfläche werden; Google Sheet darf nicht die Nutzerarbeitsfläche sein.
- Vollständige Datenwahrheit gegen Quellen ist noch nicht ausreichend bewiesen, vor allem Datum/Uhrzeit/Ort/Öffnungszeiten/Kosten.
- Visual-Key-/Bild-Fit ist noch kein systematischer Prüfprozess.

### Nächster sinnvoller Workpack

Prozess-Patch, keine Datenkorrektur:

1. Audit-Report um klarere Prozesskategorien erweitern.
2. `/inbox/` Content-Prüfung nach echten Arbeitstypen sortieren.
3. Sheet-/DB-/Repo-Korrekturwege in der UI und im Report sauber trennen.
4. Visual-Fit-Fälle als eigenen Kandidatentyp vorbereiten, aber nicht mit Datenwahrheitskorrekturen vermischen.
5. Keine konkreten Activity-/Event-Datenfixes in diesem Workpack.

### Nicht als nächstes starten

- Keine direkten Daten-Cleanup-Patches nur zum Schließen aktueller Audit-Fälle.
- Keine SEO-/Content-Ausweitung vor belastbarem Content-Prüfprozess.
- Keine pauschale manuelle Vollprüfung aller Inhalte in der Inbox.
- Keine neue Event-Visual-Produktion ohne konkreten Visual-Gap.
<!-- === END BLOCK: ROADMAP_CONTENT_QUALITY_TARGET_STATE_2026_06_23 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_GAPS_CLOSED_2026_06_18 | Zweck: schliesst den aktiven Event-Visual-Motiv-Fit-Produktionsblock fuer aktuellen Sheet-Stand; Umfang: Reststatus und naechste Arbeit nur bei neuem Bedarf === -->
## Event Visual Motif-Fit – aktuelle Sheet-Gaps geschlossen (2026-06-18)

Der aktive Event-Visual-Motiv-Fit-Produktionsblock ist nach Anwendung des finalen Restbatchs fuer den aktuellen Sheet-Stand abgeschlossen.

Ergebnis:
- Keine offenen `gap_to_produce`-Motive.
- Keine offenen `candidate_to_integrate`-Motive.
- Keine offenen `review_rules`-Motive.
- Der Gap-Backlog ist aus dem aktuellen Sheet-Export leer reproduzierbar.

Wichtig:
- Das ist kein Auftrag, jede theoretische Visual-Subcategory mit Vorratsbildern zu füllen.
- `parked_candidate` und `not_needed` bleiben normale Matrix-Zustände und sind kein Produktionsblocker.
- Event-Visual-Produktion wird erst wieder geöffnet, wenn neuer Sheet-Bedarf, ein neuer Backlog-Eintrag oder eine bewusste strategische Poolentscheidung vorliegt.

Nächste Arbeit nach diesem Block:
- Main-Merge und Live-Smoke sind abgeschlossen.
- Danach wieder zum übergeordneten KI-/Inbox-Handoff-Beweis zurückkehren.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_GAPS_CLOSED_2026_06_18 === -->


## Ausgangslage: Live-Messbasis

Stand: 2026-05-26, Live-Dashboard-Screenshot.

Belegt ist:

- Live misst bereits echte SEO- und Nutzwert-Kennzahlen.
- Status steht auf `Gelb`, nicht mehr auf `nicht konfiguriert`.
- Eigenes Tracking ist im Dashboard ausgeschlossen.
- Aktueller 28-Tage-Zeitraum zeigt unter anderem:
  - Sichtbarkeit: `7.049`
  - Such-Klicks: `264`
  - Nutzwert-Klicks: `33`
  - Detail-Interesse: `87`
  - Website-Klicks: `22`
  - Route/Maps: `5`
  - Veranstalter-CTA: `6`
  - performende Events: `28`
  - performende Ziele: `16`
  - Location-Links: `0`

Bewertung:

- `Messdaten aktivieren` ist nicht mehr der richtige To-do-Titel.
- Der nächste Reifegrad ist `Messdaten nutzbar machen`.
- Gesamtzahlen reichen für die interne Ampel.
- Für Veranstalter-Akquise braucht es zusätzlich auswertbare Anbieter-, Event-, Aktivitäts- und Location-Bezüge.
- Für `Grün` fehlen laut Dashboard-Hinweis vor allem stärkere automatisch gemessene Website-, Maps- oder Location-Klicks.

Zusatzbeleg 2026-05-27:

- Reporting-Ziel-Zuordnung ist live technisch bewiesen.
- `Anholter Schweiz erleben` sendet `reporting_target_type`, `reporting_target_id` und `reporting_target_title` im `value-track.php`-Payload.
- Das interne Dashboard zeigt das explizite Ziel `Biotopwildpark Anholter Schweiz` getrennt von `nicht zugeordnet`.
- Belegter Live-Wert nach Test: `2` Interaktionen gesamt, davon `1` Detail-Aufruf und `1` Website-Klick.
- Eigenes Tracking wurde nach dem Beweistest wieder ausgeschlossen.

---

## P0 — Monetarisierungs- und Reporting-Readiness

Diese Punkte kommen vor weiterer breiter UI-Polish-Arbeit.

### 1. Live-Zahlungsfall bewusst vollständig testen

<!-- === BEGIN BLOCK: ROADMAP_LIVE_SINGLE_EVENT_PAYMENT_PROOF_2026_05_27 | Zweck: dokumentiert erledigten P0-Live-Zahlungsbeweis für Einzeltermin; Umfang: Review-first, Stripe-Zahlung, Veröffentlichung, Rücknahme/Cleanup === -->

Status 2026-05-27:

- Live-Zahlungsfall für Einzeltermin wurde vollständig praktisch bewiesen.
- Getesteter Fall: `Bocholt erleben – Info-Nachmittag für Veranstalter`.
- Preis/Betrag im Stripe-Checkout: `9,90 €`.
- Review-first-Flow bestätigt:
  - Einreichung erzeugt noch keinen direkten Checkout.
  - Bestätigungsmail nach Einreichung kommt an.
  - Einreichung erscheint in der Review-Inbox.
  - Zahlung wird erst nach redaktioneller Vorprüfung freigegeben.
  - Zahlungslink-Mail kommt an.
- Stripe-Live-Zahlung erfolgreich abgeschlossen.
- Erfolgsseite nach Zahlung korrekt angezeigt.
- Inbox-Status nach Zahlung: bezahlt / veröffentlichungsbereit.
- Finale Veröffentlichung aus der Inbox erfolgreich.
- Veröffentlichungsmail kommt an.
- Event war anschließend im öffentlichen Eventbereich sichtbar.
- Cleanup ohne manuelle DB-Korrektur bestanden:
  - Veranstalteränderung an bereits veröffentlichtem Zukunftstermin setzt den Fall zurück in redaktionelle Prüfung.
  - Öffentliche Sichtbarkeit wird dadurch entfernt.
  - Eintrag erscheint wieder in der Review-Inbox.
  - Ablehnung/Abschluss aus der Inbox funktioniert.
  - Ablehnungsmail kommt an.
- Der Testtermin wurde nach dem Test wieder aus der öffentlichen Sichtbarkeit entfernt und abgeschlossen.

Bewertung:

- P0 `Live-Zahlungsfall bewusst vollständig testen` ist für den Einzeltermin-Funnel erledigt.
- Kein Code-Patch und keine direkte DB-Korrektur waren nötig.
- Für breite Akquise ist der bezahlte Einzeltermin-Kernfluss praktisch belastbar.
- Korrektur 2026-06-27: Der Aktivitaetspraesenz-/Abo-Livebeweis wird als erledigt behandelt. Weitere echte Abo-/Billing-Tests sind nur bei konkreter Flow-Aenderung, Periodenende-/`past_due`-Fragestellung oder Stripe-Symptom noetig, nicht als offener naechster Roadmap-Punkt.

<!-- === END BLOCK: ROADMAP_LIVE_SINGLE_EVENT_PAYMENT_PROOF_2026_05_27 === -->

Ziel:

- erster echter Live-Zahlungsfall für Event- oder Aktivitätsprodukt
- Stripe-Webhook nach echter Zahlung bewiesen
- Erfolgsseite nach echter Zahlung bewiesen
- Anbieterbereich/Dashboard-Status nach echter Zahlung bewiesen
- finale Veröffentlichung nach echter Zahlung bewiesen

Warum:

- Staging-E2E und Live-Smoke ersetzen keinen echten Produktiv-Zahlungsbeweis.
- Vor aktiver Akquise darf der bezahlte Kernfluss nicht nur theoretisch plausibel sein.

Akzeptanzkriterien:

- Testfall ist in `TEST_STATUS.md` dokumentiert.
- Keine manuelle DB-Korrektur ist nötig.
- Veranstalter-Sicht, Kurator-Sicht und öffentliche Veröffentlichung sind einmal durchgehend belegt.

### 2. Item- und Anbieter-Zuordnung für Nutzwertdaten prüfen und härten

Status 2026-05-27:

- Erster Live-Beweis abgeschlossen.
- Technische Kette ist für ein explizites Activity-Reporting-Ziel belegt:
  - `data/offers.json`
  - `js/offers-main.js`
  - Frontend-Tracking-Payload
  - `api/value-track.php`
  - `value_metric_daily`
  - internes SEO-/Mehrwert-Dashboard
- Aktuell explizit zugeordnet: `Biotopwildpark Anholter Schweiz`.
- Alle anderen nicht explizit zugeordneten Nutzwerte bleiben bewusst unter `nicht zugeordnet`.
- Der nächste Schritt ist nicht erneutes Tracking-Grundgerüst, sondern gezielte Erweiterung weiterer sauber belegbarer Reporting-Ziele und danach der erste Feedbackbericht.

Ziel:

- Detail-, Website-, Maps-, Ticket-, Location- und Veranstalter-CTA-Klicks sind pro Event/Aktivität eindeutig auswertbar.
- Events und Aktivitäten können einem Anbieter, einer Location oder einem Reporting-Ziel zugeordnet werden.
- Der Sonderfall `Anholter Schweiz` kann als erster realer Feedback-Report ausgewertet werden.

Warum:

- Gesamtzahlen sind gut für die interne Ampel.
- Veranstalter zahlen eher, wenn sie ihre eigenen Zahlen sehen.

Akzeptanzkriterien:

- Für ein konkretes Ziel ist abrufbar:
  - zugeordnete Events/Aktivitäten
  - Detail-Interesse
  - Website-/Ticket-Klicks
  - Route/Maps-Klicks
  - Zeitraum
  - Vergleich zum vorherigen Zeitraum, wenn belastbar vorhanden
- Eigene Testklicks bleiben ausgeschlossen.
- Unklare Zuordnungen werden nicht geraten, sondern als `nicht zugeordnet` sichtbar gemacht.

<!-- === BEGIN BLOCK: ROADMAP_REPORTING_TARGET_DATA_WAIT_2026_05_27 | Zweck: hält den Live-Beweis und den Datenbasis-Wartepunkt für Reporting-Ziele fest; Umfang: Akquise-Readiness, 28-/30-Tage-Datenlauf, nächster Feedbackbericht === -->

### Status 2026-05-27 — Reporting-Ziele live bewiesen, Datenbasis läuft

Belegt:

- Explizite Reporting-Ziel-Zuordnung funktioniert live.
- Erster Einzelbeweis: `Anholter Schweiz erleben` wird dem Ziel `Biotopwildpark Anholter Schweiz` zugeordnet.
- Erweiterungsbeweis: zusätzlich ergänzte Ziele aus `data/offers.json` funktionieren ebenfalls live; geprüftes Beispiel: `Aasee erleben` mit Ziel `Aasee Bocholt`.
- `value-track.php` erhält bei geprüften Activity-Requests korrekt:
  - `reporting_target_type`
  - `reporting_target_id`
  - `reporting_target_title`
- Das SEO-/Mehrwert-Dashboard trennt explizit zugeordnete Ziele von `nicht zugeordnet`.

Bewertung:

- Der technische Roadmap-Punkt `Item- und Anbieter-Zuordnung für Nutzwertdaten prüfen und härten` ist für Activities grundsätzlich bewiesen.
- Testklicks dienen nur als Funktionsbeweis und sind kein Akquise-Beleg.
- Für einen belastbaren Feedbackbericht werden jetzt organische Daten gesammelt.

Nächste Datenprüfungen:

- Kurzcheck nach ca. 7 Tagen: prüfen, ob erste echte Zielsignale plausibel einlaufen.
- Hauptcheck nach ca. 30 Tagen bzw. nach einem vollständigen 28-Tage-Zeitraum: bewerten, ob ein erster Akquise-/Feedbackbericht belastbar erstellt werden kann.
- Automatische Erinnerung ist für den 26.06.2026 angelegt.

Bis dahin:

- Kein großer Feedbackbericht auf Basis von Testklicks.
- Weitere Reporting-Ziele nur gezielt ergänzen, wenn die Zuordnung fachlich eindeutig ist.
- Nächster aktiver P0-Block kann unabhängig davon der echte Live-Zahlungsfall sein.

<!-- === END BLOCK: ROADMAP_REPORTING_TARGET_DATA_WAIT_2026_05_27 === -->

### 3. Ersten Veranstalter-/Location-Feedbackbericht bauen

Status 2026-05-28:

- Interner Location-Feedbackbericht ist im SEO-/Mehrwert-Dashboard auf Staging eingebaut und geprüft.
- Einbauort: `intern/seo-dashboard/`.
- Der Bericht nutzt die vorhandenen Reporting-Ziele aus `data/offers.json` und die Messwerte aus `value_metric_daily`.
- Sichtbar sind pro Reporting-Ziel:
  - Interaktionen gesamt
  - Detail-Aufrufe
  - Website-Klicks
  - Route/Maps-Klicks
  - Zeitraum
  - vorsichtige Einordnung bei fehlenden Signalen
- Der frühere obere Akquise-Snapshot ist jetzt als `Akquise-Gesamtstatus` einklappbar; die Status-Chips bleiben direkt sichtbar.
- Bewertung: technische Grundlage für Feedbackbericht und Akquise-Screenshot ist vorbereitet.
- Erster kleiner Live-Proof liegt für `Biotopwildpark Anholter Schweiz` vor; als belastbaren Akquise-Erfolgsnachweis erst nach längerem Zeitraum mit stabileren Nutzwert-Signalen verwenden.

Ziel:

- screenshot- oder mailfähiger Bericht für einzelne Anbieter/Locations.
- Erste Zielperson: Betriebsleitung Anholter Schweiz.

Warum:

- Das ist der direkte Übergang von internem Dashboard zu verkaufbarem Mehrwert.
- Ein konkreter Bericht ist wertvoller als abstrakte KPI-Kacheln.

Akzeptanzkriterien:

- Bericht erklärt ohne Fachbegriffe:
  - Sichtbarkeit
  - Detail-Interesse
  - konkrete Website-/Ticket-/Maps-Klicks
  - Zeitraum
  - was die Zahlen bedeuten
  - welche Zahlen noch vorsichtig zu interpretieren sind
- Keine überzogenen Claims ohne Datenbasis.
- Export/Screenshot funktioniert mobil und desktop.

### 4. Kritische Deploy-Smoke-Tests automatisieren

Status 2026-05-27:

- Umgesetzt und für Staging sowie Live/Main praktisch bewiesen.
- Workflow-Schritt `Smoke-check deployed site` läuft nach dem STRATO-SFTP-Upload.
- Staging-Proof: Commit `9f5b8a6` (`Add deploy smoke checks`).
- Live-/Main-Proof: Build `2b7f6daecf4c`.
- Der Proof ist in `TEST_STATUS.md` dokumentiert.

Ziel:

- Nach Deploys werden Kernpfade automatisch geprüft.

Mindestumfang:

- Homepage lädt.
- Events laden.
- Aktivitäten laden.
- `/api/status.php` liefert `ok`.
- `/api/events/public.php` liefert kontrolliertes JSON.
- Checkout-Endpoint liefert bei leerem Body kontrolliert `422`, keinen leeren `500`.
- Inbox-/Review-Endpunkte bleiben geschützt.

Warum:

- Das Projekt ist groß genug, dass manuelle Sichtprüfung allein zu riskant wird.

Akzeptanzkriterien:

- Smoke-Test-Ergebnis ist im Deploy oder als separater Check sichtbar.
- Fehler blockieren den Rollout oder werden bewusst als Nicht-Blocker dokumentiert.

### 5. Review-/Push-Flows gegen stille Ausfälle prüfen

Status 2026-05-27:

- Technischer Zugriffsschutzteil ist in den Deploy-Smoke-Check integriert und für Staging sowie Live/Main bewiesen.
- Review-Liste und Push-Endpunkte werden nach Deploys automatisch gegen versehentliche öffentliche Öffnung geprüft.
- Einzeltermin-Flow ist durch den Live-E2E-Test `TEST_STATUS_LIVE_SINGLE_EVENT_PAYMENT_2026_05_27` inklusive Review-Inbox-Sichtbarkeit bewiesen.
- Activity-Presence-Flow ist durch die Live-Readiness-Prüfung `TEST_STATUS_ACTIVITY_PRESENCE_LIVE_READINESS_2026_05_26` inklusive Live-Inbox-Sichtbarkeit bewiesen.
- Event-Submissions aus aktiver Mitgliedschaft sind durch die dokumentierten Membership-Reuse-Tests inklusive `review-list.php`- und `/inbox/`-Sichtbarkeit bewiesen.
- Neue Mitgliedschafts-Checkouts sind bewusst kein Review-Inbox-Fall, sondern starten den Abo-/Zahlungsflow.
- Tatsächlicher Push-Versand bleibt optional und ist kein P0-Blocker, solange die Review-Inbox zuverlässig ist.

Ziel:

- Neue Einreichungen erzeugen zuverlässig Review-Arbeit und interne Hinweise.

Warum:

- Still liegenbleibende Einreichungen wären für zahlende Veranstalter besonders schädlich.

Akzeptanzkriterien:

- Einzeltermin-, Activity-Presence- und Event-Submissions aus aktiver Mitgliedschaft erscheinen zuverlässig in der Review-Inbox.
- Neue Mitgliedschafts-Checkouts sind bewusst kein Review-Inbox-Fall, sondern starten den Abo-/Zahlungsflow.
- Push ist best-effort; wenn Push nicht ausgelöst wird, muss die Review-Inbox trotzdem korrekt sein.

---

## P0.5 — Inhaltsqualität, Prüfläufe und kontrolliertes Self-Healing

<!-- === BEGIN BLOCK: ROADMAP_CONTENT_QUALITY_SELF_HEALING_V1_2026_05_31 | Zweck: nimmt regelmäßige Live-Content-Prüfung, Warnlogik und sichere automatische Korrekturen als aktiven Workstream auf; Umfang: Events aus Google Sheet/DB, Activities aus offers.json, Audit-Reports, Google-Sheet-Reviewtab, Auto-Fix-Grenzen === -->

Status 2026-06-22:

- Der generierte Event-Feed `data/events.json` wurde aus dem Repository entfernt bzw. ist nur noch Deploy-/Runtime-Artefakt.
- Fachliche Event-Quelle bleibt das Google Sheet, Tab `Events`.
- Zugelassene Veranstalter-Einreichungen kommen zusätzlich aus der DB/API über `/api/events/public.php`.
- Activities bleiben aktuell Repo-/JSON-geführt über `data/offers.json`.
- Der nächste Qualitätsbedarf liegt vor weiterem SEO-/Content-Wachstum bei regelmäßiger Prüfung bereits live sichtbarer Inhalte.

Ziel:

- Live sichtbare Events und Aktivitäten werden regelmäßig auf technische und fachliche Auffälligkeiten geprüft.
- Der Prüflauf unterscheidet klar zwischen sicher automatisch abgefangenen Fällen und Fällen, die eine Warnung bzw. redaktionelle Prüfung brauchen.
- Automatische Korrekturen dürfen nur erfolgen, wenn die Änderung deterministisch belegbar und regressionsarm ist.
- Unsichere KI-/Website-Textinterpretationen dürfen fachliche Inhalte nicht blind überschreiben.

Warum:

- Bocholt erleben lebt vom Vertrauen in korrekte lokale Informationen.
- Neue Inhalte allein reichen nicht, wenn bestehende Events, Aktivitäten, Links, Bilder oder Quellinformationen veralten.
- Manuelle Vollkontrolle aller Live-Inhalte ist langfristig nicht realistisch.
- Blinde Auto-Korrekturen wären aber riskanter als ein fehlender Prüflauf.

Scope des dauerhaften Workstreams:

- Redaktionelle Events aus dem Google-Sheet-Tab `Events`.
- Genehmigte öffentliche DB-Events aus `/api/events/public.php`.
- Aktivitäten aus `data/offers.json`.
- Quellen-URLs, Event-URLs, Website-Links, Maps-Ziele und Bild-URLs.
- Ergebnis als JSON-/Markdown-Report im Workflow und als Google-Sheet-Tab:
  - `Content_Audit` auf live/main.
  - `Content_Audit_Staging` auf staging.
- Optional später: Darstellung im internen Dashboard, sofern der Sheet-Tab nicht mehr ausreicht.

Harte Grenzen:

- Kein automatisches Umschreiben freier Beschreibungstexte.
- Kein automatisches Löschen von Events nur wegen temporär nicht erreichbarer Quelle.
- Kein blindes Überschreiben des Google Sheets durch unsichere KI-Auswertung.
- Kein automatisches Ändern von Activity-Daten in `data/offers.json`; dafür bleibt ein sichtbarer Repo-Patch nötig.
- Kein Anbieterbericht oder öffentliche Qualitätsanzeige aus ungeprüften Audit-Befunden.

Empfohlener Prüfrhythmus:

- Events, leichtgewichtig: täglich, Fokus auf die nächsten 14 Tage.
- Events, tiefer Quellencheck: wöchentlich für den relevanten Zukunftsbestand.
- DB-/Veranstalter-Events: täglich über `/api/events/public.php`.
- Aktivitäten, technischer Check: wöchentlich.
- Aktivitäten, tiefer Plausibilitäts-/Quellencheck: monatlich bzw. über `checked_at`-Alterung.

Sichere Auto-Fix-/Auto-Abfang-Kandidaten ohne KI:

- abgelaufene Events nicht mehr ausspielen bzw. beim Feed-Build ausschließen.
- kaputte Bild-URLs durch vorhandene Fallback-Logik abfangen, sofern Fallback vorhanden ist.
- fehlende Pflichtfelder blockieren oder in Review markieren.
- harte Duplikate nach stabiler ID oder eindeutigem Schlüssel unterdrücken.
- permanente Redirects protokollieren und später kontrolliert übernehmen.
- strukturierte Quelldaten übernehmen, wenn JSON-LD, ICS oder API-Daten eindeutig demselben Event zugeordnet werden können; bis dahin nur Review-Empfehlung.

Nicht sicher ohne spezialisierte Quellenparser oder redaktionelle Prüfung:

- geänderte Uhrzeiten aus freiem Website-Text erkennen.
- verschobene oder abgesagte Events sicher interpretieren.
- subtile Ortsänderungen erkennen.
- mehrere Events auf einer Sammelseite korrekt zuordnen.
- veraltete Activity-Beschreibungen semantisch bewerten.

Benachrichtigungs- und Reaktionslogik:

- `auto_fixed`: keine Sofortwarnung; Aufnahme in Tages-/Wochenprotokoll.
- `warning`: im `Content_Audit`-Tab oder Audit-Report sichtbar machen.
- `review_needed`: interne Review-Arbeit erzeugen; nicht automatisch öffentlich ändern.
- `critical`: deutlich sichtbarer interner Hinweis im Audit-Tab; Deploy bleibt durch bestehende technische Gates geschützt.
- `source_unreachable`: protokollieren; erst nach Wiederholung oder besonderer Kritikalität eskalieren.

Technische Bausteine:

- Workflow `.github/workflows/content-quality-audit.yml`.
- Script `scripts/content-quality-audit.py` für harte technische Checks, Quellen-/Linkprüfungen und Review-Klassifizierung.
- Workflow-Reports `data/content-quality-report.json` und `data/content-quality-report.md` als nicht committete Workflow-Artefakte.
- Google-Sheet-Zieltab `Content_Audit` / `Content_Audit_Staging`.
- Google-Sheets-Schreibrechte nur für Audit-Zeilen, nicht für fachliche Datenmutation in `Events`, `Inbox` oder Activity-Quellen.

Akzeptanzkriterien:

- Der Audit läuft unabhängig vom normalen Deploy.
- Der Audit prüft den echten Sheet-/Runtime-/Repo-Bestand, nicht eine stale Event-JSON-Kopie.
- Der Audit erzeugt einen verständlichen Bericht mit Severity, betroffener Quelle und empfohlener Aktion.
- Harte technische Fehler sind klar von fachlichen Unsicherheiten getrennt.
- Kein unsicherer Befund verändert automatisch öffentliche Inhalte.
- Jede deterministische Auto-Abfanglogik ist nachvollziehbar protokolliert.

Prioritätseinordnung:

- Dieser Workstream kommt vor SEO-Themenseiten, größerem Activity-Ausbau und Anbieter-Akquise.
- Ziel ist nicht mehr Content um jeden Preis, sondern belastbarer, regelmäßig kontrollierter Content als Wachstumsbasis.

<!-- === END BLOCK: ROADMAP_CONTENT_QUALITY_SELF_HEALING_V1_2026_05_31 === -->

---

## P1 — Veranstalter-Nutzwert sichtbar machen

### 6. Anbieterbereich vom Verwaltungsbereich zum Wertzentrum ausbauen

Ziel:

- Anbieter sehen nicht nur Status und Tarif, sondern den Nutzen ihrer veröffentlichten Inhalte.

Mögliche erste Kennzahlen:

- veröffentlichte Veranstaltungen/Aktivitäten
- Detail-Interesse
- Website-/Ticket-Klicks
- Route/Maps-Klicks
- Zeitraum und einfacher Vergleich

Akzeptanzkriterien:

- Anbieter versteht ohne KPI-Wissen, was passiert ist.
- Zahlen werden nur gezeigt, wenn sie dem Anbieter sauber zuordenbar sind.
- Unvollständige Daten werden transparent gekennzeichnet.

### 7. Veranstalter-Funnel stärker auf belegbaren Mehrwert ausrichten

<!-- === BEGIN BLOCK: ROADMAP_PUBLISH_EXPLAINER_FREEZE_2026_05_29 | Zweck: dokumentiert die Umsetzung des P1-Punkts zur stärkeren Mehrwert-Erklärung im Veranstalter-/Anbieter-Funnel; Umfang: zentrale Erklärseite, Kontextlinks, Redundanz- und Funnel-Abgrenzung === -->

Status 2026-05-29:

- Umgesetzt und auf Staging geprüft.
- Neue zentrale Erklärseite: `/veroeffentlichung-erklaert/`.
- Bestehende Funnel-Seiten bleiben kurz und handlungsorientiert:
  - `/events-veroeffentlichen/`
  - `/fuer-veranstalter/`
  - `/aktivitaeten/sichtbar-werden/`
- Die bestehenden Seiten wurden nur minimal-invasiv um kontextuelle Hilfelinks ergänzt.
- Die neue Erklärseite bündelt:
  - Veröffentlichungswege
  - Prüfung und Freigabe
  - Zahlung und veröffentlichte Termine
  - Veranstaltung vs. Aktivitätspräsenz
  - Fairness ohne gekaufte Hervorhebung
  - vorsichtige Einordnung messbarer Interaktionen
- Ankerziele, FAQ-Öffnung, Scroll-Offset und Link-Hierarchie wurden nach Staging-Prüfung nachgeschärft.
- Ergebnis: Workpack ist für den aktuellen Spitzenstand gefreezt.
- Details und getestete Grenzen sind in `TEST_STATUS.md` dokumentiert.

Bewertung:

- P1 Punkt 7 ist für den aktuellen Stand erledigt.
- Kein weiterer Copy-/UI-Ausbau an den Funnel-Seiten nötig.
- Keine Formular-, Checkout-, Stripe-, Review- oder Dashboard-Logik wurde verändert.
- Nächster fachlicher Anschluss bleibt der messbare Nutzennachweis über Bericht/Reporting, nicht weiterer Erklärtext.

<!-- === END BLOCK: ROADMAP_PUBLISH_EXPLAINER_FREEZE_2026_05_29 === -->

Ziel:

- `/events-veroeffentlichen/`, `/fuer-veranstalter/` und `/aktivitaeten/sichtbar-werden/` erklären klarer, welchen konkreten Nutzen Anbieter bekommen.

Warum:

- Der aktuelle Funnel ist logisch und fair, aber noch nicht maximal verkaufsstark.

Akzeptanzkriterien:

- Kommunikation bleibt seriös und lokal.
- Keine Zahlen verwenden, die nicht belegbar sind.
- Mehrwert wird als Dienstleistung erklärt: Prüfung, Aufbereitung, Sichtbarkeit, messbare Interaktionen.

### 8. Monatlichen Wertbericht vorbereiten

<!-- === BEGIN BLOCK: ROADMAP_MONTHLY_VALUE_REPORT_PREPARED_2026_05_29 | Zweck: korrigiert Punkt 8 als vorbereiteten Reporting-/Retention-Baustein; Umfang: Status, Wartepunkt, Abgrenzung zur späteren Automatisierung === -->

Status 2026-05-29:

- Punkt 8 ist in der aktuell sinnvollen Form vorbereitet und kein aktiver Bau-Block mehr.
- Der interne `Location-Feedbackbericht` ist im SEO-/Mehrwert-Dashboard `/intern/seo-dashboard/` eingebaut und auf Staging geprüft.
- Der Bericht ist screenshot- und gesprächsfähig für interne Akquise-/Proof-Vorbereitung.
- Belastbare Anbieter-/Location-Aussagen brauchen weiter echte organische Daten über einen längeren Zeitraum.

Wartepunkt:

- Kurzcheck nach ca. 7 Tagen: prüfen, ob erste echte Zielsignale plausibel einlaufen.
- Hauptcheck nach ca. 30 Tagen bzw. nach einem vollständigen 28-Tage-Zeitraum: prüfen, ob ein erster Akquise-/Feedbackbericht belastbar ist.

Nicht jetzt bauen:

- keine automatische Monatsmail
- kein PDF-/Export-System
- kein neuer Anbieter-Self-Service-Bericht
- keine neue Datenbanktabelle nur für Reporting

Nächster aktiver Anschluss:

- Da Punkt 8 vorbereitet ist und die Datenbasis nun laufen muss, ist der nächste aktive Produkt-Workpack Punkt 9: `Heute in Bocholt` als Discovery-Einstieg.

<!-- === END BLOCK: ROADMAP_MONTHLY_VALUE_REPORT_PREPARED_2026_05_29 === -->

---

## P2 — Nutzerprodukt weiter Richtung Discovery-Portal ausbauen

### 9. `Für dich in Bocholt` – intelligente Premium-Home

<!-- === BEGIN BLOCK: ROADMAP_FOR_YOU_BOCHOLT_PREMIUM_HOME_2026_05_29 | Zweck: ersetzt den einfachen Heute-/Discovery-Workpack durch einen Premium-Produktvertrag für eine intelligente Startseite; Umfang: Home-Architektur, Empfehlungsmodell, lokale Personalisierung, Wetter, Datenvorbereitung, Abgrenzungen === -->

Status 2026-05-29:

- Der bisherige Ansatz `Heute in Bocholt` als reiner Discovery-/Filter-Workpack ist zu klein und teilweise redundant zum bestehenden Eventfeed.
- Die aktuelle mobile Eventansicht enthält bereits Suche, Zeitfilter, Kategorien sowie Gruppen wie `Heute` und `Dieses Wochenende`.
- Ein zusätzlicher Heute-/Wochenende-Block wäre daher kein Premium-Mehrwert.
- Der neue Zielzustand ist eine echte intelligente Home: `Für dich in Bocholt`.

Produktentscheidung:

- `/` wird perspektivisch zur kompakten Entscheidungsseite `Für dich in Bocholt`.
- Die bestehende Eventlogik bleibt als eigene Such-/Durchblätterseite erhalten.
- Die bestehende Aktivitätenseite bleibt als eigene Such-/Durchblätterseite erhalten.
- Die Home beantwortet nicht primär `Welche Termine gibt es?`, sondern `Was passt heute oder am Wochenende für mich in Bocholt?`

Zielbild:

- Die Startseite kombiniert Events und Aktivitäten in einem gemeinsamen Empfehlungsfeed.
- Nutzer erhalten schnell konkrete Vorschläge für:
  - heute
  - heute Abend
  - Wochenende
  - Familie / mit Kindern
  - draußen
  - bei Regen
  - spontan
- Die ersten sichtbaren mobilen Karten sind die wichtigste Produktfläche.
- Es wird keine große zusätzliche Modulfläche oberhalb der Inhalte aufgebaut.
- Die Home soll wie ein lokaler Entscheidungsassistent wirken, nicht wie ein weiterer Kalender.

Neue Seitenrollen:

- `/` = `Für dich in Bocholt`: kompakter Empfehlungsfeed aus Events + Aktivitäten.
- Eventseite = vollständige Termin-Suche, Filterung und chronologisches Stöbern.
- Aktivitätenseite = dauerhafte Orte, Ideen und Ausflugsziele suchen und filtern.
- Veranstalterbereiche bleiben getrennte Anbieterpfade.

Bottom-Navigation-Ziel:

- `Für dich`
- `Events`
- `Aktivitäten`

Explizit im Scope:

- neue Home-Logik als Premium-Entscheidungsfeed
- Events und Aktivitäten gemeinsam normalisieren
- lokales Interessenprofil ohne Account
- Merken-Funktion
- Ausblenden-/`Nicht interessant`-Funktion
- Wetterkontext für Bocholt
- Rankinglogik für Empfehlungen
- kompakte Mobile-UI
- Entscheidungssignale auf Karten
- Activity-Fallback, wenn Events dünn sind
- bestehende Event- und Aktivitätenseiten als Suchseiten erhalten

Explizit nicht im Scope:

- Nutzerkonto
- Sync zwischen Geräten
- Mittagstisch
- Push-Personalisierung
- komplexe KI-Empfehlungen
- Geolocation des Nutzers
- Preis-/Kostenfilter ohne belastbare strukturierte Daten
- automatische Anbieterberichte
- Wetterempfehlungen ohne passende Content-Tags

Architekturprinzip:

- Nicht die bestehende Eventseite weiter aufblasen.
- Stattdessen eine eigene Recommendation-Schicht bauen, die Events, Aktivitäten, Wetter und lokale Präferenzen zusammenführt.
- Bestehende Seiten bleiben möglichst stabil und werden nicht unnötig regressionsgefährdet.
- Premium bedeutet hier: ein konsistentes System, nicht mehrere isolierte Zusatzblöcke.

Benötigte technische Bausteine:

- `recommendations.js`
  - normalisiert Events und Aktivitäten
  - berechnet Scores
  - mischt Content
  - erzeugt Begründungslabels
  - berücksichtigt lokale Präferenzen
  - berücksichtigt Wetterkontext

- `user-preferences.js`
  - speichert Interessen lokal
  - speichert gemerkte Inhalte
  - speichert ausgeblendete Inhalte
  - speichert zuletzt gewählten Modus
  - kein Account, kein Sync

- `weather-context.js`
  - lädt oder hält Wetterkontext für Bocholt
  - bildet einfache Wetterklassen
  - liefert sicheren Fallback `unknown`

- neue Home-Renderlogik
  - rendert `Für dich in Bocholt`
  - rendert Modus-Auswahl
  - rendert gemischte Empfehlungskarten
  - verlinkt sauber zu Events und Aktivitäten

Lokales Profil ohne Account:

- Speicherung bevorzugt lokal im Browser.
- `localStorage` reicht für einfache Einstellungen.
- `IndexedDB` ist möglich, falls Merkliste/Ausblendungen größer oder strukturierter werden.
- Keine klassische Cookie-Logik als primärer Personalisierungsspeicher.
- Nutzerkonto und Geräte-Sync bleiben bewusst späterer Scope.

Lokale Profildaten:

- Interessen:
  - Familie
  - Draußen
  - Kultur
  - Musik
  - Essen & Trinken
  - Sport & Bewegung
  - Kurz & spontan
  - Wochenende
  - Bei Regen

- Gemerkt:
  - Event-IDs
  - Activity-IDs

- Ausgeblendet:
  - Event-IDs
  - Activity-IDs

- Nutzungskontext:
  - letzter Modus
  - bevorzugte Modi
  - optional zuletzt geklickte Kategorien

Gemeinsames Empfehlungsmodell:

Events und Aktivitäten müssen intern in ein gemeinsames `RecommendationItem` überführt werden.

Pflichtfelder intern:

- `type`: `event` oder `activity`
- `id`
- `title`
- `description`
- `category`
- `location`
- `url`
- `mapsTarget`
- `image`
- `dateContext`
- `timeContext`
- `situationTags`
- `audienceTags`
- `weatherProfile`
- `availability`
- `costLevel`
- `planningLevel`
- `recommendationWeight`
- `reasonLabels`
- `score`

Benötigte zusätzliche Event-Daten:

- `situation_tags`
  - `Mit Kindern`
  - `Draußen`
  - `Bei Regen`
  - `Abend`
  - `Wochenende`
  - `Spontan`

- `weather_profile`
  - `indoor`
  - `outdoor`
  - `mixed`
  - `weather_independent`
  - `rain_sensitive`
  - `unknown`

- `audience_tags`
  - `Familie`
  - `Erwachsene`
  - `Kultur`
  - `Musik`
  - `Sport`
  - `Essen & Trinken`

- `planning_level`
  - `spontan`
  - `planbar`
  - `ticket_or_registration_check`
  - `unknown`

- `cost_level`
  - `free`
  - `low`
  - `paid`
  - `unknown`

- `recommendation_weight`
  - `high`
  - `normal`
  - `fallback`

Benötigte zusätzliche Activity-Daten:

- `availability`
  - `always`
  - `opening_hours_check`
  - `seasonal`
  - `weather_dependent`

- `weather_profile`
  - `indoor`
  - `outdoor`
  - `mixed`
  - `rain_ok`
  - `rain_bad`
  - `weather_independent`
  - `unknown`

- `time_profile`
  - `morning`
  - `afternoon`
  - `evening`
  - `weekend`
  - `short_spontaneous`

- `cost_level`
  - `free`
  - `low`
  - `paid`
  - `unknown`

- `recommendation_weight`
  - `core`
  - `normal`
  - `fallback`

Wetterlogik:

- Wetter wird für Bocholt allgemein verwendet, nicht über Nutzer-Geolocation.
- Wetter ist ein Rankingfaktor, kein Show-Element.
- Wetter darf Empfehlungen nur beeinflussen, wenn Inhalte passende Wetterprofile haben.
- Bei fehlenden Wetterdaten muss der Feed sinnvoll ohne Wetter weiterlaufen.

Einfache Wetterklassen:

- `dry`
- `rain`
- `hot`
- `cold`
- `windy`
- `unknown`

Rankinglogik:

Höher bewerten:

- passt zu lokalen Interessen
- findet heute oder bald statt
- ist noch nicht vorbei
- passt zum gewählten Modus
- passt zum Wetter
- hat klare Location
- hat klare Uhrzeit oder klare Verfügbarkeit
- hat guten CTA
- ist für Familie/Draußen/Regen sauber getaggt
- ist gemerkt
- ergänzt den Feed sinnvoll, wenn Events dünn sind

Niedriger bewerten:

- unklare Daten
- fast vorbei
- wetterkritisch bei schlechtem Wetter
- nicht passend zum Modus
- zu ähnlich zu vorherigen Karten
- nur schwach belegte Empfehlungstags
- Activity mit Öffnungszeiten, wenn Verfügbarkeit ungeprüft ist

Ausschließen:

- ausgeblendete Inhalte
- vergangene Events
- Events ohne sinnvolle Mindestdaten
- Empfehlungen, deren Aussage nur geraten wäre

Content-Bewertung:

- Für eine Premium-Home nur mit Events reicht der Content nicht zuverlässig.
- Für eine Premium-Home mit Events + Aktivitäten ist die Grundlage vorhanden.
- Activities sind bereits stark genug als Fallback- und Ergänzungslogik.
- Events brauchen zusätzliche Empfehlungstags.
- Activities brauchen schärfere Wetter-, Verfügbarkeits- und Empfehlungsprofile.
- Mittagstisch wird bewusst später verschoben und gehört nicht zu diesem Workpack.

Wichtigster Daten-Gap:

- Events sind bisher eher kalendarisch beschrieben.
- Activities sind bereits stärker situations- und merkmalsbasiert beschrieben.
- Für `Für dich in Bocholt` müssen beide Content-Typen in ein gemeinsames Empfehlungsformat gebracht werden.

Akzeptanzkriterien für den späteren Premium-Workpack:

- `/` ist als `Für dich in Bocholt` erkennbar.
- Events und Aktivitäten erscheinen in einem gemeinsamen Empfehlungsfeed.
- Bestehende Event- und Aktivitätenseiten bleiben eigenständig nutzbar.
- Nutzer kann Interessen lokal setzen.
- Nutzer kann Inhalte merken.
- Nutzer kann Inhalte ausblenden.
- Wetterkontext beeinflusst Empfehlungen nur bei belastbaren Content-Tags.
- Der Feed funktioniert auch ohne Wetterdaten.
- Der Feed funktioniert auch ohne gespeicherte Interessen.
- Der Feed wirkt auf Mobile nicht überladen.
- Die ersten sichtbaren Karten liefern konkreten Entscheidungswert.
- Anbieter-CTA verdrängt nicht den ersten Nutzerentscheidungsbereich.
- Keine falschen Personalisierungsversprechen ohne lokale Signale.
- Keine Preis-/Kostenversprechen ohne strukturierte Daten.
- Keine Accountpflicht.

Umsetzungsreihenfolge innerhalb dieses Workpacks:

1. Datenvertrag finalisieren.
2. Event- und Activity-Datenfelder prüfen und ergänzen.
3. Recommendation-Normalisierung bauen.
4. Lokales Profilmodul bauen.
5. Wetterkontextmodul bauen.
6. Rankinglogik bauen.
7. Neue Home rendern.
8. Bottom-Navigation auf `Für dich | Events | Aktivitäten` ausrichten.
9. Eventseite als eigene Suchseite absichern.
10. Aktivitätenseite unverändert als Suchseite absichern.
11. Mobile- und Desktop-Proof.
12. Tracking-Proof für Recommendation-Klicks, Merken und Ausblenden.

Freeze-Bedingung:

- Der Workpack gilt erst als abgeschlossen, wenn die neue Home nicht nur anders aussieht, sondern einen klar höheren Nutzwert liefert als der bisherige Eventfeed.
- Maßstab ist: Nutzer erkennt schneller, was heute oder am Wochenende wirklich passt.
- Kein Release, wenn die Home nur wie ein umsortierter Kalender wirkt.

<!-- === END BLOCK: ROADMAP_FOR_YOU_BOCHOLT_PREMIUM_HOME_2026_05_29 === -->

<!-- === BEGIN BLOCK: ROADMAP_TODAY_HOME_PREMIUM_VISUAL_CONTRACT_AND_EVENT_FEED_2026_06_01 | Zweck: ordnet Today-Home-Abschluss, Premium-Visual-Contract und Events-Feed-Visuals in die richtige Reihenfolge; Umfang: Home, Event-/Activity-Bilder, Feed-Cards, Visual-Audit === -->
## Nächster Roadmap-Block – Today Home Premium Completion, Visual Contract und Events Feed Visual Integration

Status: historisch / durch spätere Workpacks überholt. Aktuelle Priorität siehe `ROADMAP_CURRENT_PRIORITY_2026_06_09`.

### 1. Today Home Premium Completion – abgeschlossen 2026-06-11

Ziel: Die Home-/Today-Seite soll nicht nur V1-tauglich, sondern vollständig premium-fertig werden.

Bereits erledigt:
- Today Home als zentraler Einstieg auf `/`.
- 3 kuratierte Tipps statt langer Liste.
- Activities als Standardempfehlungen.
- Events nur bei echter Heute-Relevanz.
- Keine Zukunftsevents mehr auf Today Home.
- Eventbilder über `visual_key -> event_visual_pool.json -> ready WebP`.
- Aktuelle Event-Visual-Coverage auf Staging: 62/62 Events.
- Premium-Layout-Polish wurde auf Staging umgesetzt.
- Regionalbezug wurde geklärt: `Heute rund um Bocholt`.
- Bottom-Tabbar wurde wieder als leichtes Glass-/Floating-Dock ausgerichtet, nicht als schwere opake Maske.

Nicht als Layout-Problem weiterführen:
- Bildqualität einzelner Activity-Karten.
- Roh wirkende Teasertexte durch harte Textabbrüche.
- Schwache oder zufällige Motive in prominenten Karten.

Abschlussstand 2026-06-11:
- Today Home ist layout- und UI-seitig auf Desktop und Mobile abgeschlossen.
- Weitere Today/Home-CSS-Arbeit nur noch bei konkretem belegtem Symptom.
- Schwache oder zu dunkle Activity-Bilder bleiben ein separater Activity-Premium-Visual-Workstream und werden nicht per CSS gerettet.

### 2. Premium Visual Contract V1

Ziel: Bildqualität künftig systematisch lösen, damit Event- und Activity-Karten nicht dauerhaft über manuelle Crop-/Focal-Point-Versuche repariert werden müssen.

Verbindliche Richtung:
- Card-Bilder werden als geprüfte 16:9-WebP-Card-Assets vorbereitet.
- Quellenhierarchie: eigene/exklusive Premium-Echtfotos, Veranstalter-/Rechteinhaber-Freigaben, sonstige rechtlich einwandfreie Premium-Fotos, danach selbst erzeugte symbolische KI-Premium-Visuals.
- KI-Premium-Visuals sind der bevorzugte Standard-Fallback, wenn kein rechtlich einwandfreies und qualitativ starkes Echtfoto verfügbar ist.
- Externe Bestandsbilder ohne saubere Premium-/Rechteprüfung sind Legacy/Übergang, nicht Zielzustand.
- Statuslogik für Visuals: `ready`, `usable`, `fallback`, `needs_review`, `blocked`.
- Prominente Flächen wie Today Home nutzen nur `ready` oder bewusst freigegebene `fallback`-Visuals.
- Schwache Bilder werden ersetzt, zurückgestuft oder ausgeschlossen.
- CSS bleibt Rendering-Rahmen, nicht Rettungssystem.
- Ein späterer interner Visual-Audit soll echte Card-Kontexte zeigen:
  - Today Mobile
  - Today Desktop
  - Events Feed
  - Activities Feed
  - später Detail-/Hero-Kontexte

Erste Umsetzungsschritte:
1. Bestehendes Event-Visual-System gegen diesen Contract prüfen.
2. Minimalmodell für Activity-Visual-Status festlegen.
3. Activity AI Visual Pool V1 analog zum Event-Visual-Pool vorbereiten.
4. Für Activities ohne rechtlich einwandfreies Premium-Echtfoto passende symbolische KI-Premium-Visuals planen/erzeugen.
5. Today Home darauf vorbereiten, keine `needs_review`/`blocked`-Visuals prominent auszuspielen.
6. Danach Bild-/Text-Polish für die sichtbaren Today-Karten durchführen.

### 3. Danach: Events Feed Visual Integration

Ziel: Das neue Event-Visual-System soll nicht nur auf Today Home, sondern auch im normalen Events Feed genutzt werden.

Geplanter Zielzustand:
- Eventkarten auf `/events/` nutzen dieselbe Visual-Contract-Logik wie Today Home:
  - `visual_key`
  - `data/event_visual_pool.json`
  - 16:9-WebP-Card-Assets
  - nur `status: ready` oder bewusst freigegebene Fallbacks
  - Fallback auf `default_city`
- Keine geplanten/non-ready Assets live ausspielen.
- Kein separater Bildstandard für Today und Events Feed.
- Mobile- und Desktop-Cards müssen mit Bildern premium wirken.
- Cropping nicht als Einzelbild-Ratespiel lösen, sondern über vorbereitete Card-Assets und Vorschauprüfung.
- Event-Detailpanel-Bilder erst danach bewerten; zunächst Fokus auf Feed-Cards.

Priorität:
1. Visual Contract V1 dokumentiert und technisch vorbereiten.
2. Today Home Content-/Visual-Polish abgeschlossen; weitere Today/Home-Layoutarbeit nur bei belegtem Symptom.
3. Events Feed Visual Integration als nächster Hauptworkstream.
4. Danach ggf. Detailpanel-/Hero-Bildlogik separat entscheiden.
<!-- === END BLOCK: ROADMAP_TODAY_HOME_PREMIUM_VISUAL_CONTRACT_AND_EVENT_FEED_2026_06_01 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_PHASE1_INTEGRATION_AND_PHASE2_DIVERSIFICATION_2026_06_03 | Zweck: ordnet die Event-Visual-Arbeit nach Phase-1-Assetintegration; Umfang: Event-Visual-Pool, Bildproduktion, Feed-Duplizierung === -->
## Event Visuals – Phase-1-Integration und Phase-2-Diversifizierung

Status: historisch / in späteren Workpacks weitergeführt. Event-Visual-Duplicate-Cleanup ist aktuell vorerst gefreezt; aktuelle Priorität siehe `ROADMAP_CURRENT_PRIORITY_2026_06_09`.

### Abgeschlossen / in Commit vorzubereiten

- 24 Phase-1-Pilotbilder wurden als `1200x675`-WebP erzeugt.
- Die 24 neuen Assets wurden in `data/event_visual_pool.json` auf `ready` gesetzt.
- Der Event-Visual-Pool hat jetzt 34 `ready`-Assets.
- `python scripts/audit-event-visual-pool.py` ist ohne Fehler.
- `python tools/audit-visual-contract.py` ist ohne Fehler.
- Roh-PNGs wurden entfernt; nur finale WebP-Card-Assets sollen ins Repo.

### Produktlogik

Ein `ready`-Bild pro Visual Key ist nur Grundabdeckung.
Der Zielzustand ist ein echter Bildpool pro Visual Key bis zum jeweiligen `target_count`.

Grund:
- Mehrere Events können denselben `visual_key` haben.
- Zeitlich nahe Events sollen nicht dasselbe Bild erhalten.
- Der Feed soll redaktionell und hochwertig wirken, nicht repetitiv.
- Die UI darf nur geprüfte `ready`-Assets nutzen.

### Nächste Roadmap-Schritte

1. Phase-1-Assetintegration committen und pushen.
2. Phase-2-Bedarf aus `data/event_visual_pool.json` berechnen:
   - Bedarf je Key = `target_count - ready_count`
3. Phase-2-Produktionsplan erzeugen.
4. Varianten key-by-key im Bildchat produzieren:
   - Pilotbild als Stilanker
   - keine Near-Duplicates
   - kleine kontrollierte Variantenrunden
5. Neue Varianten nach `1200x675`-WebP-Exportprüfung in den Pool integrieren.
6. Danach Event-Feed-Duplicate-Logik bewerten:
   - gleiche Bilder im sichtbaren Feed vermeiden
   - besonders bei zeitlich nahen Events desselben Visual Keys

### Priorisierung

Zuerst ausbauen:
- Keys mit hohem `target_count`
- Keys mit erwartbar häufiger Nutzung
- Keys mit hoher Sichtbarkeit im normalen Event-Feed
- Keys, die im Sommer-/Saisonbetrieb geclustert auftreten

Nicht zuerst lösen:
- Detailpanel-/Hero-Bilder
- Activity-Visuals
- neue Layout-Polishes
- Sonderbilder für einzelne Events

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_SUBMOTIFS_2026_06_05 === -->
## Folgepunkt: Event-Visual-Submotive für generische Visual Keys

Status 2026-06-05:

- Phase-2-Event-Visuals nutzen bei generischen Visual Keys bewusst unterschiedliche Submotive, um monotone Bildpools zu vermeiden.
- Akuter Anlass: `indoor_sport_competition` darf nicht nur aus Badminton-Motiven bestehen.
- Akzeptierte Submotive für diesen Key:
  - bestehendes Motiv: Badminton
  - `indoor-sport-competition-02`: Handball / Indoor-Teamspiel
  - `indoor-sport-competition-03`: Volleyball
- Diese Motive dürfen im generischen Pool bleiben, dürfen später aber nicht beliebig auf sportartspezifische Events gemappt werden.

Ziel:

- Vor finaler Integration der Phase-2-Assets prüfen, ob generische Visual Keys Submotiv- oder Mapping-Hinweise brauchen.
- Mögliche Feld-/Namenslogik:
  - `sub_motif`
  - `sport_type`
  - `usage_hint`
  - `mapping_note`
- Sportartspezifische Events sollen nur dann ein spezifisches Submotiv bekommen, wenn Eventtitel oder Quelle die Sportart eindeutig hergeben.
- Generische Events ohne konkrete Sportart dürfen weiterhin ein neutrales oder passend breites Indoor-Sport-Motiv nutzen.

Warum:

- Pool-Diversität ist sinnvoll, aber falsche Event-Zuordnung wirkt unprofessionell.
- Ein Volleyballbild bei einem Handballtermin oder ein Handballbild bei einem Badmintonturnier wäre fachlich falsch.
- Das Thema betrifft nicht nur Sport, sondern alle generischen Visual Keys mit klar unterscheidbaren Untertypen.

Akzeptanzkriterien:

- Für `indoor_sport_competition` sind Badminton, Handball/Indoor-Teamspiel und Volleyball vor der finalen Pool-Integration unterscheidbar dokumentiert.
- Die spätere Mapping-/Auswahllogik berücksichtigt Submotive, sobald ein Event eine konkrete Sportart nennt.
- Der generische Bildpool darf divers bleiben, ohne sportartspezifische Fehlzuordnungen zu erzeugen.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_SUBMOTIFS_2026_06_05 === -->


<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_PHASE1_INTEGRATION_AND_PHASE2_DIVERSIFICATION_2026_06_03 === -->

<!-- === BEGIN BLOCK: ROADMAP_ACTIVITY_OPENING_PUBLIC_STATUS_NEXT_2026_06_05 | Zweck: definiert naechsten Workpack fuer oeffentliche Oeffnungsstatus-Auswertung; Umfang: bestehende Activities, Today Home, Recommendation-Logik === -->
## Nächster Workpack: Öffnungsstatus öffentlich auswerten

Status 2026-06-09: historisch / umgesetzt; aktuelle Folgearbeit nur noch gezielt, siehe `ROADMAP_ACTIVITY_OPENING_FOLLOWUP_NEXT_2026_06_08` und `ROADMAP_CURRENT_PRIORITY_2026_06_09`.


Ausgangslage:
- Neue Anbieter-Einreichungen können strukturierte Öffnungszeiten liefern.
- Existing Activities in `data/offers.json` haben noch keinen belastbaren öffentlichen Öffnungsstatus.
- Today Home kann derzeit noch Activities mit `Öffnungszeiten prüfen` als Top-Tipp zeigen.

Ziel:
- Öffnungsstatus für bestehende Activities sauber modellieren.
- Today Home darf keine Top-Tipps vergeben, wenn ein Ort wahrscheinlich geschlossen ist oder der Status unklar ist.
- Anzeige soll zwischen Anbieterangabe, geprüftem Status und unklarem Status unterscheiden.

Mögliche Zielbausteine:
- `data/activity_opening_hours.json` oder kontrollierte Erweiterung von `data/offers.json`.
- `js/opening-status.js` als zentraler Auswertungsowner.
- Anbindung in `js/recommendations.js` und `js/today-home.js`.
- NRW-Feiertage, Wochenzeiten und Sonderfälle berücksichtigen.

Start im nächsten Chat:
1. Aktuelle Baseline prüfen.
2. `data/offers.json`, `js/today-home.js`, `js/recommendations.js` analysieren.
3. Entscheiden, ob Öffnungszeiten separat oder direkt in Activity-Daten modelliert werden.
4. Erst danach kleiner, owner-klarer Patch.
<!-- === END BLOCK: ROADMAP_ACTIVITY_OPENING_PUBLIC_STATUS_NEXT_2026_06_05 === -->

<!-- === BEGIN BLOCK: ROADMAP_ACTIVITY_OPENING_DATA_ENRICHMENT_NEXT_2026_06_08 | Zweck: definiert nächsten Workpack nach zentraler Öffnungsstatus-Logik; Umfang: Datenanreicherung bestehender Activities, holiday_policy, geprüfte Öffnungszeiten === -->
## Nächster Workpack: Öffnungsstatus-Daten für Activities anreichern

Status 2026-06-09: historisch / durch späteren 44-von-44-Activity-Stand überholt; keine erneute breite Massenpflege starten.


### Ausgangslage

- Die technische Öffnungsstatus-Logik ist zentralisiert.
- Today Home vergibt keine Top-Tipps mehr für `availability = opening_hours_check`.
- `data/offers.json` enthält aktuell:
  - 44 Activities
  - 22 `always`
  - 13 `seasonal`
  - 9 `opening_hours_check`
  - 44 ohne `holiday_policy`

### Ziel

Die wichtigsten bestehenden Activities sollen belastbarere öffentliche Öffnungsstatus-Daten bekommen, damit Empfehlungen nicht nur heuristisch, sondern fachlich besser abgesichert werden.

### Reihenfolge

1. Nur die 9 `opening_hours_check`-Activities prüfen und priorisieren.
2. Pro Activity entscheiden:
   - bleibt `opening_hours_check`
   - wird zu `verified`
   - braucht `holiday_policy`
   - braucht später konkrete Wochenzeiten
3. `holiday_policy` kontrolliert ergänzen:
   - `open`
   - `closed`
   - `limited`
   - `check`
4. Danach Today-Home-Ergebnis erneut gegen Regen/Sonntag/Feiertag testen.

### Nicht jetzt

- Keine breite Massenpflege aller 44 Activities.
- Keine ungeprüften echten Öffnungszeiten erfinden.
- Keine neue Daten-Datei einführen, solange `data/offers.json` als Activity-Owner ausreicht.
<!-- === END BLOCK: ROADMAP_ACTIVITY_OPENING_DATA_ENRICHMENT_NEXT_2026_06_08 === -->

<!-- === BEGIN BLOCK: ROADMAP_ACTIVITY_OPENING_FOLLOWUP_NEXT_2026_06_08 | Zweck: definiert Folgearbeiten nach abgeschlossenem Activity-Quality-Audit-V1; Umfang: Needs-Review, Saisonregeln, Detailanzeige, keine neue Massenpflege === -->
## Nächster Workpack: Activity-Öffnungsstatus gezielt verfeinern

Status 2026-06-09: gültiger Folgepunkt, aber nicht der nächste operative Pipeline-Beweis.


### Ausgangslage

- Activity Quality Audit V1 ist abgeschlossen.
- 44 von 44 Activities haben `opening_status`-Daten.
- Die zwei vorherigen Grenzfälle wurden gezielt abgeschlossen:
  - `quellengrundpark-weseke-entdecken` → `check_required`
  - `erlebnispfad-klostersee-burlo` → `free_access`
- Die Recommendation-Logik liest `opening_status` korrekt.
- Freie Activities mit `holiday_policy = open` oder `not_applicable` erzeugen keine falschen Sonntag-/Feiertag-Warnungen mehr.

### Ziel

Keine weitere breite Datenpflege. Stattdessen nur gezielte Verfeinerung der Saisonlogik und der öffentlichen Darstellung.

### Reihenfolge

1. Saisonregel-Modell schärfen:
   - Brutzeit-/Schutzzeitfälle
   - Bade-/Monitoring-Saison
   - saisonale Empfehlung ohne echte Öffnungszeit
   - saisonale Betreiberöffnung
2. UI-/Detailanzeige prüfen:
   - `opening_status.public_label`
   - `opening_status.detail_note`
   - keine leeren Öffnungszeitenblöcke bei freien Routen
3. Today-Home-/Recommendation-Verhalten visuell prüfen:
   - Werktag
   - Regen
   - Sonntag
   - Feiertag

### Nicht jetzt

- Keine neuen echten Öffnungszeiten erfinden.
- Keine pauschale Wochenöffnungszeiten-Struktur für freie Routen.
- Keine weitere breite Activity-Datenpflege ohne konkreten fachlichen Anlass.
- Keine Vermischung mit Event-Visual- oder Event-Card-Polish.
<!-- === END BLOCK: ROADMAP_ACTIVITY_OPENING_FOLLOWUP_NEXT_2026_06_08 === -->


<!-- === BEGIN BLOCK: ROADMAP_DETAILPANEL_PREMIUM_BEFORE_LIVE_2026_06_10 | Zweck: oeffnet den Detailpanel-Workstream gezielt als app-weites Premium-Systemelement vor Live; Umfang: Event/Activity/Today-Zusammenspiel, keine Activity-Fotoproduktion === -->
## Vor Live: Detailpanel als app-weites Premium-Systemelement

Die Today-Home ist funktional releasefaehig, aber Live bleibt gehalten, bis das Detailpanel app-weit Premium-Niveau erreicht.

Ziel ist nicht, Event- und Activity-Detailpanel identisch zu machen. Ziel ist ein konsistentes System:

- gleiche Panel-Chrome,
- gleiche visuelle Qualitaet,
- gleiche App-Sprache,
- unterschiedliche Rollen fuer Events und Activities.

Rollen:

- Event-Detailpanel = Terminentscheidung mit Datum, Ort, Kalender, Teilen, Quelle.
- Activity-Detailpanel = Ausflugs-/Ortsentscheidung mit Route, Website/Infos, Oeffnungsstatus, Merkmalen.
- Today-Kontext = schnelle Empfehlung, die in ein vollstaendiges Event- oder Activity-Detail fuehrt.

Primaere Owner:

- `js/details.js` fuer Event-Detailpanel,
- `js/offers-details.js` fuer Activity-Detailpanel,
- `css/overlays.css` fuer gemeinsame Panel-, Actionbar-, Safe-Area- und Chrome-Schicht.

Verbindliche Detailplanung:

- `docs/detailpanel-premium-system-contract.md`

Nicht Teil dieses Workstreams:

- neue Activity-Fotos,
- Today-Ranking,
- Eventdatenprozess,
- breite neue Feature-Ideen.

Live-Regel:

- Activity-Fotos duerfen nachgezogen werden.
- Detailpanel-Struktur, Aktionswert, Tonalitaet und UI-Qualitaet muessen vor Live app-weit konsolidiert sein.
<!-- === END BLOCK: ROADMAP_DETAILPANEL_PREMIUM_BEFORE_LIVE_2026_06_10 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_ROADMAP_2026_06_12 | Zweck: definiert den naechsten nachhaltigen Workpack fuer motivgenaue Eventbilder; Umfang: Event-Visuals, KI-Intake, Gap-Backlog === -->
## Nächster Workpack: Event-Visual-Motiv-Fit

Status 2026-06-12: geplant / noch nicht umgesetzt.

### Ausgangslage

Das Event-Visual-System ist technisch tragfähig, aber inhaltlich für manche Eventarten noch zu grob.

Aktuell reicht ein `visual_key` wie `indoor_sport_competition` für die grobe Bildfamilie. Für Premium-Qualität reicht das nicht immer, weil konkrete Events dadurch fachlich falsche Motive bekommen können.

Beispiel:
- Ein Fecht-Event darf kein Handball-, Volleyball- oder Badmintonbild bekommen.
- Ein Darts-Event darf kein generisches Hallenballsportbild bekommen.
- Ein Chor-Event darf nicht automatisch ein beliebiges Klassik-/Instrumentalmotiv bekommen.

### Zielzustand

Events sollen nicht nur eine passende Bildkategorie haben, sondern ein motivisch passendes Bild.

Dafür wird das bestehende Visual-Key-System nicht ersetzt, sondern erweitert:

1. `visual_key`
   - grobe Bildfamilie
   - Beispiel: `indoor_sport_competition`

2. `visual_motif`
   - konkreter Untertyp / konkretes Motiv
   - Beispiel: `fencing`, `darts`, `handball`, `volleyball`, `choir`, `organ_concert`

3. `visual_asset_status`
   - redaktioneller/technischer Status, ob ein passendes Motivbild vorhanden ist
   - Zielwerte z. B. `ok`, `needs_asset`, `review`

### Grundsatz

Wenn ein Event einen konkreten Eventtyp eindeutig nennt, darf die Bildauswahl nicht auf ein falsches spezifisches Unter-Motiv ausweichen.

Erlaubt:
- exaktes Motivbild
- bewusst neutrales generisches Bild, falls fachlich vertretbar
- Gap-/Backlog-Eintrag, wenn ein Premiumbild fehlt

Nicht erlaubt:
- Fechten → Handballbild
- Darts → Volleyballbild
- Chor → beliebiges Instrumentalbild
- konkrete Eventart → sichtbar falsches Bild nur wegen gleichem grobem `visual_key`

### Roadmap

#### Phase 1: Systemvertrag dokumentieren

- `VISUAL_WORKFLOW.md` um den dauerhaften Motiv-Fit-Vertrag ergänzen.
- `ROADMAP.md` hält diesen Workpack als nächsten strukturellen Event-Visual-Verbesserungspunkt fest.
- Keine UI-/Renderer-Änderung in diesem Dokumentationsschritt.

#### Phase 2: Motiv-Taxonomie klein starten

Zuerst nur generische Keys mit hohem Fehlzuordnungsrisiko modellieren.

Start-Key:
- `indoor_sport_competition`

Erste sinnvolle Motive:
- `neutral_indoor_sport`
- `badminton`
- `handball`
- `volleyball`
- `table_tennis`
- `darts`
- `fencing`

Später prüfen:
- `classical_music`
- `literature_reading_talk`
- `kids_stage_story`
- `business_messe_info`
- weitere generische Keys mit klar unterscheidbaren Untertypen

#### Phase 3: Pool-Metadaten erweitern

`data/event_visual_pool.json` soll bei passenden Bildern zusätzlich ein konkretes Motiv führen.

Beispiel:
- `visual_key`: `indoor_sport_competition`
- `visual_motif`: `fencing`

Bestehende Bilder ohne konkreten Untertyp bleiben zulässig, müssen aber als generisch oder neutral erkennbar sein.

#### Phase 4: Event-Zuordnung erweitern

Die bestehende Event-Visual-Zuordnung soll künftig neben `visual_key` auch `visual_motif` ableiten, wenn Titel/Beschreibung/Quelle eindeutig genug sind.

Beispiele:
- „Fechten“, „Fechtturnier“, „Degen“, „Florett“ → `visual_motif: fencing`
- „Darts“ → `visual_motif: darts`
- „Volleyball“ → `visual_motif: volleyball`
- „Handball“ → `visual_motif: handball`
- „Chor“, „Chorkonzert“ → `visual_motif: choir`

Unsichere Fälle nicht raten, sondern `review` oder neutrales Motiv verwenden.

#### Phase 5: Gap-Backlog einführen

Wenn ein Event ein konkretes Motiv braucht, aber kein passendes `ready`-Bild existiert, soll daraus ein sichtbarer Arbeitsauftrag entstehen.

Zieldatei:
- `data/event_visual_gap_backlog.tsv`

Mögliche Spalten:
- `status`
- `priority`
- `event_title`
- `event_date`
- `visual_key`
- `visual_motif`
- `problem`
- `recommended_action`
- `source_url`
- `notes`

Ziel:
- Die KI-/Inbox-Suche darf fehlende Motivbilder nicht still überdecken.
- Fehlende Bilder werden als konkrete Nachgenerierungsaufgabe sichtbar.

#### Phase 6: Auswahl- und Fallback-Regeln härten

Die UI-/Resolver-Logik soll später so erweitert werden:

1. Exaktes `visual_motif` suchen.
2. Falls nicht vorhanden: neutrales Bild desselben `visual_key` nur verwenden, wenn es fachlich nicht falsch wirkt.
3. Falls kein sicherer Fallback vorhanden: Gap markieren statt falsches spezifisches Bild nutzen.

#### Phase 7: Gezielt nachgenerieren

Erst nach Gap-Report neue Bilder erzeugen.

Priorität:
1. bald sichtbare Events
2. häufig wiederkehrende Eventtypen
3. Eventtypen mit hohem Falschbild-Risiko
4. stark sichtbare Kategorien im Feed

### Akzeptanzkriterien

- Ein Event mit eindeutigem konkretem Motiv bekommt kein fachlich falsches spezifisches Bild mehr.
- `visual_key` bleibt als grobe Familie erhalten.
- `visual_motif` ergänzt die konkrete Motivschicht.
- Fehlende Motivbilder werden sichtbar als Backlog-Aufgabe erfasst.
- Die Nachgenerierung erfolgt gezielt nach tatsächlichen Lücken statt pauschal pro Kategorie.
- Bestehende Visual-Audits bleiben gültig und werden später um Motiv-Fit-Prüfungen ergänzt.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_ROADMAP_2026_06_12 === -->
<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_SHEET_REVIEW_2026_06_15 | Zweck: konsolidiert den Sheet-Abgleich fuer Event-Visual-Motive und definiert die nachhaltige Abarbeitungsreihenfolge; Umfang: Events-Sheet, Visual-Motif-Taxonomie, Gap-Backlog, Runtime-Resolver, Asset-Integration === -->
## Event-Visual-Motiv-Fit – Sheet-Abgleich und Sanierungsroadmap 2026-06-15

Status: Analyse- und Sanierungsroadmap. Keine automatische Bildproduktion und keine Asset-Integration ohne nachgelagerten, belegten Patch.

### Geprüfte Grundlage

- Geprüfter Repo-Stand: aktuelle Staging-ZIP `Bocholt-Erleben-staging (60).zip`.
- Geprüfter Eventdatenstand: Google-Sheet-Export Tab `Events`, identisch mit `data/events.tsv` in der ZIP.
- Umfang des Sheet-Exports: 172 Rohzeilen inkl. 1 komplett leerer Zeile im manuellen Export.
- `data/event_visual_gap_backlog.tsv` in der ZIP ist bereits gegen diesen Sheet-Stand aufgebaut und enthält 45 gruppierte Motiv-Gaps.
- Prioritätsverteilung des aktuellen Gap-Backlogs: 5 `high`, 40 `medium`.
- `data/events.json` ist nicht die redaktionelle Quelle und darf für solche Aussagen nur als frisch erzeugtes Deploy-Artefakt genutzt werden.

### Validierter Prozessstand

Richtig und beizubehalten:

1. `visual_key` bleibt die stabile grobe Bildfamilie.
2. `visual_motif` ist die konkrete Motivschicht innerhalb eines Keys.
3. `data/event_visual_gap_backlog.tsv` ist der richtige Ort fuer fehlende Motivbilder.
4. `data/event_visual_phase2_acceptance_notes.json` ist der richtige Zwischenstand fuer akzeptierte, aber noch nicht integrierte Kandidaten.
5. Neue Bilder werden nur aus echtem Gap, konkretem Eventbedarf oder bewusstem strategischem Poolausbau erzeugt.
6. Das Google Sheet Tab `Events` ist die kanonische Eventquelle; lokale JSON-Artefakte sind nicht belastbar, wenn sie nicht frisch erzeugt wurden.

Nicht fertig bzw. zu korrigieren:

1. Der Gap-Backlog muss standardisiert mit dem aktuellen Sheet-/TSV-Stand laufen. Aktuell ist `scripts/build-event-visual-gap-backlog.py` zwar vorhanden, aber der Deploy-Workflow baut den Gap-Backlog nicht als festen Nachlauf.
2. Der manuelle TSV-Export kann komplett leere Zeilen enthalten. Der Deploy-Exporter filtert leere Zeilen, `scripts/build-events-from-tsv.py` scheitert lokal aber an einer leeren Zeile. Lokales Script und Deploy-Exporter muessen hier gleich robust sein.
3. `scripts/build-events-from-tsv.py` schreibt aktuell `visual_key`, aber kein `visual_motif` und keinen `visual_asset_status` in `data/events.json`.
4. Die Runtime-Resolver in `js/events.js` und `js/today-home.js` wählen Eventbilder aktuell nach `visual_key`, nicht nach `visual_motif`. Dadurch kann ein spezifisches Event weiterhin ein anderes spezifisches Bild derselben groben Familie bekommen.
5. `buildReadyVisualPools()` und der Today-Resolver übernehmen `visual_motif` aus `data/event_visual_pool.json` noch nicht in den JS-Poolzustand.
6. Die aktuelle Motiv-Taxonomie ist brauchbar, aber die Inferenzregeln brauchen noch Härtung gegen zusammengesetzte deutsche Begriffe, englische Eventtitel und falsch priorisierte Kategorie-/Location-Fallbacks.

### Aktueller Gap-Stand aus dem Sheet

High-Gaps:

- `business_messe_info / health_career_fair` – Beispiel: `Gesundheitsberufemesse`
- `food_drink_festival / wine_festival` – Beispiele: `Wijnfeest Aalten 2026`, `30. Bocholter Weinfest`
- `indoor_sport_competition / darts` – Beispiel: `Borkener Darts Trophy`
- `indoor_sport_competition / fencing` – Beispiel: `17. International Fencing Camp Bocholt`
- `market_stalls / fabric_market` – Beispiel: `Stoffmarkt Bocholt`

Bereits im Produktionszwischenstand belegte Kandidaten fuer aktuelle Gaps:

- `food_drink_festival / wine_festival` – akzeptiert und Download bestätigt.
- `city_tour_history / costumed_history_tour` – akzeptiert und Download bestätigt.
- `market_stalls / flea_market` – akzeptiert und Download bestätigt.
- `city_festival_street / shopping_sunday` – akzeptiert und Download bestätigt.
- `comedy_cabaret / cabaret_stage` – akzeptiert und Download bestätigt.
- `theater_stage / theater_play` – akzeptiert und Download bestätigt.
- `live_music_stage / local_band_concert` – mindestens ein Kandidat akzeptiert und Download bestätigt.
- `open_air_festival / market_square_open_air` – ausgewählt, Downloadbestätigung im Repo-Stand noch offen.
- `business_messe_info / info_evening` – ausgewählt, Downloadbestätigung im Repo-Stand noch offen.

Wichtig: Lokal heruntergeladene Bilder, die nicht in `data/event_visual_phase2_acceptance_notes.json` oder als Source-Datei im Repo dokumentiert sind, gelten nicht als belastbarer Repo-Stand. Sie duerfen erst bei der Integration als konkrete Dateien/Motive verifiziert werden.

### Bekannte Taxonomie-/Inferenzprobleme aus dem Sheet-Abgleich

Diese Punkte sind keine Bildproduktionsaufgaben, sondern Regel-/Datenqualitätsaufgaben:

- `Rosenmontagszug` muss zu `parade_festzug / carnival_parade`, nicht zu `market_stalls / neutral_market_stalls`.
- `Filmvorführung ...` muss zu `film_screening`, zusammengesetzte Begriffe wie `Filmvorführung` duerfen nicht am Wortgrenzen-Regex scheitern.
- `Fahrradtour mit Guide` und `Segwaytouren` muessen bei eindeutigem Titel zu `active_route_tour / guided_bike_tour` bzw. `active_route_tour / segway_tour`; Kategorie `Natur & Draußen` darf diese starken Titelmarker nicht überschreiben.
- Naturbezogene Führungen wie `Lebenselixier Wasser - der Pröbstingsee...` duerfen nicht allein wegen `Führung` zu `city_tour_history` werden, wenn Natur-/See-Kontext klar dominiert.
- `Bocholt Blüht mit großem Oldtimerfestival` muss wegen `Oldtimerfestival` zu `vehicle_classic / classic_car_meet` oder einem bewusst definierten Stadtfest+Oldtimer-Fall, nicht zu generischen Marktständen.
- Business-/Wirtschaftsformate wie `Markterschließung Niederlande` brauchen einen besseren Key/Motif-Pfad als `creative_making_workshop`, z. B. `business_messe_info` mit neuem oder bestehendem Motiv.
- Familien-/interkulturelle Stadtfeste wie `Internationales Familienfest...` und `Weltkindertagsfest / Eröffnung der Interkulturellen Woche` sollen auf `city_festival_street / children_intercultural_festival` geprüft werden.
- Englische Titelmarker muessen robust bleiben, z. B. `fencing` auch ohne deutsche Beschreibung.

### Nachhaltige Abarbeitungsreihenfolge

#### P0 – Datenbasis und Gap-Ermittlung stabilisieren

1. Leere Zeilen in `scripts/build-events-from-tsv.py` genauso ignorieren wie im Deploy-Exporter.
2. `scripts/build-event-visual-gap-backlog.py` standardisiert gegen den frischen Sheet-TSV-Stand laufen lassen, vorzugsweise `data/events.tsv` als Defaultquelle oder als klar dokumentierter Pflichtparameter.
3. Deploy-/CI-Entscheidung treffen: Gap-Backlog nur manuell erzeugen oder nach Sheet-Export automatisch als Report/Artefakt mitlaufen lassen.
4. Nach jedem Sheet-Abgleich immer Quelle und Stand nennen: Sheet-Export, Deploy-Artefakt oder lokaler Snapshot.

#### P1 – Key-/Motif-Inferenz härten

1. Zusammengesetzte Begriffe und englische Marker ergänzen: `Rosenmontagszug`, `Filmvorführung`, `Oldtimerfestival`, `Segwaytour(en)`, `fencing`.
2. Starke Titelmarker vor Kategorie-/Location-Fallbacks priorisieren.
3. Naturführung, Stadtführung, aktive Tour und Fahrrad-/Segwaytour klarer voneinander trennen.
4. Business-/Messe-/Info-/Workshop-Fälle sauber von Kreativworkshops trennen.
5. Nach jeder Regeländerung den aktuellen Sheet-Export erneut klassifizieren und nur echte Verbesserungen committen.

#### P2 – Eventdaten um Motivstatus erweitern

1. `scripts/build-events-from-tsv.py` soll neben `visual_key` auch `visual_motif` und optional `visual_asset_status` erzeugen.
2. Unsichere Fälle muessen `review` bleiben, nicht geraten werden.
3. Das erzeugte `data/events.json` wird dadurch runtime-fähig fuer motivgenaue Bildauswahl.

#### P3 – Runtime-Resolver motivfähig machen

1. `js/events.js` und `js/today-home.js` muessen `visual_motif` aus Eventdaten lesen.
2. Ready-Pools muessen `visual_motif` aus `data/event_visual_pool.json` in den JS-Zustand übernehmen.
3. Auswahlregel:
   - exaktes `visual_key + visual_motif` bevorzugen,
   - sonst neutrales Fallback-Motiv desselben Keys,
   - niemals anderes spezifisches Motiv desselben Keys.
4. Wenn kein sicherer Fallback vorhanden ist, soll kein fachlich falsches spezifisches Bild erzwungen werden.

#### P4 – Vorhandene Kandidaten integrieren

1. Nur Kandidaten integrieren, deren Source-Dateien vorliegen und deren Motiv eindeutig dokumentiert ist.
2. Zuerst High-Gaps und bereits akzeptierte Kandidaten schließen.
3. Integration immer mit WebP-Konvertierung, `data/event_visual_pool.json`-Update, Bildnachweis-/Attributionsprüfung und Audit.
4. Nach Integration Gap-Backlog neu bauen; erledigte Motive duerfen nicht mehr als offen erscheinen.

#### P5 – Neue Bilder nur aus priorisiertem Backlog erzeugen

1. Kein Batch aus reiner Vollständigkeitslogik der Taxonomie.
2. Neue Bildproduktion nur fuer offene Backlog-Motive oder bewusst dokumentierten strategischen Poolausbau.
3. Batches klein halten und nach jedem Batch Kandidaten in `data/event_visual_phase2_acceptance_notes.json` nachtragen.
4. High-Gaps vor Medium-Gaps, häufige Motive vor Einzelfällen, Falschbild-Risiko vor reiner Vielfalt.

#### P6 – Audit und Abschlusskriterien

1. Audit ergänzen, der erkennt, ob spezifische Events ein anderes spezifisches Motivbild erhalten könnten.
2. Audit ergänzen, der fehlende `visual_motif` bei Events mit eindeutigem Motiv meldet.
3. Abschluss erst, wenn aktueller Sheet-Export, Gap-Backlog, Runtime-Resolver und Poolzustand konsistent sind.
4. Danach in `TEST_STATUS.md` einen belegten Abschlussstand dokumentieren.

### Nicht-Ziele

- Kein vollständiges Bildarchiv fuer jede theoretische Unterkategorie erzwingen.
- Keine manuelle Pflege von `data/event_visual_gap_backlog.tsv` als Produktionsnotizzettel.
- Keine Integration von Bildern ohne Source-Datei, Motivmapping und Audit.
- Keine weitere Bildproduktion, solange Daten-/Resolver-Prozess nicht stabil ist oder kein priorisierter Gap vorliegt.

### Akzeptanzkriterien fuer diesen Workstream

- Der aktuelle Sheet-Export kann lokal und im Deploy konsistent verarbeitet werden.
- `Borkener Darts Trophy` wird als `indoor_sport_competition / darts` erkannt.
- `17. International Fencing Camp Bocholt` wird als `indoor_sport_competition / fencing` erkannt.
- Die bekannten Fehlklassifikationen aus dieser Roadmap sind korrigiert oder bewusst als Review-Fall dokumentiert.
- Eventkarten wählen keine falschen spezifischen Unter-Motive mehr.
- Der Gap-Backlog ist nach Integration der passenden Bilder nachvollziehbar kleiner und bleibt aus dem Sheet reproduzierbar.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_SHEET_REVIEW_2026_06_15 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_QA_RULEPATCH_2026_06_18 | Zweck: markiert Motiv-Fit-QA-Regelhaertung als Abschlussblock vor Freeze; Umfang: keine neue Bildproduktion, nur Mapping-Haertung === -->
## Event Visual Motif-Fit-QA – Regelhärtung abgeschlossen (2026-06-18)

Der technische Event-Visual-Abschluss wurde um eine fachliche Motiv-Fit-QA ergänzt.

Ergebnis des Pakets:
- Bekannte Fehlzuordnungen werden deterministisch auf passendere vorhandene Ready-Motive gelenkt.
- Es entsteht kein neuer Bildproduktionsbedarf.
- Matrix und Gap-Backlog bleiben frei von Produktions-/Review-Gaps.

Nach Upload und grünem Deploy ist nur noch ein kurzer Sicht-Smoke auf `/events/` nötig.
Wenn dabei keine fachlich falschen Bildzuordnungen mehr auffallen, kann der Event-Visual-Motif-Fit-Workstream für den aktuellen Sheet-Stand eingefroren werden.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_QA_RULEPATCH_2026_06_18 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_VISIBLE_MOTIF_FIT_FINAL_RULE_SWEEP_2026_06_18 | Zweck: vermerkt Abschluss des Regel-Sweeps ohne neue Bildproduktion; Umfang: naechste Arbeit nur noch bei sichtbarem Einzelfund === -->
## Event Visual Motif-Fit – finaler sichtbarer Regel-Sweep (2026-06-18)

Der nachgelagerte Regel-Sweep korrigiert die sichtbare Fehlzuordnung von thematischen/historischen Führungen als Aktivtour.

Status nach Anwendung:
- Keine neue Bildproduktion.
- Keine offenen Matrix-/Backlog-Gaps.
- Weitere Arbeit nur bei konkretem sichtbarem Einzelfund, nicht aus theoretischer Vollständigkeitslogik.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_VISIBLE_MOTIF_FIT_FINAL_RULE_SWEEP_2026_06_18 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_RESOLVER_FINAL_2026_06_18 | Zweck: markiert motivgenauen Event-Visual-Resolver als finalen Abschlusskandidaten; Umfang: keine neue Bildproduktion, nur Sicht-Smoke nach Deploy === -->
## Event Visual Motif-Fit – motivgenauer Resolver abgeschlossen (2026-06-18)

Der finale Abschlusskandidat schließt die Runtime-Lücke zwischen Matrix/Pool und Frontend:
- `visual_motif` wird in die generierten Eventdaten übernommen.
- Events- und Today-Frontend wählen motivgenau.
- Keine neue Bildproduktion.

Nach Upload/Deploy genügt ein gezielter Sicht-Smoke mit sichtbaren Zukunftsevents. Weitere Arbeit nur bei konkretem sichtbarem Einzelfund.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_RESOLVER_FINAL_2026_06_18 === -->

<!-- === BEGIN BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_CLOSED_2026_06_18 | Zweck: markiert Event-Visual-Motif-Fit als abgeschlossenen Workstream; Umfang: naechster Schritt main nur bei staging-Releasefaehigkeit === -->
## Event Visual Motif-Fit – Workstream abgeschlossen (2026-06-18)

Der Event-Visual-Motif-Fit-Workstream ist für den aktuellen Sheet-/Staging-Stand abgeschlossen.

Abgeschlossen:
- Event-Visual Gap Batch 02.
- Finaler Restbatch.
- Motiv-Regelhärtung.
- Motivgenauer Frontend-Resolver.
- Staging-Deploy.
- Technischer Smoke.
- Sichtbare Motiv-Fit-Stichprobe.
- Datenbasierte Vollprüfung.

Es gibt für diesen Arbeitsblock keine offenen Produktions-, Integrations- oder Review-Gaps.

Nächster Schritt:
- Kein weiterer Event-Visual-Patch.
- `staging → main` nur dann, wenn `staging` insgesamt releasefähig ist und keine anderen unfertigen Änderungen enthält.
- Nach Main-Deploy reicht ein kurzer Live-Smoke auf `/events/`.
<!-- === END BLOCK: ROADMAP_EVENT_VISUAL_MOTIF_FIT_CLOSED_2026_06_18 === -->
<!-- === BEGIN BLOCK: ROADMAP_REPORTING_HARDENING_LIVE_PROOF_2026_06_19 | Zweck: markiert Reporting-Hardening als technisch live bewiesen und grenzt den weiteren organischen Datenlauf fuer Akquise ab; Umfang: Status, naechste Wartepunkte, keine neue Codearbeit === -->
## Reporting-/Tracking-Hardening – technisch live bewiesen (2026-06-19)

Status:
- Technischer Workpack abgeschlossen.
- Live-Beweis nach Main-Merge bestanden.

Belegt:
- `impact_summary` im Anbieterportal wird serverseitig befüllt.
- Die neue Aktivitäts-Funnel-Route `/aktivitaeten/sichtbar-werden/` wird live als `organizer_cta_click` gezählt.
- Today-Desktop-Klicks reichen vorhandene Reporting-Ziele an das Nutzwerttracking weiter.
- Dashboard-Nachweis am 2026-06-19:
  - `Nutzwert-Klicks` stieg von 75 auf 76.
  - `Veranstalter-CTA` stieg von 3 auf 4.

Bewertung:
- Keine weitere technische Reporting-Härtung aus diesem Prüfstand nötig.
- Testklicks bleiben reine Funktionsbeweise und dürfen nicht als Akquise-Erfolgsdaten verwendet werden.
- Für Verkaufs-/Akquise-Aussagen zählt erst ein längerer organischer Datenlauf.

Nächster sinnvoller Wartepunkt:
- Nach einem vollständigen 28-Tage-/30-Tage-Zeitraum prüfen, ob genug organische Website-, Route-/Maps-, Detail- und CTA-Signale für einen ersten belastbaren Feedbackbericht vorliegen.
- Bis dahin keine neuen Reporting-Features bauen, sondern Datenqualität beobachten.
<!-- === END BLOCK: ROADMAP_REPORTING_HARDENING_LIVE_PROOF_2026_06_19 === -->
