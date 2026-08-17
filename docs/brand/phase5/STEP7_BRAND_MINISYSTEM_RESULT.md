# Phase 5 – Schritt 7: Marken-Minisystem und reale Produktwirkung

Stand: 2026-08-17
Workpack: #259
Status: **BESTANDEN**

## 1. Prüfgrundlage

Verwendet wurden die realen bestehenden Produktverträge aus `staging`:

- Headerhöhe `48px`;
- UI-Typografie `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif`;
- Brand Green `#07240F` bis `#3F852C`;
- Interaktionsgrün `#8FCB3B`;
- Gold `#D8B234`;
- Background `#EEF1E3`;
- Surface `#FFFFFF`;
- Text Primary `#18281E`;
- bestehende Lucide-/Komponenten-/Accessibility-Verträge;
- reale Today-Inhalte und reale Navigationsbegriffe.

Es wurde kein produktiver UI-Code geändert.

## 2. Primäridentität im Header

Geprüft wurde die vollständige Wortmarke aus:

- `docs/brand/phase5/step5/construction-a-mono.svg`

Mobile Testgeometrie:

- Viewport `390x844`;
- Header `48px`;
- Wortmarke ungefähr `142px` breit.

Desktop Testgeometrie:

- Viewport `1440x900`;
- Header-/Navigationsebene analog zum bestehenden Produkt.

### Befund

**Bestanden.**

Die Wortmarke bleibt im 48px-Header:

- sofort lesbar;
- ruhig und kompakt;
- sichtbar individueller als reine UI-Schrift;
- zurückhaltend genug, um den Content nicht zu dominieren;
- ohne zusätzliches Icon funktionsfähig.

Eine größere Hero-Inszenierung der Marke ist nicht erforderlich und würde die Produktlogik eher verschlechtern.

## 3. Farbrolle

### Primäre Markenfarbe

**`#0D3014` (`--brand-green-900`)** auf hellen Flächen.

Begründung:

- ausreichend ruhig und vertrauenswürdig;
- gehört bereits zur bestehenden Produktpalette;
- trennt Markenidentität sauber von der helleren Interaktionsfarbe;
- benötigt keine neue Brand-Farbe und reduziert Integrationsrisiko.

### Inverse Fassung

**Weiß** auf ausreichend dunklen vorhandenen Brand-Green-Flächen.

Die inverse Fassung wurde visuell geprüft und bleibt klar.

### Interaktionsfarbe

`#8FCB3B` bleibt **Produkt-/Aktivierungsfarbe**, nicht Wortmarkenfarbe.

Damit wird ausdrücklich vermieden, die alte Logik einer zweifarbigen Wortmarke erneut einzuführen.

### Gold

`#D8B234` bleibt seltener bestehender Sekundärakzent und ist kein Bestandteil der Primärwortmarke.

## 4. Typografierollen

### Marke

- individuelle Pfadwortmarke;
- ausschließlich für Markenabdruck und definierte Brand-Kontexte.

### Produkt

- bestehende System-UI bleibt funktionale Produkttypografie;
- keine Einführung eines neuen UI-Webfonts nötig;
- keine Nutzung der Markenbuchstaben als Display-Schrift im Produkt.

### Befund

**Bestanden.**

Die Trennung verhindert, dass Markencharakter zulasten von Lesbarkeit oder Performance auf die gesamte App übertragen wird.

## 5. Bildsprache

Verbindliche Richtung für spätere reale Anwendungen:

- echte lokale Situationen und Orte;
- möglichst natürliche Perspektive und Licht;
- ein klarer Fokus pro Bild;
- Menschen nur dann, wenn sie eine reale Situation glaubwürdig tragen;
- keine Postkarten-/Tourismusinszenierung;
- keine Festivalmassen als allgemeine Markensprache;
- keine Stock-Posen, künstlichen Lifestyle-Szenen oder lokalen Wahrzeichen als Pflichtmotiv.

Die Bildsprache unterstützt `Klar ausgewählt`: weniger visuelle Masse, stärkere einzelne Auswahl.

## 6. Bewegungsprinzip

Die Marke selbst benötigt keine Logoanimation.

Zulässig und passend ist nur ein Produktprinzip:

- kurze, ruhige Fokus-/Reveal-Übergänge;
- Auswahl wird sichtbar präzisiert oder geöffnet;
- keine pulsierenden, springenden oder dekorativen Markenbewegungen;
- `prefers-reduced-motion` bleibt vollständig respektiert.

## 7. Reale Produktwirkung

Geprüfte Kontexte:

- mobile Today-Startseite;
- Desktop Today-/Discovery-Shell;
- Karten-/Auswahlrhythmus;
- Header mit bestehenden Utility-Aktionen;
- dunkle inverse Markenfläche als Gegenprobe.

### Befund

Die Primäridentität fügt sich in das bestehende Produktsystem ein, ohne dass dieses für die Marke neu erfunden werden muss.

Besonders wichtig:

- die Wortmarke funktioniert bereits ohne App-Icon;
- das vorhandene Farbsystem trägt die Marke;
- Lime bleibt funktional und wird nicht zur Markenkrücke;
- die neue Identität wirkt als Consumer-Marke und nicht als Behörde, Tourismusportal oder generisches SaaS;
- die Marke kann im Header klein bleiben und lässt den kuratierten Inhalt führen.

## 8. Vergleich mit öffentlichem Ausgangszustand

Der öffentliche Header verwendet aktuell ein bildhaftes App-Zeichen plus die Textzeile `Bocholt erleben`.

Das neue Minisystem erreicht bereits ohne neue Kleinmarke:

- eine kohärentere Primäridentität;
- flachere und zeitgemäßere Markenwirkung;
- klarere Trennung von Marke und Produktfunktion;
- geringere Abhängigkeit von einem dekorativen App-Zeichen.

Das ist noch keine Freigabe zur produktiven Ersetzung.

## 9. Fachliches Urteil

**SCHRITT 7 – BESTANDEN.**

Die Primäridentität kann ein reales Marken-Minisystem tragen, ohne durch Farbe, Mock-up oder UI-Redesign gerettet zu werden.

Weiterhin nicht freigegeben:

- App-Icon / responsive Kleinmarke;
- Designscore;
- Red Team;
- Marken-/Ähnlichkeitsfreigabe;
- `staging`, `main` oder Live-Integration.

## 10. Nächster zulässiger Schritt

**SCHRITT 8 VON 9 – RESPONSIVE KLEINFORM / APP-MARKE UND FORMALE QUALITÄTSPRÜFUNG**

Erst dort wird entschieden, ob die Wortmarke eine typografische Kleinform erlaubt oder eine eigenständige, aber formverwandte App-Marke benötigt.
