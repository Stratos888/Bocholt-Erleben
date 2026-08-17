# Phase 5 – Prozessrevalidierung und kontrollierter Neustart

Stand: 2026-08-17
Workpack: #259
Status: **KANONISCHE PROZESSKORREKTUR – KEIN AKTUELLER PREMIUM-KANDIDAT**

## 1. Anlass

Die zuletzt als Endkandidat geführte Identität aus

- `docs/brand/phase5/step5/construction-a-mono.svg`
- `docs/brand/phase5/step8/app-mark-mono.svg`
- `docs/brand/phase5/step8/app-icon-source.svg`

wurde nach tatsächlicher Product-Owner-Sichtung als visuell nicht premiumfähig zurückgewiesen.

Befund:

- Wortmarke wirkt in realer Headergröße weitgehend wie eine normale Sans-Serif-Setzung;
- die behaupteten Eigenmerkmale sind klein kaum identitätsstiftend;
- das einzelne `e` wirkt groß wie ein `C` mit hineingelegtem Balken und nicht wie ein selbstverständlich gewachsenes Markenprinzip;
- Produkt- und Präsentationsqualität haben die schwache Eigenhöhe der Identität überdeckt;
- die internen Scores `91/100` und `90/100` waren erneut kein belastbarer Premiumnachweis.

Damit ist die Step-8-/Step-9-Freigabe als Premiumbeweis ungültig.

## 2. Wiederholter Root Cause

Der gleiche Grundfehler war bereits im Phase-2-Postmortem dokumentiert:

- dieselbe AI-Arbeitskette entwickelte;
- erklärte;
- bewertete;
- und qualifizierte denselben Kandidaten.

Phase 3 versuchte dies mit getrennten Rollen und Blindkritik zu reparieren. Das verbesserte Disziplin, erzeugte aber keine echte unabhängige ästhetische Instanz, wenn Produktion, Kritik, Konsolidierung und Endentscheidung weiterhin durch denselben AI-Orchestrator beziehungsweise dasselbe Modell erfolgen.

Der Fehler wurde in Phase 5 erneut sichtbar.

## 3. Was am bisherigen Zielvertrag richtig bleibt

Weiterhin gültig:

- keine öffentliche Änderung vor echter Freigabe;
- Name `Bocholt erleben` bleibt Standardannahme;
- Produktkern `relevante Auswahl statt Masse`;
- Creative Direction `Klar ausgewählt` als strategische Hypothese;
- reale Produktgrößen sind wichtiger als Hochglanzboards;
- Generatoroutput ist Suchmaterial, kein Finalmaster;
- finale Produktionsassets müssen reproduzierbar und technisch sauber sein;
- keine Pflicht zu B/e-Monogramm, Einzelbuchstabenicon oder separatem Symbol;
- App-Icon erst nach belastbarem Primärsystem;
- kein schwacher Kandidat wird aus Prozessgründen weitergereicht.

## 4. Was am bisherigen Prozess verworfen wird

### 4.1 Absolute ästhetische AI-Scores

Aesthetic Scores wie `90/100` oder `91/100` werden als Freigabemechanismus abgeschafft.

Zulässig bleiben numerische Messungen nur für objektive Eigenschaften, zum Beispiel:

- Kontrast;
- Pixelgrößen;
- Safe Area;
- technische SVG-Gültigkeit;
- Maskierung;
- Dateigrößen.

Premiumwirkung wird nicht mehr durch selbstvergebene Punktzahlen behauptet.

### 4.2 AI-Rollen als angebliche Unabhängigkeit

Getrennte AI-Rollen dürfen weiterhin für Ideenfindung und Gegenargumente genutzt werden, gelten aber nicht als unabhängige Premium-Gutachter.

### 4.3 Logo-first

Der Prozess beginnt nicht mehr mit einer Wortmarke oder einem App-Icon als Hauptträger der Eigenständigkeit.

Eine Marke ist ein System aus wiederkehrenden visuellen Codes. Die Wortmarke darf zurückhaltend sein, wenn das Gesamtsystem unverwechselbar ist.

### 4.4 Mikro-Modifikation einer neutralen Fontbasis als Hauptidee

`Standardschrift + kleine e/c/t-Modifikation` wird nicht weiter als primäre Suchmethode verwendet. Eine solche Lösung darf später als funktionale Wortmarke entstehen, aber nicht als Ersatz für ein tragendes Markenprinzip.

### 4.5 Product Owner als Ersatz-Designer

Der Product Owner soll keine typografischen oder formalen Richtungen fachlich auswählen müssen.

Seine Rolle bleibt:

- Produktwahrheit;
- Grenzen;
- reale Einführung;
- Rückweisung eines Ergebnisses, das sichtbar nicht überzeugt.

## 5. Externe Validierung der Prozesskorrektur

Die Prozesskorrektur stützt sich auf folgende aktuelle beziehungsweise belastbare Erkenntnisse:

### OpenAI – Bilditeration

