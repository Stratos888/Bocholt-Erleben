# Steuerzentrale – Folge-Workpack: ausnahmebasierte Eventprüfung bis zum fertigen Ergebnis

Stand: 2026-07-16  
Arbeitsbranch für die spätere Umsetzung: von aktuellem `staging` ableiten  
Aktueller dokumentierter Staging-Stand: `bba370093f83bd2190ead0fb2f8f605c46c047f5`  
Aktueller Main-Stand: `e00dfd5cbe19529b2300ee439727ea00f747c3b6`  
Status dieses Dokuments: kanonische Übergabe für den nächsten Chat

## 1. Zweck

Dieses Dokument verhindert, dass die Steuerzentrale erneut durch einzelne sichtbare Symptome verbessert wird, während die anschließende Prozesskette offenbleibt.

Das nächste Workpack darf nicht nur Warntexte, einzelne Eingabefelder oder einen konkreten CityArt-Fall korrigieren. Es muss den vollständigen Prüfprozess für **alle möglichen Befunde eines neuen Eventkandidaten** definieren und danach in einem zusammenhängenden Staging-Paket umsetzen.

Die verbindliche Leitfrage für jeden Befund lautet:

> Was sieht der Nutzer, welche konkrete Entscheidung oder Eingabe ist möglich, was wird dadurch gespeichert oder ausgelöst, wie wird der Folgeprozess bis zum fertigen Event abgeschlossen und wie wird der Endzustand verifiziert?

Eine Meldung ohne unmittelbar ausführbare Folgeaktion ist unvollständig.

## 2. Gesicherter Ausgangsstand

### 2.1 Zustandskonsistenz ist auf Staging nachgewiesen

PR `#77` vereinheitlicht Quelle, lokalen Fall, aktive API und UI-Sichtbarkeit. Der reale Nachweis ist erfolgt:

- Die „2. Bocholter Vereinsmesse“ war in der führenden Inbox bereits mit `status=verworfen` und `ablehnungsgrund=Dublette` gespeichert.
- Nach Laden der neuen Staging-Steuerzentrale wurde der lokale Altfall automatisch reconciliiert.
- Die Vereinsmesse verschwand ohne erneute Ablehnung aus der offenen Prüfliste.

Dieser Zustandsvertrag ist nicht erneut grundsätzlich umzubauen. Das Folge-Workpack setzt darauf auf.

### 2.2 PR #77 ist noch nicht in Main

`staging` und `main` sind historisch divergiert. `staging` enthält das neue Zustands-Workpack, `main` besitzt einen zusätzlichen früheren Release-Merge-Commit. Vor einem späteren Main-Release muss die Historie erneut kontrolliert synchronisiert und der kombinierte Stand validiert werden.

Es gibt bis zum Abschluss des hier beschriebenen Workpacks **keinen Main-Merge**.

### 2.3 Aktueller realer Prüffall

Führende Inbox, Zeile 2:

- Titel: `Bocholter Kulturtage 2026 - Kunstmarkt CityArt und künstlerische Mitmach-Stände für Kinder und Jugendliche`
- Datum: `2026-08-30`
- Uhrzeit: leer
- Ort: `Markt vor dem Historischen Rathaus`
- Stadt: `Bocholt`
- Kategorie: `Kinder & Familie`
- offizielle Quelle: Stadt Bocholt
- Beschreibung vorhanden
- `visual_key=art_exhibition_gallery`
- `visual_motif` leer

Die aktuelle UI zeigt deshalb:

- `Pflichtangabe fehlt: visual_motif.`
- `Fehlende Uhrzeit muss fachlich erklärt sein.`

Diese Meldungen sind technisch korrekt, aber nicht ausreichend handlungsorientiert.

### 2.4 Für CityArt ist bereits ein passendes Asset vorhanden

Die bestehende Visualregel erkennt bei `Kunstmarkt` oder `CityArt` das Motiv:

- `visual_key=art_exhibition_gallery`
- `visual_motif=art_market`

Im Visual-Pool existiert bereits ein freigegebenes Asset:

- Asset-ID: `motif-gap-art-market-01`
- Pfad: `/assets/event-visuals/motif-gap-art-market-01.webp`
- Status: `ready`

Für diesen Fall ist keine neue Bilderzeugung notwendig. Die spätere Prüfansicht muss das konkrete Bild zeigen und seine verbindliche Verwendung ermöglichen.

