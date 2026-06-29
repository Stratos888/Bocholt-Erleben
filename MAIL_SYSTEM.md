<!-- === BEGIN FILE: MAIL_SYSTEM.md | Zweck: kanonischer Mail-System-Contract für vorgefertigte Bocholt-erleben-Systemmails; Umfang: Sprache, Layout, Formatierung, Pilot-Mail und Migrationsregeln === -->

# Mail-System V1

Status: V1 umgesetzt und getestet; dieses Dokument ist der kanonische Mail-Contract und kein offener Implementierungsauftrag.

Dieser Contract definiert Sprache, Formatierung und visuelle Systematik für vorgefertigte Systemmails von Bocholt erleben.

Er gilt für:
- Einreichungsbestätigungen
- Zahlungslink-Mails
- Ablehnungs-Mails
- Veröffentlichungs-Mails
- Veranstalterbereich-/Magic-Link-Mails
- spätere vergleichbare Systemmails

Ziel ist ein einheitlicher, persönlicher und hochwertiger Mail-Auftritt, der zur Produkt- und Designsprache von Bocholt erleben passt.

---

<!-- === BEGIN BLOCK: MAIL_SYSTEM_CURRENT_STATUS_2026_06_29 | Zweck: verhindert erneutes Oeffnen des Mail-Systems als Grossbaustelle; Umfang: Statusklaerung und Abgrenzung zur Produktreife-Roadmap === -->
## Aktueller Status 2026-06-29

Mail-System V1 ist umgesetzt und getestet. Weitere Mailarbeit ist nur noetig, wenn:

- ein konkretes neues Mailtopic entsteht,
- ein Zustell-/Darstellungsfehler belegt ist,
- oeffentliche Verkaufs-/Rechtstexte neue Pflichtinformationen in bestehenden Mails erfordern.

Das Mail-System ist nicht Teil der naechsten groesseren Produktbaustellen. Es kann aber beim Workpack `Anbieterbereich und Verkaufsstrecke verkaufsfertig machen` punktuell betroffen sein.
<!-- === END BLOCK: MAIL_SYSTEM_CURRENT_STATUS_2026_06_29 === -->

## 1. Grundsatz

Bocholt erleben verschickt keine beliebigen Formular- oder Technikmails.

Systemmails sollen wirken wie eine ruhige, redaktionelle App-Kommunikation:
- klar
- persönlich
- lokal
- hochwertig
- nicht werblich überzogen
- nicht technisch
- nicht verwaltungssprachlich

Bocholt erleben ist kein Anzeigenplatz. Die Mails dürfen daher nicht so klingen, als könne Veröffentlichung einfach gekauft werden.

---

## 2. Verbindliches Wording

Bevorzugte Begriffe:
- `Einreichung`
- `Veranstaltung`
- `Einzeltermin`
- `Aktivität`
- `Aktivitätspräsenz`
- `redaktionelle Prüfung`
- `redaktionelle Freigabe`
- `sichtbar`
- `veröffentlicht`
- `Zahlungslink`
- `Veranstalterbereich`

Zu vermeiden:
- `Submission`
- interne Statusnamen
- `grundsätzlich`
- `Anzeige`
- `Premium-Platzierung`
- `sofort online`
- `direkt veröffentlichen`
- `automatisch veröffentlicht`
- unnötige System- oder Prozesssprache

---

## 3. Anrede

Mails sollen nach Möglichkeit persönlich adressiert werden.

Priorität:
1. Kontaktname aus der Einreichung oder dem Veranstalterprofil
2. Fallback: neutrale Anrede

Standard:

`Hallo [Kontaktname],`

Fallback:

`Hallo,`

Nicht als Standard verwenden:

`Hallo [Organisation],`

Organisationsnamen eignen sich nicht zuverlässig als persönliche Anrede.

---

## 4. Textstruktur

Alle Mails folgen möglichst dieser Reihenfolge:

1. Persönliche Anrede
2. kurzer Statusabsatz
3. Objektbox mit relevanten Daten
4. nächster Schritt oder CTA
5. Hinweisbox, falls rechtlich/fachlich nötig
6. Signatur

Plain-Text-Fallback-Struktur:

`Hallo [Kontaktname],`

`[Statusabsatz]`

`[Objektlabel]:`
`[Titel]`

`Referenz:`
`[Referenz]`

`[Nächster Schritt]`

`[Hinweisüberschrift]:`
`[Hinweistext]`

`Viele Grüße`
`Bocholt erleben`

---

## 5. Visueller HTML-Standard

Systemmails sollen als HTML-Mail mit Plain-Text-Fallback verschickt werden.

Technische Zielstruktur:
- `multipart/alternative`
- `text/plain`
- `text/html`
- zentrale Rendering-Funktion
- keine individuellen HTML-Einzelkonstruktionen pro Mail

HTML-Regeln:
- keine externen Fonts
- keine externen Bilder
- keine Skripte
- keine Tracking-Pixel
- keine CSS-Dateien
- keine komplexen Animationen
- Inline-Styles für Mailclient-Kompatibilität
- tabellenbasiertes Grundlayout zulässig und bevorzugt

---

## 6. Typografie

Schriftfamilie:

`font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;`

Nicht verwenden:
- Times New Roman
- Courier New
- Comic Sans
- Georgia als Standardschrift

Typografische Richtung:
- moderne Systemschrift
- ruhige Zeilenhöhe
- klare Hierarchie
- keine Kursivschrift
- Fettung nur sparsam

Fett bzw. semibold nur für:
- Mailtitel
- Datenlabels
- CTA-Button
- Hinweisüberschrift
- wichtige Kurzlabels

---

## 7. Farben

Die Mail orientiert sich an den bestehenden Bocholt-erleben-Tokens.

| Zweck | Wert |
|---|---|
| Hintergrund | `#F9FBF6` |
| Karte | `#FFFFFF` |
| Primärtext | `#1F2933` |
| Sekundärtext | `#5F6B73` |
| Akzent | `#8BCF4A` |
| sanfter Akzent | `#EAF6DB` |
| Linie | `#E3EBDD` |

Die Mail soll wie eine ruhige App-Karte wirken:
- helle Hintergrundfläche
- zentrale weiße Karte
- dezente Linie
- abgerundete Ecken
- ausreichend Weißraum

---

## 8. Komponenten

### 8.1 Brand-Zeile

Oben in der Mailkarte:

`Bocholt erleben`

Ruhig, klein, semibold.

### 8.2 Mailtitel

Kurz, statusorientiert, nicht technisch.

Beispiele:
- `Dein Einzeltermin wird geprüft`
- `Zahlungslink für deinen Einzeltermin`
- `Deine Veranstaltung ist sichtbar`
- `Deine Aktivität wird geprüft`

### 8.3 Objektbox

Für Veranstaltung, Aktivität, Referenz und ggf. Frist.

Beispiel:

Veranstaltung  
Sommerabend im Langenbergpark

Referenz  
BE-2026-001234

### 8.4 Hinweisbox

Nur einsetzen, wenn der Hinweis wichtig ist.

Beispiel:

Hinweis zur Veröffentlichung  
Die Zahlung führt nicht automatisch zur Veröffentlichung. Sichtbar wird die Veranstaltung erst nach finaler redaktioneller Freigabe.

### 8.5 CTA

Nur verwenden, wenn die Mail eine klare Handlung enthält.

Beispiele:
- Zahlungslink öffnen
- Veranstalterbereich öffnen
- Einreichung ansehen

Rohlinks sollen im Plain-Text-Fallback vollständig enthalten sein.

---

## 9. Freigegebener Pilot

Pilot-Mail:
`Eingangsbestätigung Einzeltermin`

