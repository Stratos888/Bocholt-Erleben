# E3-Korrektur – fachfallfreie Runtime-Abnahme

Stand: 2026-07-19  
Workpack: Control-Center-Workflow-Konsolidierung  
Erster integrierter Konsolidierungs-SHA: `f4635370e84c810853b9773ee624ccc36944a919`  
CityArt-Ausschluss-SHA: `97bf96980d788a35870c063620ccd1165b0ce49b`  
Runtime-only-Zwischenstand: `75ebdaa1ac0b6135732422936a18935fe3b763bf`

## Befund 1 – eingefrorener Fachfall ausgewählt

Der erste Lauf der konsolidierten `Staging Verification` war technisch read-only und vollständig mutationsfrei:

- `mutation=false`;
- `Inbox_Staging -> Events_Staging`;
- Live-Ressourcen `not_used`;
- erfolgreicher Deploy- und Runtime-Status.

Der generische Kandidatenselektor wählte dabei jedoch den eingefrorenen CityArt-Fachfall als read-only Preflight-Objekt. Es erfolgte kein fachlicher Klick, kein Writeback und keine externe Datenmutation. Der Lauf wird wegen der ausdrücklich gesetzten Fachfallgrenze nicht als finale E3-Abnahme verwendet.

## Befund 2 – falsche Kopplung an fachliche Freigabe

Nach dem expliziten CityArt-Ausschluss waren Deploy, lokaler Preflight-Vertrag und Buildabgleich grün. Der Nicht-CityArt-Schritt endete intern fail-closed; der finale Publisher setzte `control-center/runtime-preflight-e3` korrekt auf `failure`.

Damit war belegt, dass E3 noch an einen fachlich vollständig freigegebenen Plan mit `allowed=true` gekoppelt war. Diese Kopplung ist für E3 falsch: Der bestehende Contracttest beschreibt E3 ausdrücklich als Prüfung von Runtime, Resolver und Mutationsfreiheit; die fachliche Event-Entscheidungsreife besitzt separate Verträge.

## Befund 3 – kein belastbarer Nicht-CityArt-Fachdatensatz

Der diagnostische Runtime-only-Lauf übersprang CityArt korrekt. Der einzige andere aktive Inbox-Fall konnte seine aktuelle `Inbox_Staging`-Quelle nicht eindeutig auflösen. Das Evidence-Artefakt belegte:

- Deploy erfolgreich;
- Build passend;
- CityArt übersprungen;
- anderer Fall HTTP `409`, `mutation=false`;
- technischer Blocker `source_resolved`;
- kein Write und kein E4.

Damit ist ein fallbezogener E3 unter den harten Grenzen nicht dauerhaft zuverlässig. Ein anderer realer Fachfall wäre nur eine neue zufällige Abhängigkeit.

## Validierte Endkorrektur

Der bestehende authentifizierte Endpoint `api/control-center/preflight.php` erhält einen `runtime`-Modus. Es wird kein neuer Endpoint, Observer oder Writepfad eingeführt.

Der Runtime-Modus:

1. akzeptiert ausdrücklich weder `case_id` noch `action`;
2. verwendet den bestehenden Runtime-, Host-, Build- und Ressourcenresolver;
3. liest `Inbox_Staging` und validiert das kanonische Inbox-Schema;
4. liest `Events_Staging` und validiert den kanonischen Event-Header;
5. gibt nur Tabname, Schema, Headerzeile, Zeilenanzahl und Headerfingerprint zurück – keine fachlichen Zeilendaten;
6. löst den erwarteten Staging-Writer auf, ruft ihn aber nicht auf;
7. setzt `mutation=false` hart;
8. weist Live-Inbox und Live-Events als `not_used` aus;
9. läuft ausschließlich auf Staging und blockiert jede andere Umgebung fail-closed.

`Staging Verification` ruft nur noch `{ "mode": "runtime" }` auf. Sie liest keine Case-Liste und sendet keine Fachfallidentität.

## Dauerhafte Guards

`Project Guardrails` prüft:

- Runtime-Modus im bestehenden Endpoint;
- Scope `runtime_and_resource_contract_only`;
- Assertion `no_fachfall`;
- `mutation_false`;
- Abwesenheit von `/api/control-center/cases.php` und `case_id` im E3-Workflow;
- unveränderte Statuskontexte;
- E4-Trennung.

## Unveränderte Sicherheitsgrenzen

- kein E4-Lauf;
- kein CityArt- oder anderer Fachfall als E3-Abnahmeobjekt;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine Sheet- oder DB-Secrets in `Staging Verification`;
- keine Fachaktion und keine Aktionsfreigabe;
- Statuskontexte bleiben `deploy/staging-observed` und `control-center/runtime-preflight-e3`.

## Finale Abnahmebedingung

Der Workpack ist erst final abgenommen, wenn ein erneuter normaler Staging-Deploy und ein neuer E3-Lauf belegen:

- Scope `runtime_and_resource_contract_only`;
- `no_fachfall=true`;
- `mutation=false`;
- Build, Host und Staging-Environment stimmen;
- `Inbox_Staging`-Schema gültig;
- `Events_Staging`-Schema gültig;
- Writer korrekt aufgelöst, aber nicht aufgerufen;
- Live-Ressourcen unbenutzt;
- beide Commitstatus-Kontexte grün;
- kein E4-Run.
