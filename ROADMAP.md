# Roadmap – Bocholt erleben

Stand: 2026-07-24  
Status: priorisierte Produktziele; kein operativer Workpack-Status

Der operative Schritt steht ausschließlich im genau einen offenen GitHub-Issue mit `[ACTIVE WORKPACK]`. `docs/workpacks/active/CURRENT_WORKPACK.md` ist der statusfreie Router. Diese Roadmap nennt Produktziele und Kandidaten, keine laufenden SHAs, Gates oder Zwischenstände.

## 1. Zuletzt abgeschlossene Grundlagen

### SEO, strukturierte Daten und statisches Rendering

Erreicht:

- `/` bleibt kuratierter Heute-Einstieg;
- `/events/` ist die vollständige Veranstaltungs-Landingpage;
- `/aktivitaeten/` ist die dauerhafte Freizeit-/Aktivitäts-Landingpage;
- initiales HTML enthält H1, Inhaltskern und crawlbare Hauptlinks;
- JavaScript reichert denselben Ausgangszustand progressiv an;
- Event-JSON-LD erscheint nur auf geeigneten eindeutigen Detailseiten;
- Offers werden ausschließlich aus belegten Eintritts- und Ticketdaten erzeugt;
- Search-Console-Warnungen wurden URL- und quellenbezogen bearbeitet, ohne erfundene Werte.

Dauerhafte Referenzen:

- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`;
- `docs/workpacks/completed/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`;
- `docs/workpacks/completed/SEO-STRUCTURED-DATA-search-console-warnings-2026-07-22.md`.

Zeitversetzte Google-Neubewertung bleibt laufender Betrieb und öffnet abgeschlossene Workpacks nicht erneut.

### Responsive Event-Grid und Releasezuverlässigkeit

Erreicht:

- eine Spalte bis `1099.98 CSS px`;
- zwei Spalten ab `1100 CSS px`;
- geordneter und kohärenter Asset-/HTML-/Marker-/Service-Worker-/Manifest-Cutover;
- gehärtete STRATO-SFTP-Phasen;
- Warm-Service-Worker- und Live-Grid-Nachweis;
- automatische Auffindbarkeit von Push-Deploys über Commitstatus.

### Arbeitsprozess und Dokumentation

Erreicht:

- genau ein aktives Workpack-Issue als operativer Owner;
- maschinenlesbarer, vorab eingefrorener PR-Scope;
- ein Required Check `PR Gate`;
- checkout-neutrale synthetische Browser-Evidence;
- genau ein Repository-Schreiber;
- systematische Dokumentations-Reconciliation;
- keine ZIP- oder Downloadartefakte als Nutzerlieferung ohne ausdrücklichen Auftrag.

Prozessoptimierung wird nur bei einem neuen konkret belegten Engpass wieder als Workpack aktiviert.

## 2. Aktuelle Produktkandidaten

Die nächste Umsetzung wird nicht aus einer veralteten Repository-Queue übernommen. Vor Aktivierung werden aktueller Repo-, Runtime-, Daten- und Produktstand read-only neu bewertet.

### Mobile Ausnahmeprüfung kompakter priorisieren

Problem:

- auf kleinen Bildschirmen stehen Status, Vergleich, Evidence und mehrere Aktionen zu lang und nahezu gleichrangig untereinander;
- die eigentliche Entscheidung ist nicht schnell genug erfassbar.

Ziel:

- Entscheidung und Kandidat-gegen-Bestand-Vergleich zuerst;
- genau eine klare Hauptaktion;
- Neben-, Warte- und destruktive Aktionen sauber getrennt;
- vollständige Evidence weiterhin erreichbar;
- fachliche Identitäts-, Persistenz- und Desktopverträge unverändert.

Vor Umsetzung sind aktueller mobiler Referenzzustand, zulässige sichtbare Änderungen, Viewports und alle Reviewzustände zu schließen.

### Startpartner-Wachstumspilot operationalisieren

Problem:

Die öffentliche Akquiseoberfläche und der fachliche Zielzustand sind vorhanden, aber der interne End-to-End-Lebenszyklus von Kandidat bis Pilotabschluss ist noch nicht vollständig umgesetzt.

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

Referenz: `docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md`.

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
- keine allgemeine Bildproduktion ohne belegten Bedarf.

### Nutzerbindung gezielt ausbauen

- bestehende lokale Activity-Favoriten stabil halten;
- keine schwere Account-/Sync-Logik ohne belegten Bedarf;
- neue Personalisierung nur mit klarem Nutzerwert und Datenschutzvertrag.

## 4. Laufende Betriebsaufgaben

- Deploy-, HTTP- und Browser-Smokes grün halten;
- regulären Weekly-KI-Lauf auf Vorlauf, Drop-Gründe und begrenztes Feedback prüfen;
- Search-, Content- und Visual-Reports als Evidence und priorisierte Ausnahmen nutzen;
- Suchwirkung nach SEO-Änderungen mit 14-/28-Tage-Fenstern bewerten;
- keine Erfolgsbehauptung aus einzelnen Tagen oder bloßen CI-Markern.

## 5. Nicht ohne neuen belegten Bedarf starten

- allgemeine UI-Neugestaltung;
- zusätzliche Workflow-, Observer- oder Dokumentationsschichten;
- parallele schreibende Workpacks an denselben Ownern;
- gekaufte Empfehlungen oder Zwei-Klassen-Darstellung;
- Display-Werbung als Kernmodell;
- neues kostenloses Anbieterformular neben Tipp- und bezahltem Weg;
- große SEO-Massenrunde;
- neues Framework;
- neue Bildproduktion ohne konkreten Visual-Gap;
- große CSS-/JS-Sanierung ohne Produkt- und Ownerbezug.

## 6. Aktivierungsregel

Ein Workpack wird erst aktiv, wenn:

- aktueller Repo-, Runtime- und Datenstand geprüft wurde;
- Produktwirkung und Priorität gegenüber den anderen Kandidaten bestätigt sind;
- Ziel, Scope, Risiko, Owner, Evidence, Dokumentationsdelta und Rollback dokumentiert sind;
- es keine konkurrierende zentrale Änderung gibt;
- genau ein offenes GitHub-Issue den Marker `[ACTIVE WORKPACK]` trägt;
- Gate A geschlossen ist.

Die Aktivierung verändert keine Roadmap- oder Routerdatei. Operativer Fortschritt bleibt vollständig im Issue.