## 3. Warum der bisherige Prüfprozess noch nicht Premium ist

Der aktuelle Eventvertrag prüft hauptsächlich Vorhandensein, Format und einige logische Konflikte. Die UI kann daher sagen, dass etwas fehlt, aber nicht zuverlässig:

- was der Nutzer als Nächstes tun soll,
- welche Auswahlmöglichkeiten fachlich zulässig sind,
- welche Folgeaktion eine Auswahl auslöst,
- welche Angaben bereits belastbar geprüft sind,
- ob eine Bestätigung nur einen abstrakten Wert oder das konkrete Endergebnis betrifft,
- wann ein Event tatsächlich fertig und veröffentlichbar ist.

Der aktuelle Editor öffnet bei einem Blocker sämtliche Eventfelder. Der Nutzer muss dadurch erneut den gesamten Datensatz überblicken, obwohl oft nur ein oder zwei Ausnahmen offen sind.

Der Zielzustand ist keine große Vollmaske mit besseren Warntexten, sondern eine **ausnahmebasierte Prüfoberfläche mit abgeschlossenen Aktionsketten**.

## 4. Verbindliches Modell jeder Prüfausnahme

Vor jeder Implementierung ist für jeden möglichen Befund dieselbe Matrix vollständig auszufüllen:

| Stufe | Pflichtinhalt |
|---|---|
| Erkennung | Welche Regel oder Evidenz erzeugt den Befund? |
| Nutzertext | Verständliche fachliche Formulierung ohne technische Feldnamen |
| Evidenz | Welche Quelle, welcher Vergleich und welcher Vertrauensgrad liegen vor? |
| Aktion | Welche konkrete Auswahl oder Eingabe kann der Nutzer direkt vornehmen? |
| Persistenz | Welche kanonischen Felder und Werte werden gespeichert? |
| Folgeprozess | Welcher technische oder redaktionelle Prozess wird ausgelöst? |
| Verifikation | Was wird nach dem Schreiben oder Auslösen zurückgelesen und geprüft? |
| Fallzustand | Bleibt der Fall offen, wird er zurückgestellt oder entscheidungsreif? |
| UI-Endzustand | Was verschwindet, was erscheint und welche nächste Aktion ist möglich? |
| Audit/Lernen | Welche Entscheidung und Korrektur wird nachvollziehbar protokolliert? |

Kein Befund gilt als umgesetzt, solange eine dieser Stufen offen ist.

## 5. Evidenz- und Verantwortungsmodell

Jedes sichtbare Feld erhält genau einen nachvollziehbaren Prüfstatus:

| Status | Bedeutung | Nutzeraufgabe |
|---|---|---|
| `officially_verified` | eindeutig aus offizieller Quelle übernommen und gegen diese geprüft | keine Einzelprüfung erforderlich |
| `cross_checked` | durch mehrere belastbare Quellen oder bestehende Daten plausibilisiert | nur bei sichtbarem Konflikt prüfen |
| `format_validated` | technisch und logisch geprüft, aber fachlich nicht belegt | bei entscheidungsrelevanten Feldern kurz bestätigen |
| `system_suggestion` | regel- oder KI-basierter Vorschlag | Vorschlag bestätigen oder korrigieren |
| `human_required` | System kann die fachliche Entscheidung nicht treffen | Nutzer muss direkt handeln |
| `unverifiable` | derzeit keine belastbare Angabe verfügbar | zurückstellen, sichere Alternative wählen oder ablehnen |
| `conflict` | Quellen oder Daten widersprechen sich | Konflikt muss aufgelöst werden |

Der Nutzer soll nicht sämtliche öffentlichen Fakten erneut manuell prüfen. Er prüft:

1. nur offene Ausnahmen,
2. sichtbare Konflikte,
3. systemische Vorschläge mit fachlicher Wirkung,
4. abschließend die kompakte Gesamtfassung.

Eine automatische Prüfung darf nur dann als Entlastung angezeigt werden, wenn Evidenzart und Prüfergebnis im Datenvertrag vorhanden sind.

## 6. Vollständige Befundklassen für neue Eventkandidaten

Die nachfolgende Liste ist vor Codeänderungen gegen alle tatsächlichen Quellfelder, Verträge, Importregeln und bestehenden Inbox-Funktionen zu validieren. Fehlende Befundklassen sind zuerst zu ergänzen.

