<!-- === BEGIN FILE: Produktvertrag.md | Zweck: kanonischer KI-lesbarer Produktvertrag für Event-Veröffentlichungen, Event-Mitgliedschaften und Aktivitätspräsenzen; Umfang: komplette Datei === -->

# Produktvertrag – Bocholt erleben

**Version:** 2.2  
**Status:** verbindlich  
**Gültig ab:** sofort  
**Gültigkeit:** bis bewusst geändert  
**Optimierung:** KI-lesbar, eindeutig befolgbar, produkt- und umsetzungsleitend

---

## 1. Zweck dieses Dokuments

Dieses Dokument ist die kanonische Produktgrundlage für folgende Produktbereiche von **Bocholt erleben**:

1. Einzeltermin-Veröffentlichungen
2. Event-Mitgliedschaften
3. automatische Event-Anbindungen
4. Aktivitätspräsenzen

Dieses Dokument definiert verbindlich:

1. öffentliche Produktlogik
2. Tariflogik
3. Prüf-, Zahlungs- und Veröffentlichungsabläufe
4. Abgrenzung zwischen Events und Aktivitäten
5. interne Zähl- und Verbrauchslogik
6. Statuslogik
7. zulässige und unzulässige öffentliche Begriffe
8. Grundregeln für UI, Backend, Review-Inbox und Anbieterbereich

Dieses Dokument ist die **Single Source of Truth** für die Produktlogik.

Andere Dokumente dürfen dieses Dokument nicht widersprechen.

`MASTER.md` darf strategische Richtung beschreiben.  
`ENGINEERING.md` darf technische Arbeitsregeln beschreiben.  
Dieses Dokument definiert die verbindliche Produktlogik.

---

<!-- === BEGIN BLOCK: PRODUKTVERTRAG_PUBLIC_LEGAL_ALIGNMENT_2026_06_29 | Zweck: verankert den Roadmap-Punkt Recht-/Verkaufsseiten als Ableitung aus dem Produktvertrag; Umfang: keine Tarif- oder Flow-Aenderung === -->
## Aktueller Abgleich 2026-06-29 – oeffentliche Rechts-/Verkaufsseiten

Die validierte Produktreife-Roadmap fuehrt `Recht-/Verkaufsseiten fuer bezahlte Produkte haerten` als P2-Workpack. Dieser Punkt aendert die Produktlogik dieses Vertrags nicht.

Ziel ist nur, dass oeffentliche Seiten und Hilfetexte dieselben Regeln fuer Laien verstaendlich ausdruecken:

1. Zahlung ist keine automatische Veroeffentlichungsgarantie.
2. Redaktionelle Pruefung bleibt verbindlich.
3. Ablehnung, Zahlungsfreigabe, Laufzeit, Kuendigung/Aenderung und ggf. Widerruf/AGB muessen klar erklaert sein.
4. Interne Begriffe wie Token, Kontingent oder Entitlement bleiben intern und werden nicht zur oeffentlichen Produktbotschaft.
5. Datenschutz-/Tracking-Hinweise muessen separat konsistent mit der tatsaechlichen Technik sein.

Dieser Abgleich ist ein Dokumentations-/Kommunikations-Workpack, kein neuer Produkt- oder Tarifentwurf.
<!-- === END BLOCK: PRODUKTVERTRAG_PUBLIC_LEGAL_ALIGNMENT_2026_06_29 === -->

## 2. Kanonische Grundregeln

### 2.1 Allgemeine Produktregeln

1. Bocholt erleben verkauft öffentlich keinen reinen Listingeintrag.
2. Bocholt erleben verkauft öffentlich keine Anzeige.
3. Bocholt erleben verkauft öffentlich keine Premium-Platzierung.
4. Bocholt erleben bietet kuratierte Veröffentlichungs- und Präsenzservices.
5. Inhalte werden redaktionell geprüft.
6. Inhalte werden nicht automatisch durch Zahlung veröffentlicht.
7. Zahlung ist keine Veröffentlichungsgarantie.
8. Veröffentlichung erfolgt erst nach redaktioneller Freigabe.
9. Verbrauch oder Belegung erfolgt erst bei redaktioneller Freigabe.
10. Öffentliche Sprache muss einfach, serviceorientiert und nicht technisch sein.
11. Interne Begriffe wie Token, Kontingent oder Entitlement dürfen nicht als öffentliche Produktbotschaft verwendet werden.

### 2.2 Produktarten

Bocholt erleben unterscheidet fachlich drei Produktarten:

| Produktart | Zweck | Öffentliche Logik |
| --- | --- | --- |
| Einzeltermin | eine einzelne Veranstaltung veröffentlichen lassen | Veranstaltung einreichen, prüfen lassen, nach Freigabe zahlen, veröffentlichen lassen |
| Event-Mitgliedschaft | regelmäßig Veranstaltungen einreichen | monatliche Mitgliedschaft für veröffentlichte Termine |
| Aktivitätspräsenz | dauerhaftes Angebot als Aktivität sichtbar machen | Aktivität einreichen, Eignung prüfen lassen, nach Freigabe zahlen, veröffentlichen lassen |

### 2.3 Trennung von Events und Aktivitäten

1. Events sind zeitgebundene Termine.
2. Aktivitäten sind dauerhaft oder regelmäßig nutzbare Angebote.
3. Event-Veröffentlichungen zählen nicht als Aktivitätspräsenzen.
4. Aktivitätspräsenzen zählen nicht als Event-Veröffentlichungen.
5. Eine Event-Mitgliedschaft berechtigt nicht automatisch zur Veröffentlichung von Aktivitätspräsenzen.
6. Eine Aktivitätspräsenz berechtigt nicht automatisch zur Veröffentlichung von Events.
7. Ein Anbieter kann beide Produktarten parallel besitzen.

---

## 3. Produktweg: Einzeltermin

### 3.1 Zweck

Der Einzeltermin ist für gelegentliche Veranstalter gedacht, die genau eine Veranstaltung einreichen und veröffentlichen lassen möchten.

### 3.2 Tarif

| Produkt | Preis | Enthalten |
| --- | ---: | --- |
| Einzeltermin | 9,90 € einmalig | Prüfung und Veröffentlichung einer einzelnen Veranstaltung nach Freigabe |

