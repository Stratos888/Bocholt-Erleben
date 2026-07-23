# Roadmap – Bocholt erleben

Stand: 2026-07-23
Status: priorisierte Produktziele; kein operativer Workpack-Status

Der aktuelle technische Schritt steht in `docs/workpacks/active/CURRENT_WORKPACK.md`. Die technische Warteschlange steht in `docs/workpacks/queued/INDEX.md`.

Die allgemeine Prozess-, Workflow- und Dokumentationsoptimierung ist abgeschlossen. Sie wird nur bei einem neuen konkret belegten Bedarf erneut als Workpack aktiviert.

## 1. Abgeschlossen – Wirkungsmessung läuft

### SEO Recovery: Search-Intent und statische Renderingbasis

Erreicht:

- `/` bleibt kuratierter Heute-Einstieg;
- `/events/` ist die vollständige Veranstaltungs-Landingpage;
- `/aktivitaeten/` ist die dauerhafte Freizeit-/Aktivitäts-Landingpage;
- das tatsächliche initiale HTML enthält H1, sinnvollen Inhaltskern und crawlbare Hauptlinks;
- JavaScript reichert denselben Ausgangszustand progressiv an;
- Build und Browser verwenden dieselbe neutrale Grundauswahl;
- Event-JSON-LD liegt nur auf geeigneten eindeutigen Detailseiten;
- Offers werden ausschließlich aus belegten Eintritts- und Ticketdaten erzeugt;
- unbekannte Eintrittslagen führen nicht zu erfundenen Preisen, Verfügbarkeiten oder Ticket-URLs;
- Staging-E3 und Live-E6 sind geschlossen;
- die zusätzlichen Hero-Links wurden vollständig entfernt.

Zeitversetzte Nacharbeit:

- erste SEO-Wirkung nach mindestens 14 Tagen und führend nach 28 Tagen bewerten.

Eine Rankingverbesserung wird nicht aus CI, einem einzelnen Tag oder dem bloßen technischen Abschluss abgeleitet.

Referenzen:

- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`;
- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`.

## 2. Abgeschlossen – Structured-Data-Reparatur

### SEO Structured Data – Search-Console-Warnungen

Problem:

Google Search Console zeigt nach dem abgeschlossenen SEO-Release weiterhin nicht kritische Warnungen zu `performer`, `organizer`, `offers`, `price`, `priceCurrency` und `validFrom`. Der sichtbare Search-Console-Stand kann historisches Markup, bewusst fehlende optionale Werte, reale Datenlücken oder technische Mappingfehler enthalten. Diese Ursachen dürfen nicht vermischt werden.

Ziel:

- alle Warnungsklassen vollständig auf betroffene URLs auflösen;
- je URL aktuellen Live-Crawlstand, ausgeliefertes JSON-LD, sichtbaren Seiteninhalt und kanonische Quelldaten vergleichen;
- jede Warnung als historisch, bewusst akzeptiert, Datenlücke oder technischer Fehler klassifizieren;
- nur belegte Fehler minimal korrigieren;
- keine Organizer-, Performer-, Preis-, Währungs-, `validFrom`-, Availability- oder Ticketwerte erfinden;
- nur sachlich geeignete Search-Console-Validierungen starten.

Bekannte Ausgangsevidence vom 2026-07-22:

- `performer` fehlt: 2 Elemente;
- `organizer` fehlt: 2 Elemente;
- `priceCurrency` fehlt in `offers`: 1 Element;
- `validFrom` fehlt in `offers`: 1 Element;
- `price` fehlt in `offers`: 1 Element;
- `offers` fehlt: 1 Element;
- bekannte Beispiel-URL: `https://bocholt-erleben.de/events/2-bocholter-vereinsmesse-in-den-shopping-arkaden-2026-09-27/`.

Der Workpack wurde über PRs #168 bis #170 abgeschlossen und mit Main-SHA
`eb5e0f87199d03879d8ae62085e2ae7a52bdf252` veröffentlicht. Der abgeschlossene
SEO-Recovery-Workpack bleibt geschlossen.

Referenz:

- `docs/workpacks/queued/SEO-STRUCTURED-DATA-search-console-warnings-2026-07-22.md`.

## 3. Nächster vorbereiteter Produkt-Workpack

### Responsive Eventdarstellung

Issue #171 bleibt `QUEUED`. Der fachliche Scope wird durch die
Arbeitsprozess-Härtung aus Issue #172 weder umgesetzt noch erweitert.

## 4. Danach mögliches Produktziel

### Startpartner-Wachstumspilot operationalisieren

Problem: Öffentliche Akquiseoberfläche und fachlicher Zielzustand sind vorhanden, aber der interne End-to-End-Lebenszyklus von Kandidat bis Pilotabschluss ist noch nicht vollständig umgesetzt.

Ziel:

```text
Kandidat
-> Qualifizierung
-> Aufnahme oder Warteliste/Ablehnung
-> Onboarding und Aktivierung
-> sechsmonatiger Pilot
-> Zwischen- und Abschlussauswertung
-> ausdrückliche Tarifentscheidung oder geordnetes Ende
```

Verbindlich:

- Inbound und gezielte Ansprache nutzen denselben Prozess;
- maximal acht gleichzeitig aktive Partner in der ersten Kohorte;
- Kapazitätsstopp bei etwa 80 Prozent;
- sechs Monate ab tatsächlicher Aktivierung;
- keine automatische kostenpflichtige Umwandlung;
- keine breite Akquise vor geschlossenem E2E-Prozess.

Referenz:

`docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md`

## 5. Danach folgende Produktketten

### Anbieterwert weiter stärken

- objektgenaue Wirkung verständlich und belastbar halten;
- Änderungen, Absagen und Pflege vereinfachen;
- regelmäßige Quellenübernahme als Service ausbauen;
- Aktivitätspräsenz als Dauerprodukt weiterentwickeln.

### Content- und Visualqualität ausnahmebasiert sichern

- schlechte Beschreibungen upstream verhindern;
- unsichere Quellen und Fakten als typisierte Reviewfälle führen;
- konkrete Visual-Gaps gezielt lösen;
- keine neue allgemeine Bildproduktion ohne belegten Bedarf.

### Nutzerbindung gezielt ausbauen

- bestehende lokale Activity-Favoriten stabil halten;
- keine schwere Account-/Sync-Logik ohne belegten Bedarf;
- neue Personalisierung nur mit klarem Nutzerwert und Datenschutzvertrag.

## 6. Laufende Betriebsaufgaben

- Deploy-, HTTP- und Browser-Smokes grün halten;
- regulären Weekly-KI-Lauf auf Vorlauf, Drop-Gründe und begrenztes Feedback prüfen;
- Search-, Content- und Visual-Reports als Evidence und priorisierte Ausnahmen nutzen;
- Suchwirkung nach SEO-Änderungen mit 14-/28-Tage-Fenstern bewerten;
- keine Erfolgsbehauptung aus einzelnen Tagen oder bloßen CI-Markern.

## 7. Nicht als nächstes starten

- allgemeine UI-Neugestaltung ohne konkreten Fehler;
- zusätzliche Workflow-, Observer- oder Dokumentationsschichten ohne neuen belegten Bedarf;
- parallele schreibende Control-Center-Workpacks;
- gekaufte Empfehlungen oder Zwei-Klassen-Darstellung;
- Display-Werbung als Kernmodell;
- neues kostenloses Anbieterformular neben Tipp- und bezahltem Weg;
- große SEO-Massenrunde statt URL-genauer Structured-Data-Prüfung;
- neues Framework ohne belegte Notwendigkeit;
- neue Bildproduktion ohne konkreten Visual-Gap;
- große CSS-/JS-Sanierung ohne Produkt- und Ownerbezug.

## 8. Aktivierungsregel

Ein Workpack wird erst aktiv, wenn:

- aktueller Repo-, Runtime- und Datenstand geprüft wurde;
- Ziel, Scope, Risiko, Owner, Evidence und Rollback dokumentiert sind;
- es keine konkurrierende zentrale Änderung gibt;
- `CURRENT_WORKPACK.md` genau auf diesen Workpack gesetzt wurde.
