<!-- === BEGIN BLOCK: ROADMAP_STARTPARTNER_GROWTH_PILOT_IMPLEMENTATION_2026_07_18 | Zweck: setzt nach Abschluss der oeffentlichen Startpartner-Oberflaeche den operativen End-to-End-Lebenszyklus als naechsten kommerziellen Workpack; Umfang: Produktvertrag, Datenmodell, Intake, Qualifizierung, Aktivierung, Messung, Abschluss und Stop-Regel === -->
## Naechster kommerzieller Haupt-Workpack – Startpartner-Wachstumspilot operationalisieren

Status: Zielzustand fachlich validiert und dokumentiert; noch keine Implementierung in diesem Dokumentations-Workpack.

Massgebliche Zielzustandsdatei:

`docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md`

### Ausgangslage

Die oeffentliche Akquiseoberflaeche ist weitgehend vorhanden:

- Startpartner-Landingpage,
- Selbstmeldeformular,
- Einordnung als begrenzter 6-Monats-Pilot,
- Abgrenzung zu Tipp-Kanal und regulaeren Tarifen,
- vorhandener Anbieterbereich und Wirkungsmessung.

Nicht geschlossen ist der operative Lebenszyklus nach Eingang einer Anfrage. Aktuell darf deshalb noch keine groessere aktive Akquise-Kohorte gestartet werden.

### Ziel des Workpacks

Selbstmeldungen und gezielte Ansprache sollen in einen gemeinsamen, nachvollziehbaren Prozess fuehren:

```text
Kandidat
→ Qualifizierung
→ Aufnahmeentscheidung
→ Onboarding
→ Aktivierung
→ 6-Monats-Pilot
→ Zwischen- und Abschlussbewertung
→ regulaerer Tarif oder geordnetes Ende
```

### Verbindliche Umsetzungsreihenfolge

1. Startpartner-Produkt- und Prozesslogik aus dem Zielzustand in `Produktvertrag.md` uebernehmen.
2. Datenmodell fuer Kandidat, Partner, Status, Kapazitaet, Pilotdaten, Reichweitenbeitrag und Abschlussentscheidung definieren.
3. Startpartner-Anfragen aus der isolierten Formuebermittlung in einen strukturierten internen Fall ueberfuehren.
4. Qualifizierung, Aufnahme, Ablehnung, alternativen Produktweg und Warteliste in der Steuerzentrale abbilden.
5. Anbieteraccount, Pilotberechtigung, Inhalte und objektgenaue Wirkungsmessung verknuepfen.
6. Aktivierungsdatum als Beginn der sechs Monate und ein eindeutiges Pilotende speichern.
7. Kontrollpunkte nach Aktivierung, etwa 30 Tagen, etwa 90 Tagen, vor Ablauf und zum Abschluss unterstuetzen.
8. Abschlussbericht und aktive Tarif- oder Endentscheidung abbilden.
9. Kapazitaets- und Stop-Regel auswertbar machen.
10. Erst nach bestandener End-to-End-Validierung die erste aktive Akquise-Kohorte starten.

### Kapazitaets- und Stop-Regel

- maximal acht gleichzeitig aktive Startpartner in der ersten Kohorte,
- Aufnahmestopp bei etwa 80 Prozent belegter Betreuungskapazitaet,
- Warteliste statt weiterer unmittelbarer Aktivierung,
- Ende der allgemeinen kostenlosen Aufnahme nach mindestens sechs weitgehend abgeschlossenen Piloten, mindestens drei getesteten Partnerarten, mindestens vier belastbaren Wirkungsauswertungen und geklaerter Tarif-/Aufwandsbewertung,
- keine automatische Verlaengerung des Gratis-Tests bei schwacher Zahlungsquote.

### Abnahmekriterien

Der Workpack ist erst abgeschlossen, wenn:

- Inbound und aktive Akquise in denselben Prozess fuehren,
- jeder Kandidat einen eindeutigen Status und eine dokumentierte Entscheidung besitzt,
- Anbieteraccount, Inhalte, Pilotlaufzeit und Messdaten eindeutig verknuepft sind,
- Kapazitaet und Warteliste kontrollierbar sind,
- Reichweitenbeitrag und Betreuungspflichten festgehalten werden,
- Zwischen- und Abschlussbewertung funktionieren,
- ein Tarifuebergang nur nach ausdruecklicher Zustimmung erfolgt,
- das kostenlose Pilotende technisch und organisatorisch durchsetzbar ist.

