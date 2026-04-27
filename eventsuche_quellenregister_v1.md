# Bocholt erleben – Eventsuche Quellenregister v1

## Zweck
Dieses Quellenregister ergänzt das Regelwerk für die KI-Eventsuche.

Wichtig:
- Dieses Register ist **keine exklusive Whitelist**
- Es schließt gute neue Quellen **nicht** aus
- Es dient nur der **operativen Steuerung und Gewichtung** der Suche

<!-- === BEGIN BLOCK: QUELLENREGISTER_GRUNDPRINZIP_PRODUKTION_ONLY_V1 | Zweck: Grundprinzip auf Weekly-Produktion ohne aktive Discovery ausrichten | Umfang: ersetzt alte Discovery-immer-offen-Regel === -->
Grundprinzip:
- Weekly-Produktionslauf: CORE-HIGH zuerst systematisch prüfen
- danach CORE-MID streng priorisiert prüfen
- danach RECOVERY gezielt prüfen
- DISCOVERY-SEED und DISCOVERY-OPEN nicht aktiv im Weekly-Produktionslauf suchen
- Quellen-Discovery als separaten Arbeitsmodus behandeln
- LOW-VALUE nicht aktiv priorisieren
- EXCLUDE / GESPERRT nicht aktiv als Quelle nutzen
<!-- === END BLOCK: QUELLENREGISTER_GRUNDPRINZIP_PRODUKTION_ONLY_V1 === -->

---

## Statuslogik

### CORE-HIGH
Wiederholt starke, offizielle oder neutrale Quellen mit hohem PWA-Nutzen und guter Trefferqualität.

### CORE-MID
Regelmäßig brauchbare Quellen, aber mit gemischter Trefferqualität oder geringerer Priorität.

### RECOVERY
Quellen oder Seitentypen, die gute Events retten können, obwohl sie nicht immer klassische Event-Detailseiten sind.

### DISCOVERY-SEED
Konkrete bekannte Startquellen für Discovery. Sie sind erlaubt und sinnvoll, aber noch nicht stabil genug für CORE oder RECOVERY.

### DISCOVERY-OPEN
Neue, bisher nicht klassifizierte Quellen außerhalb dieses Registers bleiben erlaubt, wenn sie das Regelwerk erfüllen.

### LOW-VALUE
Quellen, die formal oft korrekt sind, aber wiederholt eher schwache, nischige oder für die PWA wenig hilfreiche Treffer liefern.

### EXCLUDE / GESPERRT
Quellen, die strategisch, monetarisierungsseitig oder qualitativ nicht aktiv genutzt werden sollen.

---

<!-- === BEGIN BLOCK: GRUNDREGELN_REGISTER_PRODUKTION_ONLY_V1 | Zweck: Grundregeln an production-only Weekly-Lauf anpassen | Umfang: ersetzt Discovery-darf-nicht-null-Regel durch getrennte Produktions- und Discovery-Logik === -->
## Grundregeln für die Nutzung

1. Dieses Register ersetzt **nicht** das Regelwerk.
2. Gute neue Quellen außerhalb des Registers bleiben erlaubt.
3. FINAL entscheidet immer das Regelwerk, nicht dieses Register.
4. Eine Quelle wird nicht wegen ihres Status automatisch FINAL-fähig.
5. Im Weekly-Produktionslauf wird aktiv nur über `CORE-HIGH`, `CORE-MID` und `RECOVERY` gesucht.
6. `DISCOVERY-SEED` und `DISCOVERY-OPEN` werden im Weekly-Produktionslauf nicht aktiv als Suchmodus genutzt.
7. Für separate Quellen-Discovery-Läufe bleiben `DISCOVERY-SEED`, `DISCOVERY-OPEN` und neue Quellen außerhalb des Registers zulässig.
8. Dieses Register steuert:
   - wo zuerst gesucht wird
   - wo Recovery sinnvoll ist
   - welche Quellen eher wenig bringen
   - welche Quellen nicht aktiv benutzt werden sollen
   - welche Quellen separat für Discovery beobachtet werden können
<!-- === END BLOCK: GRUNDREGELN_REGISTER_PRODUKTION_ONLY_V1 === -->

---

## CORE-HIGH

### Stadt Bocholt – Veranstaltungskalender / offizielle Event-Detailseiten
- Quelle / Muster:
  - `bocholt.de/veranstaltungskalender/*`
  - `bocholt.de/freizeit-und-tourismus/veranstaltungen/*`
- Status: CORE-HIGH
- Eventqualität: hoch
- PWA-Nutzen: hoch
- Technische Stabilität: hoch
- Strategierisiko: niedrig
- Warum:
  - wiederholt starke lokale Treffer
  - hohe Relevanz für Bocholt erleben
  - offizielle Quelle
- Einsatzregel:
  - in jedem Lauf früh und systematisch prüfen

### AaltenDagen
- Quelle / Muster:
  - `aaltendagen.nl/*`
- Status: CORE-HIGH
- Eventqualität: hoch
- PWA-Nutzen: hoch
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - klarer Stadtfest-/Seriencharakter
  - regional relevant
  - hoher Nutzwert
