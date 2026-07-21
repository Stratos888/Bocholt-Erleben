# Externe Ressourcen- und Schreibmatrix

## Grundregeln

1. Keine Testschreibaktion auf Live-Ressourcen.
2. Normale Implementierungsschritte mutieren keine externen Daten.
3. Pro Ressource gibt es höchstens einen aktiven Schreib-Lock.
4. Vor jedem Write stehen Ziel, stabile Identität, Vorherzustand, erwartete Mutation, Rücklesen und Rollback/Cleanup fest.
5. Teilmutation ist kein Erfolg.
6. Beim ersten unerwarteten Verhalten wird nicht erneut geschrieben.

## Zugriffsklassen

- `none`: kein Zugriff;
- `read-only`: lesen und vergleichen;
- `controlled-staging-write`: begrenzter Staging-Write mit Rücklesen und Cleanup;
- `controlled-live-admin`: genau eine ausdrücklich beauftragte Live-Änderung mit Vorherzustand, Rücklesen und Rollback.

## Matrix

| Ressource | Staging | Live | Standardregel |
|---|---|---|---|
| Google Sheet Inbox | `Inbox_Staging` | `Inbox` | Staging kontrolliert; Live nur im produktiven Fachprozess oder als beauftragte Admin-Aktion |
| Inbox-Archiv | `Inbox_Archive_Staging` | `Inbox_Archive` | nur durch den fachlichen Cleanup-Prozess |
| Google Sheet Events | `Events_Staging` über read-only Basis `Events` | `Events` | Staging schreibt nur Overlay; Live nie als Test |
| Content-/Search-Feedback | explizite Staging-Tabs, falls vorhanden | produktive Tabs | nur durch den owning Fachworkflow |
| Submission-/Anbieter-DB | Staging-DB | Live-DB | keine unbeabsichtigte Mail-, Zahlungs- oder Veröffentlichungswirkung |
| Activities | Repo-/JSON-owned | Repo-/JSON-owned | normaler Branch-/PR-Pfad |
| Visual-Pool/Assets | Repo | deployter Pool | normaler Branch-/PR-Pfad |
| STRATO | Staging-Verzeichnis | Live-Webroot | nur `Deploy to STRATO`; nie parallel |
| Stripe | Test-/Staging-Konfiguration | Live-Secrets | keine reale Zahlung ohne neue Freigabe |
| Mail/SMTP | Staging-Testempfänger | Live-Empfänger | keine echte Nachricht ohne neue Freigabe |
| Search Console/Bing | read-only | read-only | keine Konfigurationsänderung im Produktworkpack |
| GitHub Actions | Branch-/Workflowzustand | Main-Workflowzustand | Feature-Branches deployen nie |

## Events-Overlay

1. `Events` ist die Live-Basis.
2. Staging liest `Events`, schreibt aber ausschließlich `Events_Staging`.
3. Das Overlay enthält nur Staging-Freigaben oder gezielte Overrides.
4. Der Staging-Build führt Basis und Overlay über stabile Identität zusammen.
5. Konflikte blockieren fail-closed.
6. Live ignoriert `Events_Staging`.

## Einzelne Live-Admin-Mutation

Nur bei ausdrücklicher Nutzerbeauftragung und:

- eindeutigem Objekt und stabiler ID;
- vollständigem Vorherzustand;
- exakt deklarierten Feldern;
- sofortigem Rücklesen;
- unveränderten Nicht-Zielfeldern;
- eindeutigem Rollback;
- exklusivem Ressourcen-Lock.

Bei jeder Abweichung: stoppen, Zustand sichern, nicht erneut schreiben.

## Deklaration im Workpack oder PR

- Ressource und Untereinheit;
- Zugriffsklasse;
- stabile Identität;
- Lock-Owner;
- Vorherzustand;
- erwartete Mutation;
- Postconditions;
- Rollback/Cleanup.
