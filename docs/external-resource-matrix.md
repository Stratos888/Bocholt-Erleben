# Externe Ressourcen- und Schreibmatrix

## Grundregeln

1. Keine Testschreibaktion auf Live-Ressourcen.
2. Normale Implementierungsschritte mutieren keine externen Daten.
3. Pro Ressource gibt es höchstens einen aktiven Schreib-Lock.
4. Vor jedem Write stehen Ziel, stabile Identität, Vorherzustand, erwartete Mutation, Rücklesen und Rollback/Cleanup fest.
5. Teilmutation ist kein Erfolg.
6. Beim ersten unerwarteten Verhalten wird nicht erneut geschrieben.
7. Eine Nachricht, Accountanlage, Berechtigung, Veröffentlichung oder Zahlung ist eine externe oder fachliche Nebenwirkung und benötigt einen eigenen geschlossenen Vertrag.
8. Staging verwendet ausschließlich isolierte Daten, Testempfänger oder einen belegten No-Send-Modus.

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
| Startpartner-Kandidaten-/Pilotdaten | ausschließlich synthetische Kandidaten und Piloten | echte Kandidaten und Piloten | eigene stabile Candidate-/Pilot-ID; kein Live-Testfall |
| Formspree Startpartner-Anfrage | keine echte Übermittlung; Fixture oder sicherer Testweg | aktueller produktiver Übergangseingang bis zum Cutover | als externer Writer behandeln; Bestand und Export vor Cutover read-only klären; danach kein Dual-Write |
| Organizer-Portal | Staging-Organizer und Testsession | echte Organizer und Sessions | Accountanlage und Magic-Link sind Nebenwirkungen; stabile E-Mail/Organizer-ID und Rücklesen |
| Pilotberechtigung | synthetischer befristeter Grant | echte kostenlose Pilotberechtigung | keine Stripe-Subscription; Pilot-ID, Scope, Zeitraum, Status und Rücklesen erforderlich |
| Wirkungsmessung | synthetische oder klar markierte Testmetriken | produktive aggregierte Metriken | keine personenbezogene Erfolgsbehauptung; Pilot-/Organizer-/Content-Attribution muss eindeutig sein |
| Activities | Repo-/JSON-owned | Repo-/JSON-owned | normaler Branch-/PR-Pfad |
| Visual-Pool/Assets | Repo | deployter Pool | normaler Branch-/PR-Pfad |
| STRATO | Staging-Verzeichnis | Live-Webroot | nur `Deploy to STRATO`; nie parallel |
| Stripe | Sandbox/Testkonfiguration; KI/MCP nur mit explizit begrenzten Custom-Rechten | produktiver Stripe-Account; KI/MCP Stripe-seitig ausschließlich `read-only` | Live-MCP ist niemals Writer; ChatGPT-interne Bestätigungsmodi sind nur Zusatzschutz; reale Zahlungs-/Billing-Mutationen nur über owning Produkt- oder ausdrücklich geschlossenen Adminvertrag |
| Mail/SMTP | Testempfänger oder No-Send | echte Kandidaten und Partner | keine echte Nachricht ohne neue Freigabe; Zustellstatus und Fehler müssen fachlich sichtbar sein |
| Search Console/Bing | read-only | read-only | keine Konfigurationsänderung im Produktworkpack |
| GitHub Actions | Branch-/Workflowzustand | Main-Workflowzustand | Feature-Branches deployen nie |

## Startpartner-Ressourcenvertrag

### Aktueller Formspree-Pfad

Aktuell sendet `/startpartner/` an Formspree.

Vor jeder Ablösung sind read-only zu klären:

- vorhandene Anfragen;
- stabile Formspree-Referenzen;
- Exportmöglichkeit;
- Datenschutz- und Aufbewahrungsgrenze;
- Cutover-Zeitpunkt;
- Behandlung von Anfragen unmittelbar vor oder während des Cutovers.

Ziel:

```text
Formspree als Übergangswriter
-> Bestand sichern oder bewusst abgrenzen
-> First-Party-Kandidatenpfad aktivieren
-> Erfolg und Fehler beweisen
-> Formspree-Writer abschalten
```

Dauerhafter Dual-Write ist ausgeschlossen.

### Kandidaten- und Pilotdatenbank

Staging:

- nur synthetische IDs;
- Vorherzustand;
- exakt begrenzte Mutation;
- Rücklesen;
- Cleanup;
- keine echte Mail oder Veröffentlichung.

Live:

- keine Testkandidaten;
- erste echte Selbstmeldung oder Ansprache erst nach vollständiger Produkt- und Rechtsfreigabe;
- jede Adminänderung benötigt stabile Candidate-/Pilot-ID.

### Organizer und Portal

Accountanlage kann:

- Organizer erzeugen oder verknüpfen;
- Magic-Link-Mail auslösen;
- Portalzugang ermöglichen.

Deshalb sind vor dem Write festzulegen:

- Normalisierungs- und Deduplizierungsregel;
- Ziel-Organizer-ID;
- Empfänger;
- Mailmodus;
- erwartete Session-/Portalpostcondition;
- Rollback oder Deaktivierung.

### Kostenlose Pilotberechtigung

Die Pilotberechtigung ist keine Zahlung und keine Subscription.

Pflichtfelder:

- Pilot-ID;
- Organizer-ID;
- Scope;
- Startdatum;
- Enddatum;
- Status;
- Source-Referenz;
- Audit;
- Rücklesen.

Unzulässig:

- Stripe-Testsubscription;
- Preis `0` als dauerhafte reguläre Subscription;
- hinterlegte Zahlungsart als Voraussetzung;
- automatische Konversion;
- parallele Pilot- und reguläre Berechtigung ohne Cutover-Regel.