### 3.3 Zielgruppe

Geeignet für:

1. gelegentliche Veranstalter
2. Vereine mit einzelnen Terminen
3. Anbieter ohne laufende Event-Mitgliedschaft
4. Locations mit einzelnen Veranstaltungen
5. einmalige Veranstaltungen

Nicht gedacht für:

1. regelmäßige Programme mit vielen Terminen
2. dauerhafte Aktivitäten ohne festen Termin
3. reine Werbeeinträge
4. Anbieterprofile

### 3.4 Verbindlicher Ablauf

Der Einzeltermin läuft verbindlich so:

```text
Event einreichen
→ redaktionelle Vorprüfung
→ Zahlungsfreigabe oder Ablehnung
→ Zahlungslink per E-Mail
→ Zahlung
→ finale Prüfung/Freigabe
→ Veröffentlichung
→ Verbrauch/Zählung
```

### 3.5 Regeln

1. Die Einreichung wird vor der Zahlung geprüft.
2. Bei Ablehnung vor Zahlungsfreigabe entsteht kein Zahlungsfall.
3. Nach grundsätzlicher Freigabe erhält der Einreicher einen Zahlungslink.
4. Der Zahlungslink startet einen internen Zahlungsprozess.
5. Nach Zahlung kann die finale Veröffentlichung erfolgen.
6. Die Zahlung garantiert keine automatische Veröffentlichung.
7. Die Veröffentlichung erfolgt erst nach redaktioneller Freigabe.
8. Verbrauch/Zählung erfolgt erst bei Veröffentlichung.
9. Einzeltermin-Nutzer benötigen keine Event-Mitgliedschaft.
10. Einzeltermin-Nutzer erhalten keine Tarifwechsel- oder Abo-Funktionen.

### 3.6 Öffentliche Button- und Statustexte

Bevorzugte Buttontexte:

1. `Zur Prüfung einreichen`
2. `Zahlung abschließen`
3. `Status ansehen`

Nicht verwenden:

1. `Jetzt veröffentlichen`
2. `Direkt veröffentlichen`
3. `Sofort online stellen`
4. `Jetzt kaufen`

---

## 4. Produktweg: Event-Mitgliedschaften

### 4.1 Zweck

Event-Mitgliedschaften sind für Veranstalter, Vereine, Anbieter und Locations gedacht, die regelmäßig Veranstaltungen veröffentlichen lassen möchten.

### 4.2 Tarife

| Öffentlicher Tarif | Interner Modellschlüssel | Preis pro Monat | Enthalten |
| --- | --- | ---: | --- |
| Starter | `starter` | 9,99 € | bis zu 3 freigegebene Termine pro Zeitraum |
| Aktiv | `active` | 19,99 € | bis zu 8 freigegebene Termine pro Zeitraum |
| Dauerhaft | `unlimited` | 29,99 € | viele Termine im üblichen Rahmen |

### 4.3 Regeln

1. Event-Mitgliedschaften sind monatliche Abonnements.
2. Event-Mitgliedschaften können direkt abgeschlossen werden.
3. Nach aktiver Mitgliedschaft können Events ohne neuen Einzeltermin-Checkout eingereicht werden.
4. Jedes eingereichte Event wird redaktionell geprüft.
5. Veröffentlichung erfolgt erst nach Freigabe.
6. Verbrauch erfolgt erst bei Freigabe.
7. Der Tarif `Dauerhaft` ist öffentlich kein grenzenloses Leistungsversprechen.
8. `Dauerhaft` bedeutet viele Termine im üblichen Rahmen.
9. Der interne Schlüssel `unlimited` darf bestehen bleiben.
10. Öffentlich soll nicht von unbegrenzten Tokens gesprochen werden.

### 4.4 Verbindlicher Ablauf

```text
Event-Mitgliedschaft wählen
→ Stripe Checkout
→ Mitgliedschaft aktiv
→ Events einreichen
→ redaktionelle Prüfung
→ Freigabe oder Ablehnung
→ Veröffentlichung
→ Verbrauch/Zählung im aktuellen Zeitraum
```

### 4.5 Öffentliche Begriffe

Bevorzugt:

1. `Mitgliedschaft`
2. `veröffentlichte Termine`
3. `aktueller Zeitraum`
4. `Mitgliedschaft verwalten`
5. `Veranstalterbereich`

Nicht verwenden:

1. `Token`
2. `Kontingent`
3. `Slot`
4. `unbegrenzte Veröffentlichung`
5. `Abo-Slot`

---

## 5. Produktweg: Automatische Event-Anbindung

### 5.1 Zweck

Die automatische Event-Anbindung ist ein Prüfpfad für regelmäßige Event-Quellen.

### 5.2 Geeignete Quellen

Geeignet sind zum Beispiel:

1. Website
2. Online-Kalender
3. Feed
4. API
5. CSV
6. iCal
7. Tabelle

### 5.3 Regeln

1. Die automatische Anbindung ist kein eigener direkter Checkout-Pfad.
2. Sie dient zuerst der technischen und fachlichen Prüfung.
3. Sie betrifft primär Events.
4. Sie betrifft nicht automatisch Aktivitätspräsenzen.
5. Nach Prüfung wird entschieden, welches Event-Modell passt.
6. Öffentliche Sprache soll einfach bleiben.
7. Technische Begriffe sollen nur genutzt werden, wenn sie für den Nutzer hilfreich sind.

### 5.4 Verbindlicher Ablauf

```text
Quelle einreichen
→ Quelle prüfen
→ Umfang und Eignung bewerten
→ passendes Modell klären
→ Umsetzung oder Ablehnung
```

---

## 6. Produktweg: Aktivitätspräsenzen

### 6.1 Zweck

Eine Aktivitätspräsenz macht ein dauerhaft verfügbares, saisonal verfügbares oder regelmäßig buchbares Angebot als Aktivität auf Bocholt erleben sichtbar.

Die Aktivitätspräsenz ist ein kuratierter Veröffentlichungs- und Aufbereitungsservice.

Sie ist ausdrücklich nicht:

1. ein Werbeeintrag,
2. ein Branchenbucheintrag,
3. eine Anzeige,
4. eine Premium-Platzierung,
5. ein automatisch gekaufter Eintrag,
6. ein kostenloser Vorschlagsweg.

### 6.2 Öffentliche Hauptbotschaft

Verbindliche Hauptbotschaft auf der Entscheidungsseite:

```text
Als Aktivität bei Bocholt erleben sichtbar werden

Zulässige Kurzform auf der Aktivitätenliste oder in kleinen CTA-Karten:
Als Aktivität sichtbar werden

Verbindlicher Lead der Entscheidungsseite:
Für dauerhaft verfügbare oder regelmäßig buchbare Angebote. Zahlung erst nach Freigabe.

6.3 Kanonische Funnel-Routen

Der Aktivitäten-Funnel besteht aus drei öffentlichen Routen:

Route	Rolle
/aktivitaeten/sichtbar-werden/	Entscheidungsseite
/aktivitaeten/sichtbar-werden/einreichen/	Formularseite
/aktivitaeten/sichtbar-werden/erfolg/	Erfolgs- und Statusseite

Die Entscheidungsseite beantwortet:

Ist mein Angebot geeignet?
Welcher Tarif passt?
Was passiert nach der Einreichung?

Die Formularseite ist fokussiert und darf keine zweite Produkt-Landingpage werden.

6.4 Tarife
Öffentlicher Tarif	Interner Modellschlüssel	Preis pro Monat	Enthalten
Aktivitätspräsenz Basis	activity_basic	9,99 €	1 veröffentlichte Aktivität
Aktivitätspräsenz Plus	activity_plus	19,99 €	bis zu 3 veröffentlichte Aktivitäten

Öffentliche Tariftexte:

Für 1 veröffentlichte Aktivität. Zahlung erst nach Freigabe.
Für bis zu 3 veröffentlichte Aktivitäten. Zahlung erst nach Freigabe.

Verbindlicher Hinweis:

Erst veröffentlichte Aktivitäten zählen in deinem Tarif.
6.5 Eignung

Verbindliche Überschrift der Eignungskarte:

Für welche Angebote ist die Aktivitätspräsenz gedacht?

Geeignet:

Eigenständige Angebote, die dauerhaft, saisonal oder regelmäßig buchbar sind – z. B. Bouldern, Kindergeburtstage oder Kurse.

Nicht geeignet:

Einzeltermine, andere Preise, andere Öffnungszeiten oder kleine Textänderungen derselben Aktivität. Einzeltermine gehören in den Veranstaltungsbereich.
6.6 Was zählt als eine Aktivität?

Eine veröffentlichte Aktivität ist ein eigenständiges dauerhaftes, saisonales oder regelmäßig buchbares Angebot, das als eigene Karte auf der Aktivitätenseite sinnvoll ist.

Eine eigene Aktivität liegt vor, wenn:

das Angebot eigenständig verständlich ist,
Nutzer es unabhängig wahrnehmen, planen oder buchen können,
es eine eigene Nutzungssituation erfüllt,
es sinnvoll als eigene Aktivitätskarte dargestellt werden kann.

Keine eigene Aktivität liegt vor bei:

anderen Öffnungszeiten,
anderen Preisen,
kleinen Textänderungen,
künstlicher Aufsplittung desselben Angebots,
demselben Inhalt mit minimal anderer Beschreibung.

Beispiele:

Angebot	Zählung
Bouldern und Klettern allgemein	1 Aktivität
Kindergeburtstag in der Kletterhalle, dauerhaft buchbar	1 weitere Aktivität
Kletterkurs für Anfänger, dauerhaft oder regelmäßig buchbar	1 weitere Aktivität
einzelner Ferienkurs mit festem Datum	Veranstaltung, keine Aktivitätspräsenz
andere Öffnungszeiten	keine eigene Aktivität
andere Preise	keine eigene Aktivität
kleine Textänderung desselben Angebots	keine eigene Aktivität
6.7 Verbindlicher Ablauf

Öffentlicher Kurzablauf:

Einreichen → Prüfung → Zahlungslink → Aufbereitung → Veröffentlichung.

Fachlicher Ablauf:

Aktivität einreichen
→ Eignung prüfen
→ Zahlungsfreigabe oder Ablehnung
→ Zahlungslink per E-Mail
→ Zahlung oder bestehende Aktivitätspräsenz nutzen
→ redaktionelle Aufbereitung
→ finale Freigabe
→ Veröffentlichung als Aktivität
→ Zählung im aktuellen Tarif

Regeln:

Aktivitätspräsenzen werden vor Zahlung auf Eignung geprüft.
Bei Ablehnung vor Freigabe entsteht kein Zahlungsfall.
Nach Eignungsfreigabe erhält der Anbieter einen Zahlungslink.
Bei bestehender aktiver Aktivitätspräsenz ist keine neue Zahlung nötig.
Nach Zahlung oder aktiver Aktivitätspräsenz erfolgt die redaktionelle Aufbereitung.
Veröffentlichung erfolgt erst nach finaler Freigabe.
Zählung erfolgt erst bei Veröffentlichung.
Zahlung garantiert keine Veröffentlichung.
Veröffentlichung darf nicht wie eine gekaufte Empfehlung wirken.
6.8 Formularseite

Verbindliche Formularseiten-Struktur:

Hero
→ Einreichung vorbereiten
→ Tarif für die Prüfung
→ Formularabschnitte
→ Bestätigung
→ Button
→ kurzer Zahlungs-/Prüfungshinweis

Hero:

Aktivität zur Prüfung einreichen

Lead:

Trage die Angaben ein. Zahlung erst nach Freigabe.

Formularblock:

Einreichung vorbereiten
* Pflichtfelder
Zahlung erst nach Freigabe.

Tarifbereich:

Tarif für die Prüfung

Die Tarifauswahl bleibt auf der Formularseite sichtbar und änderbar, damit Direktaufrufe und Planwechsel sauber funktionieren.

Verbindliche Feldreihenfolge:

Kontaktdaten
Anbieter / Organisation
Ansprechpartner
E-Mail-Adresse
Aktivität
Name der Aktivität
Name des Standorts
Website / Buchungslink
Adresse / offizieller Treffpunkt
PLZ
Stadt / Ort
Beschreibung und Verfügbarkeit
Kurzbeschreibung der Aktivität
Verfügbarkeit
Weitere Hinweise
Bestätigung

Verfügbarkeit ist ein Auswahlfeld mit diesen Optionen:

Bitte auswählen
Dauerhaft verfügbar
Regelmäßig buchbar
Saisonal verfügbar
Nach Vereinbarung buchbar

Button:

Zur Prüfung einreichen

Hinweis unter Button:

Nach der Einreichung prüfen wir die Aktivität zuerst. Wenn sie zu Bocholt erleben passt, erhältst du per E-Mail einen Zahlungslink.
6.9 Erfolgsseite

Die Erfolgsseite unterscheidet fachlich drei Zustände.

Standardfall flow=submitted:

Aktivität eingereicht.
Es wurde noch keine Zahlung gestartet.
Prüfung vor Zahlungslink.
Danach Zahlungslink, Aufbereitung und Veröffentlichung nach Freigabe.
Primärer CTA: Zur Aktivitätenseite.
Sekundärer CTA: Weitere Aktivität einreichen.

Fall flow=existing-subscription:

Aktivität eingereicht.
Die Einreichung läuft über eine aktive Aktivitätspräsenz.
Es ist keine neue Zahlung nötig.
Redaktionelle Prüfung und Aufbereitung folgen.
Veröffentlichung erst nach Freigabe.
Primärer CTA: Anbieterbereich öffnen.
Sekundärer CTA: Zur Aktivitätenseite.

Fall session_id vorhanden:

Zahlung erhalten.
Redaktionelle Aufbereitung und finale Prüfung folgen.
Veröffentlichung erst nach Freigabe.
Die Aktivität zählt erst nach Veröffentlichung im Tarif.
Primärer CTA: Anbieterbereich öffnen.
6.10 Verantwortlichkeit und Foto-Upload

Der Einreicher bestätigt im Formular:

Ich bestätige, dass ich berechtigt bin, diese Aktivität einzureichen, die Angaben korrekt sind und der Standort öffentlich genannt werden darf.

Im V1-Formular wird kein Foto-Upload eingebaut.

Begründung:

Foto-Upload erfordert Storage-Logik.
Foto-Upload erfordert Größen- und Dateitypprüfung.
Foto-Upload erfordert Rechte-/Lizenzprüfung.
Foto-Upload erfordert Moderation.
Foto-Upload erhöht Datenschutz- und Missbrauchsrisiken.
Die öffentliche Aktivitätskarte entsteht weiterhin redaktionell.

Fotos werden redaktionell ergänzt oder später nach Freigabe separat angefragt.
## 7. Definition: Event

### 7.1 Event-Definition

Ein Event ist ein zeitgebundener Termin.

Ein Event hat in der Regel:

1. Datum
2. Uhrzeit oder Zeitraum
3. Ort
4. Veranstalter
5. konkreten Anlass

### 7.2 Beispiele für Events

Events sind zum Beispiel:

1. Konzert an einem bestimmten Datum
2. Lesung
3. Workshop
4. Führung
5. Ferienaktion mit festem Termin
6. Markt
7. einmalige Veranstaltung
8. Kurs mit festem Datum
9. Sonderveranstaltung
10. Ausstellungseröffnung mit Termin

### 7.3 Produktzuordnung

Events laufen über:

1. Einzeltermin
2. Event-Mitgliedschaft
3. automatische Event-Anbindung

---

## 8. Definition: Aktivität

### 8.1 Aktivitäts-Definition

Eine Aktivität ist ein dauerhaft oder regelmäßig nutzbares Angebot ohne einzelnen Veranstaltungstermin.

Eine Aktivität hat in der Regel:

1. eigenständigen Freizeit-, Kultur-, Sport-, Familien-, Natur- oder Ausflugswert
2. dauerhafte oder regelmäßige Nutzbarkeit
3. klare Nutzersituation
4. sinnvolle Darstellung als eigene Aktivitätskarte
5. Anbieter, Ort oder Startpunkt
6. Website, Informationsseite oder Kontaktmöglichkeit

### 8.2 Beispiele für Aktivitäten

Aktivitäten sind zum Beispiel:

1. Kletterhalle
2. Escape Room
3. Museum als dauerhaftes Ausflugsziel
4. Indoorspielplatz
5. Freizeitangebot
6. Sport- und Bewegungsangebot
7. Familienangebot
8. Kulturort mit dauerhaftem Nutzwert
9. Hof oder Ausflugsziel mit Freizeitbezug
10. dauerhaft buchbares Erlebnisangebot
11. Route oder dauerhafter Erlebnisweg

### 8.3 Produktzuordnung

Aktivitäten laufen über:

1. Aktivitätspräsenz Basis
2. Aktivitätspräsenz Plus

---

## 9. Was zählt als eine Aktivität?

### 9.1 Grundregel

Eine veröffentlichte Aktivität ist ein eigenständiges dauerhaftes Angebot, das als eigene Karte auf der Aktivitätenseite sinnvoll ist.

### 9.2 Eine eigene Aktivität liegt vor, wenn

1. das Angebot eigenständig verständlich ist,
2. Nutzer es unabhängig wahrnehmen oder buchen können,
3. es eine eigene Erwartung erfüllt,
4. es eine eigene Zielgruppe oder Nutzungssituation erfüllt,
5. es sinnvoll als eigene Aktivitätskarte dargestellt werden kann.

### 9.3 Keine eigene Aktivität liegt vor bei

1. anderen Öffnungszeiten,
2. anderen Preisen,
3. kleinen Textvarianten,
4. reinen Zielgruppenvarianten ohne eigenständiges Angebot,
5. künstlicher Aufsplittung desselben Angebots,
6. demselben Inhalt mit minimal anderer Beschreibung.

### 9.4 Beispiel: Kletterhalle

| Angebot | Zählung |
| --- | ---: |
| Bouldern und Klettern allgemein | 1 Aktivität |
| Kindergeburtstag in der Kletterhalle, dauerhaft buchbar | 1 weitere Aktivität |
| Kletterkurs für Anfänger, dauerhaft oder regelmäßig buchbar | 1 weitere Aktivität |
| einzelner Ferienkurs mit festem Datum | Event, keine Aktivität |
| andere Öffnungszeiten | keine eigene Aktivität |
| andere Preise | keine eigene Aktivität |
| kleine Textvariante desselben Angebots | keine eigene Aktivität |

---

## 10. Eignungskriterien für Aktivitätspräsenzen

### 10.1 Geeignet

Eine Aktivitätspräsenz kommt grundsätzlich in Frage, wenn das Angebot:

1. in Bocholt oder im relevanten Umfeld von Bocholt liegt,
2. für Nutzer einen Freizeit-, Kultur-, Sport-, Familien-, Natur- oder Ausflugswert hat,
3. dauerhaft oder regelmäßig nutzbar ist,
4. seriös beschrieben werden kann,
5. eine sinnvolle Website, Informationsseite oder Kontaktmöglichkeit besitzt,
6. redaktionell in die bestehende Activity-Taxonomie passt,
7. echten Nutzwert für die Plattformzielgruppe hat.

### 10.2 Bestehende Top-Level-Kategorien

Aktivitäten müssen einer der bestehenden Top-Level-Kategorien zugeordnet werden:

1. Natur & Draußen
2. Kultur
3. Sport & Bewegung
4. Freizeit & Familie

### 10.3 Nicht geeignet

Nicht geeignet sind in der Regel:

1. reine Werbung ohne konkreten Freizeit- oder Erlebniswert,
2. reine Gastronomie ohne Ausflugs- oder Aktivitätsbezug,
3. normaler Einzelhandel ohne Aktivitätsbezug,
4. reine Dienstleistungsangebote ohne Plattformfit,
5. Angebote außerhalb des relevanten Bocholt-Umfelds,
6. rechtlich problematische Angebote,
7. qualitativ ungeeignete Angebote,
8. doppelte Profile,
9. künstlich aufgesplittete Profile,
10. einzelne Veranstaltungen.

---

## 11. Interne Zähl- und Verbrauchslogik

### 11.1 Öffentliche und interne Sprache

Öffentlich verwenden:

1. veröffentlichte Termine
2. veröffentlichte Aktivitäten
3. Mitgliedschaft
4. Aktivitätspräsenz

Intern erlaubt:

1. Entitlements
2. Consumptions
3. Scopes
4. Modellschlüssel
5. Verbrauchswerte
6. Kontingente

Öffentlich nicht verwenden:

1. Token
2. Kontingent
3. Slot
4. Entitlement
5. Consumption

### 11.2 Interne Scopes

Das bestehende Entitlement-/Consumption-System soll weiterverwendet werden.

Es darf kein zweites paralleles System nur für Aktivitätspräsenzen entstehen.

Verbindliche fachliche Scopes:

```text
event_publication
activity_presence
```

### 11.3 Scope-Regeln

1. `event_publication` zählt freigegebene Event-Veröffentlichungen.
2. `activity_presence` zählt veröffentlichte Aktivitätspräsenzen.
3. Beide Scopes dürfen nicht miteinander verrechnet werden.
4. Ein Anbieter kann beide Scopes parallel besitzen.
5. Verbrauch erfolgt immer erst bei Freigabe oder Veröffentlichung.
6. Abgelehnte Einreichungen verbrauchen nichts.
7. Ungeprüfte Einreichungen verbrauchen nichts.
8. Bezahlte, aber noch nicht freigegebene Einreichungen verbrauchen nichts.

### 11.4 Verbrauch bei Events

1. Jeder freigegebene einzelne Event-Termin zählt als eine Event-Veröffentlichung.
2. Wiederkehrende Events zählen pro Termin jeweils als eigene Veröffentlichung.
3. Verbrauch erfolgt erst bei redaktioneller Freigabe.
4. Nicht freigegebene Termine zählen nicht.

### 11.5 Verbrauch bei Aktivitätspräsenzen

1. Jede veröffentlichte Aktivität belegt eine Aktivitätspräsenz.
2. Aktivitätspräsenz Basis enthält 1 veröffentlichte Aktivität.
3. Aktivitätspräsenz Plus enthält bis zu 3 veröffentlichte Aktivitäten.
4. Noch nicht veröffentlichte Aktivitäts-Einreichungen belegen keine Aktivitätspräsenz.
5. Abgelehnte Aktivitäts-Einreichungen belegen keine Aktivitätspräsenz.

### 11.6 Zeitraum

1. Event-Mitgliedschafts-Veröffentlichungen gelten für den jeweiligen Abrechnungszeitraum.
2. Nicht genutzte Event-Veröffentlichungsmöglichkeiten werden nicht angespart.
3. Es gibt kein Rollover für Event-Veröffentlichungen.
4. Aktivitätspräsenzen sind dauerhafte Profilberechtigungen innerhalb einer aktiven Aktivitätsmitgliedschaft.
5. Bei gekündigter oder nicht bezahlter Aktivitätspräsenz können neue Änderungen pausiert werden.
6. Bei dauerhaft ausbleibender Zahlung kann die Aktivitätspräsenz deaktiviert oder entfernt werden.

---

## 12. Statuslogik für Events

### 12.1 Fachliche Event-Status

Ein Event durchläuft fachlich diese Zustände:

| Fachlicher Status | Bedeutung |
| --- | --- |
| Eingereicht | Event wurde eingereicht und wartet auf Prüfung |
| In Prüfung | Angaben werden geprüft oder aufbereitet |
| Zur Zahlung freigegeben | nur bei Einzelterminen ohne aktive Mitgliedschaft |
| Bezahlt / verwendbare Mitgliedschaft vorhanden | Veröffentlichung kann final vorbereitet werden |
| Freigegeben | Event wird veröffentlicht |
| Abgelehnt | Event wird nicht veröffentlicht |

### 12.2 Interne Event-Statuswerte

Empfohlene interne Statuswerte:

```text
draft
submitted
review
payment_pending
checkout_started
paid
approved
rejected
```

### 12.3 Event-Regeln

1. Event-Status dürfen in der UI nutzerverständlich übersetzt werden.
2. Interne Statuswerte müssen nicht öffentlich sichtbar sein.
3. Einzeltermin-Zahlung darf erst nach Zahlungsfreigabe gestartet werden.
4. Event-Mitgliedschaften dürfen direkt bezahlt werden.
5. Veröffentlichung erfolgt erst nach Freigabe.

---

## 13. Statuslogik für Aktivitätspräsenzen

### 13.1 Fachliche Aktivitäts-Status

Eine Aktivitätspräsenz durchläuft fachlich diese Zustände:

| Fachlicher Status | Bedeutung |
| --- | --- |
| Eingereicht | Aktivität wurde eingereicht und wartet auf Eignungsprüfung |
| Wird geprüft | Plattformfit, Angaben und Tariflogik werden geprüft |
| Zur Zahlung freigegeben | Anbieter erhält Zahlungslink |
| Zahlung gestartet | Anbieter hat Checkout geöffnet |
| Bezahlt | Aktivitätspräsenz kann redaktionell aufbereitet werden |
| Wird aufbereitet | Inhalte werden finalisiert |
| Veröffentlicht | Aktivität ist sichtbar |
| Abgelehnt | Angebot wird nicht veröffentlicht |

### 13.2 Interne Aktivitäts-Statuswerte

Empfohlene interne Statuswerte:

```text
draft
submitted
eligibility_review
payment_pending
checkout_started
paid
content_review
approved
rejected
```

### 13.3 Aktivitäts-Regeln

1. Aktivitätspräsenzen müssen vor Zahlung auf Eignung geprüft werden.
2. Bei Ablehnung vor Zahlungsfreigabe darf keine Zahlung ausgelöst werden.
3. Nach Zahlungsfreigabe erhält der Anbieter einen Zahlungsstart-Link.
4. Nach Zahlung erfolgt redaktionelle Aufbereitung.
5. Veröffentlichung erfolgt erst nach finaler Freigabe.
6. Belegung erfolgt erst bei Veröffentlichung.

---

## 14. Zahlungslogik

### 14.1 Grundregeln

1. Zahlung garantiert keine automatische Veröffentlichung.
2. Zahlung ersetzt keine redaktionelle Freigabe.
3. Zahlungslinks sollen erst nach fachlicher Freigabe erzeugt werden, wenn es um Einzeltermine oder Aktivitätspräsenzen geht.
4. Event-Mitgliedschaften dürfen direkt abgeschlossen werden.
5. Stripe Checkout bleibt der bevorzugte Zahlungsmechanismus.
6. Generische Stripe Payment Links sollen nicht als dauerhafte Produktlinks genutzt werden.

### 14.2 Einzeltermine

Einzeltermine sollen erst nach redaktioneller Vorprüfung zur Zahlung freigegeben werden.

Ablauf:

```text
Einreichung
→ Vorprüfung
→ Zahlungsfreigabe
→ interner Zahlungsstart-Link
→ Stripe Checkout
→ Zahlung
→ finale Freigabe
→ Veröffentlichung
```

### 14.3 Event-Mitgliedschaften

Event-Mitgliedschaften können direkt abgeschlossen werden.

Ablauf:

```text
Tarif wählen
→ Stripe Checkout
→ Mitgliedschaft aktiv
→ Events einreichen
→ Prüfung
→ Freigabe
→ Verbrauch
```

### 14.4 Aktivitätspräsenzen

Aktivitätspräsenzen werden erst nach Eignungsprüfung zur Zahlung freigegeben.

Ablauf:

```text
Aktivität einreichen
→ Eignung prüfen
→ Zahlungsfreigabe
→ interner Zahlungsstart-Link
→ Stripe Checkout
→ Zahlung
→ Aufbereitung
→ finale Freigabe
→ Veröffentlichung
```

### 14.5 Zahlungsstart-Links

Zahlungsstart-Links sollen intern erzeugt werden.

Regeln:

1. Der Link verweist nicht dauerhaft direkt auf Stripe.
2. Der Link enthält oder referenziert einen sicheren Token.
3. Das Backend prüft beim Klick den Token.
4. Das Backend prüft Submission, Status und Zahlungsfähigkeit.
5. Das Backend erzeugt erst dann eine frische Stripe Checkout Session.
6. Stripe Metadata muss Submission, Produktart und Tarif eindeutig referenzieren.
7. Abgelaufene oder bereits bezahlte Links dürfen nicht erneut zu einer Zahlung führen.

---

## 15. E-Mail-Logik

### 15.1 Pflicht-Mails

Mindestens diese Mails sollen fachlich vorgesehen werden:

| Mailtyp | Auslöser |
| --- | --- |
| Eingangsbestätigung | nach Einreichung |
| Zahlungsfreigabe | nach Freigabe zur Zahlung |
| Ablehnung | nach Ablehnung |

### 15.2 Optionale Mails

Optional sinnvoll:

| Mailtyp | Auslöser |
| --- | --- |
| Rückfrage erforderlich | wenn Angaben fehlen |
| Zahlung eingegangen | nach erfolgreicher Zahlung |
| Veröffentlichung erfolgt | nach Veröffentlichung |

### 15.3 E-Mail-Regeln

1. E-Mails sollen klar, kurz und nicht technisch sein.
2. E-Mails sollen keine internen Statusnamen enthalten.
3. Zahlungsfreigabe-Mails enthalten einen internen Zahlungsstart-Link.
4. Ablehnungsmails sollen sachlich formuliert sein.
5. Bei Aktivitätspräsenzen soll klar sein, dass die Eignung geprüft wurde.

---

## 16. Anbieterbereich

### 16.1 Ziel

Der Anbieterbereich soll perspektivisch mehrere Produktarten gemeinsam verwalten können.

### 16.2 Fachliche Bereiche

Der Anbieterbereich soll fachlich unterscheiden:

1. Veranstaltungen
2. Aktivitätspräsenzen
3. Mitgliedschaften und Zahlungen
4. Status
5. Statistik und Nutzwertdaten

### 16.3 Regeln

1. Event-Produkte und Aktivitätspräsenzen bleiben fachlich getrennt.
2. Ein Anbieter kann beide Produktarten parallel besitzen.
3. Es werden zunächst keine öffentlichen Kombi-Pakete angeboten.
4. Der Anbieterbereich darf beide Produktarten gemeinsam darstellen.
5. Die Darstellung muss Event-Produkte und Aktivitätspräsenzen klar unterscheiden.
6. Der bestehende Veranstalterbereich kann technische Basis bleiben.
7. Fachlich soll langfristig „Anbieterbereich“ gedacht werden.

---

## 17. Keine öffentlichen Kombi-Pakete

### 17.1 Regel

Es werden zunächst keine öffentlichen Kombi-Pakete aus Event-Mitgliedschaft und Aktivitätspräsenz angeboten.

### 17.2 Begründung

Öffentliche Kombi-Pakete würden die Tariflogik unnötig kompliziert machen.

### 17.3 Richtige Produktlogik

Öffentlich getrennt darstellen:

1. Wer Termine veröffentlichen möchte, nutzt Event-Produkte.
2. Wer dauerhaft als Aktivität sichtbar sein möchte, nutzt Aktivitätspräsenzen.
3. Wer beides braucht, kann beides im selben Anbieterbereich nutzen.

### 17.4 Technische Zielregel

Technisch muss ein Anbieter beide Produktarten parallel besitzen können.

Beispiele:

1. Event-Mitgliedschaft Starter + Aktivitätspräsenz Basis
2. Event-Mitgliedschaft Aktiv + Aktivitätspräsenz Plus
3. nur Event-Mitgliedschaft
4. nur Aktivitätspräsenz

---

## 18. Messbarkeit und Nutzwertdaten

### 18.1 Bereits messbare Werte

Website- und Maps-Klicks sind technisch bereits erfassbar.

Relevante Metriken:

```text
activity_detail_view
event_detail_view
website_click
maps_click
location_click
organizer_cta_click
```

### 18.2 Regeln

1. Messwerte können perspektivisch im Anbieterbereich sichtbar gemacht werden.
2. Messwerte ersetzen keine Veröffentlichungsentscheidung.
3. Messwerte dienen der Nutzwertkommunikation.
4. Öffentliche Verkaufsbotschaften dürfen erst mit konkreten Zahlen arbeiten, wenn belastbare Daten vorliegen.
5. Interne Metriken sollen nicht überinterpretiert werden.
6. Eigene Testklicks sollten perspektivisch möglichst aus externen Auswertungen herausgefiltert werden.

---

## 19. Änderungen nach Veröffentlichung

### 19.1 Events

Regeln:

1. Kleine Korrekturen sind möglich.
2. Tippfehler können korrigiert werden.
3. Link-Anpassungen können korrigiert werden.
4. Substanzielle Änderungen wie Datum, Uhrzeit oder Ort können erneut geprüft werden.
5. Korrekturen lösen nicht automatisch eine zusätzliche Veröffentlichung aus.
6. Korrekturen können intern protokolliert werden.

### 19.2 Aktivitätspräsenzen

Regeln:

1. Kleine Korrekturen sind möglich.
2. Link-Anpassungen sind möglich.
3. kleinere Beschreibungskorrekturen sind möglich.
4. Substanzielle Änderungen am Angebot können erneut geprüft werden.
5. Aus einer bestehenden Aktivität darf nicht ohne Prüfung ein anderes Angebot gemacht werden.
6. Zusätzliche eigenständige Angebote zählen als weitere Aktivität.
7. Künstliche Aufsplittung zur Erzeugung mehrerer Profile ist nicht zulässig.

---

## 20. Kündigung und Zahlungsstatus

### 20.1 Event-Mitgliedschaften

Regeln:

1. Bei gekündigter Event-Mitgliedschaft bleiben Einreichungen bis zum Periodenende grundsätzlich möglich.
2. Bei Zahlungsausfall können neue Event-Veröffentlichungen pausiert werden.
3. Bereits veröffentlichte Events bleiben grundsätzlich sichtbar, sofern keine inhaltlichen Gründe dagegen sprechen.
4. Einzeltermin-Nutzer erhalten keine Mitgliedschaftsverwaltung.

### 20.2 Aktivitätspräsenzen

Regeln:

1. Bei gekündigter Aktivitätspräsenz bleibt die Präsenz bis zum Periodenende grundsätzlich aktiv.
2. Bei Zahlungsausfall können neue Änderungen pausiert werden.
3. Bei dauerhaft ausbleibender Zahlung kann die Aktivitätspräsenz deaktiviert oder entfernt werden.
4. Bereits veröffentlichte Aktivitätspräsenzen bleiben nur sichtbar, solange die fachliche und zahlungsbezogene Grundlage besteht.
5. Inhaltlich ungeeignete oder nicht mehr aktuelle Aktivitätspräsenzen können unabhängig vom Zahlungsstatus geprüft, pausiert oder entfernt werden.

---

## 21. Öffentliche Begriffssystematik

### 21.1 Bevorzugte Begriffe für Events

Verwenden:

1. Veranstaltung einreichen
2. prüfen und veröffentlichen lassen
3. Einzeltermin
4. Event-Mitgliedschaft
5. Veranstalterbereich
6. Meine Einreichung
7. Bereich öffnen
8. veröffentlichte Termine
9. aktueller Zeitraum
10. Mitgliedschaft verwalten

### 21.2 Bevorzugte Begriffe für Aktivitätspräsenzen

Verwenden:

1. Aktivitätspräsenz
2. Aktivität
3. veröffentlichte Aktivität
4. Zahlung erst nach Freigabe
5. Zahlungslink
6. Aufbereitung
7. Veröffentlichung
8. Standort
9. Anbieterbereich
10. Zur Prüfung einreichen
11. Als Aktivität bei Bocholt erleben sichtbar werden
12. Als Aktivität sichtbar werden

### 21.3 Nicht als öffentliche Produktbotschaft verwenden

Nicht verwenden:

1. Ort vorschlagen
2. Location eintragen
3. Anzeige buchen
4. Werbeeintrag
5. Premium-Listing
6. kostenlos eintragen
7. Angebot vorschlagen
8. Verbrauch
9. Token
10. Zahlungsstart
11. Kontingent
12. Slot
13. Entitlement
14. Consumption
15. Abo als Hauptbegriff
16. unbegrenzt als uneingeschränktes Serviceversprechen
---

## 22. UI- und Umsetzungsregeln

### 22.1 Allgemeine UI-Regeln

1. Neue Produktseiten müssen sich an bestehenden Seiten orientieren.
2. Keine neue UI-DNA einführen.
3. Bestehende Tokens verwenden.
4. Bestehende Button-Systematik verwenden.
5. Bestehende Card-Systematik verwenden.
6. Bestehende Formularmuster verwenden.
7. Bestehende Review- und Statuslogik wiederverwenden, soweit fachlich passend.
8. Page-spezifische Sonderlösungen vermeiden.

### 22.2 Aktivitätsseite

Auf der Aktivitätenseite soll ein Anbieter-Einstieg möglich sein.

Ziel-CTA:

```text
Als Aktivität sichtbar werden
```

Nicht verwenden:

```text
Ort vorschlagen
```

### 22.3 Aktivitäts-Funnel

Der Aktivitäts-Funnel ist verbindlich schlank, mobil und an der Event-Funnel-DNA ausgerichtet.

Kanonische Routen:

1. `/aktivitaeten/sichtbar-werden/` = Entscheidungsseite
2. `/aktivitaeten/sichtbar-werden/einreichen/` = Formularseite
3. `/aktivitaeten/sichtbar-werden/erfolg/` = Erfolgs-/Statusseite

Verbindliche Reihenfolge Entscheidungsseite:

```text
Hero
→ Eignungskarte
→ Tarifwahl
→ kurzer Ablaufblock