### Nicht parallel starten

- keine breite aktive Partneransprache vor geschlossenem End-to-End-Prozess,
- keine zweite Startpartner-Variante oder andere Pilotlaufzeit,
- kein offener kostenloser Anbieterzugang,
- keine automatische kostenpflichtige Umwandlung,
- keine neue allgemeine UI-Polish-Runde ohne konkreten Funnel-Befund.
<!-- === END BLOCK: ROADMAP_STARTPARTNER_GROWTH_PILOT_IMPLEMENTATION_2026_07_18 === -->

<!-- === BEGIN BLOCK: ROADMAP_COMMERCIAL_FUNNEL_PHASE_2026_07_06 | Zweck: priorisiert den final validierten Tipp-/Startpartner-Funnel nach Event-Impact-Backbone und Dashboard-Cockpit; Umfang: Tipp-Kanal ueber Feedback, 6-Monats-Startpartner, bezahlte Service-Abgrenzung, Nicht-Ziele === -->
## Kommerzielle Premium-Phase – Tipp-Kanal, Anbieterwert und Startpartner

Status: aktuelle taktische Top-Prioritaet nach Event-Impact-Backbone und Anbieter-Dashboard-Cockpit. Aeltere Roadmap-Bloecke bleiben Beweisarchiv und Kontext, sind aber nachrangig, wenn sie nicht direkt auf Veranstalter-/Anbietergewinnung, messbaren Mehrwert oder Plattformvollstaendigkeit einzahlen.

### Ziel in einem Satz

Bocholt erleben soll vom guten lokalen Freizeitprodukt zum kuratierten lokalen Sichtbarkeitskanal werden: Nutzer finden relevante Events/Aktivitaeten schneller, Veranstalter und Anbieter bekommen weniger Pflegeaufwand und nachvollziehbaren Nutzen.

### Produktregeln

- Alle veroeffentlichten Inhalte haben denselben oeffentlichen Qualitaetsstandard.
- Keine Zwei-Klassen-Optik zwischen kostenlosen redaktionellen und bezahlten Inhalten.
- Keine gekauften Empfehlungen oder kuenstlichen Top-Platzierungen.
- Kostenlose Hinweise sind erlaubt, damit die Plattform vollstaendiger wird.
- Kostenloser Hinweis bedeutet Tipp ueber Feedback, nicht kostenloser Anbieter-/Einreichungsprozess.
- Bezahlter Mehrwert entsteht ueber Service, Aufwandersparnis, Messbarkeit, dauerhafte Praesenz und komfortable Pflegewege.
- Startpartner sind ein klar befristeter Wirkungsnachweis, kein dauerhaft kostenloses Produkt.
- Werbung, Affiliate und Ticketprovisionen sind spaetere Zusatzoptionen, nicht das Kernmodell.

### Final validierter Zielzustand

#### 1. Kostenloser Tipp-Kanal

Ziel: Fehlende Events, Aktivitaeten oder Orte sollen niedrigschwellig gemeldet werden koennen, ohne ein neues Formularprodukt zu erfinden.

Regeln:

- Nutzung des bestehenden Feedback-Formulars.
- Einstieg ueber konkrete Copy wie `Fehlt ein Event oder eine Aktivitaet?`.
- Ein Link oder kurzer Hinweis muss reichen.
- Keine Veroeffentlichungsgarantie.
- Kein Dashboard.
- Kein Wirkungsbericht.
- Keine Prioritaet.
- Keine dauerhafte Pflegezusage.
- Keine Anbieter-/Payment-/Submission-Logik.

Akzeptanzkriterium: Ein Nutzer kann einen fehlenden Inhalt in wenigen Sekunden als Tipp senden, ohne den Eindruck zu bekommen, er habe einen kostenlosen Eintrag beauftragt.

#### 2. Bezahlter Sichtbarkeitsweg

Ziel: Wer konkret veroeffentlichen, pflegen und Wirkung sehen moechte, nutzt weiterhin den strukturierten bezahlten Weg.

