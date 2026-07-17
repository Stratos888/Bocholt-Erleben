# Externe Ressourcen- und Schreibmatrix

Stand: 2026-07-17  
Status: verbindlicher Sicherheitsvertrag für Repo-, Staging- und KI-Arbeit

## Zweck

Git-Konflikte erkennen nur Codeüberschneidungen. Google Sheets, Datenbanken, STRATO, Stripe, Mail und weitere Dienste benötigen zusätzlich einen Ressourcen-Lock und reale Runtime-Evidence.

Jeder Workpack deklariert externe Ressourcen vor der Umsetzung. Nicht inventarisierte oder nicht nachweisbar isolierte Ressourcen bleiben fail-closed und read-only.

## Allgemeine Regeln

1. Keine Testschreibaktion auf Live-Ressourcen.
2. Normale Implementierungsschritte mutieren keine externen Daten.
3. Ein `R3`-Workpack darf innerhalb eines erteilten Arbeitsmandats isolierte synthetische Staging-Schreibtests ausführen, wenn Gate C, stabile Identität, Vorherzustand, Rücklesen und Cleanup dokumentiert sind.
4. Ein echter Fachdatensatz wird erst nach E4 verwendet.
5. Pro externer Ressource darf höchstens ein aktiver Schreib- oder Abnahme-Lock bestehen.
6. Eine manuelle Datenkorrektur ist kein technischer Root-Cause- oder Erfolgsnachweis.
7. Scope-Erweiterung, Live-Write, reale Zahlung, echte Nachricht oder irreversible Aktion erfordern eine neue Nutzerfreigabe.

## Kanonische Matrix

| Ressource | Staging | Live | Staging-Schreibstatus | Gate-/Lock-Regel |
|---|---|---|---|---|
| Google Sheet Inbox | `Inbox_Staging` | `Inbox` | kontrolliert möglich | R3; E3-Preflight, synthetischer E4-Test, Rücklesen |
| Google Sheet Inbox-Archiv | `Inbox_Archive_Staging` | `Inbox_Archive` | kontrolliert möglich | nur mit zugehörigem Inbox-Test und eindeutiger Identität |
| Google Sheet Events | `Events_Staging` als isoliertes Overlay auf der read-only Basis `Events` | `Events` | kontrolliert möglich | Staging schreibt nur `Events_Staging`; Live-Tab bleibt read-only |
| Google Sheet Content-/Search-Feedback | je Workflow explizit auflösen | produktive Feedback-Tabs | standardmäßig gesperrt | erst nach E3 und eigener Testidentität |
| Growth-Backlog | konkrete Quelle je API/Workflow | produktive Quelle | standardmäßig gesperrt | Quelle, Umgebung und Objekt-ID vor Write inventarisieren |
| Veranstalter-/Submission-Datenbank | Staging-DB nach Runtime-Nachweis | Live-DB | kontrolliert nach E3/E4 | keine Zahlung, Mail oder Veröffentlichung als Nebenwirkung |
| Activities | Repo-/JSON-owned | Repo-/JSON-owned | über Branch/PR | normaler Code-/Owner-Lock |
| Visual-Pool und Asset-Backlog | Repo-/Artefaktvertrag | deployter Pool | über Branch/PR | `VISUAL_WORKFLOW.md` und Asset-Identität beachten |
| STRATO Staging | Verzeichnis `staging` | nicht zutreffend | sequenzieller Deploy | ein Deploy/Smoke gleichzeitig; primärer Chat ist Owner |
| STRATO Live | nicht zutreffend | Webroot `.` | keine Testschreibaktion | nur `staging -> main` und read-only Live-Smoke |
| Stripe | Test-/Staging-Secrets | Live-Secrets | nur eigener R3-Workpack | keine reale Zahlung ohne neue Nutzerfreigabe |
| Mail/SMTP | Staging-Konfiguration | Live-Konfiguration | nur eigener R3-Workpack | Testempfänger; keine echte Nachricht ohne Freigabe |
| Search Console/Bing | read-only Export | read-only Export | read-only | keine Property-/Konfigurationsänderung im Produktworkpack |
| GitHub Actions / Deploy | Branch-/Workflowzustand | Main-Workflowzustand | kontrolliert | Feature-Branches duerfen niemals deployen; nur `main` und `staging` |

## Events-Overlay-Vertrag

1. `Events` bleibt die gemeinsame read-only Basis und darf von Staging niemals beschrieben werden.
2. `Events_Staging` enthält ausschließlich Staging-Freigaben oder gezielte Overrides.
3. Der Staging-Deploy führt Basis und Overlay anhand stabiler ID beziehungsweise URL zusammen; Konflikte blockieren fail-closed.
4. Der Live-Deploy ignoriert `Events_Staging` vollständig.
5. Eine Staging-Freigabe gilt erst nach Rückleseprüfung von Eventzeile, terminalem Inboxstatus und lokalem Fallstatus als abgeschlossen.

## Ressourcendeklaration

PR und `CURRENT_WORKPACK.md` nennen:

- Ressource und Untereinheit;
- Zugriff `none`, `read-only` oder `controlled-write`;
- stabile Test-/Objektidentität;
- Besitzer des Ressourcen-Locks;
- Vorherzustand, erwartete Mutation und Postconditions;
- Cleanup/Rollback;
- benötigte Evidence-Stufe.

`controlled-write` ist keine pauschale Freigabe. Es ist innerhalb des vereinbarten R3-Workpacks nur für den dokumentierten synthetischen Staging-Test zulässig. Der echte Fachfall folgt erst nach E4.

## Stop-the-line

Sofort stoppen bei:

- unerwarteter Umgebung, Sheet-ID, Tab-, DB- oder Deploy-Zielauflösung;
- unklarer oder mehrfacher Objektidentität;
- Abweichung zwischen Quelle, lokaler Kopie, API und UI;
- unerwarteter oder partieller Mutation;
- fehlendem Vorherzustand;
- manueller Grünkorrektur;
- parallelem Zugriff auf dieselbe Ressource.

Danach gelten Fehlerbudget und Gate-Regeln aus `AI_ENTRYPOINT.md`: keine Wiederholung ohne neue E3-/E4-Evidence und nach zwei widerlegten Hypothesen Architektur- oder Revert-Entscheidung.