# Evidence – E4-Abbruch vor Mutation durch direkten MySQL-Zugriff

Stand: 2026-07-20  
Evidence-Stufe: fehlgeschlagener E4-Start vor erster Mutation

## Beobachtung

Der erste manuell bestätigte Lauf von `Control Center E4 Synthetic Proof` erreichte den Schritt `Run isolated synthetic E4 proof` und brach beim Aufbau der direkten MySQL-Verbindung vom GitHub-hosted Runner zur Staging-Datenbank ab.

Belegter Fehler:

```text
pymysql.err.OperationalError: (2003, "Can't connect to MySQL server ... (timed out)")
```

Der Stack endete im Konstruktor der bisherigen `Database`-Klasse bei `pymysql.connect(...)`.

## Mutationsgrenze

Der bisherige Harness setzte `mutations_started = True` erst nach:

1. Verbindung zu Google Sheets;
2. Verbindung zur Staging-Datenbank;
3. erfolgreichem DB-Preflight;
4. Build- und Restzustandsprüfung.

Der Timeout trat bereits in Schritt 2 auf. Damit wurden im fehlgeschlagenen Lauf nicht ausgeführt:

- kein Append nach `Inbox_Staging`;
- kein Seed nach `Events_Staging`;
- kein Control-Center-Case;
- keine Control-Center-Operation;
- kein synthetischer Staging-Feed-Deploy;
- keine Live-Mutation.

Ein Cleanup-Write war nicht erforderlich, weil noch kein synthetischer Zustand entstanden war.

## Ursachenbewertung

Der Fehler ist ein Netzwerk-Erreichbarkeitsfehler des Architekturpfads:

```text
GitHub-hosted Runner
-> direkter externer TCP/MySQL-Zugriff
-> geschützte STRATO-Staging-Datenbank
```

Ein Timeout belegt keine falschen DB-Zugangsdaten und keinen Fehler im fachlichen Inbox-Writer. Der Runner erhielt auf dem Datenbankpfad keine rechtzeitige Verbindung.

## Verworfene Reparatur

Nicht umgesetzt werden:

- öffentlicher MySQL-Port für GitHub-hosted Runner;
- wechselnde GitHub-IP-Allowlist;
- breitere Datenbankfreigabe;
- zweiter Lauf mit unverändertem Architekturpfad;
- manuelle synthetische Datenkorrektur.

Diese Optionen würden die Angriffsfläche erhöhen und keinen stabilen, wartbaren E4-Vertrag schaffen.

## Architekturentscheidung

Der direkte Datenbank-Client im GitHub-Runner wird ersetzt durch:

```text
GitHub-hosted Runner
-> HTTPS + bestehendes Review-Passwort
-> ausschließlich Staging-Endpunkt
-> private serverseitige PDO-Verbindung
-> nur exakter synthetischer Run-Key
```

Der Serververtrag darf ausschließlich:

- synthetische Restzustände zählen;
- den kontrollierten `partial-failed`-Operationszustand für einen zuvor angelegten synthetischen Case setzen;
- synthetische Case-/Operationszustände rücklesen;
- alle DB-Zustände des exakten Run-Keys bereinigen.

Er verweigert Produktion, falsche Builds, fremde Run-Keys, fremde Operationen und nichtsynthetische Cases. Cleanup bleibt auch nach einer zwischenzeitlichen Staging-Bewegung möglich, bleibt aber weiterhin auf den exakten Run-Key begrenzt.

## Stop-the-line und Folgezustand

Der fehlgeschlagene Lauf wird nicht erneut gestartet. Vor einem neuen Ersatzlauf sind erforderlich:

1. neuer serververmittelter Contract und Adapter;
2. positive und negative E2-Tests;
3. grüne `Control Center CI`, `Project Guardrails` und `PR Gate`;
4. Integration nach `staging`;
5. grüner Deploy und fachfallfreies read-only E3 für den neuen finalen SHA;
6. erst danach genau ein neuer manueller E4-Lauf mit neuer synthetischer Run-ID.

CityArt und alle anderen realen Fachfälle bleiben vollständig ausgeschlossen.
