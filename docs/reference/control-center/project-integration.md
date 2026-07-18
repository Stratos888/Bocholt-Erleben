# Steuerzentrale – Integration in das Gesamtprojekt

Stand: 2026-07-10
Status: verbindlicher E2E- und Prozessvertrag

## 1. Rolle im Gesamtprojekt

Die Steuerzentrale ist die zentrale private Betreiberoberfläche von Bocholt erleben. Sie ersetzt nicht die fachlich führenden Datenquellen, sondern orchestriert menschliche Arbeit über diese Quellen hinweg.

Sie verbindet:

- KI-/Sheet-Eventrecherche,
- Content-Audit,
- Anbieter-Einreichungen und Zahlung,
- Event- und Aktivitätsverwaltung,
- Aufgaben, Backlog und Ideen,
- Systemstatus und relevante Automatisierungen,
- Deploy- und Veröffentlichungswirkung.

Nicht zulässig ist eine zweite, unabhängige Datenwelt in der Steuerzentrale.

## 2. Führende Quellen

| Fachbereich | Führende Quelle | Rolle der Steuerzentrale |
|---|---|---|
| Eventbestand | Events-Sheet und daraus erzeugte öffentliche Daten | suchen, bearbeiten, validieren, Writeback auslösen, Veröffentlichung verfolgen |
| Aktivitäten | bestehender Aktivitätsbestand/führende Datenquelle | suchen, bearbeiten, Writeback auslösen, Veröffentlichung verfolgen |
| KI-/Recherche-Kandidaten | generierter Inbox-Feed und Apps-Script-Prozess | fokussierte Entscheidung und Quell-Writeback |
| Content-Audit | Content_Audit-Tab | prüfen, korrigieren, verifizieren, zurückstellen, Quell-Writeback |
| Anbieter-Einreichungen | MySQL-Submission-Prozess | Prüfung, Zahlungsfreigabe, Warten, Veröffentlichung, Ablehnung |
| Aufgaben/Arbeit | zentrales control_cases-Modell | führender Arbeitsstatus und Verlauf |
| Growth-/Acquisition-Backlog | bestehende Backlog-API bis Migration | vollständig in kanonischen Arbeitslebenszyklus migrieren |
| Repo-Workpacks/technische Schulden | Repo-Dokumentation/GitHub | nur kuratierte, handlungsrelevante Punkte übernehmen; keine ungefilterte Spiegelung |

## 3. E2E-Grundregel

Jede fachliche Änderung folgt diesem Ablauf:

`Betreiberaktion -> fachliche Validierung -> führende Quelle aktualisieren -> zentralen Verlauf schreiben -> Daten neu erzeugen/deployen -> öffentliche Wirkung prüfen`

Ein zentraler Vorgang darf erst abgeschlossen werden, wenn der fachliche Writeback erfolgreich war. Bei einem nachgelagerten Build-/Deploy-Fehler bleibt die Änderung als wartender oder blockierter Vorgang sichtbar.

## 4. Arbeitslebenszyklus

Die Steuerzentrale führt einen gemeinsamen Arbeitslebenszyklus:

- Idee: ungeprüfter möglicher Nutzen, keine tägliche Aufgabenlast.
- Backlog: bewusst vorgemerkte Arbeit, noch nicht aktiv eingeplant.
- Aufgabe: entschiedene konkrete Arbeit mit nächstem Schritt.
- Bearbeiten: ungeklärte oder entscheidungsbedürftige Fälle aus Quellsystemen.
- Information: relevant, aber ohne Handlung.

Übergänge erfolgen ohne Kopien:

`Idee -> Backlog -> Aufgabe -> erledigt`

Direkte Übergänge sind erlaubt, wenn die Entscheidung bereits getroffen ist. Ein Sachverhalt besitzt dabei immer genau einen führenden Arbeitsstatus.

## 5. Navigationsziel

Die Hauptnavigation lautet verbindlich:

1. Übersicht
2. Bearbeiten
3. Arbeit
4. Verwaltung
5. Menü

### Übersicht

Verdichtet echten Handlungsbedarf, nächste Arbeit und relevante Systemauswirkung.

### Bearbeiten

Fokussierte Bearbeitung von neuen Inhalten, Qualitätsfällen, Anbieterfällen, Änderungen und Freigaben.

### Arbeit

Gemeinsamer Bereich für:

- aktive Aufgaben,
- wartet/blockiert,
- Backlog,
- Ideen,
- Archiv.

### Verwaltung

Echte interne Objektverwaltung für Events und Aktivitäten einschließlich Bearbeitung und automatischer Übernahme in die führende Quelle.

### Menü

Selten benötigte Funktionen:

- Anbieterbereich,
- Systemstatus,
- Einstellungen,
- Abmelden,
- technische Diagnose nur nachrangig.

## 6. Verwaltung als E2E-Prozess

Die Verwaltung ist nur vollständig, wenn sie:

- Event oder Aktivität sucht,
- aktuelle Daten und Publikationsstatus zeigt,
- offene Vorgänge verknüpft,
- fachliche Felder bearbeitbar macht,
- beim Speichern die führende Quelle aktualisiert,
- notwendige Datengenerierung/Deploy auslöst oder zuverlässig anstößt,
- Erfolg oder Fehler verständlich zurückmeldet,
- den Änderungsverlauf protokolliert.

Eine reine Link- oder Suchliste ist keine fertige Verwaltung.

## 7. Backlog-Integration

Vor dem vollständigen Zielzustand müssen alle Backlogquellen inventarisiert und dedupliziert werden:

- Growth-/Acquisition-Backlog der bisherigen Inbox,
- manuelle Ideen,
- dokumentierte offene Workpacks,
- relevante technische Schulden,
- geplante Content- und Produktverbesserungen.

Nicht jeder Repo-Hinweis wird automatisch Arbeit. Übernommen werden nur Punkte mit:

- klarer Relevanz,
- nachvollziehbarem Nutzen oder Risiko,
- eindeutiger Zuständigkeit im Projekt,
- dedupliziertem Bezug zu bestehender Arbeit.

## 8. Systemstatus

Der Standardzustand ist fachlich formuliert:

`Alle relevanten Quellen sind synchronisiert. Keine bekannte Störung mit Auswirkung.`

Rohwerte wie `seen` und `upserted` sind nur unter technischen Details sichtbar. Prominent wird eine Störung nur bei echter Auswirkung oder notwendiger Betreiberaktion.

## 9. Altansichten

Die bisherige `/inbox/` bleibt nur so lange erhalten, wie reguläre Funktionen noch nicht migriert sind. Sie darf nach Abschluss der Migration nur noch technische Diagnose oder Übergangsvergleich unterstützen und wird anschließend weitergeleitet.

## 10. Freigaberegel

Die Steuerzentrale ist erst als Gesamtprodukt freigegeben, wenn:

- alle führenden Quellen korrekt angebunden sind,
- Verwaltung echte Writebacks und Veröffentlichungswirkung besitzt,
- Growth-Backlog und kuratierte Projektarbeit integriert sind,
- Aufgaben, Backlog und Ideen ohne Doppelpflege funktionieren,
- alle Abnahmekriterien erfüllt sind,
- die alte Inbox für reguläre Arbeit nicht mehr benötigt wird.
