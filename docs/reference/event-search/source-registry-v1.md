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
Quellen oder Seitentypen, die gute Events retten können, obwohl sie nicht immer klassische Event-Detailseiten sind. Dazu zählen auch bewusst freigegebene gemeinnützige, soziale oder öffentlich geförderte Familien-/Jugendquellen mit hoher Nutzerrelevanz und geringer realistischer Abo-Zahlungsbereitschaft.

### DISCOVERY-SEED
Konkrete bekannte Startquellen für Discovery. Sie sind erlaubt und sinnvoll, aber noch nicht stabil genug für CORE oder RECOVERY.

### DISCOVERY-OPEN
Neue, bisher nicht klassifizierte Quellen außerhalb dieses Registers bleiben erlaubt, wenn sie das Regelwerk erfüllen.

### LOW-VALUE
Quellen, die formal oft korrekt sind, aber wiederholt eher schwache, nischige oder für die PWA wenig hilfreiche Treffer liefern.

### EXCLUDE / GESPERRT
Quellen, die strategisch, monetarisierungsseitig oder qualitativ nicht aktiv genutzt werden sollen.

---

<!-- === BEGIN BLOCK: GRUNDREGELN_REGISTER_PRODUKTION_ONLY_V1 | Zweck: Grundregeln an production-only Weekly-Lauf anpassen | Umfang: ersetzt Discovery-darf-nicht-null-Regel durch getrennte Produktions- und Discovery-<!-- === BEGIN BLOCK: GRUNDREGELN_REGISTER_PRODUKTION_ONLY_SPRACHPRIORITAET_V2 | Zweck: Grundregeln um deutsche Ziel-URL-Priorität bei NL-/mehrsprachigen Quellen ergänzen | Umfang: ersetzt Grundregeln für die Nutzung vollständig === -->
## Grundregeln für die Nutzung

1. Dieses Register ersetzt **nicht** das Regelwerk.
2. Gute neue Quellen außerhalb des Registers bleiben erlaubt.
3. FINAL entscheidet immer das Regelwerk, nicht dieses Register.
4. Eine Quelle wird nicht wegen ihres Status automatisch FINAL-fähig.
5. Im Weekly-Produktionslauf wird aktiv nur über `CORE-HIGH`, `CORE-MID` und `RECOVERY` gesucht.
6. `DISCOVERY-SEED` und `DISCOVERY-OPEN` werden im Weekly-Produktionslauf nicht aktiv als Suchmodus genutzt.
7. Für separate Quellen-Discovery-Läufe bleiben `DISCOVERY-SEED`, `DISCOVERY-OPEN` und neue Quellen außerhalb des Registers zulässig.
8. Bei niederländischen oder mehrsprachigen Quellen gilt zusätzlich: Wenn eine stabile deutsche Eventdetailseite oder gleichwertige deutsche Event-/Infoseite derselben offiziellen Quelle und derselben konkreten Instanz existiert, muss diese als `url` bevorzugt werden.
9. Deutsche Alternativen dürfen nur verwendet werden, wenn sie Titel/Eventbezug, Datum und Ort derselben konkreten Instanz belastbar belegen.
10. Google-Translate-, Browser-Übersetzungs-, Sprachumschalter-, Suchsnippet-, Tracking- oder Weiterleitungs-URLs sind keine zulässigen deutschen FINAL-URLs.
11. Wenn keine echte stabile deutsche Äquivalentseite existiert, bleibt die niederländische Originalseite zulässig.
12. Dieses Register steuert:
   - wo zuerst gesucht wird
   - wo Recovery sinnvoll ist
   - welche Quellen eher wenig bringen
   - welche Quellen nicht aktiv benutzt werden sollen
   - welche Quellen separat für Discovery beobachtet werden können
   - bei welchen Quellen die deutsche Ziel-URL-Prüfung besonders wichtig ist
<!-- === END BLOCK: GRUNDREGELN_REGISTER_PRODUKTION_ONLY_SPRACHPRIORITAET_V2 === -->

---

