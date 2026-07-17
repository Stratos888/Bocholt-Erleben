# Externe Ressourcen- und Schreibmatrix

Stand: 2026-07-17  
Status: verbindlicher Sicherheitsvertrag fuer Repo-, Staging- und KI-Arbeit

## Zweck

Git-Konflikte erkennen nur konkurrierende Codeaenderungen. Dieses Projekt verwendet daneben Google Sheets, Datenbanken, STRATO, Stripe, Mail, Search-Metrics und weitere externe Ressourcen. Zwei technisch konfliktfreie Branches koennen deshalb trotzdem denselben Datensatz, Prozess oder Deploy beeinflussen.

Jeder Workpack muss externe Ressourcen vor der ersten Umsetzung deklarieren. Nicht inventarisierte oder nicht nachweisbar isolierte Ressourcen sind fail-closed und werden read-only behandelt.

## Allgemeine Regeln

1. Keine Testschreibaktion auf Live-Ressourcen.
2. Keine reale Datenmutation durch einen Workpack-Chat.
3. Kontrollierte Staging-Schreibproben werden ausschliesslich durch den Integrations-Chat koordiniert.
4. Vor einer Schreibprobe muessen Zielumgebung, Ressource, stabile Objektidentitaet, Vorherzustand, erwartete Mutation, Ruecklesen und Rollback feststehen.
5. Pro externer Ressource darf hoechstens ein aktiver Workpack einen Schreib- oder Abnahme-Lock besitzen.
6. Ist die Isolation unbekannt oder nur aus Dateinamen beziehungsweise UI-Text abgeleitet, bleibt Schreiben gesperrt.
7. Eine manuelle Datenkorrektur ist kein technischer Root-Cause-Nachweis.

## Kanonische Matrix

| Ressource | Staging | Live | Staging-Schreibstatus | Lock-/Abnahmeregel |
|---|---|---|---|---|
| Google Sheet Inbox | `Inbox_Staging` | `Inbox` | kontrolliert moeglich | nur dedizierter Testfall, Vorher-/Nachher-Evidence und Ruecklesen |
| Google Sheet Inbox-Archiv | `Inbox_Archive_Staging` | `Inbox_Archive` | kontrolliert moeglich | nur zusammen mit dem zugehoerigen Inbox-Test und eindeutigem Fall |
| Google Sheet Events | `Events_Staging` als isoliertes Overlay auf der read-only Basis `Events` | `Events` | kontrolliert moeglich | Staging darf nur `Events_Staging` mutieren; Deploy merged Basis und Overlay fail-closed; Live-Tab bleibt read-only |
| Google Sheet Content-/Search-Feedback | Tab und Umgebung je Workflow pruefen | produktive Feedback-Tabs | standardmaessig gesperrt | nur nach belegter Staging-Zielaufloesung und eigener Testidentitaet |
| Growth-Backlog | konkrete Quelle je API/Workflow pruefen | produktive Quelle | standardmaessig gesperrt | vor Schreibtests Quelle, Umgebung und Objekt-ID inventarisieren |
| Veranstalter-/Submission-Datenbank | Staging-DB laut Deploy-Konfiguration; Runtime separat nachweisen | Live-DB | kontrolliert erst nach Nachweis | keine Zahlung, Mail oder Veroeffentlichung als Testnebenwirkung |
| Activities | Repo-/JSON-owned | Repo-/JSON-owned | ueber Branch/PR | keine stille externe Datenmutation; normaler Code-Scope-Lock |
| Visual-Pool und Asset-Backlog | Repo-/Artefaktvertrag je Workpack | deployter Pool | ueber Branch/PR; externe Produktion separat | `VISUAL_WORKFLOW.md` und konkrete Asset-Identitaet beachten |
| STRATO Staging | Verzeichnis `staging` | nicht zutreffend | nur sequenzieller Deploy | ausschliesslich Integrations-Chat; genau ein Deploy/Smoke gleichzeitig |
| STRATO Live | nicht zutreffend | Webroot `.` | keine Testschreibaktion | nur `staging -> main` Release und read-only Live-Smoke |
| Stripe | Test-/Staging-Secrets laut Deploy-Konfiguration | Live-Secrets | keine echte Zahlung als Smoke | Write-/Checkout-Test nur eigener freigegebener Zahlungs-Workpack |
| Mail/SMTP | Staging-Konfiguration | Live-Konfiguration | keine echte Mail im normalen Smoke | nur expliziter Mail-Workpack mit Testempfaenger und Freigabe |
| Search Console/Bing | read-only Export | read-only Export | read-only | keine Konfigurations- oder Property-Aenderung im Produktworkpack |
| GitHub Actions / Deploy | Branch- und Workflowzustand | Main-Workflowzustand | nur kontrolliert | Feature-Branches duerfen niemals deployen; `main` und `staging` sind die einzigen erlaubten Deploy-Refs |

## Events-Overlay-Vertrag

1. `Events` bleibt die gemeinsame read-only Basis und darf von Staging niemals beschrieben werden.
2. `Events_Staging` enthaelt ausschliesslich Staging-Freigaben oder gezielte Staging-Overrides.
3. Der Staging-Deploy fuehrt Basis und Overlay anhand stabiler ID beziehungsweise URL zusammen; doppelte oder widerspruechliche Identitaeten blockieren den Deploy.
4. Der Live-Deploy liest ausschliesslich `Events` und ignoriert `Events_Staging` vollstaendig.
5. Eine Staging-Freigabe gilt erst nach unabhaengiger Ruecklesepruefung von Eventzeile und terminalem `Inbox_Staging`-Status als abgeschlossen.

## Ressourcendeklaration im Draft-PR

Jeder PR nennt unter `Externe Ressourcen` mindestens:

- Ressource und konkrete Untereinheit, zum Beispiel `Google Sheet / Inbox_Staging`;
- Zugriff: `none`, `read-only`, `controlled-write`;
- stabile Test- oder Objektidentitaet;
- Besitzer des Ressourcen-Locks;
- erwartete Staging-Abnahme;
- Rollback beziehungsweise Bereinigung.

`controlled-write` ist nur eine Anforderung an den Integrations-Chat und keine Vorabfreigabe fuer den Workpack-Chat.

## Stop-the-line

Sofortiger Arbeitsstopp bei:

- unerwarteter Umgebung, Sheet-ID, Tab-, DB- oder Deploy-Zielauflösung;
- unklarer oder mehrfacher Objekt-/Zeilenidentitaet;
- Abweichung zwischen Quelle, lokaler Kopie, API und UI;
- unerwarteter realer Mutation;
- fehlendem Vorherzustand;
- manueller Korrektur, die einen technischen Test scheinbar gruen macht;
- parallelem Zugriff eines zweiten Workpacks auf dieselbe Ressource.

Danach sind nur read-only Forensik, Evidence-Sicherung und Root-Cause-Analyse erlaubt. Kein weiterer Patch, keine Datenkorrektur, keine Schreibprobe und kein Merge bis zur expliziten Freigabe durch den Integrations-Chat.
