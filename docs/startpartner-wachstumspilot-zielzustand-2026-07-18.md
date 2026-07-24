# Startpartner-Wachstumspilot – vollständiger End-to-End-Zielvertrag

Stand: 2026-07-24
Status: fachlich und architektonisch konsolidierter Zielzustand; noch nicht vollständig implementiert
Geltungsbereich: Akquise, Kandidatensteuerung, Aufnahme, Pilotvereinbarung, Anbieterzugang, kostenlose Pilotberechtigung, Onboarding, Aktivierung, Inhalte, Wirkungsmessung, Betreuung, Abschluss und Übergang in reguläre Produkte

## 1. Rolle und Verbindlichkeit

Dieses Dokument ist der vollständige Zielvertrag für den Startpartner-Wachstumspiloten.

Es beschreibt:

- den aktuellen belegten Ausgangszustand;
- den fachlichen End-to-End-Prozess;
- die notwendigen Datenobjekte und Zustände;
- die Abgrenzung zwischen fachlicher Source of Truth und operativen Projektionen;
- die kostenlose sechsmonatige Pilotberechtigung;
- die Verknüpfung zu Anbieteraccount, Inhalten und Wirkungsmessung;
- Kommunikations-, Ausnahme- und Abschlussregeln;
- die Umsetzungsreihenfolge und den Gesamtnachweis vor aktiver Akquise.

Es ist keine Behauptung, dass der Zielprozess bereits technisch vorhanden oder produktiv freigegeben ist.

Kanonische Rollen:

- `Produktvertrag.md` definiert ausschließlich bereits gültige Produktmechanik. Bis zur technischen und öffentlichen Umsetzung bleibt der Startpartner-Pilot dort bewusst außerhalb der aktiven Tarifmechanik.
- `COMMERCIAL_STRATEGY.md` begründet das kommerzielle Modell.
- `ROADMAP.md` priorisiert die Umsetzung.
- `docs/architecture/SYSTEM_MAP.md` beschreibt Systemrollen, Datenhoheit und Projektionen.
- `docs/external-resource-matrix.md` beschreibt externe Ressourcen und Schreibgrenzen.
- dieses Dokument definiert den vollständigen fachlichen und architektonischen Zielzustand.

Ein späterer Teil-Workpack darf nicht als vollständige Umsetzung bezeichnet werden, solange die Gesamtabnahme aus Abschnitt 35 nicht erfüllt ist.

## 2. Belegter aktueller Ausgangszustand

### 2.1 Öffentliche Anfrage

Die öffentliche Route `/startpartner/` ist aktuell ein schlanker Anfrageweg.

Sie erfasst:

- Organisation oder Anbieter;
- E-Mail-Adresse;
- Kurzbeschreibung oder Link;
- Datenschutzbestätigung.

Die Übermittlung erfolgt aktuell an Formspree.

Die aktuelle Anfrage:

- ist keine Aufnahmezusage;
- ist keine Pilotvereinbarung;
- erstellt keinen Anbieteraccount;
- erstellt keine kostenlose Berechtigung;
- erstellt kein Stripe-Abonnement;
- benötigt keine Zahlungsart;
- veröffentlicht keinen Inhalt;
- erzeugt nach belegtem Repository-Stand keinen kanonischen strukturierten Startpartner-Kandidaten in der eigenen Datenbank.

### 2.2 Bereits vorhandene technische Bausteine

Vorhanden sind:

- Organizer- und Submission-Datenbank;
- Magic-Link-Zugang und Anbieterportal-Sessions;
- Event- und Aktivitätseinreichungen;
- reguläre Stripe-Subscriptions;
- Veröffentlichungsberechtigungen und Verbrauchsbuchungen;
- objekt- und anbieterbezogene Nutzwertmetriken;
- generische `control_cases` und `control_case_events` für operative Aufgaben und Entscheidungen;
- öffentliche Event- und Aktivitätsdarstellung;
- redaktionelle Review- und Veröffentlichungsprozesse.

Diese Bausteine dürfen wiederverwendet werden, sind aber noch kein Startpartner-End-to-End-Prozess.

### 2.3 Aktuelle Lücken

Noch nicht vollständig vorhanden und nachgewiesen sind:

- kanonische Startpartner-Kandidaten;
- gemeinsame Source of Truth für Selbstmeldung und aktive Ansprache;
- Qualifizierungs-, Kapazitäts- und Aufnahmeentscheidungen;
- Pilotvereinbarung und Versionierung der bestätigten Bedingungen;
- kostenlose zeitlich begrenzte Pilotberechtigung ohne Stripe-Abo;
- eindeutige Zuordnung von Kandidat, Organizer, Pilot, Inhalten und Messdaten;
- Aktivierungs- und Enddatum;
- Partnerdistribution und deren Nachweis;
- Kontrollpunkte und Aufgaben;
- Pause, Abbruch, Störung und Wiederaufnahme;
- Abschlussbericht, Tarifentscheidung und geordnetes Ende;
- Kohorten-Stop-Regel als auswertbarer Systemzustand;
- vollständiger E2E-Test vor aktiver Akquise.

## 3. Produktdefinition und öffentliche Sprache

### 3.1 Verbindliche Bezeichnung

Bevorzugte öffentliche Begriffe:

- `Startpartner-Pilot`;
- `kostenlose sechsmonatige Pilotphase`;
- `Startpartnerplatz anfragen`;
- `Pilotbedingungen bestätigen`;
- `Pilot starten`;
- `nach sechs Monaten gemeinsam entscheiden`.

Nicht als Hauptbegriff verwenden:

- `kostenloses Abo`;
- `Gratis-Tarif`;
- `kostenlose Mitgliedschaft`;
- `automatische Verlängerung`;
- `kostenlos buchen`;
- `kostenlos veröffentlichen`;
- `Testabo`, wenn daraus eine automatische Zahlung vermutet werden kann.

Im Gespräch darf der Vorgang umgangssprachlich als kostenloses Startpartner-Abo bezeichnet werden. Produkt- und Systemvertrag behandeln ihn jedoch als ausdrücklich angenommenen, befristeten Pilot ohne Stripe-Subscription.

### 3.2 Was der Pilot ist

Der Startpartner-Pilot ist:

- einmalig;
- kuratiert;
- auf eine kleine erste Kohorte begrenzt;
- sechs Monate ab belegter Aktivierung;
- für den Partner kostenlos;
- ohne Zahlungsart und ohne Stripe-Abonnement;
- mit klar vereinbartem Leistungsumfang;
- mit Anbieterzugang, Pflegeweg und Wirkungsmessung;
- mit verbindlichen Kontrollpunkten;
- mit ausdrücklicher Entscheidung am Ende.

### 3.3 Was der Pilot nicht ist

Der Pilot ist:

- kein dauerhaft kostenloser Tarif;
- kein offener Gratisweg für beliebige Anbieter;
- keine Veröffentlichungsgarantie;
- keine gekaufte Empfehlung;
- keine bessere öffentliche Darstellung;
- keine automatische Umwandlung in ein kostenpflichtiges Produkt;
- kein Ersatz für den redaktionellen Tippweg;
- kein Umweg um ein bereits eindeutig passendes reguläres Produkt ohne zusätzlichen Lern- oder Wachstumswert.