### Mail und Kommunikation

Jede Nachricht benötigt:

- Anlass;
- Empfänger;
- Vorlage oder Inhalt;
- Environment;
- erwarteten Zustellstatus;
- fachlichen Folgezustand;
- Wiederholungs- und Fehlergrenze.

Ein Mailfehler ist keine erfolgreiche Kommunikation und darf einen fachlichen Zustand nicht automatisch weiterstellen.

### Reguläre Konversion

Erst nach ausdrücklicher Partnerentscheidung:

```text
Tarif und Preis anzeigen
-> Zustimmung
-> normaler Stripe-Checkout
-> Subscription und reguläre Berechtigung zurücklesen
-> Pilotberechtigung geordnet beenden
```

Keine rückwirkende Zahlung für Pilotmonate.

## Stripe KI-/MCP-Zugriffsvertrag

### Harte Berechtigungsgrenze

- Der produktive Stripe-Account ist für KI-/MCP-Zugriff **Stripe-seitig ausschließlich read-only** autorisiert.
- Diese Stripe-seitige Berechtigung ist die harte Safety-Grenze. ChatGPT-interne Einstellungen wie `Leseaktionen zulassen / vor Änderungen fragen` sind nur zusätzliche Defense-in-Depth und dürfen nicht als technische Write-Sperre vorausgesetzt werden.
- Sandbox-/Testwrites sind nur innerhalb eines ausdrücklich begrenzten Test- oder Workpack-Vertrags zulässig. Vorherzustand, Zielobjekt, Mutation, Readback und Cleanup müssen feststehen.
- Ein unerwarteter Sandbox-Write beendet den Versuch: Zustand sichern, Cleanup durchführen und Ursache klären. Derselbe Versuch wird **nicht** auf Live wiederholt.
- Live-Stripe-Writes sind kein Diagnose-, Smoke- oder Release-Testinstrument. Eine spätere echte Live-Admin-Mutation benötigt weiterhin einen eigenen ausdrücklich autorisierten Vertrag und eine dafür geeignete Berechtigungsgrenze; der normale KI-/MCP-Livezugriff wird dafür nicht erweitert.

### Read-only Reconciliation

Bei Payment-, Checkout-, Subscription- oder Billing-Fragen wird vorhandene Stripe-Evidence genutzt, statt Status aus Code oder Screenshots zu erraten:

```text
Environment + livemode belegen
-> relevantes Stripe-Objekt read-only über stabile interne Referenz/Metadata finden
-> Checkout Session / Payment Intent / Charge / Invoice / Subscription soweit fachlich vorhanden lesen
-> Webhook-Endpoint und relevante Eventfamilie read-only prüfen
-> Stripe-Referenzen gegen kanonische interne Submission-/Organizer-/Subscription-Owner abgleichen
-> Abweichung einem Owner zuordnen
-> erst danach über eine Mutation oder einen separaten Fix-Workpack entscheiden
```

Dabei gelten:

- keine PII, Secrets, vollständigen Checkout-IDs oder Account-IDs als dauerhafte Repository-Dokumentation speichern;
- fehlende Connector-Berechtigung oder nicht lesbare Daten erzeugen `STOP_EVIDENCE_CEILING`, nicht automatisch eine Scope-Erweiterung;
- Stripe-Readback beweist Stripe-Zustand, aber nicht automatisch korrekten internen DB-, Webhook- oder Publication-Zustand; diese Owner werden separat abgeglichen;
- interne Daten allein beweisen umgekehrt keinen erfolgreichen Stripe-Zustand, wenn Stripe read-only direkt geprüft werden kann.

### Payment-relevanter Release-Preflight

Wenn ein Release-Diff Checkout, Preise, Billing, Stripe-Konfiguration, Webhook-Verarbeitung oder Subscription-Logik berührt, gehört zum Preflight soweit mit dem vorhandenen read-only Scope belegbar:

- erwartete Live-Produkte und Preise existieren und sind aktiv;
- die verwendeten Preis-/Produktmodelle entsprechen dem beabsichtigten Produktvertrag;
- der kanonische Live-Webhook ist aktiviert, zeigt auf den owning Webhook-Pfad und besitzt die für den tatsächlichen Diff benötigten Eventfamilien;
- relevante Checkout-/Payment-/Subscription-Zustände werden bei Bedarf read-only mit den internen Referenzen abgeglichen;
- unerwartete Drift wird vor Release geklärt und nicht durch einen Live-Write „repariert“.

Ein solcher Read-only-Preflight ist zusätzliche Runtime-Evidence. Er ersetzt weder Contracttests noch Staging-E2E und autorisiert keinen Live-Write.

### Trigger für spätere Stripe-Workpacks

Die Verfügbarkeit des Stripe-Zugriffs ist **kein eigener Featurebedarf**. Neue Payment-Workpacks werden aus realem Nutzungs- oder Fehler-Evidence abgeleitet, z. B.:

- erster verifizierter Nicht-Test-Einzelpayment: End-to-End-Reconciliation `Checkout -> Payment -> Webhook -> interne Owner`;
- erste verifizierte Nicht-Test-Subscription: realen Subscription-Lifecycle und interne Projektion prüfen;
- erster echter Payment-Failure, Refund oder Dispute: genau diesen beobachteten Lifecycle gezielt prüfen;
- wiederkehrendes reales Volumen: erst dann über automatisierte Reconciliation oder Monitoring entscheiden.

Keine vorsorgliche Parallelarchitektur, kein künstliches Live-Testobjekt und kein großer Stripe-Workpack allein wegen verfügbarer Tools.

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
- Rollback/Cleanup;
- Mail-, Zahlungs-, Account-, Berechtigungs- und Veröffentlichungswirkung;
- Environment- und Empfängergrenze.
