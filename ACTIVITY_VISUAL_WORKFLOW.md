# Activity Visual Workflow

Stand: 2026-06-09
Scope: Activity-Bilder für `Bocholt erleben`
Status: Prozessvertrag vor Massenproduktion

## 1. Zielzustand

Jede Activity erhält ein eigenes exklusives Premium-Hauptbild.

Verbindlich:

- Ein Activity-Bild darf nicht im Event-Visual-Pool verwendet werden.
- Ein Activity-Bild darf nicht für eine andere Activity wiederverwendet werden.
- Ein Activity-Bild darf innerhalb derselben Activity mehrfach erscheinen, z. B. Home, Aktivitätenseite und Detailpanel.
- Der finale Zielzustand ist nicht `usable`, sondern `ready`.
- CSS-Cropping, Overlays oder Layout-Tricks dürfen schwache Bilder nicht retten.
- Externe Legacy-Bilder sind nur Übergang, nicht Zielzustand.

## 2. Rechtlich saubere Wiedererkennbarkeit

Activity-Bilder sollen zur jeweiligen Activity passen und sie wiedererkennbar machen. Gleichzeitig müssen sie rechtlich und produktionslogisch sauber bleiben.

Daraus folgt:

- Das Bild darf die Activity über Nutzung, Stimmung, Ortstyp, Materialien, Landschaftscharakter, Wegeführung, Wasser-/Wald-/Innenstadt-/Kultur-Kontext und typische Handlung wiedererkennbar machen.
- Das Bild darf nicht behaupten, ein dokumentarisches Foto eines konkreten echten Ortes zu sein, wenn kein eigenes oder eindeutig freigegebenes Referenzmaterial verwendet wurde.
- KI-Bilder sind standardmäßig symbolisch-activity-spezifisch, nicht dokumentarisch.
- Exakte Nachbildung realer privater Gebäude, geschützter Kunstwerke, Logos, Markenauftritte, Plakate, Beschilderungen oder fremder Foto-Kompositionen ist nicht zulässig.
- Wenn ein Bild ein konkretes reales Objekt oder eine konkrete reale Anlage dokumentarisch zeigen soll, ist eine eigene Aufnahme oder eine belastbar freigegebene Quelle erforderlich.
- Bei Unsicherheit wird nicht `ready` vergeben, sondern `needs_review` oder `blocked`.

Leitsatz:

> Wiedererkennbar durch Activity-Charakter und lokale Plausibilität, nicht durch riskante Kopie eines fremden Fotos oder eine falsche dokumentarische Ortsbehauptung.

## 3. Ready-Definition für Activity-Bilder

Ein Activity-Bild ist nur `ready`, wenn alle Punkte erfüllt sind:

- exklusiv genau einer Activity zugeordnet
- nicht im Event-Visual-System verwendet
- nicht für eine andere Activity verwendet
- visuell auf Premium-Niveau
- activity-spezifisch statt generisch
- lokal plausibel für Bocholt, Westmünsterland oder die konkrete Umgebung
- rechtlich bzw. produktionslogisch sauber
- keine falsche dokumentarische Ortsbehauptung
- WebP
- echtes 16:9-Card-Asset, Zielmaß `1200x675`
- card-tauglich auf Mobile und Desktop
- keine lesbaren Marken, Logos, Plakate, Schilder oder fremden Kunstwerke als Fokus
- keine erkennbaren Kinder
- keine dominanten klar erkennbaren Privatpersonen
- keine auffälligen KI-Artefakte
- keine geklonten Personen, Hände, Gesichter oder unnatürlichen Objektstrukturen
- finale Review dokumentiert

## 4. Statuslogik

`planned`:
Bild ist geplant, aber noch nicht erzeugt oder noch nicht importiert.

`needs_review`:
Bild oder Motividee braucht Prüfung. Es darf nicht prominent verwendet werden.

`usable`:
Übergangsstatus für bestehende Bilder. Nicht Zielzustand und nicht ausreichend für den vollständigen Premium-Freeze.

`ready`:
Final geprüftes, exklusives Activity-Premiumbild.

