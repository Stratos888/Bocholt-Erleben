# ENGINEERING – Bocholt erleben

Diese Datei enthält dauerhafte technische Regeln. Der Arbeitsablauf steht in `AI_ENTRYPOINT.md`.

## 1. Quellen der Wahrheit

- `staging` ist Entwicklungsbaseline.
- `main` ist Live-Releasebranch.
- Kein Patch ohne aktuellen Inhalt der owning Datei.
- Generierte Eventdateien sind Buildartefakte, keine manuell gepflegten Quellen.
- Ein Workpack-Issue ist nur für große oder riskante Vorhaben erforderlich.

## 2. Owner und Schreiber

1. bestehenden Owner bestimmen;
2. Ursache dort beheben;
3. konkurrierende Pfade entfernen;
4. nur notwendige Guards ergänzen.

Nicht zulässig:

- zweiter Branch oder Pull Request für dieselbe Ursache;
- mehrere Schreiber an derselben Datei;
- Wrapper auf Wrapper;
- parallele Statusführung;
- große Multi-Datei-Änderungen über die Contents API ohne Checkout und lokale Tests.

Unabhängige Owner dürfen parallel bearbeitet werden. Zentrale Schema-, Authentifizierungs-, Zahlungs-, Deployment- und Governanceowner werden seriell geändert.

## 3. Git-Topologie

```text
Feature-Branch -> staging -> main
```

- Normale Änderungen benötigen einen Branch und einen PR, aber kein Issue.
- Workpacks besitzen genau einen deklarierten Branch und einen PR.
- Mehrere unabhängige Feature-PRs nach `staging` sind erlaubt.
- Exakte Dateiüberschneidungen zwischen offenen PRs werden blockiert.
- Korrekturen bleiben im bestehenden Branch und PR.
- Kein direkter Commit nach `staging` oder `main`.
- Kein Force-Push oder Force-Reset.
- Merge nur bei grünem Check auf exakt aktuellem Head-SHA.

## 4. Entwicklungsumgebung

Größere Codearbeit benötigt einen vollständigen Checkout mit lokaler Suche, Patchbearbeitung und Tests.

Der GitHub-Connector dient primär für Lesen, Issues, PRs, Checks, Logs und Merge. Ohne Checkout sind nur kleine, vollständig gelesene und deterministische Änderungen zulässig.

## 5. Tests und CI

Der Required Check `PR Gate` validiert zunächst Branch, optionales Workpack und Konflikte.

### Draft

```bash
bash scripts/validate-repo.sh quick
```

### Reviewbereit

Der Diff bestimmt den kleinsten zuverlässigen Plan:

- `docs`: Contract-, Diff- und Formatprüfung;
- `quick`: schnelle Repository-, Syntax- und Validatorprüfungen;
- `backend`: Backend-, PHP-, Daten- und zugehörige Datenbankverträge;
- `frontend`: Frontendverträge und Browser-Fixtures;
- `full`: vollständige Repository-, Datenbank-, Frontend- und Browserprüfung.

PR-Textänderungen lösen einen neuen Check aus. Nach Codeänderungen muss der aktuelle Head-SHA erneut grün sein. Bei Workpacks wird unmittelbar vor dem Merge das PR-Label `final-validation` gesetzt; der dadurch gestartete Lauf lädt den aktuellen Issue-Vertrag erneut. Nach einer späteren Vertrags- oder Codeänderung wird das Label entfernt und erst für den neuen finalen Lauf wieder gesetzt.

## 6. Workpacks und Risikogrenzen

Ein Workpack ist verpflichtend für:

- `.github/workflows/**` und zentrale KI-/Governancedateien;
- Datenbankschema;
- Stripe, Webhooks und Zahlungen;
- Magic-Link-, Billing- und vergleichbare Berechtigungsgrenzen;
- kontrollierte externe Writes.

Der Workpack-Vertrag nennt erlaubte und gesperrte Pfade, Tests, Done-Kriterien und verbotene Wirkungen. Der PR referenziert nur `Workpack: #<Issue>`.

## 7. Environment und externe Writes

```text
staging: Staging-Quellen und Staging-Ziele
live:    Live-Quellen und Live-Ziele
```

Externe Writes benötigen stabile Identität, Vorherzustand, begrenzte Mutation, Readback und Cleanup. Beim ersten unerwarteten Verhalten wird gestoppt.

Live-Schreibtests bleiben ausgeschlossen. Secrets gehören weder in Repository noch Chat.

## 8. Evidence, Deploy und Release

Normalfall:

- automatisierter Contracttest;
- normaler Staging-Deploy;
- vorhandener Deploy-Smoke.

Synthetische Mutation, Readback und Cleanup erfolgen in einem Lauf. Temporäre Runtime-Endpunkte und mehrstufige Cleanup-PR-Ketten sind kein Standardweg.

Release ausschließlich per `staging -> main`, anschließend normaler Live-Smoke.

## 9. UI und Inhalte

- Komponenten stylen sich selbst; Layoutdateien platzieren sie.
- Token-first, keine Override-Ketten.
- Daten- und Bildprobleme upstream lösen.
- Sichtbare Änderungen benötigen relevante Viewports und Browser- oder Screenshotnachweis.
- Progressive Enhancement bei relevantem Scope mit und ohne JavaScript prüfen.

## 10. Dokumentation

- `AI_ENTRYPOINT.md`: Arbeitsmodell;
- `AGENTS.md`: Kurzrouter;
- optionales aktives Issue: operativer Workpack-Status;
- `SYSTEM_MAP.md`: Owner und Datenflüsse;
- `TEST_STATUS.md`: dauerhafte Evidence-Grenzen;
- Roadmap und Produktverträge: nur echte Zieländerungen.

Dauerhafte Dokumentation wird genau einmal und nur bei echtem Wissensdelta aktualisiert.