## 4. Ziele und Erfolgssystem

Der Pilot soll gleichzeitig:

1. hochwertige und aktuelle lokale Inhalte gewinnen;
2. relevante Inhaltslücken schließen;
3. Partnerreichweite für Bocholt erleben aktivieren;
4. messbaren Anbieterwert erzeugen;
5. Einrichtungs- und Betreuungsaufwand real bestimmen;
6. die Passung der regulären Tarife prüfen;
7. belastbare Argumente für eine freiwillige kostenpflichtige Fortführung erzeugen.

Nicht als alleiniger Erfolg gelten:

- Zahl eingegangener Anfragen;
- Zahl kostenlos aufgenommener Partner;
- Zahl veröffentlichter Einträge;
- reine Impressionen;
- nicht zuordenbare Reichweitenbehauptungen;
- eine Fortführung, die nur durch automatische oder missverständliche Verlängerung entsteht.

Zentrale Erfolgsgrößen:

- Anzahl aktivierter geeigneter Startpartner;
- zusätzliche hochwertige Inhalte;
- zusätzliche qualifizierte Nutzung;
- belegter Partnerdistributionsbeitrag;
- Aufwand pro aktivem Partner und dauerhaft nutzbarem Inhalt;
- freiwillige Fortführungsentscheidungen;
- Gründe für Fortführung, Ablehnung oder Ende.

## 5. Akteure und Verantwortlichkeiten

### 5.1 Bocholt erleben

Bocholt erleben verantwortet:

- Kandidatenaufnahme und Identität;
- Qualifizierung und nachvollziehbare Entscheidung;
- Kapazitätssteuerung;
- Pilotvereinbarung und dokumentierte Bestätigung;
- Account- und Berechtigungsanlage;
- redaktionelle Prüfung;
- Aufbereitung geeigneter Inhalte;
- Pflege- und Änderungswege;
- Messzuordnung;
- Kontrollpunkte und Auswertung;
- rechtzeitige Vorbereitung des Pilotendes;
- geordnetes Ende oder Übergang in einen regulären Tarif.

### 5.2 Kandidat beziehungsweise Startpartner

Der Kandidat beziehungsweise Partner verantwortet:

- korrekte Organisations- und Kontaktdaten;
- belastbare Inhaltsquellen;
- zeitnahe Änderungs- und Absageinformationen;
- einen verantwortlichen Ansprechpartner;
- Bestätigung der Pilotbedingungen;
- Einhaltung des redaktionellen Qualitätsstandards;
- konstruktives Feedback;
- den vereinbarten realistischen Reichweitenbeitrag;
- eine ausdrückliche Entscheidung zur Fortführung.

### 5.3 Redaktion

Die Redaktion entscheidet weiterhin unabhängig über:

- fachliche Eignung;
- Quellenlage;
- Vollständigkeit;
- Darstellung;
- Veröffentlichung;
- Ablehnung, Pausierung oder Entfernung ungeeigneter oder veralteter Inhalte.

Startpartnerstatus ersetzt keine redaktionelle Prüfung.

### 5.4 System

Das System darf:

- Fristen berechnen;
- Aufgaben erzeugen;
- fehlende Pflichtdaten anzeigen;
- Kapazität aus belegten Zuständen berechnen;
- Erinnerungen vorbereiten;
- Messwerte aggregieren;
- Widersprüche blockieren.

Das System darf nicht automatisch:

- einen Kandidaten aufnehmen;
- Pilotbedingungen im Namen eines Partners akzeptieren;
- Inhalte freigeben;
- einen bezahlten Tarif abschließen;
- eine Zahlungsart hinterlegen;
- eine kostenpflichtige Verlängerung starten;
- einen Partner ohne dokumentierte Entscheidung beenden oder konvertieren.

## 6. Vollständiger End-to-End-Prozess

```text
Selbstmeldung oder gezielte Identifizierung
→ kanonischer Kandidat
→ Vorqualifizierung
→ Kontakt oder Eingangsbestätigung
→ Rückmeldung und Datenergänzung
→ fachliche Qualifizierung
→ Kapazitäts- und Kohortenprüfung
→ Aufnahmeentscheidung
→ Pilotbedingungen senden
→ ausdrückliche Bestätigung
→ Organizer anlegen oder verknüpfen
→ Pilot und kostenlose Pilotberechtigung anlegen
→ Onboarding
→ erster veröffentlichungsfähiger Inhalt
→ Messzuordnung
→ Reichweitenstart vorbereiten
→ Aktivierung
→ sechsmonatige Pilotphase
→ Kontrollpunkte nach Aktivierung, etwa 30 Tagen, etwa 90 Tagen und vor Ende des fünften Monats
→ Abschlussauswertung
→ ausdrückliche Tarifentscheidung oder geordnetes Ende
→ Kohorten- und Stop-Regel aktualisieren
```

Keine Stufe darf stillschweigend übersprungen werden. Ein späterer Zustand benötigt den belegten vorherigen Zustand und die zugehörigen Pflichtfelder.

## 7. Source of Truth und Systemrollen

### 7.1 Fachliche Source of Truth

Kanonische fachliche Objekte:

1. `StartpartnerCandidate`;
2. `StartpartnerDecision`;
3. `StartpartnerPilot`;
4. `StartpartnerPilotScope`;
5. `StartpartnerCheckpoint`;
6. `StartpartnerCommunication`;
7. `StartpartnerContentLink`;
8. `StartpartnerMetricSnapshot`.

Diese Objekte liegen in der eigenen Submission-/Anbieter-Datenbank oder einer fachlich gleichwertigen eigenen Datenbankdomäne.

### 7.2 Operative Projektion

`control_cases` und `control_case_events` sind ausschließlich:

- Aufgabenprojektion;
- Entscheidungsprojektion;
- Aufmerksamkeits- und Fristenansicht;
- Audit der operativen Bearbeitung.

Sie sind nicht die alleinige Source of Truth für:

- Kandidatenprofil;
- Pilotbedingungen;
- Serviceumfang;
- Berechtigungen;
- Aktivierungs- und Enddatum;
- Inhaltszuordnung;
- Messdaten;
- Abschlussentscheidung.

### 7.3 Bestehende Zielsysteme

- `organizers`: angenommener Partner und Portalidentität;
- `submissions`: Event- und Aktivitätseinreichungen;
- `publication_entitlements`: technische Veröffentlichungsberechtigungen, sofern der Zielscope sauber abbildbar ist;
- `subscriptions`: ausschließlich reguläre Stripe-Mitgliedschaften;
- `value_metric_daily`: aggregierte Wirkungsmessung;
- Event-/Activity-Owner: öffentliche Projektion nach redaktioneller Freigabe;
- SMTP/Mail: Kommunikation;
- Formspree: aktueller Übergangseingang, im Zielzustand kein dauerhafter fachlicher Owner.

## 8. Kandidatenidentität und Deduplizierung