Verbindliche Reihenfolge Formularseite:

Hero
→ Einreichung vorbereiten
→ Tarif für die Prüfung
→ Formularabschnitte
→ Bestätigung
→ Button
→ kurzer Zahlungs-/Prüfungshinweis

Keine langen zusätzlichen Erklärblöcke ergänzen.

Die Zielwirkung ist:
Verstehen → Eignung prüfen → Tarif wählen → Formular ausfüllen → Prüfung abwarten
## 23. Technische Zielregeln

### 23.1 Submission-Arten

Mindestens diese Submission-Arten sollen fachlich unterscheidbar sein:

```text
event
activity_presence
```

### 23.2 Entitlement-Scopes

Mindestens diese Entitlement-Scopes sollen fachlich unterscheidbar sein:

```text
event_publication
activity_presence
```

### 23.3 Stripe-Metadata

Stripe-Zahlungen müssen eindeutig referenzieren:

1. Submission-ID
2. Produktart
3. Tarif
4. Anbieter oder Einreicher
5. Scope
6. Zahlungszweck

### 23.4 Review-Inbox

Die Review-Inbox muss unterscheiden können:

1. Event-Einreichungen
2. Aktivitätspräsenzen
3. Zahlungsstatus
4. Prüfstatus
5. Freigabestatus