- Einsatzregel:
  - in Saisonläufen aktiv mitführen

---

## CORE-MID

### Stadt Rhede – offizielle Veranstaltungsdetailseiten
- Quelle / Muster:
  - `rhede.de/regional/veranstaltungen/detail-*`
- Status: CORE-MID
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - offizielle Quelle
  - einzelne gute Treffer
- Risiko:
  - nicht jeder Treffer ist stark genug
- Einsatzregel:
  - regelmäßig prüfen, aber streng priorisieren

### FARB Borken – klassische Veranstaltungsdetailseiten
- Quelle / Muster:
  - `farb.borken.de/.../veranstaltungen/termine/*`
- Status: CORE-MID
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel bis hoch
- Strategierisiko: niedrig
- Warum:
  - mehrfach saubere Detailseiten
  - brauchbar für den Nahraum
- Risiko:
  - gemischte Relevanz
  - etliche formal korrekte, aber nicht sehr starke Treffer
- Einsatzregel:
  - aktiv prüfen, aber stärker nach Breiteninteresse filtern

---

## RECOVERY

### Stadt Bocholt – offizielle Event-News-/Info-Seiten
- Quelle / Muster:
  - `bocholt.de/*` außerhalb klassischer Kalenderdetailseiten mit klarem Eventfokus
- Status: RECOVERY
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel bis hoch
- Technische Stabilität: hoch
- Strategierisiko: niedrig
- Warum:
  - kann gute Events retten, die nicht als klassische Detailseite vorliegen
- Risiko:
  - Save-the-date
  - Vorschau-/Teaserlogik
  - Orga-/CTA-Blöcke
- Einsatzregel:
  - nur FINAL, wenn Titel + Datum + Ort + Besucherfokus im klaren sichtbaren Kernblock vorhanden sind

### Stadt Rhede – offizielle Event-News-/Info-Seiten
- Quelle / Muster:
  - `rhede.de/*` mit event-spezifischem Fokus
- Status: RECOVERY
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - kann einzelne gute Events liefern
- Risiko:
  - Abruf-/Stabilitätsprobleme
- Einsatzregel:
  - nur mit sauberer Instanz- und URL-Prüfung

### Koppelkerk
- Quelle / Muster:
  - `koppelkerk.nl/agenda/*`
- Status: RECOVERY
- Eventqualität: mittel
- PWA-Nutzen: niedrig bis mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - liefert echte Eventdetailseiten
  - wiederholt formal saubere Treffer
- Risiko:
  - oft eher nischig
  - nicht immer stark für die PWA
- Einsatzregel:
  - gezielt prüfen, aber nur starke Treffer in FINAL

### Isselburg – offizielle Veranstaltungsseiten
- Quelle / Muster:
  - `isselburg.de/de/veranstaltungen/termine/*`
- Status: RECOVERY
- Eventqualität: mittel
- PWA-Nutzen: niedrig bis mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - offizielle Quelle
  - punktuell brauchbar
- Risiko:
  - Canonical-/Instanz-URL-Probleme
- Einsatzregel:
  - nur mit harter URL-/Instanzprüfung

---

## DISCOVERY-SEED

### Bredevoort.nu / lokale Agenda-Portale
- Quelle / Muster:
  - `bredevoort.nu/agenda/*`
- Status: DISCOVERY-SEED
- Eventqualität: gemischt
- PWA-Nutzen: gemischt
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - gut als Signalgeber für neue Kandidaten
- Einsatzregel:
  - zur Entdeckung erlaubt
  - nicht blind direkt FINAL

### Allgemeine Kulturkalender / regionale Eventübersichten
- Quelle / Muster:
  - regionale Kulturübersichten
  - allgemeine Veranstaltungsübersichten im Suchraum
- Status: DISCOVERY-SEED
- Eventqualität: gemischt
- PWA-Nutzen: gemischt
- Technische Stabilität: gemischt
- Strategierisiko: niedrig
- Warum:
  - kann neue Kandidaten liefern
- Einsatzregel:
  - nur Discovery, FINAL nur nach offizieller Verifikation

### Ticketseiten / Ticketshops
- Quelle / Muster:
  - Ticketseiten mit Eventbezug
- Status: DISCOVERY-SEED
- Eventqualität: gemischt
- PWA-Nutzen: gemischt
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - oft guter Frühindikator
- Einsatzregel:
  - nie blind direkt FINAL
  - nur Discovery-Hinweis

---

## DISCOVERY-OPEN

### Regel
Neue Quellen außerhalb dieses Registers bleiben ausdrücklich erlaubt.

Wenn eine neue Quelle:
- regelkonforme Events liefert
- wiederholt gute FINAL-Kandidaten erzeugt
- technisch stabil ist
- strategisch unkritisch ist

dann kann sie später hochgestuft werden:
- zuerst zu `DISCOVERY-SEED`
- dann ggf. zu `RECOVERY`
- später zu `CORE-MID` oder `CORE-HIGH`

---

## LOW-VALUE

### Kreis Borken – sehr spezielle Themen-/Workshopseiten
- Quelle / Muster:
  - `kreis-borken.de/.../veranstaltungen-gleichstellung/termine/*`
  - ähnliche Spezial-Workshop-/Seminarbereiche