### 6.1 Identität und Titel

Mögliche Zustände:

- Titel fehlt.
- Titel ist zu allgemein oder nur eine technische Bezeichnung.
- Titel widerspricht der offiziellen Quelle.
- Titel enthält unnötige Orts-, Datums- oder Werbeelemente.
- Titel ist bereits belastbar aus der offiziellen Quelle bestätigt.

Erforderliche Aktionen:

- Titel direkt eingeben oder vorgeschlagenen bereinigten Titel bestätigen.
- Originaltitel und Vorschlag nebeneinander anzeigen.
- Nach Speicherung den finalen Titel zurücklesen und den Befund neu bewerten.

### 6.2 Datum, Zeitraum und Wiederholung

Mögliche Zustände:

- Startdatum fehlt oder ist ungültig.
- Enddatum fehlt bei Mehrtagesevent.
- Enddatum liegt vor dem Startdatum.
- Termin ist abgelaufen.
- Quelle nennt einen anderen Termin.
- Wiederkehrende Veranstaltung wurde fälschlich als ein einzelner Termin erfasst.
- Mehrere einzelne Termine müssen als Serie oder getrennte Events behandelt werden.

Erforderliche Aktionen:

- Datum oder Zeitraum direkt korrigieren.
- `Einzeltermin`, `mehrtägig` oder `Terminserie` fachlich auswählen.
- Abgelaufene beziehungsweise abgesagte Termine typisiert ablehnen oder archivieren.
- Bei Serien die nachgelagerte Erzeugungslogik und Dublettenprüfung vollständig definieren.

### 6.3 Uhrzeit und Zeitstatus

Freitext allein ist nicht ausreichend. Es wird ein typisierter Zeitvertrag benötigt.

Kanonische fachliche Zustände:

| Zeitstatus | Eingabe/Folge | Freigabewirkung |
|---|---|---|
| `fixed_time` | konkrete Uhrzeit eintragen oder bestätigen | Punkt gelöst |
| `all_day` | ganztägig bestätigen | Punkt gelöst |
| `during_opening_hours` | gültige Öffnungszeit oder erklärenden Zeitraum binden | Punkt gelöst, wenn Zeitraum belastbar ist |
| `multiple_times` | mehrere Zeiten strukturiert erfassen oder offiziellen Programmhinweis binden | Punkt gelöst, wenn Darstellung feststeht |
| `time_not_published` | Quelle enthält noch keine Zeit | Event bleibt offen und wird automatisch zur erneuten Quellenprüfung vorgemerkt |
| `time_conflict` | Quellen widersprechen sich | Event bleibt blockiert, bis der Konflikt aufgelöst ist |
| `time_not_applicable` | Uhrzeit ist fachlich wirklich nicht anwendbar | nur für klar definierte Eventtypen zulässig |

Die UI muss unmittelbar anbieten:

- offizielle Quelle öffnen,
- Uhrzeit eintragen,
- einen zulässigen Zeitstatus auswählen,
- bei `time_not_published` ein Wiedervorlagedatum bestätigen.

Nach der Aktion:

1. Werte in die führende Quelle schreiben,
2. zurücklesen,
3. Zeitvertrag neu bewerten,
4. Prüfschritt als gelöst oder weiterhin offen anzeigen.

### 6.4 Stadt, lokaler Bezug und Reichweite

Mögliche Zustände:

- Stadt fehlt.
- lokaler Bezug ist nicht nachgewiesen.
- Ort liegt außerhalb des zulässigen Gebiets.
- Online- oder Hybridveranstaltung benötigt einen eigenen Reichweitenvertrag.
- Stadt und Veranstaltungsort widersprechen sich.

Erforderliche Aktionen:

- Stadt oder lokalen Bezug bestätigen/korrigieren.
- außerhalb des Scopes typisiert ablehnen.
- Online-/Hybridstatus strukturiert erfassen.

### 6.5 Veranstaltungsort und Adresse

Mögliche Zustände:

- Veranstaltungsort fehlt.
- Adresse fehlt, obwohl Navigation erforderlich ist.
- Ortsname genügt, weil der Ort zentral bekannt und eindeutig ist.
- Treffpunkt unterscheidet sich vom Veranstaltungsort.
- Adresse ist widersprüchlich oder nicht geocodierbar.

