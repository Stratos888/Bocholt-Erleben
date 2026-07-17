# Steuerzentrale – Hinweis zum bisherigen Workmode-Entwurf

Stand: 2026-07-17  
Status: **nicht kanonisch, nicht verbindlich, nicht freigegeben**

Die frühere Fassung dieser Datei bezeichnete den darin beschriebenen Arbeits- und Abnahmemodus zu früh als verbindlich. Das ist korrigiert.

Der aktuelle nachweisbare Übergabestand liegt hier:

`docs/handoffs/steuerzentrale-analyse-validierung-uebergabe-2026-07-17.md`

Der zu analysierende und zu validierende Governance-Vorschlag liegt ausschließlich hier:

`docs/proposals/steuerzentrale-arbeitsweise-governance-vorschlag-2026-07-17.md`

Weder dieser Vorschlag noch der Prototyp in PR #80 dürfen ohne erneute unabhängige Analyse, Validierung und ausdrückliche Nutzerentscheidung als Zielprozess behandelt oder gemergt werden.

Bis dahin gilt nur der dokumentierte Arbeitsstopp:

- keine funktionalen Änderungen an der Eventprüfung;
- keine weiteren Testschreibaktionen in `Inbox` oder `Inbox_Staging`;
- kein Merge von PR #80;
- keine Branch-Protection-Änderung auf Basis des unvalidierten Vorschlags;
- kein Merge nach `main`.
