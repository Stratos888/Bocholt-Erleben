<!-- === BEGIN BLOCK: DETAILPANEL_PREMIUM_SYSTEM_CONTRACT_2026_06_10 | Zweck: definiert den app-weiten Premium-Zielvertrag fuer Event- und Activity-Detailpanel vor Live; Umfang: Zielbild, Rollen, Owner, Patch-Reihenfolge, Akzeptanzkriterien === -->
# Detailpanel Premium System Contract

Stand: 2026-06-10  
Branch: `staging`  
Ausgangsstand: `dcc741c`

## Ziel

Das Detailpanel ist ein app-weites Systemelement und muss vor Live als Premium-Komponente konsolidiert werden.

Nicht ausreichend ist:

- das Event-Detailpanel nur fuer Today optisch aufzuwerten,
- Event und Activity kuenstlich identisch zu machen,
- reine CSS-Kosmetik ohne klaren Aktionswert,
- neue Activity-Fotos als Voraussetzung fuer den Livegang zu behandeln.

Zielzustand:

- Cards dienen der schnellen Vorauswahl.
- Detailpanels dienen der vollstaendigen Entscheidung und klaren naechsten Aktion.
- Event- und Activity-Detailpanels wirken app-weit konsistent, aber rollenspezifisch.
- Das Detailpanel passt sichtbar zu Header, Bottom-Navigation, Cards, Icons, Typografie, Actions und ruhiger Premium-Hierarchie der gesamten App.
- Activity-Fotos werden separat nachgezogen und blockieren diesen Detailpanel-Workstream nicht.

## Systemprinzip

Konsistent bedeutet nicht identisch.

Gemeinsam sein muessen:

- Bottom-Sheet-Chrome,
- Overlay-Verhalten,
- Close-Button,
- Handle/Grabber,
- Safe-Area- und Bottom-Navigation-Abstand,
- Panel-Rhythmus,
- Icon-Sprache,
- Link-/Action-Styling,
- Fokus- und Touch-Zustaende,
- ruhige Premium-Tonalitaet.

Rollenspezifisch bleiben muessen:

- Informationsarchitektur,
- Action-Prioritaet,
- Meta-Reihenfolge,
- Detailtiefe,
- Kontextgewichtung zwischen Event, Activity und Today.

## Rollenvertrag

### Event-Detailpanel

Event bedeutet: Terminentscheidung.

Das Event-Detailpanel muss schnell beantworten:

1. Was ist das?
2. Wann ist es?
3. Wo ist es?
4. Wie kann ich es speichern, teilen oder die Quelle oeffnen?

Pflichtwirkung:

- starker Titel,
- Datum/Uhrzeit und Ort sofort erfassbar,
- Bild als atmosphaerischer Kontext, nicht als Selbstzweck,
- Beschreibung gut lesbar,
- Quelle/Website ruhig, aber eindeutig,
- Actionbar mit klarem Nutzen.

Rollenspezifische Actions:

- Kalender,
- Teilen,
- Quelle/Website im Content,
- Ort/Maps ueber Ort-Link oder ruhigen Link, nicht als konkurrierende Haupt-CTA, sofern bereits im Ort integriert.

### Activity-Detailpanel

Activity bedeutet: Ort/Ausflug entscheiden und starten.

Das Activity-Detailpanel muss schnell beantworten:

1. Was kann ich dort machen?
2. Ist es jetzt sinnvoll, geoeffnet oder wettergeeignet?
3. Fuer wen passt es?
4. Wie komme ich hin?
5. Wo finde ich weitere Infos?

Pflichtwirkung:

- klare Activity-Kennung,
- Titel und Ort sofort erfassbar,
- Oeffnungsstatus sichtbar, aber nicht ueberladen,
- Merkmale/Tags als Entscheidungshilfe,
- Beschreibung gut lesbar,
- Navigation und Website/Infos als rollenspezifische Hauptaktionen.

Rollenspezifische Actions:

- Route/Maps,
- Website/Infos,
- optional spaeter Teilen/Merken nur bei echtem Produktnutzen,
- kein Kalender als Standardaktion.

### Today-Kontext

Today bedeutet: schnelle Empfehlung.

Das Detailpanel muss die Today-Card sinnvoll aufloesen:

- Card zeigt schnelle Vorauswahl.
- Detailpanel zeigt vollstaendige Entscheidung.
- Event bleibt Event.
- Activity bleibt Activity.
- Today darf keine dritte Detailpanel-Logik erfinden.

## Owner-Map

### Event-Detailpanel

Primaerer Owner:

- `js/details.js`

Aufgaben:

- Event-ViewModel,
- Event-Meta,
- Event-Links,
- Event-Actionbar,
- Event-Detail-HTML,
- Kalender-/Teilen-Interaktionen,
- Event-Detailpanel-Tracking.

### Activity-Detailpanel

Primaerer Owner:

- `js/offers-details.js`

Aufgaben:

- Activity-Detail-HTML,
- Activity-Media,
- Bildnachweis,
- Oeffnungsstatus,
- Activity-Facts/Tags,
- Maps-/Website-Actions,
- Activity-Detailpanel-Tracking.

### Gemeinsame visuelle Schicht

Primaerer Owner:

- `css/overlays.css`

Aufgaben:

- Overlay,
- Bottom-Sheet,
- Panel-Chrome,
- Close-Button,
- Scrollbereich,
- Actionbar,
- Safe-Area,
- Event-Detail-CSS,
- Activity-Detail-CSS,
- gemeinsame Link-/Action-/Focus-Zustaende.

### Nicht primaere Owner fuer diesen Workstream

Nicht direkt als Hauptowner anfassen, ausser ein konkreter Nachweis es erfordert:

- `js/today-home.js`
- `js/events.js`
- `js/offers.js`
- `css/today.css`
- `css/home.css`
- `css/components.css`

## Bekannter Ist-Stand

Belegt durch Analyse am 2026-06-10:

- `js/details.js` ist als konsolidiertes Event-Detailpanel-Modul markiert.
- `js/offers-details.js` rendert ein eigenes Activity-Detailpanel.
- Beide verwenden `#event-detail-panel` / `#detail-content`.
- Event-Actionbar ist auf Kalender + Teilen ausgerichtet.
- Activity-Actions sind auf Maps + Website/Infos ausgerichtet.
- Gemeinsame Panel- und Actionbar-CSS liegt in `css/overlays.css`.
- `ROADMAP.md` nennt Event-Detailpanel bisher als gefreezt; fuer den Livegang wird das Detailpanel nun gezielt als app-weites Premium-Systemelement wieder geoeffnet.

## Patch-Reihenfolge

### Schritt 1 – Detailpanel-Systemvertrag dokumentieren

Dieses Dokument anlegen und committen.

Ziel:

- Wissensstand sichern,
- Owner-Grenzen festlegen,
- naechste Patches effizient und ohne Neudiskussion ausfuehrbar machen.

### Schritt 2 – DOM-/Render-Audit mit konkretem Zielzustand

Zu pruefen:

- Event-Detailpanel HTML aus `js/details.js`,
- Activity-Detailpanel HTML aus `js/offers-details.js`,
- gemeinsame CSS-Struktur aus `css/overlays.css`.

Ergebnis soll keine lange Theorie sein, sondern:

- 3 Haupt-Gaps,
- 1 priorisierter Patchplan,
- betroffene Owner-Dateien,
- Akzeptanzkriterien.

### Schritt 3 – Gemeinsame Panel-Chrome konsolidieren

Moeglicher Scope:

- Close/Handle/Sheet-Abstaende,
- Actionbar-Abstand und Wertigkeit,
- Scrollbereich/Safe-Area,
- visuelle Ruhe im Zusammenspiel mit Bottom-Navigation.

Datei voraussichtlich:

- `css/overlays.css`

### Schritt 4 – Event-Detailpanel rollenstaerker machen

Moeglicher Scope:

- Meta-Hierarchie,
- Beschreibung/Quelle/Links,
- Actionbar-Wording/Wirkung,
- Eventbild im richtigen Gewicht.

Datei voraussichtlich:

- `js/details.js`
- ggf. `css/overlays.css`

### Schritt 5 – Activity-Detailpanel rollenstaerker machen

Moeglicher Scope:

- Oeffnungsstatus,
- Facts/Tags,
- Maps/Website-Actions,
- Activity-Bildplatzhalter bzw. vorhandene Bilder ohne Foto-Workstream zu veraendern.

Datei voraussichtlich:

- `js/offers-details.js`
- ggf. `css/overlays.css`

### Schritt 6 – App-weite Smoke-Tests

Mindestens pruefen:

- Today Event oeffnen,
- Today Activity oeffnen,
- Events-Seite Event oeffnen,
- Aktivitaeten-Seite Activity oeffnen,
- Detailpanel schliessen,
- Actionbar nutzen,
- Bottom-Navigation bleibt nicht verdeckt,
- keine Console-Errors,
- keine Regression beim Feedback-Panel.

## Akzeptanzkriterien vor Live

Event-Detailpanel:

- wirkt nicht wie eine groessere Kopie der Card,
- Titel, Ort, Datum/Uhrzeit und Beschreibung sind sauber gewichtet,
- Kalender/Teilen haben erkennbaren Nutzwert,
- Quelle/Website ist auffindbar ohne die Hauptentscheidung zu stoeren,
- Bild wirkt hochwertig eingebettet.

Activity-Detailpanel:

- Route/Website sind klare Hauptaktionen,
- Oeffnungsstatus ist sichtbar und hilfreich,
- Merkmale/Tags helfen bei der Entscheidung,
- Beschreibung ist lesbar,
- vorhandene Activity-Bilder duerfen noch nicht final sein, duerfen aber die Panel-Qualitaet nicht zerstoeren.

App-weite Konsistenz:

- Event und Activity nutzen dieselbe Premium-Sprache,
- beide bleiben fachlich unterschiedlich,
- Panel-Chrome wirkt identisch hochwertig,
- Actions wirken systemisch,
- Bottom-Navigation/Safe-Area funktionieren,
- Header, Cards und Detailpanel wirken wie ein gemeinsames Produkt.

## Live-Regel

Die Today-Home ist funktional releasefaehig, aber Live bleibt gehalten, bis das Detailpanel-System vor Live auf Premium-Niveau konsolidiert und auf Staging geprueft ist.

Activity-Premium-Fotos bleiben explizit ein separater Workstream und blockieren den Detailpanel-Livegang nicht.
<!-- === END BLOCK: DETAILPANEL_PREMIUM_SYSTEM_CONTRACT_2026_06_10 === -->