Erforderliche Aktionen:

- Ort, Treffpunkt oder Adresse direkt erfassen.
- `Adresse nicht erforderlich` nur mit fachlichem Grund zulassen.
- Karten-/Routingstatus nach Speicherung neu prüfen.

### 6.6 Kategorie und Zielgruppe

Mögliche Zustände:

- Kategorie fehlt.
- Systemvorschlag ist eindeutig.
- mehrere Kategorien sind plausibel.
- Kategorie widerspricht Inhalt oder Zielgruppe.
- Kinder-/Familienbezug, Barrierefreiheit oder Altersgrenzen beeinflussen Darstellung und Filter.

Erforderliche Aktionen:

- vorgeschlagene Kategorie mit Begründung anzeigen,
- direkt bestätigen oder Alternative auswählen,
- Filter- und Darstellungswirkung anschließend prüfen.

### 6.7 Quelle und Quellenqualität

Mögliche Zustände:

- offizielle Quelle bestätigt.
- nur sekundäre Quelle vorhanden.
- Quelle ist nicht erreichbar.
- Quelle ist veraltet.
- Eventlink und ursprüngliche Prüfquelle sind verschieden.
- mehrere Quellen widersprechen sich.
- offizielle Quelle enthält zu wenig Informationen.

Erforderliche Aktionen:

- Event-/Zielseite und offizielle Prüfquelle getrennt anzeigen.
- alternative belastbare Quelle hinterlegen.
- schwache Quelle nicht durch einen beliebigen Freitext überstimmen.
- bei temporär fehlenden Informationen automatische Wiedervorlage ermöglichen.
- Quellenkonflikte sichtbar und feldbezogen auflösen.

### 6.8 Beschreibung und Behauptungsprüfung

Mögliche Zustände:

- Beschreibung fehlt.
- Beschreibung ist zu kurz, generisch oder werblich.
- Beschreibung enthält nicht belegte Aussagen.
- Beschreibung ist automatisch erstellt und benötigt Bestätigung.
- offizielle Quelle wurde korrekt verdichtet.
- einzelne wichtige Fakten fehlen.

Erforderliche Aktionen:

- aktuelle Fassung und Vorschlag vergleichen,
- nur betroffene Textstellen bearbeiten,
- belegte Kernaussagen sichtbar machen,
- nach Speicherung Qualitäts- und Quellenprüfung erneut ausführen.

### 6.9 Ticket, Buchung, Preis und Zugang

Mögliche Zustände:

- Ticketlink erforderlich und fehlt.
- Veranstaltung ist kostenlos und benötigt keinen Ticketlink.
- Anmeldung ist erforderlich, aber kein klassisches Ticket.
- Ticket-/Anmeldelink ist ungültig oder führt nicht zum Event.
- Preisangaben widersprechen sich.
- ausverkauft, Warteliste oder Anmeldung noch nicht geöffnet.

Erforderliche Aktionen:

- `kostenlos`, `ohne Anmeldung`, `Anmeldung erforderlich`, `Ticket erforderlich` typisiert auswählen,
- Link direkt hinterlegen oder korrigieren,
- zeitabhängige Zustände automatisch nachprüfen,
- keine Freigabe mit einem fachlich erforderlichen, aber ungeklärten Zugangsweg.

### 6.10 Dubletten und Serienabgrenzung

Mögliche Zustände:

- keine relevante Übereinstimmung.
- wahrscheinliche Dublette.
- harte Dublette mit bestehendem Event.
- gleicher Titel, aber anderer Termin.
- wiederkehrende Reihe mit eigenständiger Ausgabe.
- bestehendes Event soll ergänzt statt dupliziert werden.

Erforderliche Darstellung:

- bestehender Eventtitel,
- Datum und Ort,
- direkter Link,
- Match-Art,
- nachvollziehbare Begründung beziehungsweise Score.

Erforderliche Aktionen:

- `Dublette ablehnen`,
- `eigenständiger Termin`,
- `bestehendes Event ergänzen`,
- `unklar – zurückstellen`.

Jede Auswahl benötigt eine definierte Persistenz- und Folgeaktion. Ein bloßer Marker `hard_duplicate` reicht für die Nutzerentscheidung nicht.

### 6.11 Visual-Key, Motiv und konkretes Asset

Die Kette muss drei getrennte Ebenen behandeln:

1. **Visual-Key** – grober Bildbereich,
2. **Visual-Motiv** – fachlich passendes Motiv,
3. **konkretes Asset** – tatsächlich später verwendete Bilddatei.

Die Bestätigung eines Motivbegriffs allein ist unzureichend.

#### Fall A: genaues Motiv und freigegebenes Asset vorhanden

UI:

- konkretes Bild anzeigen,
- Motivlabel und symbolischen Charakter erklären,
- Aktionen `Dieses Bild verwenden`, `Anderes Bild anzeigen`, `Motiv passt nicht`.

Folge bei Bestätigung:

1. `visual_key` speichern,
2. `visual_motif` speichern,
3. eine verbindliche `visual_asset_id` oder gleichwertige Asset-Override-Bindung speichern,
4. führende Quelle und Asset-Verfügbarkeit zurücklesen,
5. Vorschau erneut aus dem final gebundenen Asset rendern,
6. Prüfpunkt schließen.

#### Fall B: exaktes Motiv fehlt, sicherer neutraler Fallback vorhanden

UI:

- Fallback-Bild anzeigen,
- klar als neutralen Fallback kennzeichnen,
- Aktionen `Fallback verwenden` oder `Auf passendes Bild warten`.

Bei `Fallback verwenden`:

- Fallback verbindlich binden,
- Event darf weiterlaufen,
- parallel automatisch einen Visual-Gap für das exakte Motiv anlegen.

Bei `Auf passendes Bild warten`:

- Event bleibt blockiert oder wird zurückgestellt,
- Visual-Gap wird automatisch angelegt.

#### Fall C: kein sicheres Asset vorhanden

Folgeprozess:

1. Motiv fachlich bestätigen,
2. Visual-Gap automatisch erzeugen,
3. Event in einen klaren Wartezustand setzen,
4. Bild durch die Visual-Pipeline erzeugen oder rechtssicher beschaffen,
5. Format, Rechte, Symbolcharakter, lokale Plausibilität und Motivduplikate prüfen,
6. Asset als Kandidat registrieren,
7. Kandidat in die Steuerzentrale zurückführen,
8. Nutzer bestätigt oder verwirft das konkrete Ergebnis,
9. erst danach `ready` und verbindliche Bindung.

Der Nutzer erzeugt keine Bilder manuell. Seine Aufgabe ist die fachliche Motiv- und Ergebnisbestätigung.

#### Fall D: offizielles Bild vorhanden

Vor Verwendung müssen geklärt sein:

- Nutzungsrecht,
- Bildqualität und Format,
- sachliche Zuordnung,
- Dokumentarcharakter versus symbolisches Bild,
- Datenschutz beziehungsweise erkennbare Personen, soweit relevant.

Ohne geklärte Rechte darf die offizielle Abbildung nicht als sichere Alternative angeboten werden.

### 6.12 Absage, Verschiebung und sonstige Aktualität

Mögliche Zustände:

- Event abgesagt.
- Event verschoben, neuer Termin bekannt.
- Termin oder Ort noch unter Vorbehalt.
- Quelle wurde nach Eingang verändert.

Erforderliche Aktionen:

- Absage typisiert schließen,
- Verschiebung strukturiert übernehmen und erneut auf Dublette prüfen,
- Vorbehalt sichtbar machen und gegebenenfalls Wiedervorlage auslösen.

## 7. Zielbild der Prüfansicht

Die Karte beginnt nicht mit einer roten technischen Fehlerliste, sondern mit einem handlungsorientierten Status:

> **Noch zwei Punkte klären**

Jede offene Aufgabe enthält:

- verständlichen Titel,
- kurze Begründung,
- Evidenz und Link zur relevanten Quelle,
- direkt eingebettete Eingabe oder Auswahl,
- sichtbare Folge der Entscheidung,
- eigenen Speichern-/Bestätigen-Schritt,
- verifizierten Erledigt-Zustand.

Bereits geprüfte Angaben erscheinen in einer kompakten Zusammenfassung und müssen nicht erneut einzeln bearbeitet werden. Technische Rohdaten bleiben unter `Details` verfügbar.

Nach Abschluss aller Ausnahmen folgt eine kompakte Gesamtprüfung:

> **Event entscheidungsreif**  
> Alle Pflichtangaben und Folgeprozesse sind abgeschlossen. Automatisch geprüfte Werte, von dir bestätigte Vorschläge und verbleibende Hinweise sind eindeutig gekennzeichnet.