### 23.5 Keine neue Parallellogik

Nicht neu bauen, wenn bestehende Systeme erweitert werden können:

1. Submission-System erweitern statt zweites Intake-System bauen.
2. Stripe-Checkout-System erweitern statt separate Payment-Logik bauen.
3. Mailfunktion wiederverwenden.
4. Entitlement-/Consumption-System erweitern statt zweites Token-System bauen.
5. Anbieterbereich erweitern statt komplett getrenntes Portal bauen.

---

## 24. Änderungsregeln

1. Änderungen an diesem Produktvertrag erfolgen bewusst.
2. Änderungen werden versioniert.
3. Eine neue Version ersetzt ältere Regelungen vollständig.
4. Implizite Änderungen sind nicht zulässig.
5. Änderungen müssen auf Auswirkungen geprüft werden für:
   - Zahlungslogik
   - Review-Inbox
   - Anbieterbereich
   - Entitlement-/Consumption-System
   - Event-Funnel
   - Aktivitäts-Funnel
   - öffentliche Sprache
6. Dieses Dokument bleibt die kanonische Produktgrundlage.

---

## 25. Kanonische Kurzfassung für KI

Bocholt erleben verkauft keine Anzeigen, keine Premium-Listings und keine automatischen Einträge.

Es gibt drei zentrale Produktwege:

1. **Einzeltermin**
   - 9,90 € einmalig
   - erst prüfen
   - dann Zahlungslink
   - dann Zahlung
   - dann finale Freigabe und Veröffentlichung

2. **Event-Mitgliedschaft**
   - Starter 9,99 € / Monat für bis zu 3 freigegebene Termine
   - Aktiv 19,99 € / Monat für bis zu 8 freigegebene Termine
   - Dauerhaft 29,99 € / Monat für viele Termine im üblichen Rahmen
   - Mitgliedschaft kann direkt abgeschlossen werden
   - Events werden trotzdem geprüft
   - Verbrauch erst bei Freigabe

3. **Aktivitätspräsenz**
   - Basis 9,99 € / Monat für 1 veröffentlichte Aktivität
   - Plus 19,99 € / Monat für bis zu 3 veröffentlichte Aktivitäten
   - erst Eignung prüfen
   - dann Zahlungslink
   - dann Zahlung
   - dann redaktionelle Aufbereitung
   - dann finale Freigabe und Veröffentlichung

Events und Aktivitätspräsenzen sind getrennte Produktarten.

Intern sollen getrennte Scopes genutzt werden:

```text
event_publication
activity_presence
```

Öffentlich nicht von Tokens sprechen.

Für Aktivitätspräsenzen öffentlich sagen:

```text
Als Aktivität bei Bocholt erleben sichtbar werden
```

Nicht sagen:

```text
Ort vorschlagen
Location eintragen
Anzeige buchen
Premium-Listing
```

Website- und Maps-Klicks sind technisch bereits messbar und können perspektivisch im Anbieterbereich sichtbar gemacht werden.

<!-- === END FILE: Produktvertrag.md | Zweck: kanonischer KI-lesbarer Produktvertrag für Event-Veröffentlichungen, Event-Mitgliedschaften und Aktivitätspräsenzen; Umfang: komplette Datei === -->
