# Projekt: Bocholt erleben

<!-- BEGIN: PROJECT_CONTRACT -->
## Zweck
Lokale Event-Plattform für Bocholt.
Fokus: Übersicht, Qualität, Vertrauen, kein Werbechaos.

## Feste Rahmenbedingungen
- Hosting: STRATO
- Source of Truth: GitHub Repository
- Deployment: statische Dateien
- Architektur: Vanilla HTML / CSS / JS
- PWA mit Service Worker
- Kein Backend (Stand jetzt)
- Keine Frameworks

## Arbeits- & Änderungsregeln (verbindlich)
- Eine Datei pro Schritt (HTML ≠ JS ≠ CSS strikt getrennt)
- Konsolidierungs-Modus: letzter geposteter Stand einer Datei ist vollständig
- Änderungen nur als: Datei + exakte BEGIN-Zeile + exakte END-Zeile + Ersatzblock separat
- Keine Vermutungen: wenn etwas nicht eindeutig aus dem Stand hervorgeht → STOPP, erst klären
- Jede Änderung kommt mit Checkliste (sichtbar + manuell prüfbar)

## Design- & UX-Regeln (gleichrangig)
- Header ist visuelle Referenz (alles darunter darf nie lauter sein)
- Ein UI-System: Header, Filter, Detail-Panel sprechen eine Sprache
- Zwischenzustände sind erlaubt, aber bewusst (Neutral/Grau nie als „final“ verkaufen)
- Ziel: Top-App-Niveau (iOS/Linear/System-UIs)

## Architektur / Ebenentrennung (Contract)
### Begriffe (fix)
- Top-Stack: Sticky-Bereich, enthält nur Trigger
- Pill: reiner Trigger (öffnet Sheet), keine Optionsliste
- Sheet: isoliertes Overlay/Bottom-Sheet (Optionen)
- Option: konkreter Wert (Zeit/Kategorie)
- Filter-State: aktuelle Auswahl (Zeit + Kategorie + Suche)
- Event-Liste: Renderer, kennt keine Sheets

### Top-Stack-Regel
Oben bleiben immer nur:
- Zeit-Pill
- Kategorie-Pill
- Reset

Keine Optionen, keine Listen, keine Karten.

### Sheet-Regel
Sheet ist:
- logisch isoliert
- per JS steuerbar
- per Overlay + ESC + ❌ schließbar
- mentales Modell identisch zum Detail-Panel

## Datei-Verantwortungen (SOLL / verbindlich)
- js/main.js
  - Boot, Events laden, Module verbinden (kein Filter-State, keine Sheet-Logik)
- js/filter.js
  - EINZIGER Owner von Filter-State + Sheet-UI + Filter anwenden
- js/events.js
  - NUR Renderer für Event Cards (kein Filter-State, kein Filter-UI Wiring)
- js/details.js
  - DetailPanel
- js/locations-modal.js
  - Locations Modal
- css/style.css
  - Darstellung (keine Logik)

## Block-Markierungen (Standard)
HTML:
- <!-- BEGIN: NAME -->
- <!-- END: NAME -->

JS/CSS:
- // BEGIN: NAME
- // END: NAME

Regeln:
- 2–4 Worte, GROSS, Unterstrich
- keine Varianten (ein Name = ein Block)

## Aktueller Fokus
- Filter-System strukturell stabilisieren:
  - Doppelzuständigkeit entfernen (events.js vs filter.js)
  - klarer Contract zwischen DOM / JS / CSS
<!-- END: PROJECT_CONTRACT -->