`blocked`:
Nicht verwenden. Gründe können sein: Rechts-/Lizenzrisiko, falsche Ortsbehauptung, Logo-/Textproblem, erkennbare Kinder, KI-Fehler, Motiv-Mismatch, generische Stockwirkung oder Wiederverwendung.

## 5. Dauerhafter Produktionsprozess

### Schritt 1: Activity-Brief

Für jede Activity wird vor der Bildproduktion ein kurzer Brief festgelegt:

- Activity-ID / Slug
- Zweck der Activity
- was im Bild wiedererkennbar sein soll
- welche Elemente nur symbolisch angedeutet werden dürfen
- rechtliche Grenzen / keine dokumentarische Behauptung
- gewünschte Stimmung
- harte Negativregeln

### Schritt 2: Prompt aus Prompt-Kit bauen

Der Prompt wird aus `ACTIVITY_VISUAL_PROMPT_KIT.md` aufgebaut:

1. globaler Qualitätsblock
2. rechtlicher Sicherheitsblock
3. Activity-Brief
4. Motivmodul
5. Output-Regeln
6. Negativregeln

Nur der Activity-Brief und das Motivmodul sollen pro Activity deutlich variieren.

### Schritt 3: Testgenerierung

Standard: 4 Varianten pro Activity erzeugen.

Ziel ist nicht, alle vier zu verwenden, sondern das beste exklusive Hauptbild auszuwählen.

### Schritt 4: Review

Jedes Bild wird gegen diese Achsen bewertet:

- Activity-Passung
- lokale Plausibilität
- rechtliche Sauberkeit
- keine dokumentarische Falschbehauptung
- Premium-Wirkung
- 16:9-Card-Lesbarkeit
- Mobile-/Desktop-Tauglichkeit
- Personen-/Kinder-/Logo-/Text-Sicherheit
- KI-Artefakte
- Exklusivität gegenüber Events und anderen Activities

### Schritt 5: Export und Eintrag

Nur akzeptierte Bilder werden als WebP-Card-Asset exportiert:

- Zielpfad: `assets/activity-visuals/`
- Zielmaß: `1200x675`
- Format: WebP
- Dateiname: `activity-<slug>-01.webp`
- keine Rohbilder oder temporären Downloads committen
- Alt-Text und Review-Status dokumentieren

### Schritt 6: Audit

Vor einem Freeze müssen mindestens geprüft werden:

- keine Activity ohne finales exklusives `ready`-Bild
- keine Bilddatei doppelt zwischen Activities
- keine Bilddatei im Event- und Activity-System gleichzeitig
- keine `ready`-Activity-Bilder ohne WebP/16:9/Alt-Text
- keine bekannten Rechts-/Motiv-/Prompt-Risiken offen

## 6. Anchor-Test vor Massenproduktion

Vor der Produktion aller Activities wird das Prompt-Kit an mindestens zwei Anchor-Activities getestet:

1. `bocholter-innenstadt-erleben`
   - prüft urbane Szene, Menschen, Schilder, Marken, regionale Plausibilität und falsche Ortsbehauptungen
2. `stadtwald-bocholt-erleben`
   - prüft Natur-/Waldszene, lokale Plausibilität, generische Motive, Wegeführung und Familien-/Personensicherheit

Das Prompt-Kit wird erst als stabil betrachtet, wenn beide Tests ohne strukturelle Qualitätsprobleme funktionieren.

## 7. Repo-Dokumentation

Dauerhafte Dokumentation:

- `VISUAL_WORKFLOW.md`: strategischer Premium-Visual-Contract
- `ACTIVITY_VISUAL_WORKFLOW.md`: operativer Activity-Prozess
- `ACTIVITY_VISUAL_PROMPT_KIT.md`: wiederverwendbarer Prompt-Baukasten
- später: finale Zuordnung pro Activity in einer Manifest-/Datenstruktur oder kontrolliert in `offers.json`

## 8. Nicht-Ziele dieses Workstreams

Nicht Teil dieses Prozessvertrags:

- sofortige UI-Integration
- automatische Auswahl aus einem Shared-Pool
- Verwendung von Event-Visuals
- pauschale Übernahme bestehender Remote-Bilder als finaler Premiumstand
- CSS-Rettung einzelner Bildausschnitte