OpenAI empfiehlt bei Bildgenerierung klare kurze Prompts, zuerst die Kernidee zu stabilisieren und anschließend jeweils nur kleine gezielte Änderungen vorzunehmen. Ein kleiner Referenzsatz ist leichter zu kontrollieren als eine große, ungerichtete Menge.

Quelle:

- https://openai.com/academy/image-generation/

### AI-Logos versus Expertendesign

Eine Reihe experimenteller Studien fand, dass Logos professioneller menschlicher Designer im Mittel besser beurteilt wurden als AI-Logo-Maker-Ergebnisse, insbesondere bei Ästhetik, Einzigartigkeit und Branchenwirkung. AI kann bei bereits klarer Designvision nützlich sein, ersetzt aber nicht automatisch kreative Differenzierung.

Quelle:

- https://doi.org/10.1016/j.sheji.2020.07.004

### VLM-Bewertung bei ähnlichen Varianten

Aktuelle Forschung zeigt, dass Vision-Language-Modelle bei nah beieinanderliegenden Designvarianten konsistente Beschreibungen erzeugen können, aber nicht zuverlässig zu einer stabilen, richtungsentscheidenden Präferenz konvergieren, während professionelle Designer dies deutlich besser leisten.

Quelle:

- https://doi.org/10.1016/j.daai.2026.100082

### Graphic-Design-Aesthetics-Benchmark

Aktuelle Benchmarks zeigen weiterhin klare Lücken zwischen VLMs und den nuancierten Anforderungen menschlicher grafischer Ästhetikbewertung.

Quelle:

- https://arxiv.org/abs/2603.01083

### Human-AI-Logo-Workflow

OpenAI-Community-Fallbeispiele behandeln generierte Logos sinnvoll als Konzeptmaterial und bauen Schrift/Geometrie anschließend reproduzierbar neu; der Prozess wird ausdrücklich nicht als vollständig automatisiert beschrieben.

Quelle:

- https://community.openai.com/t/the-dndgpt-case-study-for-you-and-me/745668/3

### Brand ist mehr als Logo

Empirische Forschung zu visueller Markensprache zeigt, dass Wiedererkennung aus einem Bündel visueller Codes entsteht und nicht zwingend aus einem dominanten Logo allein.

Quellen:

- https://arxiv.org/abs/1810.09941
- https://link.springer.com/article/10.3758/s13428-024-02525-x

## 6. Neuer verbindlicher Beweisweg

Der Prozess wird von `Logo erzeugen -> AI bewerten` auf `Markensystem beweisen -> Wortmarke ableiten` umgestellt.

### R0 – Freeze

Erhalten:

- Name `Bocholt erleben`;
- Produktkern;
- bestehendes Produktsystem;
- Farbwelt als Ausgangsmaterial, nicht als Zwang;
- bisherige Fehlversuche als Negativwissen.

Verworfen als Kandidaten:

- Candidate 201;
- `Präzise Humanität`-Wortmarke;
- `e`-Kleinmarke;
- alle bisherigen Premiumscores.

Keine neue Gestaltung, bevor R1 definiert ist.

### R1 – visuelle Kalibrierungsleiter

Statt allgemeiner Moodboards entsteht ein kleiner, expliziter Referenzsatz:

- 6–8 hochwertige reale Consumer-Marken beziehungsweise Identitätssysteme;
- 4–6 Anti-Referenzen für SaaS, Stadtmarketing, Tourismus, generische Local-/Event-Portale;
- für jede Referenz werden konkrete sichtbare Mechanismen benannt: Typografiedichte, Bildlogik, Farbverhalten, Layoutcode, Bewegungsprinzip, Wiedererkennungsasset.

Die Referenzen sind **Qualitätsanker**, keine Kopiervorlagen.

### R2 – drei Distinctive-Asset-Systeme, noch ohne individuelles Logo

Es werden höchstens drei Markenprinzipien entwickelt, jeweils aus einer Produktwahrheit abgeleitet.

Jedes Prinzip muss als vollständiger visueller Code funktionieren über:

- realen mobilen Header;
- Today-Karten;
- Detailseite;
- Fotobehandlung/Zuschnitt;
- typografische Hierarchie;
- einfache Social-/Avatarfläche;
- ein Bewegungs- oder Übergangsprinzip.

Wichtig:

- alle drei Systeme verwenden zunächst **dieselbe neutrale Kontrollwortmarke** `Bocholt erleben`;
- kein individuelles App-Icon;
- kein Lettering-Gimmick;
- dadurch wird geprüft, ob das System selbst eine echte Markenidee besitzt.

Ein System ohne erkennbare Eigenhöhe wird beendet, bevor Wortmarkenarbeit beginnt.

### R3 – Blindvergleich und unabhängiges Wahrnehmungssignal

AI darf weiterhin harte Fehlassoziationen und technische Probleme suchen, darf aber keine Premiumfreigabe erteilen.

Für die verbliebenen Systeme wird ein einfacher Blindtest mit Menschen aus der Zielgruppe durchgeführt. Dafür ist keine Designqualifikation nötig.

