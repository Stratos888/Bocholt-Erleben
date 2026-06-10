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

<!-- === BEGIN BLOCK: ACTIVITY_VISUAL_WORKFLOW_SOURCING_GATE_V1_2026_06_10 | Zweck: dokumentiert das verpflichtende Sourcing-Gate vor jeder Activity-Bildproduktion === -->

## Sourcing-Gate vor jeder Activity-Bildproduktion

Vor jedem Prompt und vor jeder Bildgenerierung muss die Activity einer Strategie zugeordnet werden.

| Strategie | Bedeutung | Premium-Endbild möglich? |
|---|---|---|
| `symbolic_ai_ok` | Activity kann atmosphärisch, über Nutzung oder allgemeinen Ortstyp ehrlich dargestellt werden. | Ja, nach Review. |
| `own_or_licensed_real_photo_required` | Konkreter Ort oder konkrete Ausstattung muss real gezeigt werden. | Nur mit eigener Aufnahme oder belastbarer Lizenz. |
| `official_permission_candidate` | Bildmaterial existiert wahrscheinlich bei Stadt, Betreiber, Verein, Presse oder Rechteinhaber. | Nur mit schriftlicher Freigabe. |
| `blocked_until_photo_available` | Kein rechtlich und fachlich geeignetes Bild verfügbar. | Nein. Nicht prominent verwenden. |

`symbolic_ai_ok` ist nur zulässig, wenn eine leichte Abstraktion keine falsche Nutzererwartung erzeugt. Das Bild darf atmosphärisch passen, aber nicht so tun, als zeige es einen konkreten realen Ortsausschnitt.

`own_or_licensed_real_photo_required` gilt, wenn die Activity wesentlich an einem konkreten Objekt, einer konkreten Ausstattung oder einer wiedererkennbaren Anlage hängt.

Ein Bild kann nur `ready` werden, wenn es zugleich visuell premiumfähig, rechtlich sauber, inhaltlich passend, exklusiv zugeordnet und frei von falschen dokumentarischen Orts-/Objektbehauptungen ist.

Zulässige Realbildquellen sind eigene Fotos, schriftlich freigegebene Fotos von Stadt/Betreiber/Verein/Presse/Rechteinhaber, offen lizenzierte Bilder mit dokumentierter Lizenz oder direkt lizenzierte Fotografien.

Nicht zulässig sind ungeprüfte Netzbilder, Pressebilder ohne Freigabe, Social-Media-Bilder ohne Freigabe, fremde Fotos als 1:1-KI-Vorlage, Bilder mit erkennbaren Kindern oder dominierenden Privatpersonen ohne Rechteklärung sowie Bilder mit problematischen Logos, Schildern, Kunstwerken oder Marken als Hauptmotiv.

Für jede Activity soll später dokumentiert werden: `activity_id`, `visual_sourcing_strategy`, `reason`, `asset_status`, `source_type`, `rights_status`, `photo_required_reason`, `permission_source` und `review_notes`.

Lernfall:

```text
suderwicker-maerchenspielplatz
visual_sourcing_strategy: official_permission_candidate
fallback_status: blocked_until_photo_available
reason: konkreter Spielplatz mit spezifischer Ausstattung; KI erzeugt falsche dokumentarische Ortswirkung
```

<!-- === END BLOCK: ACTIVITY_VISUAL_WORKFLOW_SOURCING_GATE_V1_2026_06_10 === -->
