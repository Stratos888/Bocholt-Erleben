# E3-Korrektur – fachfallfreie Runtime-Abnahme

Stand: 2026-07-19  
Workpack: Control-Center-Workflow-Konsolidierung  
Erster integrierter Konsolidierungs-SHA: `f4635370e84c810853b9773ee624ccc36944a919`  
CityArt-Ausschluss-SHA: `97bf96980d788a35870c063620ccd1165b0ce49b`

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

## Validierte Korrektur

`Staging Verification` trennt nun zwei Aussagen:

1. **Runtime-/Ressourcenvertrag:** muss vollständig grün sein.
2. **Fachliche Aktionsfreigabe:** wird nur dokumentiert und nicht als E3-Voraussetzung verwendet.

Der finale E3-Selektor:

- schließt `cityart` in Titel, Objekt-ID oder Quellenreferenz vor jedem Preflight-Aufruf aus;
- akzeptiert ausschließlich einen vorhandenen read-only Plan aus HTTP `200` oder fail-closed HTTP `409`;
- verlangt `mutation=false`;
- verlangt passenden Build und Manifest;
- verlangt Staging-Host und Staging-Environment;
- verlangt `Inbox_Staging -> Events_Staging`;
- verlangt den erwarteten Staging-Writer;
- verlangt eindeutig aufgelöste Quelle und read-only geprüftes Staging-Ziel;
- verlangt unbenutzte Live-Ressourcen;
- speichert `action_allowed` und `business_blockers`, ohne daraus eine Aktionsfreigabe abzuleiten;
- schreibt auch im Fehlerfall ein Diagnoseartefakt;
- bricht ohne gültigen Nicht-CityArt-Runtimevertrag fail-closed ab.

`Project Guardrails` schützt dauerhaft:

- CityArt-Ausschluss;
- `frozen_case_excluded`;
- Scope `runtime_and_resource_contract_only`;
- Dokumentation von `business_blockers`;
- unveränderte Statuskontexte;
- E4-Trennung.

## Unveränderte Sicherheitsgrenzen

- kein E4-Lauf;
- kein CityArt-Fachfall als Abnahmeobjekt;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine Sheet- oder DB-Secrets in `Staging Verification`;
- fachliche Blocker werden nicht übergangen und keine Aktion wird freigegeben;
- Statuskontexte bleiben `deploy/staging-observed` und `control-center/runtime-preflight-e3`.

## Finale Abnahmebedingung

Der Workpack ist erst final abgenommen, wenn ein erneuter normaler Staging-Deploy und ein neuer E3-Lauf belegen:

- ausgewählter Fall enthält keinen CityArt-Bezug;
- Scope ist `runtime_and_resource_contract_only`;
- `frozen_case_excluded=true`;
- `mutation=false`;
- Build, Host und Staging-Environment stimmen;
- `Inbox_Staging -> Events_Staging`;
- Live-Ressourcen unbenutzt;
- eventuelle `business_blockers` sind nur dokumentiert und lösen keine Aktion aus;
- beide Commitstatus-Kontexte grün;
- kein E4-Run.
