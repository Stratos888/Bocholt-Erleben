# Postmortem – Fehler des Phase-2-Markenverfahrens

Stand: 2026-08-01
Workpack: #225
Befund: Prozessfehler, nicht nur Kandidatenfehler

## 1. Zusammenfassung

`Offener Impuls` wurde intern mit 91/100 qualifiziert, obwohl die reale mobile Darstellung eine generische Wortmarke, ein artefaktähnliches Detail und eine schwache Kleinmarke zeigte.

Die zentrale Ursache war zirkuläre Qualifizierung:

1. dieselbe Arbeitskette entwickelte die Idee;
2. dieselbe Arbeitskette formulierte ihre Bedeutung;
3. dieselbe Arbeitskette vergab die Punkte;
4. ein technischer Validator prüfte, ob Name, Punkte und Textmarker vorhanden waren;
5. der grüne CI-Status wurde anschließend wie ein Qualitätsnachweis behandelt.

CI hatte jedoch niemals die gestalterische Qualität geprüft.

## 2. Fehlerbaum

### F1 – fehlende Bewertungsunabhängigkeit

Vorgesehen war unabhängige Kritik. Belegt war sie nicht.

Es fehlten:

- getrennte Evaluator-Identitäten;
- blind abgegebene Einzelurteile;
- unveränderliche Bewertungen vor Konsolidierung;
- dokumentierte Abweichungen;
- ein Kritiker mit explizitem Ablehnungsauftrag.

Folge: Die Bewertung verteidigte die Produktionshypothese.

### F2 – zirkulärer Validator

Der frühere Validator verlangte kandidatenspezifisch:

- einen bestimmten Kandidatennamen;
- `91/100`;
- eine Scorecard, deren eingetragene Werte 91 ergaben;
- Texte, die den Kandidaten als qualifiziert beschrieben.

Damit prüfte er nur die Konsistenz der Behauptung.

Folge: Ein formal grüner Lauf wurde fälschlich als ästhetische Bestätigung interpretiert.

### F3 – vermischte Qualitätsarten

Gestalterischer Score enthielt Punkte für:

- Konstruktion;
- Provenienz;
- Raster;
- Masken;
- Produktintegration.

Diese Aspekte sind wichtig, aber technische Eintrittsbedingungen. Sie dürfen keine schwache Eigenständigkeit kompensieren.

Folge: technische Sorgfalt hob eine durchschnittliche Gestaltung über die Premiumschwelle.

### F4 – zu enge Markenarchitektur

Die Primärlösung war vorab auf eine custom-gezeichnete Wortmarke festgelegt. In der tatsächlichen Umsetzung wurde `custom` zu großzügig ausgelegt:

- vorhandene Schriftbasis;
- Outline-Konvertierung;
- kleiner dekorativer Eingriff.

Folge: Der Suchraum driftete zu `Standardschrift plus Detail`.

### F5 – nicht auditierbare Exploration

Neun Prinzipien wurden benannt und bewertet, aber nicht als vollständige, neutral vergleichbare Schwarz-Weiß-Evidenz festgehalten.

Folge: Weder Breite noch Fairness der Auswahl waren nachprüfbar.

### F6 – Erklärung vor Wahrnehmung

Kandidatenname, Formidee und Herleitung waren vor dem visuellen Urteil bekannt.

Folge: Betrachter konnten eine behauptete Bedeutung in ein schwaches Detail hineinlesen.

### F7 – falsche Präsentationsreihenfolge

Große Markenansichten und kuratierte Mock-ups dominierten. Der reale mobile Header zeigte die Schwäche erst später.

Folge: Präsentationsqualität verdeckte Nutzungsqualität.

### F8 – erkannter Kategoriefehler wurde nur verkleinert

Eine breitere Fassung erzeugte bereits eine Pflaster-/Etikettassoziation. Statt die Grundidee zu beenden, wurde das Detail reduziert.

Folge: groß blieb es artefaktähnlich, klein verlor es seine Identitätswirkung.

## 3. Kontrolllücken

| Kontrollziel | Phase 2 | Phase 3 |
|---|---|---|
| Produzent darf Endscore nicht bestimmen | nur behauptet | maschinenlesbar gesperrt |
| Blindkritik | nicht belegt | zwei getrennte Rollen |
| Red Team | nicht vorhanden | Pflichtrolle |
| Knock-outs vor Score | unvollständig | harte Reihenfolge |
| Technik getrennt von Design | nein | vollständig getrennt |
| Originalgröße zuerst | nein | Gate P0 |
| Austauschbarkeitstest | nicht verbindlich | Pflicht |
| Genericity-Ablation | nicht verbindlich | Pflicht |
| CI-Grenze | unklar | keine Ästhetikbehauptung |
| Einzelurteile auditierbar | nein | Pflichtdateien |

## 4. Nicht zulässige Reparaturen

- nur die Mindestpunktzahl erhöhen;
- dieselbe Scorecard strenger auslegen;
- einen weiteren kleinen Eingriff an einer Standardschrift testen;
- noch mehr Mock-ups erzeugen;
- den Produzenten um eine kritischere Selbstbewertung bitten;
- technische Validatoren um weitere Kandidatenmarker erweitern.

## 5. Erforderliche Reparatur

Die vollständige Lösung ist in `AI_BRAND_PHASE3_EVALUATION_FRAMEWORK.md` definiert.

Die Reparatur gilt erst als abgeschlossen, wenn:

- `Offener Impuls` sichtbar archiviert und zurückgewiesen ist;
- keine CI-Datei ästhetische Qualität zertifiziert;
- Rollen und Reihenfolge maschinenlesbar sind;
- technische und gestalterische Gates getrennt sind;
- neutrale Evidenzvorlagen existieren;
- keine neue Gestaltung Teil dieses Workpacks ist.