Testprinzip:

- anonymisierte, identisch präsentierte Varianten;
- keine Konzeptnamen oder Erklärtexte vor der Entscheidung;
- Vergleich mit dem aktuellen öffentlichen System und einer neutralen hochwertigen Kontrolle;
- Fragen in Alltagssprache, zum Beispiel:
  - `Welches wirkt wie das hochwertigste und vertrauenswürdigste lokale Freizeitprodukt?`
  - `Welches würdest du am ehesten wiedererkennen?`
  - `Woran erinnert dich die Gestaltung spontan?`
- kurze Wiedererkennungs-/Erinnerungsfrage nach Abstand zum ersten Eindruck.

Ziel ist kein wissenschaftlicher Markttest, sondern ein **unabhängiges Wahrnehmungssignal**, das die bisherige AI-Selbstbestätigung ersetzt.

Wenn kein System klar trägt, zurück zu R2. Kein Logo wird gebaut.

### R4 – Wortmarke erst innerhalb des bewiesenen Systems

Erst das stärkste System erhält Wortmarkenarbeit.

Regeln:

- reale Headergröße ist die primäre Arbeitsansicht, nicht die Großansicht;
- zunächst mehrere hochwertige typografische Materialien beziehungsweise Letteringprinzipien testen;
- eine kommerzielle oder Open-Source-Schriftbasis ist zulässig, wenn sie funktional und lizenzierbar ist;
- individuelle Modifikation nur, wenn sie sichtbar systemisch und nicht dekorativ ist;
- höchstens zwei ernsthafte Wortmarkenfassungen.

Die Wortmarke muss nicht allein die gesamte Differenzierung tragen.

### R5 – Blindvergleich Wortmarke gegen Kontrolle

Die zwei Wortmarkenfassungen werden ohne Erklärung verglichen mit:

- aktueller öffentlicher Wortsetzung;
- hochwertiger neutraler Kontrollsetzung.

Eine Fassung wird nur weitergeführt, wenn sie in realer Größe sichtbar mindestens gleichwertig funktioniert und das gewählte Markensystem stärkt.

Kein absoluter AI-Score.

### R6 – App-Icon zuletzt

Erst jetzt wird geprüft, welche responsive Kleinform aus dem **gesamten Markensystem** logisch entsteht.

Zulässig:

- Systemasset;
- typografischer Ausschnitt;
- abstrakte Markenform;
- notfalls bewusst sehr einfache Kleinform.

Nicht erzwungen:

- einzelnes `e`;
- `b/e`;
- Initialmonogramm.

Das App-Icon wird gegen reale Store-/Launcher-Nachbarschaft und Bildähnlichkeit geprüft.

### R7 – technische Härtung

Erst nach bestandenem Wahrnehmungsgate:

- SVG-/Rastermaster;
- Größen und Masken;
- Kontrast;
- Provenienz/Lizenz;
- reale Produktanwendung;
- PWA-/Favicon-/Social-Derivate.

Technik kann nur `PASS/FAIL`, niemals Premiumqualität aufwerten.

### R8 – Endgate

Der Product Owner erhält **eine** fachlich weitergeführte Identität im echten Produkt.

Er entscheidet nur:

- reale Einführung freigeben;
- oder wegen eines klar benennbaren Produkt-/Markenproblems zurückweisen.

Er wird nicht als Designer eingesetzt.

### R9 – separate Integration

Erst danach eigener Integrationsworkpack auf aktueller `staging`-Basis.

## 7. Neue harte Regeln gegen Drift

1. Vor jeder Aktion muss der `ACTIVE_EXECUTION_LOCK` gelesen werden.
2. Nur Aktionen des aktuell genannten Schritts sind zulässig.
3. Kein automatischer Übergang aufgrund eines hübschen Zwischenbildes.
4. Kein ästhetischer 0–100-Score als Gate.
5. Keine App-Kleinform vor bewiesenem Primärsystem.
6. Keine Großdarstellung als primäre Wortmarkenbewertung.
7. Keine AI-Selbstbewertung als unabhängige Freigabe.
8. Kein Product Owner als Ersatz für Designexpertise.
9. Kein Kandidat erhält den Status `Premium`, bevor ein unabhängiges menschliches Wahrnehmungssignal vorliegt.
10. Wenn der erforderliche unabhängige menschliche Test nicht durchgeführt wird, darf das Ergebnis nur `AI-intern qualifiziert`, nicht `premium-validiert` heißen.

## 8. Aktueller Status

- Name: `Bocholt erleben`;
- öffentliche Marke: unverändert;
- qualifizierte Premiumkandidaten: `0`;
- bisherige Step-5-/Step-8-Identität: `verworfen als Premiumkandidat / Negativwissen`;
- Strategie: erhalten;
- aktueller nächster Schritt: **R1 – visuelle Kalibrierungsleiter erstellen**;
- keine Produktintegration freigegeben.
