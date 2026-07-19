# E3-Korrektur – Ausschluss des eingefrorenen CityArt-Fachfalls

Stand: 2026-07-19  
Workpack: Control-Center-Workflow-Konsolidierung  
Ausgang der Korrektur: integrierter SHA `f4635370e84c810853b9773ee624ccc36944a919`

## Befund

Der erste Lauf der konsolidierten `Staging Verification` war technisch read-only und vollständig mutationsfrei:

- `mutation=false`;
- `Inbox_Staging -> Events_Staging`;
- Live-Ressourcen `not_used`;
- erfolgreicher Deploy- und Runtime-Status.

Der generische Kandidatenselektor wählte dabei jedoch den eingefrorenen CityArt-Fachfall als read-only Preflight-Objekt. Es erfolgte kein fachlicher Klick, kein Writeback und keine externe Datenmutation. Der Lauf wird wegen der ausdrücklich gesetzten Fachfallgrenze dennoch nicht als finale E3-Abnahme verwendet.

## Ursache

Der erste Selektor filterte ausschließlich nach `source.system == inbox_feed` und wählte den lexikografisch ersten technisch geeigneten Fall. Er besaß keinen expliziten Ausschluss für eingefrorene Fachfälle.

## Korrektur

`Staging Verification` wird fail-closed gehärtet:

1. Kandidaten mit `cityart` in Titel, Objekt-ID oder Quellenreferenz werden vor jedem Preflight-Aufruf ausgeschlossen.
2. Ausgeschlossene Fälle werden als `frozen_cases_skipped` im Evidence-Artefakt dokumentiert.
3. Die finale Evidence enthält die Assertion `frozen_case_excluded=true`.
4. Existiert kein geeigneter Nicht-CityArt-Fall, wird E3 rot statt auf den eingefrorenen Fall zurückzufallen.
5. `Project Guardrails` prüft dauerhaft den Ausschlussvertrag.

## Unveränderte Sicherheitsgrenzen

- kein E4-Lauf;
- kein CityArt-Fachfall als Abnahmeobjekt;
- keine Mutation in `Inbox_Staging`, `Events_Staging`, `Inbox` oder `Events`;
- keine Sheet- oder DB-Secrets in `Staging Verification`;
- Statuskontexte bleiben `deploy/staging-observed` und `control-center/runtime-preflight-e3`.

## Finale Abnahmebedingung

Der Workpack ist erst final abgenommen, wenn ein erneuter normaler Staging-Deploy und ein neuer E3-Lauf belegen:

- ausgewählter Fall enthält keinen CityArt-Bezug;
- `frozen_case_excluded=true`;
- `mutation=false`;
- `Inbox_Staging -> Events_Staging`;
- Live-Ressourcen unbenutzt;
- beide Commitstatus-Kontexte grün;
- kein E4-Run.
