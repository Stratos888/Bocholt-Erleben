# Externe Ressourcen- und Schreibmatrix

Stand: 2026-07-18  
Status: verbindlicher Sicherheitsvertrag

## 1. Grundregeln

1. Keine Testschreibaktion auf Live-Ressourcen.
2. Normale Implementierungsschritte mutieren keine externen Daten.
3. Nicht inventarisierte oder nicht nachweisbar isolierte Ressourcen bleiben fail-closed und read-only.
4. Pro Ressource darf nur ein aktiver Schreib- oder Abnahme-Lock bestehen.
5. Vor jedem Write stehen Ziel, stabile Identität, Vorherzustand, erwartete Mutation, Rücklesen und Cleanup/Rollback fest.
6. Eine Teilmutation oder manuelle Grünkorrektur ist kein Erfolg.
7. Ein wiederverwendbarer R3-Pfad nutzt zuerst einen isolierten synthetischen Staging-Beweis.
8. Eine einzelne ausdrücklich beauftragte deterministische Live-Admin-Mutation ist nur nach dem Ausnahmevertrag aus `AI_ENTRYPOINT.md` zulässig und niemals ein Test.

## 2. Zugriffsklassen

- `none`: kein Zugriff im Workpack.
- `read-only`: lesen, vergleichen und Evidence sichern.
- `controlled-staging-write`: nur dokumentierter synthetischer oder freigegebener Staging-Write mit Rücklesen und Cleanup.
- `controlled-live-admin`: genau eine ausdrücklich beauftragte deterministische Live-Änderung mit Vorherzustand, Rücklesen und Rollback.

`controlled-live-admin` ist keine pauschale Live-Schreibfreigabe.

## 3. Kanonische Matrix

| Ressource | Staging | Live | Standardstatus | Ausnahme-/Gate-Regel |
|---|---|---|---|---|
| Google Sheet Inbox | `Inbox_Staging` | `Inbox` | Staging kontrolliert, Live read-only | R3; E3, synthetisches E4, Rücklesen |
| Inbox-Archiv | `Inbox_Archive_Staging` | `Inbox_Archive` | nur mit zugehörigem Inbox-Test | eindeutige Identität und Cleanup |
| Google Sheet Events | `Events_Staging` über read-only Basis `Events` | `Events` | Staging schreibt nur Overlay | einzelnes Live-Event nur als `controlled-live-admin` |
| Content-/Search-Feedback | je Workflow explizit | produktive Tabs | standardmäßig read-only | eigener R3-Vertrag und Testidentität |
| Growth-Backlog | je API/Workflow | produktive Quelle | standardmäßig read-only | Quelle, Umgebung und Objekt-ID inventarisieren |
| Submission-/Anbieter-DB | Staging-DB | Live-DB | nach E3/E4 kontrolliert | keine Zahlung, Mail oder Veröffentlichung als unbeabsichtigte Nebenwirkung |
| Activities | Repo-/JSON-owned | Repo-/JSON-owned | Branch/PR | normaler Code-/Owner-Lock |
| Visual-Pool/Assets | Repo-/Artefaktvertrag | deployter Pool | Branch/PR | `VISUAL_WORKFLOW.md` und Asset-Identität |
| STRATO Staging | Verzeichnis `staging` | – | sequenzieller Deploy | ein Deploy/Smoke gleichzeitig |
| STRATO Live | – | Webroot `.` | nur `staging -> main` | direkter Main-Hotfix nur nach vollständig freigeschaltetem Ausnahmevertrag; nie als Test |
| Stripe | Test-/Staging-Konfiguration | Live-Secrets | eigener R3-Workpack | keine reale Zahlung ohne neue Freigabe |
| Mail/SMTP | Staging-Testempfänger | Live-Empfänger | eigener R3-Workpack | keine echte Nachricht ohne neue Freigabe |
| Search Console/Bing | read-only | read-only | read-only | keine Property-/Konfigurationsänderung im Produktworkpack |
| GitHub Actions/Deploy | Branch-/Workflowzustand | Main-Workflowzustand | kontrolliert | Feature-Branches deployen nie; nur `staging` und `main` |

## 4. Events-Overlay-Vertrag

1. `Events` ist gemeinsame Live-Basis.
2. Staging liest `Events`, schreibt aber ausschließlich `Events_Staging`.
3. Das Overlay enthält nur Staging-Freigaben oder gezielte Overrides.
4. Der Staging-Build führt Basis und Overlay über stabile Identität zusammen; Konflikte blockieren fail-closed.
5. Der Live-Build ignoriert `Events_Staging`.
6. Eine Staging-Freigabe ist erst nach Event-Rücklesen, terminalem Inboxstatus und lokalem Fallstatus abgeschlossen.

## 5. Einzelne Live-Event-Admin-Mutation

Zulässig nur bei ausdrücklicher Nutzerbeauftragung und:

- eindeutigem Event und stabiler ID;
- vollständigem Vorherzustand;
- exakt deklarierten Feldern;
- fachlichen, Schema- und Qualitätsguards;
- sofortigem Rücklesen;
- unveränderten Nicht-Zielfeldern;
- eindeutigem Rollback;
- read-only Feed-/Detailseitenprüfung;
- exklusivem Ressourcen-Lock.

Bei jeder Abweichung: stoppen, Evidence sichern, nicht erneut schreiben.

## 6. Ressourcendeklaration im Workpack/PR

- Ressource und Untereinheit;
- Zugriffsklasse;
- stabile Test-/Objektidentität;
- Lock-Owner;
- Vorherzustand;
- erwartete Mutation;
- Postconditions;
- Cleanup/Rollback;
- benötigte Evidence.

## 7. Stop-the-line

Sofort stoppen bei unerwarteter Umgebung oder Ressource, unklarer Identität, Abweichung zwischen Quelle/API/DB/UI, unerwarteter oder partieller Mutation, fehlendem Vorherzustand oder parallelem Zugriff.

Danach gelten Fehlerbudget und Gates aus `AI_ENTRYPOINT.md`.