- Status: LOW-VALUE
- Eventqualität: formal oft okay, redaktionell oft schwach
- PWA-Nutzen: niedrig
- Technische Stabilität: hoch
- Strategierisiko: niedrig
- Warum:
  - häufig zu speziell
  - oft wenig Breitenrelevanz
- Einsatzregel:
  - nicht aktiv priorisieren
  - nur bei ungewöhnlich hoher öffentlicher Relevanz berücksichtigen

### Museums-/Vorschau-/Ausstellungs-Unterseiten mit dünnem Eventcharakter
- Quelle / Muster:
  - `museum/Vorschau/*`
  - Preview-/Vorschau-Seiten
- Status: LOW-VALUE
- Eventqualität: formal oft okay, inhaltlich oft zu schwach
- PWA-Nutzen: niedrig bis mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - erhöht Risiko für formal korrekte, aber schwache FINALs
- Einsatzregel:
  - nicht aktiv priorisieren
  - nur mit starkem Besucherfokus und klarer Relevanz berücksichtigen

### Kleine Spezialformate / Workshops / Bildungsformate
- Quelle / Muster:
  - Workshops
  - Resilienzformate
  - kleine Spezialtermine
- Status: LOW-VALUE
- Eventqualität: gemischt
- PWA-Nutzen: meist niedrig
- Technische Stabilität: gemischt
- Strategierisiko: niedrig
- Warum:
  - häufig nicht stark genug für FINAL
- Einsatzregel:
  - nur bei außergewöhnlicher lokaler oder öffentlicher Relevanz berücksichtigen

---

## EXCLUDE / GESPERRT

### Direkte Venue-Promo monetarisierungsrelevanter Locations
- Quelle / Muster:
  - venue-eigene Event-/Promo-Seiten monetarisierungsrelevanter Locations
- Status: EXCLUDE / GESPERRT
- Warum:
  - Monetarisierungsschutz
- Einsatzregel:
  - nicht aktiv als Quelle nutzen

### Kulturort Alte Molkerei – venue-zentrierte direkte Quellen
- Quelle / Muster:
  - venue-eigene Seiten / direkte Promo
- Status: EXCLUDE / GESPERRT
- Warum:
  - ausdrücklich geschützte Venue
- Einsatzregel:
  - nicht aktiv suchen
  - nur neutrale/offizielle Drittquellen-Ausnahme nach Regelwerk kann einzelne Events retten

### Social-only / Hinweis-only ohne offizielle Verifikation
- Quelle / Muster:
  - Facebook-only
  - Instagram-only
  - Social-Hinweise ohne belastbare offizielle Zielseite
- Status: EXCLUDE / GESPERRT als FINAL-Quelle
- Warum:
  - zu instabil
  - nicht belastbar genug
- Einsatzregel:
  - allenfalls Discovery-Hinweis, nie direkt FINAL

---

## Pflege-Regeln

### Quelle hochstufen
Eine Quelle kann hochgestuft werden, wenn sie in mehreren Läufen:
- wiederholt starke FINALs liefert
- technisch stabil ist
- strategisch unkritisch ist

### Quelle runterstufen
Eine Quelle sollte runtergestuft werden, wenn sie:
- wiederholt nur schwache, formal korrekte Treffer liefert
- wenig PWA-Nutzen bringt
- technisch problematisch ist

### Neue Quelle aufnehmen
Eine neue Quelle wird zunächst als:
- `DISCOVERY-SEED`
oder
- `DISCOVERY-OPEN` Kandidat

behandelt, nicht sofort als CORE.

---

<!-- === BEGIN BLOCK: OPERATIVE_NUTZUNG_PRODUKTION_ONLY_V1 | Zweck: Register-Nutzung für Weekly-Produktion und separate Quellen-Discovery trennen | Umfang: ersetzt die bisherige Discovery-immer-mitführen-Regel === -->
## Operative Nutzung für die nächsten Testläufe

### Weekly-Produktionslauf
1. CORE-HIGH immer zuerst aktiv prüfen
2. danach CORE-MID streng priorisiert prüfen
3. dann RECOVERY gezielt prüfen
4. DISCOVERY-SEED nicht aktiv als Weekly-Suchmodus nutzen
5. DISCOVERY-OPEN nicht aktiv als Weekly-Suchmodus nutzen
6. LOW-VALUE nicht aktiv priorisieren
7. EXCLUDE / GESPERRT nicht aktiv als Quelle verwenden

### Quellen-Discovery
Für separate Quellen-Discovery-Läufe bleiben erlaubt:
- DISCOVERY-SEED
- DISCOVERY-OPEN
- neue gute Quellen außerhalb des Registers

Wichtig:
Dieses Register soll die Weekly-Produktion systematischer machen, ohne gute neue Quellen grundsätzlich auszuschließen. Neue Quellen werden aber separat entdeckt, bewertet und erst danach bewusst hochgestuft.
<!-- === END BLOCK: OPERATIVE_NUTZUNG_PRODUKTION_ONLY_V1 === -->