Erst dann ist `Übernehmen` aktiv.

## 8. Konkreter Zielablauf für CityArt

1. System erkennt `art_market`.
2. Steuerzentrale zeigt `motif-gap-art-market-01` als konkrete Vorschau.
3. Nutzer bestätigt `Dieses Bild verwenden` oder wählt eine Alternative.
4. `visual_key`, `visual_motif` und konkrete Asset-Bindung werden gespeichert und verifiziert.
5. Nutzer öffnet die offizielle Quelle und klärt den Zeitstatus.
6. Nutzer trägt eine konkrete Uhrzeit ein oder wählt einen fachlich gültigen Zeitstatus.
7. Bei `time_not_published` wird nicht freigegeben, sondern eine automatische Wiedervorlage gespeichert.
8. Nach jeder Teilaktion wird nur der betroffene Prüfpunkt neu bewertet.
9. Sind alle Punkte gelöst, zeigt die Karte `Event entscheidungsreif`.
10. Erst die abschließende Übernahme veröffentlicht beziehungsweise übernimmt den vollständigen Datensatz.

## 9. Verbindliche technische Anforderungen des nächsten Workpacks

- kanonischer Befund- und Aufgabenvertrag statt reiner Blockertexte,
- typisierte Zeitstatus,
- feldbezogene Evidenzstatus,
- direkte Inline-Aktionen,
- partielle Speicherung einzelner Aufgaben ohne vollständige Übernahme,
- Rückleseprüfung jeder Teilaktion,
- erneute serverseitige Bewertung nach jeder Teilaktion,
- konkrete Asset-Bindung,
- Visual-Gap als echter Prozessfall mit Rückführung,
- getrennte Event- und Quellenlinks,
- Dedupe-Evidenz,
- abgelaufene und abgesagte Termine,
- klarer Warte-/Wiedervorlagevertrag,
- finaler Gesamtentscheidungsnachweis.

## 10. Verbindliche Teststrategie

### 10.1 Vor Codeänderung

Der nächste Chat muss zuerst:

1. sämtliche heute möglichen Blocker, Warnungen und Rohstatus aus Backend, Sheet und ursprünglicher Inbox inventarisieren,
2. die obige Matrix gegen reale Datenfelder vervollständigen,
3. jede Nutzeraktion bis zum Endzustand modellieren,
4. vorhandene Visual-, Dedupe-, Quellen- und Wiedervorlageprozesse wiederverwenden,
5. erst danach den Patch entwerfen.

### 10.2 CI-Verträge

Mindestens erforderlich:

- Fixture für jede Befundklasse,
- erlaubte Aktionen je Befund,
- Persistenzwerte je Auswahl,
- Postcondition je Teilaktion,
- Re-Evaluation der offenen Aufgaben,
- kein `ready`, solange Folgeprozesse offen sind,
- konkrete Asset-Vorschau entspricht final gebundenem Asset,
- Visual-Gap erzeugt genau einen idempotenten Folgefall,
- `time_not_published` erzeugt Wiedervorlage statt Scheinfertigstellung,
- Konflikte bleiben sichtbar,
- bereits verifizierte Felder werden nicht als Nutzeraufgabe ausgegeben.

### 10.3 Reale Staging-Abnahme

Mindestens folgende reale oder kontrollierte Fälle:

- CityArt: vorhandenes exaktes Asset + ungeklärte Uhrzeit,
- fehlende Uhrzeit, aber fachlich ganztägig,
- Uhrzeit noch nicht veröffentlicht,
- fehlendes Motiv mit sicherem Fallback,
- Motiv ohne verfügbares Asset,
- harte Dublette,
- ähnlicher Titel, aber eigenständiger Termin,
- abgelaufenes Event,
- schwache beziehungsweise widersprüchliche Quelle,
- erforderlicher Ticketlink fehlt,
- Mehrtagesevent ohne Enddatum,
- Aktivität außerhalb des lokalen Scopes.

Für jeden Fall muss nachweisbar sein:

```text
Befund
→ verständliche Aufgabe
→ direkte Aktion
→ Persistenz
→ Folgeprozess
→ Rückleseprüfung
→ neue Bewertung
→ korrekte UI und Fallzustand
```

