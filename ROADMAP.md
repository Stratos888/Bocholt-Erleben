# Roadmap – Bocholt erleben

Stand: 2026-07-22  
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
- es gibt keine zweite SEO-Rankinglogik und keinen Bot-Sonderpfad;
- Event-JSON-LD liegt nur auf geeigneten eindeutigen Detailseiten;
- Offers werden ausschließlich aus belegten Eintritts- und Ticketdaten erzeugt;
- unbekannte Eintrittslagen führen nicht zu erfundenen Preisen, Verfügbarkeiten oder Ticket-URLs;
- die zusätzlichen Hero-Links wurden vollständig entfernt und der ursprüngliche Seitencharakter wiederhergestellt.

Technischer Abschluss auf Staging:

- SHA `2ee2990bb06ee03ac8248e47150bb12de8a1c74e`;
- PR Gate #219 grün;
- normaler Staging-Deploy grün;
- mobile Sichtprüfung bei 327 × 779 Pixeln grün.

Getrennte Nacharbeiten:

- finalen Staging-Stand regulär nach `main` veröffentlichen;
- read-only Live-E6 durchführen;
- Search-Console-Validierung des `offers`-Befunds starten;
- erste Wirkung nach mindestens 14 Tagen und führend nach 28 Tagen bewerten.

Eine Rankingverbesserung wird nicht aus CI, einem einzelnen Tag oder dem bloßen technischen Abschluss abgeleitet.

Referenzen:

- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`;
- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`.

## 2. Bereites nächstes Produktziel

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

## 3. Danach folgende Produktketten

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

## 4. Laufende Betriebsaufgaben

- Deploy-, HTTP- und Browser-Smokes grün halten;
- regulären Weekly-KI-Lauf auf Vorlauf, Drop-Gründe und begrenztes Feedback prüfen;
- Search-, Content- und Visual-Reports als Evidence und priorisierte Ausnahmen nutzen;
- Suchwirkung nach SEO-Änderungen mit Baseline sowie 14-/28-Tage-Fenstern bewerten;
- Search-Console-Validierung des Event-/Offer-Befunds getrennt vom Ranking beobachten;
- keine Erfolgsbehauptung aus einzelnen Tagen oder bloßen CI-Markern.

## 5. Nicht als nächstes starten

- allgemeine UI-Neugestaltung ohne konkreten Fehler;
- zusätzliche Workflow-, Observer- oder Dokumentationsschichten ohne neuen belegten Bedarf;
- parallele schreibende Control-Center-Workpacks;
- gekaufte Empfehlungen oder Zwei-Klassen-Darstellung;
- Display-Werbung als Kernmodell;
- neues kostenloses Anbieterformular neben Tipp- und bezahltem Weg;
- große SEO-Massenrunde statt konkreter datenbasierter Nachprüfung;
- neues Framework ohne belegte Notwendigkeit;
- neue Bildproduktion ohne konkreten Visual-Gap;
- große CSS-/JS-Sanierung ohne Produkt- und Ownerbezug.

## 6. Aktivierungsregel

Ein Produktziel wird erst aktiver Workpack, wenn:

- aktueller Repo-, Runtime- und Datenstand geprüft wurde;
- Ziel, Scope, Risiko, Owner, Evidence und Rollback dokumentiert sind;
- es keine konkurrierende zentrale Änderung gibt;
- `CURRENT_WORKPACK.md` genau auf diesen Workpack gesetzt wurde.