<!-- === BEGIN BLOCK: QUELLENREGISTER_HISTORIE_KONSOLIDIERUNG_V2 | Zweck: Quellenregister aus realer Events-/Inbox-/Archiv-/Benchmark-Historie konsolidieren | Umfang: ersetzt CORE-HIGH bis operative Nutzung vollständig === -->
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
  - mit Abstand stärkste historisch belegte Quelle
  - liefert lokale Bocholt-Kerntreffer
  - offizielle Quelle
  - wiederholt starke Highlights und Basisbestand
- Risiken:
  - vereinzelt Parameter-/Slug-Varianten
  - nicht jede offizielle Seite ist automatisch FINAL-fähig
  - englische Kalender-/News-Varianten können kanonisch schwächer sein
- Einsatzregel:
  - in jedem Weekly-Produktionslauf zuerst systematisch prüfen
  - bevorzugt deutsche, kanonische Detailseiten verwenden
  - bei mehreren URLs immer die stabilste erreichbare deutschsprachige Event-URL wählen
  - keine 404-/Suchsnippet-/Parameter-URL als FINAL übernehmen

<!-- === BEGIN BLOCK: QUELLE_AALTENDAGEN_SPRACHPRIORITAET_V1 | Zweck: AaltenDagen um deutsche Zielseitenprüfung und Instanzsicherheit ergänzen | Umfang: ersetzt Quelle AaltenDagen vollständig === -->
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
  - wiederholt gute Benchmark-Treffer
  - mehrere echte Tagesinstanzen möglich
- Risiken:
  - eine gemeinsame Seite kann mehrere Termine enthalten
  - Tagesblöcke können unterschiedliche Zeiten haben
  - deutsche Varianten können fehlen, generisch sein oder nur Übersetzungs-/Sprachumschalter-Charakter haben
- Einsatzregel:
  - in Saisonläufen aktiv mitführen
  - einzelne AaltenDag-Termine dürfen eigene Einträge sein, wenn Datum/Instanz getrennt sind
  - gleiche `source_url` ist zulässig, wenn Datum/Instanz unterschiedlich ist
  - `time` nur setzen, wenn eine eindeutige Startzeit für die konkrete Instanz belegt ist; sonst leer lassen
  - vor FINAL aktiv prüfen, ob eine stabile deutsche Event-/Infoseite derselben konkreten AaltenDagen-Instanz existiert
  - wenn eine stabile deutsche Seite dieselbe konkrete Tages-/Eventinstanz belegt, muss diese in `url`
  - wenn keine echte stabile deutsche Äquivalentseite existiert, darf die niederländische Originalseite als `url` verwendet werden
  - keine Google-Translate-, Browser-Übersetzungs-, Sprachumschalter- oder generische deutsche Startseite als `url` verwenden
<!-- === END BLOCK: QUELLE_AALTENDAGEN_SPRACHPRIORITAET_V1 === -->
---

## CORE-MID

### Stadt Rhede – offizielle Veranstaltungsdetailseiten
- Quelle / Muster:
  - `rhede.de/regional/veranstaltungen/detail-*`
- Status: CORE-MID
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel bis hoch
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - historisch mehrere Live-Treffer
  - offizielle Quelle
  - einzelne starke Stadt-/Kultur-/Innenstadttermine
- Risiken:
  - nicht jeder Treffer ist breit genug
  - Zeitangaben können Zeiträume sein und dürfen nicht ungeprüft in `time`
  - teils kleine Führungen, Krammärkte oder Standardformate
- Einsatzregel:
  - regelmäßig prüfen
  - nur starke oder öffentlich relevante Termine in FINAL
  - `time` nur als Startzeit `HH:MM`, keine Zeitspannen
  - kleine Führungen, Routine-/Standardmärkte und schwache Sonderfälle eher REVIEW oder weglassen

### Isselburg – offizielle Veranstaltungsseiten
- Quelle / Muster:
  - `isselburg.de/de/veranstaltungen/termine/*`
