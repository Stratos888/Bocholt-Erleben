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

### Mobile Ausnahmeprüfung in der Steuerzentrale

Erreicht:

- Status, belegter Kandidat-gegen-Bestand-Vergleich und genau eine unmittelbare Entscheidungsebene stehen mobil zuerst;
- Evidence und Nebenaktionen bleiben vollständig erreichbar, aber nachrangig;
- Ready-, Warte-, Konflikt-, Fehler- und destruktive Zustände bleiben fachlich unverändert;
- mobile Viewports `360x780` und `390x844` sowie Desktop `1440x900` sind dauerhaft im PR Gate abgedeckt;
- Staging- und Live-Release wurden vollständig grün abgeschlossen.

Operative Evidence bleibt im abgeschlossenen Issue #128.

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

## 2. Nächste Produktkette: Startpartner-Wachstumspilot

### Problem

Die öffentliche Startpartner-Anfrage und ein strategischer Zielzustand sind vorhanden. Der vollständige interne End-to-End-Prozess ist jedoch noch nicht technisch umgesetzt und darf nicht auf einen bloßen Kandidaten- oder Aufnahmeprozess verkürzt werden.

Noch zu schließen sind zusammenhängend:

```text
Selbstmeldung oder gezielte Ansprache
-> Kandidat und Qualifizierung
-> Kapazität und Aufnahmeentscheidung
-> Pilotbedingungen und ausdrückliche Bestätigung
-> Organizer und kostenlose Pilotberechtigung
-> Onboarding und Aktivierung
-> Inhalte, Quellen und Wirkungsmessung
-> sechsmonatiger Betrieb mit Kontrollpunkten
-> Abschlussauswertung
-> ausdrückliche Tarifentscheidung oder geordnetes Ende
-> Kohorten- und Stop-Regel
```

### Produktziel

Bocholt erleben gewinnt eine kleine Zahl strategisch geeigneter Veranstalter und Anbieter, die:

- hochwertige Inhalte beitragen;
- eigene lokale Zielgruppen aktivieren;
- einen stabilen Pflege- oder Quellenweg besitzen;
- messbaren Anbieterwert erhalten;
- nach sechs Monaten bewusst über einen regulären Anbieterweg entscheiden.

### Verbindliche Produktgrenzen

- Der öffentliche Weg bleibt eine Anfrage und keine automatische Aufnahme.
- Selbstmeldung und aktive Ansprache verwenden denselben fachlichen Kandidatenprozess.
- Eine Anfrage erzeugt weder Anbieteraccount noch Berechtigung noch Zahlung.
- Der kostenlose Pilot startet erst nach ausdrücklicher Aufnahme und bestätigten Pilotbedingungen.
- Der Pilot verwendet keine Stripe-Subscription und benötigt keine Zahlungsart.
- Die kostenlose Leistung wird als befristete Pilotberechtigung mit eindeutigem Serviceumfang geführt.
- Die Laufzeit beträgt sechs Kalendermonate ab belegter Aktivierung.
- Maximal acht Plätze dürfen gleichzeitig belegt sein.
- Der konservative Soft-Stop greift bei sechs belegten Plätzen; geeignete weitere Kandidaten gehen auf die Warteliste.
- Redaktionelle Prüfung und öffentliche Qualitätsparität gelten unverändert.
- Partnerdistribution, Inhalte, Aufwand und Wirkung werden nachvollziehbar gemessen.
- Ein regulärer Tarif entsteht ausschließlich durch neue ausdrückliche Zustimmung.
- Ohne Zustimmung endet die kostenlose Leistung geordnet.
- Keine aktive breite Akquise vor dem vollständigen E2E-Nachweis.

Vollständige Referenz:

`docs/startpartner-wachstumspilot-zielzustand-2026-07-18.md`

### Umsetzungsfolge

Die Produktkette wird in fachlich geschlossenen Gates umgesetzt:

1. Produkt-, Rechts-, Daten- und Cutover-Vertrag;
2. Kandidat und gemeinsamer Intake;
3. Qualifizierung, Entscheidung, Kapazität und Warteliste;
4. Pilotbedingungen, Organizer und kostenlose Pilotberechtigung;
5. Onboarding, Inhalte, Messung und Aktivierung;
6. Betrieb, Kommunikation, Kontrollpunkte und Ausnahmen;
7. Abschluss, ausdrückliche Konversion oder geordnetes Ende;
8. vollständiger synthetischer Staging-E2E-Nachweis;
9. erst danach bewusste Freigabe der ersten echten Partneransprache.

Ein einzelnes frühes Gate ist kein operationalisierter Startpartner-Wachstumspilot.

## 3. Danach folgende Produktketten

### Anbieterwert weiter stärken

- objektgenaue Wirkung verständlich und belastbar halten;
- Änderungen, Absagen und Pflege vereinfachen;
- regelmäßige Quellenübernahme als Service ausbauen;
- Aktivitätspräsenz als Dauerprodukt weiterentwickeln;
- Erkenntnisse aus dem Startpartner-Pilot in Tarif, Leistung und Betreuung überführen.

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
- neues kostenloses Anbieterformular neben Tipp-, Startpartner- und bezahltem Weg;
- aktive Startpartner-Massenakquise vor geschlossenem E2E-Prozess;
- Stripe-Testabo oder automatische kostenpflichtige Verlängerung für Startpartner;
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

Für den Startpartner-Wachstumspiloten gilt zusätzlich:

- jeder Teil-Workpack referenziert den vollständigen E2E-Zielvertrag;
- der Teilumfang und die bewusst noch offenen nachfolgenden Gates werden ausdrücklich benannt;
- keine Teilintegration darf aktive Akquise freigeben;
- externe Nachrichten, Accounts, Berechtigungen, Zahlungen und Veröffentlichungen benötigen eigene kontrollierte Verträge.

Die Aktivierung verändert keine Routerdatei. Operativer Fortschritt bleibt vollständig im Issue.