Jeder Kandidat besitzt eine stabile interne ID.

Mindestens zu normalisieren:

- Organisationsname;
- primäre E-Mail-Adresse;
- Website oder Hauptquelle;
- vorhandene Organizer-ID;
- Herkunftskanal;
- externe Anfrage- oder Kontaktreferenz.

Deduplizierungsregeln:

1. gleiche vorhandene Organizer-ID bedeutet derselbe Kandidatenkontext;
2. gleiche normalisierte E-Mail plus gleiche Organisation ist ein starker Treffer;
3. gleiche Domain plus sehr ähnlicher Organisationsname ist ein Prüfhinweis, keine automatische Zusammenführung;
4. mehrere Ansprechpartner derselben Organisation dürfen einem Kandidaten zugeordnet werden;
5. Unsicherheit erzeugt einen manuellen Zusammenführungsfall;
6. eine erneute Anfrage aktualisiert nicht stillschweigend eine abgeschlossene Entscheidung.

Wiederholte Übermittlung mit derselben stabilen Anfrage-ID muss idempotent sein.

## 9. Eingangskanäle und Cutover

### 9.1 Selbstmeldung

Die öffentliche Selbstmeldung erzeugt im Zielzustand direkt einen strukturierten Kandidaten über einen eigenen First-Party-Endpunkt.

Pflichtverhalten:

- serverseitige Validierung;
- Datenschutzbestätigung mit Version und Zeitpunkt;
- idempotente Anfrageidentität;
- sichere Erfolgsantwort;
- keine Anbieter-, Berechtigungs- oder Zahlungsanlage;
- interne Aufgabenprojektion;
- nachvollziehbare Eingangsbestätigung, sofern Mailversand aktiviert ist.

### 9.2 Aktive Ansprache

Gezielte Ansprache startet mit einem intern angelegten Kandidaten.

Vor der ersten Nachricht müssen vorhanden sein:

- Organisation;
- belegter lokaler Bezug;
- öffentliche Kontaktquelle;
- Grund der Ansprache;
- erwarteter Inhalts- oder Reichweitenhebel;
- verantwortlicher interner Bearbeiter;
- Kommunikationsgrundlage und zulässiger Kanal.

Aktive Ansprache und Selbstmeldung verwenden danach dieselben Kandidatenzustände und Entscheidungskriterien.

### 9.3 Formspree-Cutover

Der aktuelle Formspree-Pfad ist eine Übergangslösung.

Vor Cutover sind festzulegen:

- eindeutiger Cutover-Zeitpunkt;
- Behandlung bereits eingegangener Anfragen;
- Export- oder manuelle Übernahmemöglichkeit;
- Abgleich gegen bereits vorhandene Kandidaten;
- Abschaltung des alten Writers;
- keine dauerhafte Doppelübermittlung;
- Erfolgs- und Fehlerkommunikation im First-Party-Pfad.

Nach belegtem Cutover darf Formspree nicht als zweiter fachlicher Writer weiterlaufen.

Nicht verifiziert ist, ob und wie viele historische Startpartner-Anfragen aktuell bei Formspree vorliegen.

## 10. Kandidatenzustände

Verbindliche fachliche Zustände:

| Zustand | Bedeutung |
|---|---|
| `new` | Kandidat wurde identifiziert oder selbst gemeldet |
| `prequalifying` | Mindestdaten und offensichtliche Passung werden geprüft |
| `contact_pending` | Kontaktaufnahme oder Eingangsbestätigung ist vorzubereiten |
| `awaiting_response` | Rückmeldung des Kandidaten fehlt |
| `qualifying` | strategische und operative Passung wird bewertet |
| `needs_information` | konkrete Pflichtinformation fehlt |
| `decision_ready` | Entscheidungsgrundlage ist vollständig |
| `accepted_pending_terms` | Pilotplatz ist fachlich reserviert; Bedingungen noch nicht bestätigt |
| `waitlisted` | geeignet, aber aktuell keine unmittelbare Kapazität |
| `routed_to_regular_product` | bewusste Weiterleitung in einen regulären Weg |
| `rejected` | nicht geeignet oder nicht vereinbar |
| `withdrawn` | Kandidat zieht Anfrage oder Teilnahme zurück |
| `expired` | Prozess endet nach dokumentierter Frist ohne notwendige Rückmeldung |

Regeln:

- jeder Zustandswechsel besitzt Zeitpunkt, Akteur, Grund und optional Evidence;
- `accepted_pending_terms` ist noch kein aktiver Pilot;
- nur `accepted_pending_terms` reserviert Kapazität;
- `waitlisted`, `routed_to_regular_product`, `rejected`, `withdrawn` und `expired` erzeugen keine Pilotberechtigung;
- eine Wiederaufnahme benötigt ein dokumentiertes neues Ereignis.

## 11. Kandidatendaten

Mindestens zu speichern:

### Identität und Kontakt

- Kandidaten-ID;
- Organisation;
- Ansprechpartner;
- E-Mail;
- optional Telefon;
- Website;
- lokaler Bezug;
- Herkunftskanal;
- Eingangs- oder Identifikationszeitpunkt;
- vorhandene Organizer-ID, falls vorhanden.

### Angebotsprofil

- Partnerart;
- Events, Aktivitäten, Orte oder Kombination;
- vorhandene Quellen;
- Aktualisierungsfrequenz;
- erwartete Inhaltsmenge;
- relevante Zielgruppen;
- Reichweitenkanäle;
- redaktionelle Besonderheiten.

### Qualifizierung

- Mindestkriterien;
- Inhaltshebel;
- Reichweitenhebel;
- Nutzerbedarf;
- Pflegefähigkeit;
- Kooperationsbereitschaft;
- Tarifpotenzial;
- erwarteter Einrichtungsaufwand;
- erwarteter Betreuungsaufwand;
- mögliche reguläre Zielprodukte;
- offene Fragen;
- Evidence und Begründungen.

### Entscheidung

- Entscheidungsstatus;
- Ergebnis;
- Entscheidungstext;
- Entscheider;
- Entscheidungszeitpunkt;
- Kapazitätsstand zum Entscheidungszeitpunkt;
- reservierter Platz bis;
- Alternativweg;
- Ablehnungs- oder Wartelistengrund.

## 12. Qualifizierungsvertrag

Jede Bewertungsdimension erhält:

- Status `unknown`, `weak`, `adequate` oder `strong`;
- kurze Begründung;
- Evidence oder Quellenhinweis;
- Zeitpunkt;
- Bearbeiter.

Es gibt keinen rein automatischen Gesamtscore, der Aufnahme garantiert.

Mindestblocker für `decision_ready`:

- lokaler Bezug geklärt;
- Organisation und Ansprechpartner belastbar;
- Inhaltstyp und Quelle geklärt;
- redaktionelle Grundpassung geklärt;
- erwarteter Inhalts- und Reichweitenhebel bewertet;
- Betreuungsaufwand eingeschätzt;
- möglicher regulärer Zielweg benannt;
- offene rechtliche oder technische Ausschlussgründe geklärt;
- Kapazitätsprüfung möglich.

