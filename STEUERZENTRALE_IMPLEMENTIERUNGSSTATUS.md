# Steuerzentrale – Implementierungsstatus

Stand: 2026-07-10
Status: technisches Fundament vorhanden, Gesamtprodukt noch nicht freigegeben

## 1. Verbindliche Dokumente

- `STEUERZENTRALE_ZIELBILD.md`
- `STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md`
- `STEUERZENTRALE_VORGANGSKATALOG.md`
- `STEUERZENTRALE_INFORMATIONARCHITEKTUR.md`
- `STEUERZENTRALE_SCREENVERTRAG.md`
- `STEUERZENTRALE_ABNAHMEMATRIX.md`
- `STEUERZENTRALE_BACKEND_GAP_ANALYSE.md`

Bei Widersprüchen gelten Zielbild, Gesamtprojekt-Integration und Abnahmematrix in dieser Reihenfolge als führend.

## 2. Bereits umgesetzt

### Technische Grundlage

- kanonisches MySQL-Modell `control_cases` und Verlauf,
- eindeutige Quellreferenzen und Deduplizierung,
- geschützte APIs für Übersicht, Vorgänge, Details und Aktionen,
- Quell-Writeback vor zentralem Abschluss,
- serverseitige Präsentationsnormalisierung,
- deutsche Status- und fallgerechte Aktionsbezeichnungen,
- CSS- und Produktvertrags-Governance,
- mobile Authentifizierung und permanente Navigation.

### Angebundene Prozesse

#### KI-/Sheet-Kandidaten

- Synchronisation,
- Übernahme über bestehenden Eventprozess,
- Ablehnung mit Grund,
- Zurückstellen mit Wiedervorlage.

#### Content-Audit

- nur echte Handlungsfälle in der Queue,
- Korrigieren, Verifizieren, Ablehnen, Zurückstellen,
- Writeback in den führenden Audit-Tab.

#### Anbieter-Einreichungen

- Synchronisation aus MySQL,
- Zahlungsfreigabe,
- wartender Zahlungsstatus,
- erneute Vorlage nach Zahlung,
- finale Veröffentlichung oder Ablehnung.

### Sichtbare Oberfläche

- verdichtete Übersicht,
- fokussierter Bereich `Bearbeiten`,
- Desktop Queue plus Detail,
- Aufgabenanlage,
- erste Event-/Aktivitätssuche,
- erste Ideenerfassung,
- dauerhaft sichtbare Bottom-Navigation.

## 3. Festgestellte Restlücken

### Navigation und Arbeitsmodell

Die derzeit sichtbare Navigation `Übersicht / Bearbeiten / Aufgaben / Verwaltung / Mehr` ist noch nicht der korrigierte Endzustand.

Verbindlicher Endzustand:

1. Übersicht
2. Bearbeiten
3. Arbeit
4. Verwaltung
5. Menü

`Arbeit` vereinigt Aufgaben, Backlog, Ideen und Archiv. `Menü` enthält nur seltene Funktionen.

### Backlog und Projektarbeit

Noch nicht integriert:

- Growth-/Acquisition-Backlog der bisherigen Inbox,
- kuratierte offene Repo-Workpacks,
- relevante technische Schulden,
- geplante Produkt- und Contentverbesserungen.

Der aktuelle Aufgaben-Zähler zeigt deshalb nicht die gesamte reale Projektarbeit.

### Verwaltung

Der Bereich kann derzeit suchen, öffentliche Ansichten öffnen und objektbezogene Aufgaben anlegen. Noch erforderlich:

- stabile Suche ohne Fokusverlust,
- echte Editoren für Events und Aktivitäten,
- Validierung,
- Writeback in die führende Quelle,
- automatische Datenaufbereitung/Deploy,
- Bestätigung der öffentlichen Wirkung,
- Verlauf und Fehlerzustände,
- Absage-/Änderungsprozess.

Die aktuelle Verwaltung ist daher funktional begonnen, aber noch nicht vollständig.

