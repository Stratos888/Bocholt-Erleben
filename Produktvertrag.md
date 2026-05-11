# Produktvertrag – Veröffentlichungsservice & Mitgliedschaften (v2.0)

**Projekt:** Bocholt erleben  
**Status:** Verbindlich beschlossen  
**Gültig ab:** sofort  
**Gültigkeit:** bis aktiv geändert (bewusste Entscheidung erforderlich)

---

## 1. Ziel, Zweck & kanonische Rolle

Dieser Produktvertrag definiert verbindlich den Veröffentlichungsservice von *Bocholt erleben* für Event-Einreichungen, einmalige Veröffentlichungen und regelmäßige Mitgliedschaften.

Er ist die **einzige kanonische Quelle** für:

* öffentliche Produktlogik
* sichtbare Tarif- und Preislogik
* Einreichungs-, Prüfungs-, Freigabe- und Veröffentlichungsregeln
* Regeln für Einzeltermine und Mitgliedschaften
* interne Zähl-/Verbrauchslogik bei Freigabe

Er dient außerdem als **Single Source of Truth** für:

* spätere UI-Texte
* Backend-Logik
* Zahlungs- und Prüfprozesse
* Support- und Statuskommunikation

`MASTER.md` darf strategische Richtung definieren, `ENGINEERING.md` technische Arbeitsregeln – aber **keine** dieser Dateien darf die hier festgelegte Produktlogik parallel neu definieren.

---

## 2. Grundprinzipien

1. Bocholt erleben verkauft öffentlich keinen reinen Listingeintrag, sondern einen **Veröffentlichungsservice**.
2. Nutzer reichen Veranstaltungen ein; Bocholt erleben prüft die Angaben, bereitet sie sauber auf und veröffentlicht sie nach Freigabe.
3. Es gibt öffentlich zwei legitime Wege:
   * **Einzeltermin** – einmaliger Veröffentlichungsservice für eine einzelne Veranstaltung.
   * **Mitgliedschaft** – regelmäßiger Veröffentlichungsservice für Vereine, Veranstalter, Anbieter und Locations.
4. Events werden **kuratierend veröffentlicht**, nicht ungeprüft.
5. Verbrauch bzw. Zählung findet **erst bei Freigabe** statt, nicht bei Einreichung oder Zahlung.
6. Die Plattform bleibt einfach, fair und transparent – für Veranstalter wie Nutzer.

---

## 3. Öffentliche Produktwege

### 3.1 Einzeltermin

| Weg | Preis | Zweck | Öffentliche Einordnung |
| --- | ---: | --- | --- |
| Einzeltermin | 9,90 € einmalig | eine einzelne Veranstaltung einreichen, prüfen und veröffentlichen lassen | für gelegentliche Veranstaltungen ohne laufende Mitgliedschaft |

Regeln:

* Einzeltermin-Nutzer benötigen **keine aktive Mitgliedschaft**.
* Die einmalige Zahlung berechtigt zur Prüfung und möglichen Veröffentlichung genau einer eingereichten Veranstaltung.
* Die Veröffentlichung erfolgt nur nach redaktioneller Freigabe.
* Einzeltermin-Nutzer erhalten öffentlich keine Abo-, Tarifwechsel- oder Mitgliedschaftsfunktionen.
* Der Rückkehrpfad für Einzeltermine heißt öffentlich sinngemäß **Meine Einreichung** oder **Status ansehen**.

### 3.2 Mitgliedschaften

| Öffentlicher Tarif | Interner Modellschlüssel | Preis / Monat | Öffentliche Einordnung | Interne Veröffentlichungslogik |
| --- | --- | ---: | --- | --- |
| Starter | `starter` | 9,99 € | für kleine Veranstalter mit gelegentlichen Terminen | bis zu 3 freigegebene Termine pro Zeitraum |
| Aktiv | `active` | 19,99 € | für Vereine, Orte und Anbieter mit regelmäßigem Programm | bis zu 8 freigegebene Termine pro Zeitraum |
| Dauerhaft | `unlimited` | 29,99 € | für laufende Programme mit vielen Terminen im üblichen Rahmen | Fair-Use / viele Termine im üblichen Rahmen |

Regeln:

* Mitgliedschaften sind monatliche Abonnements.
* Mitglieder können regelmäßig Events einreichen, ohne für jede Einreichung einen neuen Checkout zu starten.
* Der öffentliche Nutzen ist der laufende Veröffentlichungsservice, nicht der Kauf einzelner Slots.
* Die interne Zählung dient der Freigabe-, Abrechnungs- und Missbrauchskontrolle.
* Der Tarif **Dauerhaft** darf öffentlich nicht als grenzenloses Leistungsversprechen verstanden werden. Er steht für viele Termine im üblichen Rahmen und kann intern weiterhin über den Modellschlüssel `unlimited` abgebildet werden.

---

## 4. Interne Zähl- und Verbrauchslogik

### 4.1 Bedeutung