- Status: CORE-MID
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - historisch mehrere Live-Treffer
  - offizielle Quelle im Nahraum
- Risiken:
  - Canonical-/Instanz-URL-Probleme
  - wiederkehrende Führungen können falsche Datums-/URL-Zuordnung erzeugen
  - Scope/PWA-Nutzen nicht immer stark
- Einsatzregel:
  - aktiv prüfen, aber mit harter URL-/Instanzprüfung
  - keine datumsfremden URLs übernehmen
  - bei Führungs-/Serienformaten nur sauber belegte Einzelinstanzen

### Stadt Hamminkeln – offizielle Veranstaltungsseiten
- Quelle / Muster:
  - `hamminkeln.de/*`
- Status: CORE-MID
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - historisch mehrere Live-Treffer
  - kann im erweiterten Nahraum brauchbare Kultur-/Markt-/Bühnenformate liefern
- Risiken:
  - Scope zum Bocholt-Kern teils grenzwertig
  - nicht jeder Treffer ist für Bocholt-Nutzer relevant genug
- Einsatzregel:
  - regelmäßig, aber nachrangig prüfen
  - nur bei klarer Bocholt-/Nahraum-Relevanz und starkem Eventcharakter in FINAL

### Stadttheater Bocholt
- Quelle / Muster:
  - `stadttheater-bocholt.de/programm/*`
- Status: CORE-MID
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel bis hoch
- Technische Stabilität: hoch
- Strategierisiko: niedrig bis mittel
- Warum:
  - historisch mehrere gute Kultur-/Bühnen-Treffer
  - lokaler Bocholt-Bezug
- Risiken:
  - veranstalter-/ticketnah
  - nicht jede Programmliste ist automatisch eine saubere Eventinstanz
- Einsatzregel:
  - für starke Bühnen-/Kulturtermine aktiv prüfen
  - nur konkrete Eventdetailseiten übernehmen
  - bei Ticket-/Programmübersichten keine unsicheren Instanzen ableiten

### FARB Borken – Veranstaltungsdetailseiten
- Quelle / Muster:
  - `farb.borken.de/.../veranstaltungen/termine/*`
  - `farb.borken.de/de/veranstaltungen/*`
- Status: CORE-MID
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel bis hoch
- Strategierisiko: niedrig
- Warum:
  - historisch Live-, Review- und Benchmark-Treffer
  - offizielle Kulturquelle im Nahraum
- Risiken:
  - gemischte Qualität
  - Ausstellungen, Führungen und Museumsformate können formal korrekt, aber PWA-schwach sein
  - Vorschau-/Museumsseiten sind oft keine starken Eventseiten
- Einsatzregel:
  - aktiv prüfen, aber stark nach Breiteninteresse filtern
  - Open-Air-/Familien-/Sonderformate bevorzugen
  - reine Ausstellungen, kleine Führungen und Preview-Seiten nur bei starkem Besuchsanlass

### Borken 800 / offizielle Jubiläumsseiten
- Quelle / Muster:
  - `800.borken.de/*`
- Status: CORE-MID
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - historisch mehrere Live-Treffer
  - offizielle kampagnen-/jubiläumsbezogene Quelle
- Risiken:
  - saison-/kampagnenartig
  - nicht dauerhaft als normale Quelle belastbar
  - nicht jeder Termin hat Bocholt-Nähe oder Breiteninteresse
- Einsatzregel:
  - während aktiver Jubiläums-/Kampagnenphase prüfen
  - nur starke, öffentlich relevante Einzeltermine übernehmen
  - nach Ende der Kampagne runterstufen oder entfernen

---

## RECOVERY

### Stadt Bocholt – offizielle Event-News-/Info-Seiten
- Quelle / Muster:
  - `bocholt.de/*` außerhalb klassischer Kalenderdetailseiten mit klarem Eventfokus
  - `bocholt.de/kulturtage`
  - `bocholt.de/Interkulturellewoche`
  - thematische Bocholt-News-/Info-Seiten mit konkretem Eventbezug