Bestandteile:

- Einzeltermin-Einreichung,
- Mitgliedschaft fuer regelmaessige Veranstaltungen,
- Aktivitaetspraesenz,
- automatische Quellenuebernahme-Pruefung,
- Anbieterbereich,
- Wirkungsmessung,
- Pflege-/Aenderungsweg.

Akzeptanzkriterium: Der bezahlte Weg wirkt nicht wie eine Formulargebuehr, sondern wie ein Service fuer Sichtbarkeit, Pflege und messbare Wirkung.

#### 3. Startpartner-Funnel

Ziel: Passende fruehe Partner gewinnen, ohne das Produkt als Rabattmodell oder dauerhaft kostenlos zu entwerten.

Finale Regel:

- ein Modell,
- 6 Monate voller Anbieterzugang,
- normale oeffentliche Darstellung im Plattformstandard,
- Dashboard mit Wirkung,
- Pflegeweg,
- Auswertung vor dem Uebergang,
- danach regulaerer Tarif.

Begruendung fuer 6 Monate:

- 90 Tage sind fuer Aktivitaeten, Orte, saisonale Angebote und schwankende Nachfrage oft zu knapp.
- 6 Monate reichen fuer Einrichtung, Optimierung, wiederholte Nutzung und belastbarere Wirkung.
- 12 Monate waeren zu nah an dauerhaft kostenloser Anbieterleistung.

Akzeptanzkriterium: Ein potentieller Partner versteht sofort, dass Startpartner ein befristeter Wirkungsnachweis ist und danach in einen regulaeren Anbieterweg uebergeht.

### Priorisierte Workpacks

#### 1. Tipp-Kanal und Startpartner-Funnel scharfziehen

Ziel: Bestehendes Feedback als Tipp-Kanal nutzen und Startpartner als 6-Monats-Anfrageweg sichtbar machen.

Bestandteile:

- globaler Footer-Hinweis `Fehlt ein Event oder eine Aktivitaet?`,
- Veröffentlichungsseiten mit klarer Abgrenzung `Tipp senden` vs. `sichtbar werden`,
- Startpartner-Seite mit 6-Monats-Modell,
- Startpartner-Anfrage ohne Tarifverwirrung,
- Strategie-Doku aktualisiert.

#### 2. Objektgenaue Nutzwertmessung weiter absichern

Ziel: Wirkung nicht behaupten, sondern einfach und glaubwuerdig messen.

Zu messen:

- Detail-Aufrufe,
- Website-/Ticket-Klicks,
- Maps-/Route-Klicks,
- optional Share-Klicks,
- Zeitraum 7/30 Tage und perspektivisch Pilotzeitraum.

Grenze: Keine Behauptung, dass daraus echte Besucher, Umsatz oder Ticketverkaeufe direkt bewiesen sind.

#### 3. Anbieter-Dashboard als Wirkungszentrum halten

Ziel: Das Dashboard beantwortet zuerst `Was bringt mir Bocholt erleben?`, nicht nur `Was ist mein Verwaltungsstatus?`.

Bestandteile:

- veroeffentlichte Inhalte,
- Wirkung letzte 30 Tage,
- Link ansehen/teilen,
- Aenderung melden,
- Absage/Verschiebung melden,
- naechsten Termin einreichen oder Uebernahme pruefen.

#### 4. Verkaufs-/Sichtbar-werden-Seiten weiter auf Ergebnisversprechen pruefen

Ziel: Die oeffentlichen Anbietertexte verkaufen nicht den Prozess, sondern den Nutzen.

Kernbotschaft:

- lokal sichtbar werden,
- professionell und einheitlich dargestellt werden,
- teilbaren Link erhalten,
- in passenden Uebersichten/Suchen gefunden werden,
- Wirkung sehen,
- Pflegeaufwand reduzieren.

Wichtig: Zahlung bleibt keine automatische Veroeffentlichungsgarantie. Redaktionelle Eignung und Plattformqualitaet bleiben fuehrend.

#### 5. Aktivitaetspraesenz als Dauerprodukt weiter ableiten

Nach dem Einzeltermin-Referenzpfad folgt die Aktivitaetspraesenz:

- dauerhafte Detailseite,
- passende Today-/Aktivitaeten-/Filter-Kontexte,
- Nutzwertmessung,
- Aenderungsweg,
- spaeter Location-/Naehe-Modell.

#### 6. Regelmaessige Veranstalter und automatische Uebernahme ausbauen

Spaeterer Haupthebel fuer zahlende Anbieter:

- Quellenprofil je Veranstalter,
- regelmaessige Uebernahme geeigneter Termine,
- erkannte/neue/unklare Termine im Dashboard,
- weniger manuelle Formularpflege,
- monatlicher Wirkungsbericht.

### Parallel laufende Qualitaetssicherung

Diese Punkte bleiben wichtig, sind aber nicht die kommerzielle Hauptbaustelle:

- naechsten regulaeren Weekly-KI-Proof dokumentieren,
- konkrete Visual-Fit-Hinweise aus Reports abarbeiten,
- Browser-Smoke fuer Kernwege gruen halten,
- keine gefreezten UI-Bereiche ohne konkretes Symptom neu oeffnen.

#### Event-Beschreibungen als Premium-Qualitaetsvertrag

Eventbeschreibungen zahlen direkt auf den kommerziellen Premium-Anspruch ein: Eine teilbare Eventseite wirkt nur hochwertig, wenn der Text lokal-redaktionell, freundlich-serioes und quellenbasiert ist.

Prioritaet im laufenden Commercial-Track:

1. Bestehende aktive/future Events duerfen keine Quellenleaks, PDF-Hinweise, KI-Floskeln oder Werbesprache im Public-Feed zeigen.
2. Weekly-KI und Manual-Intake muessen schlechte Beschreibungen upstream verhindern.
3. Content-Quality-Audit muss Description-Issues typisiert in den Search-Feedback-Loop geben.
4. Kuratierte Overrides sind nur Bruecke fuer Bestandsdaten; Ziel bleibt Korrektur im Sheet bzw. saubere KI-Ausgabe.

Massgeblich: `EVENT_DESCRIPTION_STANDARD.md`.

### Nicht als naechstes starten

- keine grosse allgemeine UI-Neugestaltung,
- keine Zwei-Klassen-Darstellung fuer Premium-Inhalte,
- keine gekauften Today-Empfehlungen,
- keine harte Paywall fuer einfache Tipps,
- kein neues kostenloses Vorschlagsformular,
- keine Display-Werbung als Kernmodell,
- keine Ticketing-Plattform bauen,
- keine SEO-Massenrunde ohne Detailseiten und Tracking,
- keine neue Bildproduktion ohne konkreten Gap,
- keine grosse CSS-/JS-Sanierung ohne Produktkontext.

### Naechster konkreter Haupt-Workpack

```text
Workpack: Tipp-Kanal und 6-Monats-Startpartner-Funnel
- Feedback-Formular als Tipp-Kanal sichtbar machen
- kein neues Vorschlagsformular bauen
- Startpartner-Seite mit 6-Monats-Modell
- Veröffentlichungsseiten klar abgrenzen: Tipp / Sichtbarkeit / Startpartner
- Strategie und Roadmap aktualisieren
```
<!-- === END BLOCK: ROADMAP_COMMERCIAL_FUNNEL_PHASE_2026_07_06 === -->

<!-- === BEGIN BLOCK: ROADMAP_WEEKLY_KI_FEEDBACK_LOOP_PROOF_2026_07_02 | Zweck: setzt den aktuellen taktischen Fortsetzungspunkt fuer die selbstlernende KI-Suche; Umfang: erledigte E2E-Punkte, naechster regulärer Proof, keine manuelle Kosten-Ausloesung === -->
## Weekly-KI / Self-Learning Search – E2E bestanden, naechster Proof regulär

Status: E2E-Liveprozess bestanden; kein manueller kostenpflichtiger Weekly-KI-Lauf als Zusatztest.

### Abgeschlossen

