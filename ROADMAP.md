# Roadmap – Bocholt erleben

Stand: 2026-07-20  
Status: priorisierte Produktziele; kein operativer Workpack-Status

Der aktuelle technische Schritt steht in `docs/workpacks/active/CURRENT_WORKPACK.md`. Die technische Warteschlange steht in `docs/workpacks/queued/INDEX.md`.

Die allgemeine Prozess-, Workflow- und Dokumentationsoptimierung ist abgeschlossen. Sie wird nur bei einem neuen konkret belegten Bedarf erneut als Workpack aktiviert.

## 1. Bereite Produktziele

### A. Search-Intent und statische Renderingbasis

Problem: Die Today-Home bietet guten Produktnutzen, liefert im initialen HTML aber zu wenig Suchsemantik und crawlbare Einstiege. Gleichzeitig hat die historisch starke Homepage ihre frühere Veranstaltungsintention verloren.

Ziel:

- `/` bleibt kuratierter Heute-Einstieg;
- `/events/` bleibt vollständige Veranstaltungs-Landingpage;
- `/aktivitaeten/` bleibt dauerhafte Freizeit-/Aktivitäts-Landingpage;
- initiales HTML enthält sinnvolle H1, Copy, Links und einen kleinen Inhaltskern;
- JavaScript reichert denselben Ausgangszustand progressiv an;
- keine zweite SEO-Rankinglogik und kein Bot-Sonderpfad.

Referenzen:

- `docs/decisions/2026-07-18-search-intent-und-static-rendering.md`
- `docs/workpacks/queued/SEO-RECOVERY-search-intent-static-rendering-2026-07-18.md`

### B. Startpartner-Wachstumspilot operationalisieren

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

## 2. Danach folgende Produktketten

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

## 3. Laufende Betriebsaufgaben

- Deploy-, HTTP- und Browser-Smokes grün halten;
- regulären Weekly-KI-Lauf auf Vorlauf, Drop-Gründe und begrenztes Feedback prüfen;
- Search-, Content- und Visual-Reports als Evidence und priorisierte Ausnahmen nutzen;
- Suchwirkung nach SEO-Änderungen mit Baseline sowie 14-/28-Tage-Fenstern bewerten;
- keine Erfolgsbehauptung aus einzelnen Tagen oder bloßen CI-Markern.

## 4. Nicht als nächstes starten

- allgemeine UI-Neugestaltung ohne konkreten Fehler;
- zusätzliche Workflow-, Observer- oder Dokumentationsschichten ohne neuen belegten Bedarf;
- parallele schreibende Control-Center-Workpacks;
- gekaufte Empfehlungen oder Zwei-Klassen-Darstellung;
- Display-Werbung als Kernmodell;
- neues kostenloses Anbieterformular neben Tipp- und bezahltem Weg;
- große SEO-Massenrunde statt klarer Intent-/Renderingkorrektur;
- neues Framework ohne belegte Notwendigkeit;
- neue Bildproduktion ohne konkreten Visual-Gap;
- große CSS-/JS-Sanierung ohne Produkt- und Ownerbezug.

## 5. Aktivierungsregel

Ein Produktziel wird erst aktiver Workpack, wenn:

- aktueller Repo-, Runtime- und Datenstand geprüft wurde;
- Ziel, Scope, Risiko, Owner, Evidence und Rollback dokumentiert sind;
- es keine konkurrierende zentrale Änderung gibt;
- `CURRENT_WORKPACK.md` genau auf diesen Workpack gesetzt wurde.