- Status: RECOVERY
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel bis hoch
- Technische Stabilität: hoch
- Strategierisiko: niedrig
- Warum:
  - kann starke Events retten, die nicht als klassische Kalenderdetailseite vorliegen
- Risiken:
  - Save-the-date
  - Vorschau-/Teaserlogik
  - Orga-/CTA-/Anmeldeseiten
  - englische oder parameterlastige Varianten können schwächer sein
- Einsatzregel:
  - nur FINAL, wenn Titel, Datum, Ort und Besucherfokus im klaren sichtbaren Kernblock vorhanden sind
  - bevorzugt deutsche kanonische URL nutzen
  - generische Info-/Übersichtsseiten nur bei klar trennbarem Eventblock

### Stadt Rhede – offizielle Event-News-/Info-Seiten und offizieller Ticketkontext
- Quelle / Muster:
  - `rhede.de/*` mit event-spezifischem Fokus
  - `stadtrhede.ticket.io/*` nur bei offiziell erkennbarem Rhede-/Stadt-Kontext
- Status: RECOVERY
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig bis mittel
- Warum:
  - kann einzelne gute Kultur-/Bühnen-Termine retten
- Risiken:
  - Ticketseiten sind keine bevorzugten FINAL-Quellen
  - Detailbasis und offizielle Einordnung müssen sauber sein
- Einsatzregel:
  - zuerst bessere offizielle Rhede-Detailseite suchen
  - Ticketseite nur nutzen, wenn sie offiziell zuordenbar, instanzsicher und regelkonform ist
  - keine Ticketshop-Only-Treffer ohne belastbare Eventeinordnung

<!-- === BEGIN BLOCK: QUELLE_KOPPELKERK_SPRACHPRIORITAET_V1 | Zweck: Koppelkerk um deutsche Zielseitenprüfung bei Event-URLs ergänzen | Umfang: ersetzt Quelle Koppelkerk vollständig === -->
### Koppelkerk
- Quelle / Muster:
  - `koppelkerk.nl/agenda/*`
  - `koppelkerk.nl/evenementen/*`
- Status: RECOVERY
- Eventqualität: mittel
- PWA-Nutzen: niedrig bis mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - liefert echte Eventdetailseiten
  - wiederholt formal saubere Benchmark-Treffer
  - besonders Buchmärkte und einzelne Konzerte können relevant sein
- Risiken:
  - oft nischig
  - viele kleine Kultur-/Buch-/Vortragsformate mit begrenztem PWA-Nutzen
  - Ortsgranularität nicht feiner setzen als belegt
  - deutsche Alternativseiten können fehlen oder nur allgemeine Informationen liefern
- Einsatzregel:
  - gezielt prüfen, aber nur starke Treffer in FINAL
  - Bücherbörsen/Märkte eher geeignet
  - kleine Buchvorstellungen, Spezialkonzerte oder Nischenformate nur bei erkennbarem Breiteninteresse
  - wenn eine stabile deutsche Koppelkerk-Seite dieselbe konkrete Eventinstanz mit Datum und Ort belegt, diese als `url` nutzen
  - wenn keine stabile deutsche Eventseite existiert oder die deutsche Seite die konkrete Instanz nicht belegt, darf die niederländische Eventdetailseite verwendet werden
  - keine generische deutsche Koppelkerk-Info-/Startseite als Ersatz für eine niederländische Eventdetailseite verwenden
<!-- === END BLOCK: QUELLE_KOPPELKERK_SPRACHPRIORITAET_V1 === -->
<!-- === BEGIN BLOCK: QUELLE_SAISONALE_EINZELSEITEN_SPRACHPRIORITAET_V1 | Zweck: saisonale NL-/mehrsprachige Highlightquellen um deutsche URL-Priorität ergänzen | Umfang: ersetzt Quelle Saisonale starke Einzelseiten vollständig === -->
### Saisonale starke Einzelseiten im Suchradius
- Quelle / Muster:
  - `wijnfeest-aalten.nl/*`
  - `countryfair.de/*`
  - `bredevoortschittert.nl/*`
  - `cityart-bocholt.de/*`
  - `oldtimertreffenaalten.nl/*`
  - `grensmarktdinxperlo.com/*`