## 13. Aufnahmeentscheidungen

Zulässige Ergebnisse:

- `startpartner_pilot`;
- `waitlist`;
- `event_membership`;
- `activity_presence`;
- `automatic_source_review`;
- `single_event`;
- `editorial_tip`;
- `reject`.

Eine Startpartneraufnahme benötigt:

- belegten zusätzlichen Lern- oder Wachstumswert;
- passende Kohortenwirkung;
- vertretbaren Betreuungsaufwand;
- möglichen regulären Zielweg;
- freie oder bewusst reservierte Kapazität;
- keine ungeklärte harte Ausschlussfrage.

Ein reguläres Produkt ist vorzuziehen, wenn:

- der Bedarf bereits eindeutig durch einen vorhandenen Tarif gedeckt ist;
- kein zusätzlicher Pilotlernwert besteht;
- der Partner keine Pilotbegleitung benötigt;
- der Pilot nur zur Umgehung einer Zahlung dienen würde.

## 14. Kapazität, Reservierung und Warteliste

### 14.1 Harte Obergrenze

Maximal acht Partner dürfen gleichzeitig einen Platz belegen.

Als belegter Platz zählen:

- Kandidaten im Zustand `accepted_pending_terms`;
- Piloten in `onboarding`;
- Piloten in `activation_ready`;
- Piloten in `active`;
- Piloten in `paused`;
- Piloten in `closing`.

### 14.2 Operativer Aufnahmestopp

Der konservative Soft-Stop greift bei sechs belegten Plätzen.

Begründung:

- sechs von acht Plätzen entsprechen 75 Prozent und bilden die technisch eindeutige Schwelle unmittelbar unter den strategischen etwa 80 Prozent;
- zwei Plätze bleiben für bereits weit fortgeschrittene Kandidaten und unerwarteten Betreuungsaufwand verfügbar;
- die harte Obergrenze acht bleibt unverändert.

Bei sechs oder mehr belegten Plätzen:

- keine neue unmittelbare Aufnahme ohne dokumentierte Kapazitätsentscheidung;
- geeignete Kandidaten werden in der Regel auf die Warteliste gesetzt;
- bereits reservierte oder laufende Partner werden nicht automatisch beendet.

### 14.3 Reservierung

Nach Aufnahmeentscheidung wird ein Platz höchstens 30 Kalendertage für die Bestätigung und das Onboarding reserviert.

Eine Verlängerung:

- ist nicht automatisch;
- benötigt Grund, neuen Termin und Entscheider;
- darf die harte Obergrenze nicht verletzen.

Ohne Bestätigung oder belastbaren Fortschritt wird die Reservierung freigegeben und der Kandidat bewusst auf `waitlisted`, `expired` oder `withdrawn` gesetzt.

### 14.4 Warteliste

Die Warteliste speichert:

- Eignungsgrund;
- Prioritätsgrund;
- frühestmögliche Neubewertung;
- Kontaktstatus;
- letzte Bestätigung des Interesses;
- mögliche reguläre Alternative.

Wartelistenplätze sind keine Pilotplätze und begründen keinen Anspruch auf spätere Aufnahme.

## 15. Pilotvereinbarung und ausdrückliche Annahme

Ein Pilot darf erst angelegt werden, wenn der Kandidat die Pilotbedingungen ausdrücklich bestätigt hat.

Zu speichern sind:

- Version der Pilotbedingungen;
- Inhalt oder unveränderliche Referenz der bestätigten Bedingungen;
- bestätigende Person;
- Organisation;
- Bestätigungszeitpunkt;
- Bestätigungskanal;
- vereinbarter Serviceumfang;
- vereinbarte Quellen- und Pflegewege;
- vereinbarter Reichweitenbeitrag;
- vorgesehener Aktivierungszeitraum;
- Datenschutz- und Kommunikationshinweise;
- Hinweis auf keine automatische kostenpflichtige Verlängerung.

Die Bestätigung:

- ist keine Stripe-Zahlung;
- benötigt keine Zahlungsart;
- darf nicht durch vorangekreuzte oder implizite Zustimmung entstehen;
- darf nicht aus bloßer Nutzung oder aus der ersten Anfrage abgeleitet werden.

Vor aktiver Akquise müssen Pilotbedingungen, Datenschutztexte, Auftrags- und Speichergrenzen juristisch beziehungsweise fachlich geprüft sein. Dieses Dokument ersetzt keine Rechtsprüfung.

## 16. Organizer und Portalzugang

Nach bestätigten Bedingungen wird:

1. ein vorhandener Organizer eindeutig verknüpft oder
2. genau ein neuer Organizer angelegt.

Dubletten sind vor Anlage zu prüfen.

Der Portalzugang erfolgt über die vorhandene Magic-Link- und Sessionlogik.

Mindestanforderungen:

- funktionierende E-Mail;
- eindeutige Organizer-ID;
- verantwortlicher Ansprechpartner;
- korrekte Organisation;
- Portalzugang erfolgreich getestet;
- Pilotstatus im Anbieterbereich verständlich, sobald die UI implementiert ist;
- keine reguläre Stripe-Mitgliedschaft nur zur Darstellung des Piloten erzeugen.

## 17. Pilotobjekt und Zustände

Verbindliche Pilotzustände:

| Zustand | Bedeutung |
|---|---|
| `onboarding` | Bedingungen bestätigt, Einrichtung läuft |
| `activation_ready` | alle Aktivierungsbedingungen sind belegt |
| `active` | sechsmonatige Pilotphase läuft |
| `paused` | Leistung ist bewusst vorübergehend eingeschränkt |
| `closing` | Abschluss und Entscheidung werden vorbereitet |
| `converted` | reguläres Produkt wurde ausdrücklich abgeschlossen |
| `ended_without_conversion` | Pilot endete geordnet ohne kostenpflichtige Fortführung |
| `terminated` | Pilot wurde vorzeitig mit dokumentiertem Grund beendet |

Zusätzliche Gesundheitsanzeige:

- `healthy`;
- `attention`;
- `blocked`.

Gesundheit ersetzt nicht den fachlichen Zustand.

## 18. Pilotdaten

Mindestens zu speichern:

- Pilot-ID;
- Kandidaten-ID;
- Organizer-ID;
- Kohortenkennung;
- Zustand und Gesundheit;
- Bedingungen-Version und Bestätigung;
- Serviceumfang;
- reguläre Zielprodukte;
- reserviert bis;
- Onboardingbeginn;
- Aktivierungsbereitschaft;
- Aktivierungszeitpunkt;
- geplantes Enddatum;
- tatsächliches Enddatum;
- Pausen und Gründe;
- interner Owner;
- Partneransprechpartner;
- Quelle und Pflegeweg;
- Reichweitenbeitrag;
- Messziel und Reporting-ID;
- Abschlussentscheidung;
- Konversionsreferenz oder Endgrund.

## 19. Kostenlose Pilotberechtigung

### 19.1 Grundsatz

Die sechsmonatige Leistung wird nicht als Stripe-Subscription modelliert.

Es gilt:

- kein Stripe-Checkout;
- keine Zahlungsart;
- kein Preisobjekt;
- keine Testphase einer später automatisch kostenpflichtigen Subscription;
- keine automatische Rechnung;
- keine automatische Verlängerung.

### 19.2 Technischer Zielvertrag

Die Berechtigung wird als eigene befristete Pilotberechtigung geführt.

Sie referenziert:

- Pilot-ID;
- Organizer-ID;
- Start- und Enddatum;
- fachliche Scopes;
- vereinbarte Limits;
- Status;
- Quelle `startpartner_pilot`;
- vollständige Auditspur.

Bestehende `publication_entitlements` dürfen wiederverwendet werden, wenn:

- Event- und Aktivitätsscope eindeutig getrennt werden können;
- die Pilot-ID stabil referenziert wird;
- keine Stripe-Subscription erforderlich ist;
- Zeitraum und Status fail-closed geführt werden;
- Doppelberechtigungen verhindert werden.

Ist das bestehende Schema dafür nicht eindeutig, erhält die Pilotberechtigung einen eigenen Owner und projiziert nur benötigte Berechtigungen in bestehende Systeme.

### 19.3 Serviceumfang und Tarifspiegel

Jeder Pilot erhält einen ausdrücklich festgelegten Umfang, der den voraussichtlich passenden regulären Produkten entspricht.

Vor der Annahme werden ein oder mehrere reguläre Zielmodelle als `target_plan_key` dokumentiert. Der kostenlose Pilot spiegelt deren fachlichen Leistungsumfang, ohne bereits ein kostenpflichtiges Produkt zu erzeugen.

Standardspiegel:

| Zielmodell | Pilotumfang |
|---|---|
| `starter` | bis zu 3 redaktionell freigegebene Event-Veröffentlichungen je Pilotmonat |
| `active` | bis zu 8 redaktionell freigegebene Event-Veröffentlichungen je Pilotmonat |
| `unlimited` | viele Termine im ausdrücklich dokumentierten üblichen Rahmen; kein uneingeschränktes Leistungsversprechen |
| `activity_basic` | 1 gleichzeitig veröffentlichte Aktivitätspräsenz |
| `activity_plus` | bis zu 3 gleichzeitig veröffentlichte Aktivitätspräsenzen |
| Kombination | Summe der ausdrücklich vereinbarten Event- und Aktivitätsscopes |

Pilotmonate beginnen am lokalen Aktivierungsdatum und laufen jeweils bis zum Vortag desselben Kalendertags im Folgemonat. Existiert der Tag im Folgemonat nicht, gilt dessen letzter Kalendertag. Der sechste Pilotmonat endet am geplanten Pilotenddatum.

Für `unlimited` werden vor Aktivierung zusätzlich dokumentiert:

- erwartete monatliche Terminmenge;
- Quellenumfang;
- zulässiger redaktioneller Betreuungsrahmen;
- Überprüfungsregel bei erheblicher Überschreitung.

Mögliche zusätzliche Scopes:

- Prüfung einer automatischen Eventquelle;
- Änderungs-, Absage- und Aktualisierungsservice;
- Anbieterportal und Wirkungsmessung.

Der Pilot ist nicht automatisch unbegrenzt.

Mindestens zu dokumentieren:

- Zieltarif oder Zieltarife;
- Eventumfang;
- Aktivitätsumfang;
- Quellenumfang;
- Betreuungsumfang;
- ausgeschlossene Leistungen;
- Periodenlogik;
- Vorgehen bei Überschreitung.

Abweichungen vom Standardspiegel benötigen eine ausdrückliche Begründung, einen konkreten Erkenntniszweck und klar begrenzte Limits.

## 20. Onboarding

Verbindliche Onboarding-Checkliste:

- Pilotbedingungen bestätigt;
- Organizer verknüpft;
- Ansprechpartner bestätigt;
- Portalzugang getestet;
- Pilotberechtigung angelegt und zurückgelesen;
- Serviceumfang sichtbar und widerspruchsfrei;
- relevante Quellen erfasst;
- Pflege- und Änderungsweg vereinbart;
- Rechte an gelieferten Inhalten und Bildern geklärt;
- erster veröffentlichungsfähiger Inhalt vorbereitet;
- redaktionelle Prüfung möglich;
- Messzuordnung vorbereitet;
- Partner-Reichweitenbeitrag konkret vereinbart;
- Aktivierungszieltermin festgelegt;
- offene Blocker gleich null.

Nicht abgeschlossene Punkte verhindern `activation_ready`.

## 21. Inhalte und Quellen

### 21.1 Bestehende Prozesse wiederverwenden

Startpartner-Inhalte nutzen dieselben fachlichen Wege wie reguläre Inhalte:

- Event-Submissions;
- Aktivitäts-Submissions;
- redaktionelle Prüfung;
- Event-/Activity-Projektion;
- Änderungen und Absagen;
- Quellenprüfung und gegebenenfalls automatische Übernahme.

Es entsteht kein zweites paralleles Veröffentlichungssystem.

### 21.2 Pilotzuordnung

Jeder relevante Inhalt benötigt:

- Organizer-ID;
- Pilot-ID oder stabile Pilot-Referenz;
- Contenttyp;
- Content-ID oder Submission-ID;
- Veröffentlichungsstatus;
- Zuordnungszeitpunkt;
- Messzuordnung;
- optional Quellreferenz.

### 21.3 Redaktionelle Unabhängigkeit

Pilotberechtigung bedeutet nur, dass die vereinbarte Anbieterleistung kostenlos erbracht werden kann.

Sie bedeutet nicht:

- automatische Freigabe;
- garantierte Anzahl veröffentlichter Inhalte;
- Ausnahme von Qualitätsregeln;
- bessere Platzierung;
- dauerhafte Sichtbarkeit ungeeigneter oder veralteter Inhalte.

## 22. Aktivierungsvertrag und sechs Monate

### 22.1 Aktivierungsbedingungen

Ein Pilot wird nur aktiviert, wenn gleichzeitig belegt sind:

1. funktionierender Anbieterzugang;
2. aktive und korrekt zugeordnete Pilotberechtigung;
3. mindestens ein relevanter veröffentlichter Inhalt;
4. technisch funktionierende Organizer- und Inhaltsmesszuordnung;
5. vorbereiteter Partner-Reichweitenstart;
6. keine harten Onboarding-Blocker.

### 22.2 Datumslogik

Zu speichern:

- `activated_at` als eindeutiger Zeitpunkt;
- `activation_date_local` in `Europe/Berlin`;
- `planned_end_date` sechs Kalendermonate nach Aktivierungsdatum;
- `actual_end_at` bei tatsächlichem Abschluss.

Bei einem Aktivierungstag, der im Zielmonat nicht existiert, gilt der letzte Kalendertag des Zielmonats.

Beispiel:

- Aktivierung 31. August;
- geplantes Ende am letzten gültigen Februartag.

### 22.3 Verlängerung und Pause

Es gibt keine automatische Verlängerung.

Eine Ausnahme benötigt:

- dokumentierten Grund;
- ausdrückliche Entscheidung;
- neues Enddatum;
- aktualisierte Bedingungen, falls erforderlich.