Betreff:

`Dein Einzeltermin wird geprüft`

Plain-Text-Fassung:

Hallo [Kontaktname],

vielen Dank für deine Einreichung. Wir prüfen den Termin redaktionell und achten darauf, dass die Angaben vollständig und verständlich sind.

Veranstaltung:
[Titel]

Referenz:
[Referenz]

Wenn der Termin zu Bocholt erleben passt, senden wir dir im nächsten Schritt den Zahlungslink für den Einzeltermin.

Hinweis zur Veröffentlichung:
Die Zahlung führt nicht automatisch zur Veröffentlichung. Sichtbar wird die Veranstaltung erst nach finaler redaktioneller Freigabe.

Viele Grüße
Bocholt erleben

Diese Mail ist der Referenzstandard für Ton, Struktur und visuelle Hierarchie der weiteren Mailmigration.

---

## 10. Migrationsreihenfolge

Die bestehenden vorgefertigten Mails werden nicht gesammelt blind ersetzt, sondern einzeln gegen diesen Contract geprüft und freigegeben.

Reihenfolge:
1. Eingangsbestätigung Einzeltermin
2. Eingangsbestätigung Aktivitätspräsenz
3. Zahlungslink Einzeltermin
4. Zahlungslink Aktivitätspräsenz
5. Ablehnung Einzeltermin
6. Ablehnung Aktivitätspräsenz
7. Veröffentlichung Einzeltermin
8. Veröffentlichung Aktivitätspräsenz
9. Zugangslink Veranstalterbereich

Für jede Mail gilt:
- Vorher/Nachher prüfen
- Betreff festlegen
- Plain-Text-Fassung festlegen
- HTML-Darstellung aus zentralem Renderer ableiten
- benötigte Variablen prüfen
- erst danach technisch umsetzen

---

## 11. Technische Zielentscheidung

Die technische Umsetzung soll zentral erfolgen.

Ziel:
- bestehende Mailfunktion erweitern
- HTML-Renderer zentral kapseln
- Plain-Text-Fallback immer erhalten
- keine Mail-spezifischen Layout-Fragmente in einzelnen API-Endpunkten

Bevorzugter Owner:
`api/_bootstrap.php`

Die einzelnen Endpunkte sollen nur noch fachliche Daten liefern:
- Empfänger
- Kontaktname
- Betreff
- Mailtitel
- Introtext
- Objektangaben
- CTA-Daten
- Hinweistext
- Plain-Text-Fallback

---

## 12. Umsetzungsstatus 2026-06-27

Der Mail-System-Contract ist nicht mehr als offener Implementierungsauftrag zu lesen.

Aktueller Stand:

- zentrale Topic-Logik in `api/_bootstrap.php` umgesetzt,
- HTML-Mail und Plain-Text-Fallback zentral ableitbar,
- neun Mailtopics im Sammeltest geprüft,
- Pilot- und Zahlungslink-/Statusmails als Systempfad dokumentiert,
- dieses Dokument bleibt Referenz fuer Ton, Aufbau, Komponenten und kuenftige neue Mailtopics.

Weitere Arbeit nur bei konkretem Anlass:

- neues fachliches Mailtopic,
- nachgewiesener Zustellfehler,
- Darstellungsproblem in einem relevanten Mailclient,
- geaenderter Zahlungs-/Freigabeprozess mit neuen Pflichtinformationen.

Keine offene Standardaufgabe mehr:

- kein pauschaler Neubau des Mail-Renderers,
- keine blinde Migration ohne neuen Mailfall,
- keine neue Mail-Designrunde ohne Symptom.

<!-- === END FILE: MAIL_SYSTEM.md | Zweck: kanonischer Mail-System-Contract für vorgefertigte Bocholt-erleben-Systemmails; Umfang: Sprache, Layout, Formatierung, Pilot-Mail und Migrationsregeln === -->