- Status: RECOVERY
- Eventqualität: mittel bis hoch
- PWA-Nutzen: mittel bis hoch
- Technische Stabilität: gemischt
- Strategierisiko: niedrig
- Warum:
  - historisch oder benchmarkseitig starke Einzel-/Saisonformate
  - oft echte Highlights im Radius
- Risiken:
  - keine normalen Kalenderquellen
  - Seiten können jährlich wechseln
  - Datums-/Jahresbezug muss exakt geprüft werden
  - Scope muss je Ort streng geprüft werden
  - NL-/mehrsprachige Seiten können deutsche Informationsseiten, niederländische Originalseiten und Übersetzungsvarianten parallel anbieten
- Einsatzregel:
  - in Backfill-/Saisonläufen gezielt prüfen
  - nur konkrete Jahresinstanz übernehmen
  - bei fehlendem Jahr, unsicherer Aktualität oder unklarer Terminseite REVIEW bzw. weglassen
  - nicht als generische Dauerquelle missverstehen
  - bei `bredevoortschittert.nl/*`, `wijnfeest-aalten.nl/*`, `oldtimertreffenaalten.nl/*` und `grensmarktdinxperlo.com/*` immer aktiv prüfen, ob eine stabile deutsche offizielle Event-/Infoseite derselben konkreten Instanz existiert
  - wenn eine stabile deutsche Seite dieselbe Jahres-/Eventinstanz mit Datum und Ort belegt, muss diese in `url`
  - wenn die deutsche Seite nur Startseite, Sprachumschalter, Browser-/Google-Translate-Variante oder allgemeine Image-/Info-Seite ist, bleibt die niederländische Originalseite zulässig
  - keine deutsche URL erzwingen, wenn sie die konkrete Instanz nicht selbst belastbar belegt
<!-- === END BLOCK: QUELLE_SAISONALE_EINZELSEITEN_SPRACHPRIORITAET_V1 === -->

### Öffentliche Kultur-/Museums-/Institutionenquellen
- Quelle / Muster:
  - `textilwerk.lwl.org/*`
  - offizielle öffentliche Museums-, Bibliotheks-, Kultur- oder Bildungseinrichtungsseiten im Suchradius
- Status: RECOVERY
- Eventqualität: mittel
- PWA-Nutzen: mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - kann starke öffentliche Kulturtermine liefern
- Risiken:
  - viele Ausstellungen, Führungen, Kurs- oder Bildungsformate
  - nicht jeder Termin hat Eventcharakter oder Breiteninteresse
- Einsatzregel:
  - nur konkrete, öffentlich relevante Eventinstanzen übernehmen
  - Ausstellungen und Dauerformate nur bei starkem Besuchsanlass
  - kleine Bildungs-/Kursformate zurückstellen

<!-- === BEGIN BLOCK: QUELLE_GEMEINNUETZIGE_FAMILIEN_JUGEND_RECOVERY_V1 | Zweck: freigegebene Low-Monetization-Familienquellen dauerhaft als RECOVERY verankern | Umfang: ergänzt RECOVERY vor DISCOVERY-SEED === -->
### Gemeinnützige Familien-/Jugendquellen mit geringer Abo-Erwartung
- Quelle / Muster:
  - `jugendfarm-mitdir.de/*`
  - `unser-ferienprogramm.de/bocholt/*`
  - `kinderschutzbund-bocholt.de/*`
  - `juboh.de/*`
  - `bocholt.de/*kulturrucksack*`
  - `bocholt.de/*stadtbibliothek*`
  - `jusina.de/*`
  - `cafe-karton.de/*`
  - `fabi-bocholt.de/*` nur für öffentliche Sondertermine, nicht als Kurs-Komplettquelle