Eine Pause verlängert den Pilot nicht automatisch.

Technisch verursachte längere Ausfälle können nur durch bewusste Einzelfallentscheidung ausgeglichen werden. Partnerverursachte Inaktivität begründet keine automatische Verlängerung.

## 23. Wirkungsmessung und Attribution

### 23.1 Messbare Produktwirkung

Soweit technisch verfügbar und datenschutzkonform:

- Detail-Aufrufe;
- Website- oder Ticket-Klicks;
- Route- und Maps-Klicks;
- Anbieter-CTA-Klicks;
- Teilungen und Linkkopien;
- Zahl und Typ veröffentlichter Inhalte;
- Inhaltsaktualität;
- Einrichtungs- und Betreuungsaufwand.

### 23.2 Zuordnung

Messwerte müssen eindeutig referenzieren:

- Organizer;
- Inhalt;
- Pilot;
- Zeitraum.

Vor Aktivierung ist ein read-only Messpreflight erforderlich.

Messausfall darf nicht als null Wirkung interpretiert werden. Er erzeugt einen Datenqualitäts- oder Technikblocker.

### 23.3 Partnerdistribution

Der vereinbarte Reichweitenbeitrag wird als konkrete Vereinbarung gespeichert, zum Beispiel:

- Website-Link;
- Social-Media-Beitrag;
- Newsletter;
- Mitgliederkommunikation;
- QR-Code;
- Vor-Ort-Hinweis;
- Teilung konkreter Seiten.

Zu dokumentieren:

- Kanal;
- geplantes Datum;
- Ziel-Link oder Kampagnenreferenz;
- Status;
- Nachweis;
- beobachtbare Zugriffe, soweit zulässig.

Es gibt keine pauschale Postingpflicht. Der Beitrag muss aber vor Aktivierung konkret und realistisch sein.

### 23.4 Zulässige Aussagen

Zulässig:

- gemessene Aktionen;
- veröffentlichte Inhalte;
- nachvollziehbare Klicks;
- ausgewiesener Zeitraum;
- klare Datenqualitätsgrenzen.

Nicht zulässig:

- Besucherzahlen vor Ort aus Klickdaten ableiten;
- Buchungen oder Umsatz ohne Beleg behaupten;
- eindeutige Personen aus Interaktionen ableiten;
- Partnerwirkung behaupten, wenn Attribution fehlt;
- Test- oder interne Klicks als Partnererfolg präsentieren.

## 24. Kommunikation und Zustellstatus

Verbindliche Kommunikationsereignisse:

- Eingangsbestätigung;
- Rückfrage;
- Kontaktaufnahme;
- Wartelisteninformation;
- Ablehnung oder Alternativweg;
- Aufnahme und Pilotbedingungen;
- Erinnerung an ausstehende Bestätigung;
- Onboardinginformationen;
- Aktivierungsbestätigung;
- 30-Tage-Kontakt;
- 90-Tage-Zwischenbewertung;
- Vorbereitung des Pilotendes;
- Abschlussauswertung;
- Tarifangebot oder geordnetes Ende.

Jede Kommunikation speichert:

- Kommunikationsart;
- Empfänger;
- Vorlagen- oder Inhaltsversion;
- Auslöser;
- Zeitpunkt;
- sendender Akteur;
- Zustellstatus;
- Fehler;
- Bezug zu Kandidat oder Pilot.

Ein Mailfehler darf den fachlichen Zustand nicht stillschweigend als erfolgreich fortschreiben.

Staging verwendet ausschließlich Testempfänger oder einen sicheren No-Send-Modus.

### 24.1 Fristen und Erinnerungsgrenzen

Für fehlende Qualifizierungsinformationen gilt standardmäßig:

- Rückmeldefrist: 14 Kalendertage;
- erste Erinnerung frühestens nach 7 Tagen;
- zweite und letzte Erinnerung frühestens nach 12 Tagen;
- ab Tag 15 bewusste Entscheidung `expired`, `waitlisted` oder manuell begründete Fristverlängerung.

Für bestätigungsbedürftige Pilotbedingungen innerhalb der 30-tägigen Platzreservierung gilt:

- erste Erinnerung frühestens nach 7 Tagen;
- zweite und letzte Erinnerung frühestens nach 21 Tagen;
- vor Ablauf der Reservierung eine bewusste Freigabe-, Verlängerungs- oder Endentscheidung.

Für jeden Kommunikationsanlass werden höchstens zwei automatische Erinnerungen versendet. Weitere Kontakte benötigen eine bewusste manuelle Entscheidung. Zustellfehler zählen nicht als erfolgreiche Erinnerung.

## 25. Kontrollpunkte

### Aktivierung

Zu prüfen:

- Account;
- Berechtigung;
- Inhalt;
- Messung;
- Distribution;
- offene Blocker.

### Etwa 30 Tage

Zu prüfen:

- technische Stabilität;
- Aktualität der Inhalte;
- Nutzung des Pflegewegs;
- erste Messdaten;
- Zustell- oder Accountprobleme;
- realer Betreuungsaufwand;
- notwendige Korrekturen.

### Etwa 90 Tage

Zu prüfen:

- belastbare Inhalts- und Nutzungsentwicklung;
- stärkste Inhalte;
- Reichweitenbeitrag;
- Partnerfeedback;
- Betreuungsaufwand;
- frühe Tarifpassung;
- Fortführungsrisiken.

### Vor Ende des fünften Monats

Zu prüfen:

- vollständige Datenlage;
- noch offene Mess- oder Inhaltslücken;
- Tarifempfehlung;
- Kosten-Nutzen-Verhältnis;
- Termin für Abschlussgespräch;
- gewünschter Fortführungsweg.

### Nach sechs Monaten

Zu prüfen:

- veröffentlichte Inhalte;
- aktuelle Inhalte;
- gemessene Wirkung;
- Partnerdistribution;
- Pflege- und Betreuungsaufwand;
- Probleme und Lernpunkte;
- Partnerfeedback;
- empfohlener Tarif;
- ausdrückliche Partnerentscheidung;
- End- oder Konversionspostconditions.

Jeder Kontrollpunkt besitzt Status `scheduled`, `due`, `completed`, `skipped_with_reason` oder `overdue`.

## 26. Ausnahme- und Fehlerfälle

### Fehlende Rückmeldung

- konkrete Rückfrage und Frist dokumentieren;
- höchstens die in Abschnitt 24.1 definierten zwei Erinnerungen;
- danach bewusst `expired`, `waitlisted` oder `withdrawn`;
- keine endlose offene Reservierung.

### Onboarding stagniert

- Blocker und Owner dokumentieren;
- Reservierungsfrist prüfen;
- Kapazität gegebenenfalls freigeben;
- keine Aktivierung ohne vollständige Bedingungen.

### Portalzugang fehlerhaft

- Pilot nicht aktivieren;
- Fehlerfall erzeugen;
- keine Mess- oder Laufzeitbehauptung.

### Messzuordnung fehlerhaft