- Weekly-KI -> Manual KI Event Intake -> Live-Inbox ist auf `main` bewiesen.
- Ablehnen mit neuem fachlichem Grund `Termin liegt in der Vergangenheit` funktioniert live nach Apps-Script-Fix.
- Abgelaufene Kandidaten werden in der Inbox blockiert und koennen nicht mehr übernommen werden.
- Ein gültiger Kandidat wurde übernommen und deployed.
- Inbox Cleanup und Daily Content Quality Audit liefen nach dem Prozess ohne harte Fehler.
- Self-Learning-Architektur ist nicht als unkontrolliertes Prompt-Wachstum gebaut, sondern als begrenzter Kontext:
  - Typisierte Feedbackklassen.
  - Begrenzter Regelpool.
  - Begrenzte Prompt-Regeln.
  - Keine automatische Regelbuch-Mutation.
  - Keine automatische fachliche Datenkorrektur durch KI.
  - Deterministischer Datums-/Vorlauf-Guard als Sicherheitsnetz.

### Aktueller offener Beweis

Der naechste regulaere Weekly-Lauf muss belegen, dass die Upstream-Suche nach dem Fix wirklich sauberer wird:

- Keine Vergangenheit in `data/inbox_manual.json`.
- Keine normalen same-day-/next-day-Kandidaten.
- Falls das Modell solche Rohkandidaten liefert: Drop-Reason `past_event` oder `too_short_notice`.
- Feedbackregeln werden sichtbar geladen und angewendet.
- Feedback bleibt klein, typisiert und nicht aufgebläht.
- Neue Kandidaten behalten belastbare Quellen, saubere Eventdaten und Visual-Handoff.

### Pruefmaterial beim naechsten regulaeren Lauf

Nach dem regulaeren Lauf pruefen:

1. `weekly-event-diagnostics.zip`.
2. Weekly-Run-Log, insbesondere `Run weekly KI websearch to manual inbox`.
3. Live-Inbox-Screenshot, falls Kandidaten erzeugt wurden.
4. Optional danach Daily Content Quality Report.

### Nicht tun

- Weekly-KI nicht manuell nur fuer diesen Proof ausloesen, weil der Lauf kostenpflichtig ist.
- Keine neue Mechanik erfinden, bevor das naechste Diagnostics-Artefakt einen echten Fehlbefund zeigt.
- Keine Feedbacksignale als lange Einzelfallliste in den Prompt kippen.
- Keine Audit-/KI-Ergebnisse automatisch in fachliche Daten schreiben.

<!-- === END BLOCK: ROADMAP_WEEKLY_KI_FEEDBACK_LOOP_PROOF_2026_07_02 === -->

<!-- === BEGIN BLOCK: ROADMAP_ACTIVITY_FAVORITES_PREMIUM_2026_06_30 | Zweck: schaerft Nutzerbindung als Premium-Zielzustand ohne Account-Schwere und ohne Event-Favoriten; Umfang: Activity-Favoriten, lokale Priorisierung, Card-Parity === -->
## P1 Nutzerbindung Premium – Activity-Favoriten

Status: umgesetzt und auf Staging fachlich/visuell abgenommen.

Zielzustand: Bocholt erleben bietet lokale Aktivitaets-Favoriten als leicht verstaendliches Premium-Nutzerfeature. Nutzer markieren wiederkehrend interessante Aktivitaeten per Herz; die App priorisiert diese Aktivitaeten automatisch oben. Favoriten sind keine Inhaltsfilter, erscheinen nicht als Schnellfilter-Pill und erzeugen keine eigene Feed-Section.

Entscheidungen:

- Begriff und Icon: `Favoriten` mit Herz.
- Geltung: nur Aktivitaeten, nicht Events. Events bleiben ueber Kalender-/Terminaktionen geloest.
- Kein Login, kein Konto, kein Sync, kein Backend.
- Speicherung: lokal im Browser/PWA via bestehendem Nutzerpraeferenz-Speicher.
- Datenschutz: keine Cookies, keine Serveruebertragung, kein Tracking; Hinweis in `/datenschutz/`.
- Mobile UI: Activity-Cards werden in der Bildgeometrie an Event-Cards angeglichen, damit Seitenwechsel nicht wie ein Layoutsprung wirkt.
- Mobile Schnellfilter: als einzeilige horizontale Premium-Chip-Rail; Desktop bleibt unveraendert.
<!-- === END BLOCK: ROADMAP_ACTIVITY_FAVORITES_PREMIUM_2026_06_30 === -->