- Status: RECOVERY
- Eventqualität: mittel bis hoch
- PWA-Nutzen: hoch für Familien, Kinder und Jugendliche
- Technische Stabilität: gemischt
- Strategierisiko: niedrig bis mittel
- Warum:
  - liefert alltagsnahe Familien-/Kinder-/Jugendtermine mit hohem Nutzwert
  - realistische Abo-Zahlungsbereitschaft ist bei gemeinnützigen, sozialen oder öffentlich geförderten Quellen eher gering
  - stärkt den Basisnutzen von Bocholt erleben, ohne kommerzielle Anbieter-/Venue-Monetarisierung direkt zu schwächen
- Risiken:
  - viele Angebote sind Kurs-, Betreuungs-, Ferienwochen- oder Anmeldeformate statt echte Events
  - einzelne Angebote können schnell ausgebucht sein
  - manche Quellen enthalten viele Serientermine mit begrenztem Breiteninteresse
  - FABI, JUNGE UNI und ähnliche Anbieter dürfen nicht als Massen-Kursquelle missverstanden werden
- Einsatzregel:
  - gezielt prüfen, aber nur starke öffentliche Einzeltermine übernehmen
  - bevorzugt übernehmen: Tag der offenen Tür, Familientag, Kindertrödelmarkt, öffentliche Feste, kostenlose oder öffentlich geförderte Kinder-/Jugendkultur, besondere Ferienhighlights
  - nicht übernehmen: normale Öffnungszeiten, Schließtage, reine Betreuungswochen, ausgebuchte Ferienangebote ohne offenen Besuchsanlass, interne Gruppen-/Vereinstermine, normale Kursreihen
  - bei Ferienprogrammen nur einzelne klar terminierte Highlights übernehmen; keine Massenübernahme aller Slots
  - `time` nur setzen, wenn eine eindeutige Startzeit der konkreten Instanz belegt ist
  - bei Anmeldepflicht in der Beschreibung sachlich knapp erwähnen, wenn öffentlich belegbar
  - bei kommerzielleren Kurs-/Bildungsanbietern streng prüfen, ob der öffentliche Eventnutzen stärker ist als die Anbieter-Promo
<!-- === END BLOCK: QUELLE_GEMEINNUETZIGE_FAMILIEN_JUGEND_RECOVERY_V1 === -->

---

## DISCOVERY-SEED

<!-- === BEGIN BLOCK: QUELLE_BREDEVOORT_NU_SPRACHPRIORITAET_V1 | Zweck: Discovery-Quelle Bredevoort.nu um deutsche Äquivalent-URL-Prüfung ergänzen | Umfang: ersetzt Quelle Bredevoort.nu vollständig === -->
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
- Risiken:
  - lokale Agenda-Portale können auf offizielle Veranstalterseiten verweisen
  - Sprach-/Kanonik-Mix möglich
  - nicht jede Agenda-Seite ist selbst die beste FINAL-URL
- Einsatzregel:
  - zur Quellen-Discovery erlaubt
  - im Weekly-Produktionslauf nicht aktiv suchen
  - FINAL nur nach offizieller oder gleichwertig event-spezifischer Verifikation
  - bei Bredevoort-/NL-Kandidaten immer prüfen, ob eine stabile deutsche offizielle Zielseite derselben Eventinstanz existiert
  - wenn eine stabile deutsche offizielle Event-/Infoseite existiert und die konkrete Instanz belegt, diese als `url` nutzen
  - wenn nur das Agenda-Portal oder eine niederländische Originalseite die Instanz belastbar belegt, deutsche generische Seiten nicht ersatzweise verwenden
<!-- === END BLOCK: QUELLE_BREDEVOORT_NU_SPRACHPRIORITAET_V1 === -->
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
  - kann neue Kandidaten und neue Quellen liefern
- Einsatzregel:
  - nur Quellen-Discovery
  - nicht als Weekly-Hauptsuchmodus
  - FINAL nur nach offizieller oder gleichwertig event-spezifischer Verifikation

### Ticketseiten / Ticketshops allgemein
- Quelle / Muster:
  - Ticketshops mit Eventbezug
  - `reservix.de/*`
  - sonstige externe Ticketplattformen