- Pilot nicht aktivieren beziehungsweise Messstatus blockieren;
- Messausfall nicht als null Wirkung werten;
- Korrektur und Rücklesen erforderlich.

### Inhalt ungeeignet

- normale redaktionelle Ablehnung;
- Pilotberechtigung erzwingt keine Veröffentlichung;
- wiederholte strukturelle Nichteignung kann Pilotgesundheit verschlechtern oder zur Beendigung führen.

### Veraltete oder falsche Daten

- Partner zur Korrektur auffordern;
- betroffene Inhalte pausieren oder korrigieren;
- schwere oder wiederholte Verstöße dokumentieren;
- gegebenenfalls vorzeitige Beendigung.

### Kapazität ausgeschöpft

- keine stille Überbelegung;
- Warteliste oder regulärer Alternativweg;
- bestehende Zusagen bleiben nachvollziehbar.

### Vorzeitiger Abbruch

Mögliche Gründe:

- Partner zieht sich zurück;
- wesentliche Bedingungen werden nicht erfüllt;
- fachliche Eignung entfällt;
- wiederholte Datenprobleme;
- unvertretbarer Betreuungsaufwand;
- technische Leistung kann nicht zuverlässig erbracht werden;
- beidseitige Vereinbarung.

Vorzeitige Beendigung benötigt Grund, Zeitpunkt, Auswirkungen auf Inhalte, Berechtigung, Portal, Messung und Kommunikation.

## 27. Abschlussbericht

Der Abschlussbericht enthält mindestens:

- Pilotzeitraum;
- vereinbarten Serviceumfang;
- veröffentlichte Inhalte;
- Inhaltsqualität und Aktualität;
- Wirkungsmessung mit Zeitraum und Grenzen;
- Partnerdistributionsbeitrag;
- Einrichtungs- und Betreuungsaufwand;
- technische und organisatorische Probleme;
- Partnerfeedback;
- empfohlene reguläre Produkte;
- Begründung der Empfehlung;
- ausdrückliche Entscheidung;
- End- oder Konversionsdatum.

Der Bericht ist für interne Entscheidung und verständliche Partnerkommunikation geeignet. Er enthält keine unbelegten Erfolgsbehauptungen.

## 28. Tarifentscheidung und Konversion

Mögliche reguläre Zielwege:

- Event-Mitgliedschaft Starter;
- Event-Mitgliedschaft Aktiv;
- Event-Mitgliedschaft Dauerhaft;
- Aktivitätspräsenz Basis;
- Aktivitätspräsenz Plus;
- Kombination mehrerer Produkte;
- automatische Quellenanbindung mit passendem Produkt;
- kein kostenpflichtiger Fortführungsweg.

Konversion benötigt:

1. konkrete Tarifempfehlung;
2. verständliche Leistung und Preis;
3. ausdrückliche Zustimmung;
4. normalen regulären Checkout oder sonstigen gültigen Vertragsweg;
5. erfolgreich angelegte reguläre Berechtigung;
6. Rücklesen;
7. geordnetes Ende der Pilotberechtigung ohne Doppelbelegung.

Es gibt:

- keine automatische Stripe-Subscription;
- keine automatische Zahlungsart;
- keine stillschweigende Verlängerung;
- keine rückwirkende Berechnung der Pilotmonate.

## 29. Geordnetes Ende ohne Konversion

Wenn kein reguläres Produkt gewählt wird:

- endet die kostenlose Pilotberechtigung spätestens zum bestätigten Enddatum;
- werden keine neuen kostenlosen Anbieterleistungen mehr zugesagt;
- bleiben Abschlussbericht und Entscheidung dokumentiert;
- wird der Anbieterzugang auf einen zulässigen Status gesetzt;
- werden neue Einreichungs- und Pflegewege entsprechend gesperrt oder auf reguläre Wege verwiesen;
- werden bestehende Inhalte nach ihrem normalen Produkt- und Redaktionstyp behandelt.

Für Inhalte gilt:

- vergangene Events folgen dem normalen Eventlebenszyklus;
- laufende oder künftige Events bleiben nur erhalten, wenn ihre fachliche und produktbezogene Grundlage besteht;
- Aktivitätspräsenzen mit erforderlicher laufender Anbieterleistung werden ohne reguläre Fortführung geordnet beendet oder deaktiviert;
- redaktionell unabhängig übernommene Inhalte dürfen nur nach bewusster Re-Klassifizierung als redaktionelle Inhalte fortbestehen;
- keine öffentliche Kennzeichnung als ehemaliger oder bezahlter Startpartner.

## 30. Kohorten- und Stop-Regel

Die kostenlose Aufnahme der ersten Kohorte endet, wenn mindestens:

1. sechs Partner den Pilot vollständig oder nahezu vollständig durchlaufen haben;
2. drei unterschiedliche Partnerarten getestet wurden;
3. für vier Partner belastbare Wirkungsdaten vorliegen;
4. Einrichtungs- und Betreuungsaufwand realistisch bewertet werden können;
5. für alle abgeschlossenen Partner eine Fortführungsentscheidung vorliegt;
6. Tarifpassung oder konkreter Anpassungsbedarf erkennbar ist.

Zusätzlich gelten:

- Soft-Stop ab sechs belegten Plätzen;
- harte Obergrenze acht;
- keine Verlängerung des Gratisprogramms nur wegen schwacher Zahlungsquote;
- bei schwacher Fortführung zuerst Zielgruppe, Nutzen, Leistung, Preis und Prozess prüfen;
- weitere kostenlose Piloten nur mit neuem Erkenntnisziel, eigener Grenze, Laufzeit und Stop-Regel.

Nach Abschluss der ersten Kohorte wird die öffentliche Seite bewusst auf einen der Zustände gesetzt:

- Pilotphase läuft, aktuell Warteliste;
- Pilotphase abgeschlossen, Interesse vormerken;
- reguläre Anbieterwege;
- neuer begrenzter Pilot mit neuem Erkenntnisziel.

## 31. Datenschutz, Recht und Sicherheit

Vor aktiver Akquise müssen geschlossen sein:

- Rechtsgrundlage und Datenschutzhinweis für Selbstmeldung;
- Rechtsgrundlage für aktive Ansprache;
- Pilotbedingungen;
- Nutzungs- und Inhaltsrechte;
- Kommunikationszustimmung und zulässige Erinnerungen;
- Aufbewahrungs- und Löschfristen;
- Umgang mit Widerruf oder Rücknahme der Einwilligung;
- Datenexport und Löschprozess;
- Rollen und Zugriffsrechte;
- Protokollierung administrativer Entscheidungen.

Grundregeln:

- Datenminimierung;
- keine unnötigen Zahlungsdaten;
- keine Secrets im Frontend;
- keine sensiblen Freitexte ohne Bedarf;
- getrennte Staging- und Live-Daten;
- keine echten Partnernachrichten in Staging;
- keine Live-Testaufnahme;
- keine automatische Fachentscheidung aus Scores;
- keine dauerhafte Speicherung ohne festgelegte Frist.

