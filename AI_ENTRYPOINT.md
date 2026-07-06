# Bocholt erleben – KI-Einstieg

Arbeitsbranch: `staging`.

Diese Datei ist der schnelle Einstieg fuer KI-Arbeit am Repository. Sie macht die GitHub-first-Arbeitsweise auffindbar und ausfuehrbar.

## Grundregel

Bei jeder Repo-Aufgabe zuerst den aktuellen GitHub-Stand von `Stratos888/Bocholt-Erleben` auf Branch `staging` pruefen, sofern der GitHub-Connector verfuegbar ist.

Nicht aus Memory, alten Chatstaenden oder alten ZIPs als Quelle der Wahrheit arbeiten. Memory und alte Chats sind nur Kontext.

## Quellenhierarchie

1. GitHub-Connector: aktueller Branch `staging`.
2. Aktiver Codespace- oder lokaler Git-Stand, wenn der Nutzer Terminalausgaben aus einer laufenden Arbeitskopie liefert.
3. ZIP-Snapshot nur als Fallback.
4. Memory, fruehere Chats und alte Artefakte nur als Kontext.

## Arbeitsmodi

### G1 GitHub Read

Standard fuer Analyse, Review, Ursachenpruefung und Planung.

Pflicht:

- `staging` zuerst lesen.
- Betroffene Dateien nennen.
- Unsicherheit offen benennen.

### G2 Direkter Staging-Commit

Erlaubt nach ausdruecklicher Nutzerfreigabe fuer kleine, klar begrenzte und reversible Aenderungen.

Geeignet fuer:

- kleine Doku-Aktualisierungen,
- kleine Copy-, HTML- oder CSS-Korrekturen,
- kleine statische JS-Guards,
- kleine Konfigurations- oder Audit-Haertungen mit niedrigem Risiko.

Nach einem Direktcommit nennt die KI:

- gelesene Baseline,
- geaenderte Dateien,
- Commit-SHA,
- Diff-Zusammenfassung,
- erwartete Staging-Pruefung,
- Ruecknahmeweg per Revert-Commit.

### G3 AI-Branch und Draft-PR gegen `staging`

Standard fuer mittlere oder breitere Implementierungen.

Geeignet fuer:

- mehrere Dateien,
- neue Struktur,
- Dashboard-, Inbox- oder Tracking-Aenderungen,
- UI- oder JS-Aenderungen mit hoeherem Regressionsrisiko.

### G4 Codespace oder lokale Ausfuehrung

Codespace ist nicht mehr Standard fuer jede Repo-Arbeit. Codespace ist die Ausfuehrungs-, Preview-, Smoke-, Build- und Debug-Umgebung.

Nutzen bei:

- Browser-Smoke oder Playwright,
- Python-, Node- oder PHP-Ausfuehrung,
- Build- oder Deploy-Skripten,
- generierten Artefakten,
- Backend, API, DB, Stripe oder Mail,
- Service Worker, Cache oder Deploy-Workflow,
- grossen Refactorings,
- Asset- oder Bildverarbeitung.

### G5 ZIP-Fallback

ZIP ist nur noch Fallback, wenn GitHub nicht verfuegbar ist oder ein bewusster Snapshot-Vergleich gebraucht wird.

## Visueller Standard-Loop

Fuer kleine, owner-file-klare UI-, CSS- oder statische JS-Aenderungen gilt:

1. GitHub/staging lesen.
2. Owner-Datei und Risiko nennen.
3. Nach Nutzerfreigabe direkt auf `staging` committen.
4. Staging-Deploy als echte visuelle Pruefumgebung nutzen.
5. Nutzer prueft Screenshot oder Device.
6. Maximal ein gezielter Polish-Commit.
7. Wenn danach weitere Korrekturen noetig sind: stoppen, neu bewerten, nicht weiterflicken.

Codespace Preview ist fuer visuelle Arbeit nicht mehr der Standard.

## Harte Grenzen

- Keine direkten KI-Schreibaktionen auf `main`.
- Kein Force-Push.
- Keine Datei-Loeschung ohne ausdrueckliche Freigabe.
- Keine stillen Aenderungen an produktiven Zugangsdaten oder externen Deployments.
- Keine manuellen Aenderungen an generierten Datenartefakten als fachliche Quelle.
- Keine automatischen fachlichen Datenmutationen aus KI- oder Audit-Ergebnissen.
- Keine grossen Multi-Datei-Direktcommits auf `staging`.
- Reverts erfolgen bevorzugt als Revert-Commit.

## Wichtige Steuerdateien

- `MASTER.md` – strategische Steuerung.
- `ROADMAP.md` – taktische Workpacks.
- `ENGINEERING.md` – technische Regeln, Owner und Sicherheitsgrenzen.
- `TEST_STATUS.md` – Proof-Archiv.
- `Produktvertrag.md` – Produktmodell.
- `VISUAL_WORKFLOW.md` – Bild- und Motivarbeit.
- `COMMERCIAL_STRATEGY.md` – Anbieter- und Veranstalterstrategie.
- `EVENT_IMPACT_TRACKING.md` – Nutzwert- und Wirkungsmessung.

## Standardantwort der KI vor Umsetzung

Vor jeder Umsetzung nennt die KI knapp:

- Quelle,
- gelesene Dateien,
- Arbeitsmodus,
- Risiko,
- ob Nutzerfreigabe fuer Schreibaktion erforderlich ist.

## Dokumentationskonflikte

Diese Datei ist der aktuelle Einstiegspunkt fuer die neue GitHub-first-Arbeitsweise. Aeltere Codespace- oder ZIP-first-Formulierungen in `ENGINEERING.md` bleiben als historische Arbeitsregeln lesbar, duerfen aber nicht mehr als Standardprozess interpretiert werden, wenn der GitHub-Connector verfuegbar ist.