- Status: DISCOVERY-SEED
- Eventqualität: gemischt
- PWA-Nutzen: gemischt
- Technische Stabilität: mittel
- Strategierisiko: mittel
- Warum:
  - oft guter Frühindikator
- Einsatzregel:
  - nie blind direkt FINAL
  - im Weekly-Produktionslauf nicht aktiv als Quelle nutzen
  - nur als Hinweis nutzen und nach offizieller Detailseite suchen
  - Ausnahme nur bei eindeutig offizieller kommunaler Ticketseite und instanzsicherem Eventbezug

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

Wichtig:
- Im Weekly-Produktionslauf wird DISCOVERY-OPEN nicht aktiv als Suchmodus genutzt.
- Im Aufbau-/Backfill-Kontext dürfen neue Quellen als Nebenfund dokumentiert werden.
- Die Quelle selbst wird erst nach Bewertung ins Register übernommen.

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
  - historisch bzw. benchmarkseitig eher schwache Spezial-/Workshop-Treffer
  - häufig zu speziell
  - oft wenig Breitenrelevanz
- Einsatzregel:
  - nicht aktiv priorisieren
  - nur bei außergewöhnlich hoher öffentlicher Relevanz berücksichtigen

### Museums-/Vorschau-/Ausstellungs-Unterseiten mit dünnem Eventcharakter
- Quelle / Muster:
  - `museum/Vorschau/*`
  - Preview-/Vorschau-Seiten
  - reine Ausstellungsankündigungen ohne starken Eventzug
- Status: LOW-VALUE
- Eventqualität: formal oft okay, inhaltlich oft zu schwach
- PWA-Nutzen: niedrig bis mittel
- Technische Stabilität: mittel
- Strategierisiko: niedrig
- Warum:
  - erhöht Risiko für formal korrekte, aber schwache FINALs
  - in Benchmarks mehrfach als problematische Fülltreffer sichtbar
- Einsatzregel:
  - nicht aktiv priorisieren
  - nur mit starkem Besucherfokus und klarer Relevanz berücksichtigen

### Kleine Spezialformate / Workshops / Bildungsformate / Führungen
- Quelle / Muster:
  - Workshops
  - Resilienzformate
  - kleine Spezialtermine
  - kleine Führungen
  - Beratungs-/Informationsformate
- Status: LOW-VALUE
- Eventqualität: gemischt
- PWA-Nutzen: meist niedrig
- Technische Stabilität: gemischt
- Strategierisiko: niedrig
- Warum:
  - häufig nicht stark genug für FINAL
  - erzeugt bei freier Suche viele formal korrekte, aber schwache Kandidaten
- Einsatzregel:
  - nicht aktiv priorisieren
  - nur bei außergewöhnlicher lokaler oder öffentlicher Relevanz berücksichtigen

### Direkte Vereins-/Chor-/Kleinstveranstalterseiten
- Quelle / Muster:
  - `shanty-chor-bocholt.de/*`
  - ähnliche direkte Kleinstveranstalter-/Vereinsseiten
- Status: LOW-VALUE
- Eventqualität: gemischt
- PWA-Nutzen: niedrig bis mittel
- Technische Stabilität: gemischt
- Strategierisiko: mittel
- Warum:
  - kann einzelne echte Termine liefern
  - oft aber nischig, veranstalternah oder nicht breit genug
- Einsatzregel:
  - nicht aktiv priorisieren
  - nur bei starkem öffentlichem Eventcharakter oder neutraler Drittquelle berücksichtigen

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
  - `alte-molkerei.info/*`
  - venue-eigene Seiten / direkte Promo
- Status: EXCLUDE / GESPERRT
- Warum:
  - ausdrücklich geschützte Venue
  - historisch zwar Eventsignale vorhanden, aber strategisch nicht als normale Quelle bespielen
- Einsatzregel:
  - nicht aktiv suchen
  - direkte Venue-Quelle nicht als Weekly-Quelle nutzen
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