Dieses Dokument legt keine juristisch verbindliche Aufbewahrungsdauer fest. Die konkrete Frist ist ein Pflicht-Gate vor dem ersten produktiven Kandidaten.

## 32. Umgebungs- und Schreibvertrag

### Staging

- eigene Staging-Datenbank;
- ausschließlich synthetische Kandidaten und Piloten;
- Testempfänger oder No-Send-Mail;
- keine Stripe-Zahlung;
- keine Live-Formspree-Anfrage;
- keine Veröffentlichung in Live;
- stabile IDs, Rücklesen und Cleanup;
- ein vollständiger synthetischer Lebenszyklus vor realem Fall.

### Live

- keine Testschreibaktion;
- erster echter Kandidat oder Partner nur nach ausdrücklicher fachlicher Freigabe;
- Nachrichten, Accountanlage, Berechtigung und Veröffentlichung sind echte Nebenwirkungen;
- jede manuelle Admin-Mutation benötigt stabile Identität, Vorherzustand, Rücklesen und Rollback.

## 33. Umsetzungsarchitektur

Zielbeziehungen:

```text
StartpartnerCandidate
  ├─ CandidateDecision
  ├─ CandidateCommunication
  └─ control_case projection
          |
          v
StartpartnerPilot
  ├─ Organizer
  ├─ PilotScope / PilotEntitlement
  ├─ ContentLinks -> Submissions -> Events / Activities
  ├─ MetricSnapshots -> value_metric_daily
  ├─ Checkpoints
  ├─ Communications
  └─ FinalDecision -> regular product or ordered end
```

Es entstehen keine parallelen Systeme für:

- Anbieterlogin;
- Event- oder Aktivitätseinreichung;
- redaktionelle Veröffentlichung;
- Wirkungsmessung;
- Stripe-Zahlung;
- allgemeine Aufgabensteuerung.

Neue fachliche Owner sind nur für Kandidat, Pilot, Scope, Kontrollpunkte, Kommunikation und Abschlussentscheidung zulässig.

## 34. Verbindliche Umsetzungsreihenfolge

### Gate 0 – Produkt-, Rechts- und Datenvertrag

- Produktgrenze in `Produktvertrag.md` bei tatsächlicher Implementierung aktivieren;
- Pilotbedingungen und Datenschutz klären;
- reale Staging-/Live-Schemata lesen;
- Formspree-Bestand und Cutover klären;
- physisches Datenmodell und Owner festlegen.

### Gate 1 – Kandidat und Intake

- First-Party-Kandidat;
- Selbstmeldung und aktive Ansprache;
- Deduplizierung;
- Zustände;
- Audit;
- Control-Center-Projektion;
- sichere Migration oder bewusste Behandlung bestehender Formspree-Anfragen.

### Gate 2 – Qualifizierung, Entscheidung und Kapazität

- Bewertungsfelder;
- Entscheidungsergebnisse;
- Reservierung;
- Soft-Stop;
- harte Obergrenze;
- Warteliste;
- Alternativwege.

### Gate 3 – Bedingungen, Organizer und Pilotberechtigung

- ausdrückliche Bestätigung;
- Organizer-Verknüpfung;
- Pilotobjekt;
- Serviceumfang;
- kostenlose befristete Berechtigung;
- Portalzugang;
- keine Stripe-Subscription.

### Gate 4 – Onboarding und Aktivierung

- Checkliste;
- Quellen und Pflegeweg;
- erster Inhalt;
- Messzuordnung;
- Distribution;
- Aktivierungsdatum;
- Enddatum.

### Gate 5 – Betrieb und Wirkung

- Partnerdashboard oder geeignete Statusansicht;
- Messwerte;
- Kontrollpunkte;
- Kommunikationsereignisse;
- Ausnahmefälle;
- Betreuungsaufwand.

### Gate 6 – Abschluss und Konversion

- Abschlussbericht;
- Tarifempfehlung;
- ausdrückliche Entscheidung;
- normaler Checkout;
- Pilotende;
- Berechtigungs-Cutover;
- keine Doppelberechtigung;
- Stop-Regel.

### Gate 7 – Gesamtnachweis vor aktiver Akquise

- vollständiger synthetischer Staging-Lebenszyklus;
- negative und Wiederholungsfälle;
- Mail-No-Send oder Testzustellung;
- Berechtigungs- und Mess-Readback;
- Pause, Abbruch, Ende und Konversion;
- dokumentierte Rechts- und Datenschutzfreigabe;
- bewusste Freigabe der ersten echten Ansprache.

Ein einzelnes früheres Gate darf nicht als vollständig operationalisierter Startpartner-Pilot bezeichnet werden.

## 35. Gesamtabnahme

Der Startpartner-Pilot gilt erst als vollständig umgesetzt, wenn:

- Selbstmeldung und aktive Ansprache denselben Kandidatenprozess nutzen;
- Kandidatenidentität und Deduplizierung belegt sind;
- jeder Kandidat Zustand, Bewertung, Entscheidung und Audit besitzt;
- Kapazität, Reservierung und Warteliste fail-closed funktionieren;
- Pilotbedingungen ausdrücklich und versioniert bestätigt werden;
- der Pilot ohne Stripe-Subscription und ohne Zahlungsart angelegt wird;
- Organizer und Portalzugang funktionieren;
- Serviceumfang und Berechtigung eindeutig sind;
- Onboarding vollständig geprüft wird;
- Aktivierung und sechsmonatiges Ende deterministisch geführt werden;
- Inhalte und Quellen den bestehenden redaktionellen Prozessen folgen;
- Organizer, Pilot, Inhalte und Messwerte verknüpft sind;
- Partnerdistribution dokumentiert ist;
- Kommunikations- und Zustellfehler sichtbar sind;
- Kontrollpunkte und Ausnahmefälle bearbeitbar sind;
- Abschlussbericht und Tarifempfehlung erzeugt werden können;
- Konversion nur nach ausdrücklicher Zustimmung erfolgt;
- Ende ohne Konversion vollständig und nachvollziehbar ist;
- Stop-Regel auswertbar ist;
- Staging synthetisch vollständig geprüft ist;
- keine aktive Akquise vor dem Gesamtnachweis begonnen wurde.

## 36. Bewusst offene Vorimplementierungs-Gates

Vor dem ersten Runtime-Write sind read-only zu klären:

1. tatsächlicher Staging- und Live-Datenbankschemastand;
2. vorhandene spätere Migrationen für Entitlement-Scopes;
3. aktuelle Organizer- und Inhaltsattribution der Wirkungsmessung;
4. Formspree-Bestand, Exportfähigkeit und Cutover;
5. konkrete Pilotbedingungen und Datenschutztexte;
6. Aufbewahrungs- und Löschfristen;
7. genaue Serviceumfangsabbildung auf reguläre Zielprodukte;
8. Mailvorlagen, Empfängergrenzen und Staging-No-Send;
9. physische Tabellen-, API- und UI-Owner;
10. vollständiger Staging-Write-, Readback- und Cleanup-Vertrag.

Diese Punkte sind keine versteckten Restarbeiten. Sie sind ausdrückliche Gates des späteren Implementierungs-Workpacks.