* Öffentlich wird vorzugsweise von **veröffentlichten Terminen** oder **Veröffentlichungen im aktuellen Zeitraum** gesprochen.
* Intern darf weiter mit Kontingenten, Verbrauchswerten oder Modellschlüsseln gearbeitet werden.
* Interne Begriffe wie Token oder Kontingent sind keine öffentliche Produktbotschaft.

### 4.2 Verbrauch

* Jeder freigegebene einzelne Event-Termin zählt als eine Veröffentlichung.
* Wiederkehrende Events zählen pro Termin jeweils als eigene Veröffentlichung.

### 4.3 Zeitpunkt des Verbrauchs

* Es wird **nicht** bei Erstellung, Einreichung oder Zahlung verbraucht.
* Verbrauch bzw. Zählung erfolgt **erst bei redaktioneller Freigabe**.
* Abgelehnte oder noch ungeprüfte Einreichungen verbrauchen keine Veröffentlichung.

### 4.4 Zeitraum

* Mitgliedschafts-Veröffentlichungen gelten für den jeweils aktuellen Abrechnungszeitraum.
* Nicht genutzte Veröffentlichungsmöglichkeiten werden nicht angespart.
* Es gibt kein Rollover in spätere Zeiträume.

---

## 5. Event-Einreichung

### 5.1 Zulässige Einreichungswege

Events dürfen eingereicht werden über:

1. Einzeltermin mit einmaliger Zahlung.
2. Aktive Mitgliedschaft.
3. Wiederverwendung einer aktiven Mitgliedschaft ohne neuen Checkout.

Eine Mitgliedschaft ist **nicht** Voraussetzung für einen Einzeltermin.

### 5.2 Statusmodell

Ein Event durchläuft folgende Zustände:

1. **Eingereicht** – wartet auf Prüfung.
2. **In Prüfung** – Angaben werden geprüft oder aufbereitet.
3. **Freigegeben** – Event wird veröffentlicht; Verbrauch/Zählung erfolgt.
4. **Abgelehnt** – Event wird nicht veröffentlicht; kein Verbrauch.

### 5.3 Prüfung

Jedes Event wird redaktionell geprüft. Kriterien sind unter anderem:

* Plausibilität
* Verständlichkeit
* lokale Relevanz
* ausreichende Angaben zu Datum, Ort und Veranstalter
* Vermeidung von Dubletten, Spam oder ungeeigneten Inhalten

Die Zahlung garantiert nicht automatisch die Veröffentlichung. Sie startet den passenden Prüf- und Veröffentlichungsprozess.

---

## 6. Automatische Anbindung

Automatische Anbindung ist ein zusätzlicher Servicepfad für regelmäßige Termine.

Regeln:

* Eine Anbindung ist kein dritter öffentlicher Hauptweg neben Einzeltermin und Mitgliedschaft.
* Sie dient dazu zu prüfen, ob Termine aus einer vorhandenen Quelle wie Website, Feed, API, CSV oder iCal übernommen werden können.
* Das passende Modell wird erst nach Prüfung der Quelle und des erwarteten Umfangs geklärt.
* Die Anbindung soll öffentlich als Service-Vorteil sichtbar sein, aber nicht als eigenständiger Checkout-Pfad behandelt werden.

---

## 7. Änderungen nach Veröffentlichung

* Kleine inhaltliche Korrekturen wie Tippfehler oder Link-Anpassungen sind möglich.
* Substanzielle Änderungen wie Datum, Uhrzeit oder Ort können erneut geprüft werden.
* Korrekturen lösen nicht automatisch eine zusätzliche Veröffentlichung aus, können aber intern protokolliert werden.

---

## 8. Kündigung & Zahlungsstatus

* Bei gekündigter Mitgliedschaft bis zum Periodenende bleiben Einreichungen im noch aktiven Zeitraum möglich.
* Bei Zahlungsausfall können neue Veröffentlichungen pausiert oder gesperrt werden.
* Bereits veröffentlichte Events bleiben grundsätzlich sichtbar, sofern keine inhaltlichen Gründe dagegen sprechen.
* Einzeltermin-Nutzer erhalten keine Mitgliedschaftsverwaltung.

---

## 9. Öffentliche Begriffssystematik

Bevorzugte Begriffe:

* Veranstaltung einreichen
* prüfen und veröffentlichen lassen
* Einzeltermin
* Mitgliedschaft
* Mein Veranstalterbereich
* Meine Einreichung
* Bereich öffnen
* veröffentlichte Termine
* aktueller Zeitraum
* Mitgliedschaft verwalten

Nicht als öffentliche Produktbotschaft verwenden:

* Token
* Kontingent
* Abo als Hauptbegriff
* Statusbereich als erklärter Systembegriff
* unbegrenzt als uneingeschränktes Serviceversprechen

---

## 10. Änderungsregeln

* Änderungen an diesem Produktvertrag erfolgen bewusst und explizit.
* Änderungen werden versioniert.
* Eine neue Version ersetzt ältere Regelungen vollständig.
* Implizite oder beiläufige Änderungen sind nicht zulässig.

---

**Ende des Produktvertrags – Version 2.0**