### Test-/Dummy-/nicht reale Quellen
- Quelle / Muster:
  - `example.com/*`
  - manuelle Smoke-Test-Datensätze
- Status: EXCLUDE / GESPERRT
- Warum:
  - keine reale Quelle
  - nur technische Testdaten
- Einsatzregel:
  - niemals für Suche, FINAL oder Quellenbewertung nutzen

---

## Pflege-Regeln

### Quelle hochstufen
Eine Quelle kann hochgestuft werden, wenn sie in mehreren Läufen:
- wiederholt starke FINALs liefert
- technisch stabil ist
- strategisch unkritisch ist
- keine auffälligen URL-/Instanzfehler produziert

### Quelle runterstufen
Eine Quelle sollte runtergestuft werden, wenn sie:
- wiederholt nur schwache, formal korrekte Treffer liefert
- wenig PWA-Nutzen bringt
- technisch problematisch ist
- viele Review-/Verwerfen-Fälle erzeugt
- Monetarisierungs- oder Venue-Risiken verstärkt

### Neue Quelle aufnehmen
Eine neue Quelle wird zunächst als:
- `DISCOVERY-SEED`
oder
- `DISCOVERY-OPEN` Kandidat

behandelt, nicht sofort als CORE.

Ausnahme:
Wenn eine Quelle aus realer Bestandshistorie bereits mehrfach starke Live-/FINAL-Treffer geliefert hat, darf sie direkt als `CORE-MID` oder `RECOVERY` klassifiziert werden.

Zusatz-Ausnahme:
Strategisch bewusst freigegebene gemeinnützige, soziale oder öffentlich geförderte Familien-/Jugendquellen mit hoher Nutzerrelevanz und geringer realistischer Abo-Zahlungsbereitschaft dürfen direkt als `RECOVERY` geführt werden, wenn die Einsatzregel des Blocks `Gemeinnützige Familien-/Jugendquellen mit geringer Abo-Erwartung` eingehalten wird.

---

## Operative Nutzung für die nächsten Testläufe

### Aufbau-/Backfill-Produktionslauf
1. CORE-HIGH zuerst systematisch prüfen
2. danach CORE-MID systematisch prüfen
3. danach RECOVERY gezielt prüfen
4. bekannte saisonale starke Einzelseiten aus RECOVERY mitnehmen
5. DISCOVERY-SEED nicht aktiv als Hauptsuchmodus nutzen
6. DISCOVERY-OPEN nicht aktiv als Hauptsuchmodus nutzen
7. LOW-VALUE nicht aktiv priorisieren
8. EXCLUDE / GESPERRT nicht aktiv als Quelle verwenden

Ziel:
- den aktuellen 180-Tage-Zeitraum im Suchradius systematisch abgrasen
- gute Quellen dürfen mehrere echte Instanzen liefern
- keine künstliche Quellenbegrenzung
- keine schwachen Fülltreffer

### Normaler Weekly-Delta-Lauf
1. CORE-HIGH zuerst prüfen
2. CORE-MID prüfen
3. RECOVERY prüfen
4. Dedupe gegen Bestand, Inbox, Archiv und Manual-Puffer strikt anwenden
5. nur neue Delta-Kandidaten liefern

Ziel:
- neue gute Events seit dem letzten Lauf finden
- keine Wiederholung bereits entschiedener Fälle
- weniger Backfill, mehr Delta-Qualität

### Quellen-Discovery
Für separate Quellen-Discovery-Läufe bleiben erlaubt:
- DISCOVERY-SEED
- DISCOVERY-OPEN
- neue gute Quellen außerhalb des Registers

Wichtig:
Dieses Register soll die Weekly-Produktion systematischer machen, ohne gute neue Quellen grundsätzlich auszuschließen. Neue Quellen werden aber separat entdeckt, bewertet und erst danach bewusst hochgestuft.
<!-- === END BLOCK: QUELLENREGISTER_HISTORIE_KONSOLIDIERUNG_V2 === -->