### Systemstatus

Der Dialog zeigt derzeit technische Rohwerte wie `seen` und `upserted`. Er muss auf fachliche Alltagssprache umgestellt werden; Rohdaten gehören in eingeklappte technische Details.

### Aufgaben

Noch fehlen vollständige Übergänge und Ansichten für:

- Als Nächstes,
- wartet,
- blockiert,
- Fälligkeit bearbeiten,
- Backlog,
- Ideen,
- Archiv,
- Umwandlung ohne Doppelkarte.

### Alte Inbox

`/inbox/` bleibt Fallback für noch nicht migrierte Funktionen, insbesondere Growth-Backlog und technische Nebenfunktionen. Noch keine Weiterleitung.

## 4. Bewertung im Gesamtprojektkontext

Die Steuerzentrale ist als zentrales Betreiber-Subsystem sinnvoll und passend, wenn:

- Sheets/MySQL/Repo weiterhin fachlich führend bleiben,
- die Steuerzentrale menschliche Arbeit und Writebacks orchestriert,
- keine parallelen Datenkopien entstehen,
- Änderungen E2E bis zur öffentlichen Darstellung verfolgt werden,
- automatisierbare Fälle nicht als künstliche Aufgaben erscheinen,
- Repo-Arbeit nur kuratiert und nicht ungefiltert gespiegelt wird.

Diese Grundrichtung ist bestätigt.

## 5. Verbindliche nächste Workpacks

### Workpack 1 – Arbeit und Backlog konsolidieren

- Navigation `Aufgaben` -> `Arbeit`, `Mehr` -> `Menü`,
- Growth-Backlog inventarisieren und migrieren,
- Repo-Workpacks/Schulden kuratieren und deduplizieren,
- Aufgaben, Backlog, Ideen und Archiv als ein Lebenszyklus,
- Übergänge ohne Kopien,
- vollständige Status- und Fälligkeitslogik.

### Workpack 2 – Verwaltung E2E fertigstellen

- Suchfokus reparieren,
- Event- und Aktivitätseditoren,
- fachliche Validierung,
- Writeback in führende Quellen,
- automatische Datengenerierung/Deploy,
- öffentliche Wirkung bestätigen,
- Verlauf, Änderung und Absage,
- Fehler als wartet/blockiert führen.

### Workpack 3 – Systemstatus und Menü

- fachlicher Normalzustand,
- Störungen nur mit Auswirkung hervorheben,
- technische Details einklappen,
- Anbieter, Einstellungen, Abmelden und Diagnose sinnvoll platzieren.

### Workpack 4 – Altprozess ablösen

- verbleibende Push-/Deploy-/Backlog-Sonderfunktionen migrieren oder bewusst neu platzieren,
- `/inbox/` für reguläre Arbeit überflüssig machen,
- Weiterleitung erst danach.

### Workpack 5 – Gesamtvalidierung

- automatisierte Tests erweitern,
- reale Writeback- und Deploy-Fehlerfälle,
- Pflicht-Viewports,
- vollständige Staging-Abnahmematrix,
- kontrollierter Main-Merge.

## 6. Freigabestatus

| Bereich | Status |
|---|---|
| Vorgangs- und Writeback-Grundlage | tragfähig |
| Bearbeiten-Queue | funktional begonnen, reale Aktionen weiter prüfen |
| Übersicht | strukturell verbessert, Gesamtpriorisierung weiter validieren |
| Arbeit/Backlog/Ideen | nicht vollständig integriert |
| Verwaltung | begonnen, E2E-Writeback fehlt |
| Systemstatus | nicht produktreif |
| Alte Inbox-Ablösung | offen |
| Main-Merge | nicht zulässig |

## 7. Nächster Schritt

Als Nächstes wird Workpack 1 umgesetzt: vollständige Konsolidierung von Aufgaben, Backlog, Ideen und kuratierter Projektarbeit unter `Arbeit`. Danach folgt die E2E-Fertigstellung der Verwaltung.