## 11. Nicht-Ziele und Schutzregeln

- Kein einzelner CityArt-Hotfix.
- Keine rein kosmetische Umformulierung der roten Box.
- Kein neues Vollformular als Hauptlösung.
- Keine manuelle Bilderzeugung durch den Betreiber.
- Kein Erfolg nur aufgrund eines HTTP-`ok`.
- Keine automatische Freigabe aufgrund eines beliebigen Freitexts.
- Kein Main-Merge vor vollständigem Staging-Nachweis.
- Keine Wiederaufnahme der alten separaten Inbox-Architektur.
- Keine Änderung am kanonischen Backlogmodell.
- Keine erneute Grundsatzänderung des mit PR #77 bestätigten Zustandsvertrags ohne nachgewiesenen Fehler.

## 12. Definition of Done

Das Folge-Workpack ist erst abgeschlossen, wenn:

- alle real auftretenden Eventbefunde inventarisiert und dokumentiert sind,
- jeder Befund eine vollständige Aktionskette besitzt,
- die UI nur noch ungelöste Ausnahmen als Aufgaben zeigt,
- der Nutzer offene Angaben direkt an der Aufgabe klären kann,
- automatisch geprüfte Felder nachvollziehbar gekennzeichnet sind,
- CityArt ohne Vollformular durch Uhrzeitklärung und konkrete Bildbestätigung entscheidungsreif wird,
- fehlende Assets automatisch in einen geschlossenen Visual-Prozess übergehen,
- alle Teilaktionen idempotent und zurückverifiziert sind,
- ein realer Staging-Sweep die Befundmatrix bestätigt,
- erst danach ein kontrollierter Release nach Main vorbereitet wird.

## 13. Startanweisung für den nächsten Chat

```text
Wir setzen das Projekt „Bocholt Erleben“ im Repository Stratos888/Bocholt-Erleben fort.

Lies zuerst ausschließlich die aktuellen kanonischen Dateien auf dem Branch staging:

1. docs/steuerzentrale-naechstes-workpack-ausnahmebasierte-eventpruefung-2026-07-16.md
2. docs/steuerzentrale-e2e-state-consistency-workpack-2026-07-16.md
3. docs/steuerzentrale-inbox-feature-gap-analysis-2026-07-16.md
4. api/control-center/_editorial_contracts.php
5. api/control-center/_presentation.php
6. api/control-center/_source_reconciliation.php
7. api/control-center/_verified_source_writeback.php
8. js/control-center/review-render.js
9. js/control-center/review-actions.js
10. scripts/event_visual_motifs.py
11. data/event_visual_pool.json
12. scripts/build-event-visual-gap-backlog.py

Ausgangsstand:
- PR #77 ist auf staging gemergt.
- Staging-Commit vor der Übergabedokumentation: bba370093f83bd2190ead0fb2f8f605c46c047f5.
- Der reale Vereinsmesse-Fall wurde erfolgreich reconciliiert und ist aus der Prüfliste verschwunden.
- PR #77 ist noch nicht nach main veröffentlicht.
- Der nächste reale Fall ist CityArt. Es fehlen Uhrzeit/Zeitstatus und visual_motif.
- Für CityArt existiert bereits das ready-Asset motif-gap-art-market-01 für das Motiv art_market.

Arbeitsauftrag:
Analysiere vor jeder Codeänderung alle möglichen Event-Prüfbefunde und vervollständige die End-to-End-Aktionsmatrix. Behandle nicht nur CityArt. Für jeden Befund müssen Nutzertext, Evidenz, direkte Aktion, Persistenz, Folgeprozess, Verifikation, Fallzustand und UI-Endzustand definiert sein. Entwirf danach einen einzigen zusammenhängenden Premium-Staging-Workpack-Patch für eine ausnahmebasierte, evidenzgestützte Eventprüfung.

Verbindlich:
- keine symptomatischen Einzelpatches,
- keine Änderung auf main,
- kein Merge vor vollständiger CI und realer Staging-Abnahme,
- bestehende Zustandskonsistenz aus PR #77 beibehalten,
- Nutzer prüft nur Ausnahmen und das finale Gesamtbild,
- fehlende Bilder werden durch einen automatischen Visual-Prozess bearbeitet, nicht manuell durch den Nutzer,
- jede Aktion muss bis zum verifizierten Endzustand durchdacht und getestet werden.
```
