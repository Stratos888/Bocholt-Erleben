# Security Workpack – CodeQL Browser-Secret-Cleanup

Stand: 2026-07-21  
Status: aktiv  
Branch: `agent/security-codeql-session-cleanup`

## Belegter Anlass

- Ein historisch öffentlich gewordener Airtable-PAT wurde außerhalb des Repositorys widerrufen und der Secret-Scanning-Fund als `revoked` geschlossen.
- CodeQL meldet persistente Klartextspeicherung des Review-Passworts in der aktuellen Steuerzentrale und in zwei abgelösten internen Oberflächen.
- Zwei abgeschlossene Badegewässer-Proof-Skripte erzeugen weiterhin CodeQL-Funde, obwohl ihre Workflows bereits entfernt sind.

## Zielzustand

1. Kein Review-Passwort wird in `localStorage` oder `sessionStorage` geschrieben.
2. Das Passwort existiert in der aktuellen Steuerzentrale nur während der geöffneten Browserseite im Arbeitsspeicher.
3. `/intern/` und `/intern/work.html` führen ohne eigene Authentifizierungslogik zur kanonischen `/steuerzentrale/`.
4. Abgeschlossene Badegewässer-Proof-/Discovery-Skripte und ihre historischen Arbeitsdokumente sind aus dem aktuellen Arbeitsbaum entfernt.
5. Ein Frontend-Contract verhindert die erneute persistente Passwortspeicherung.
6. Aktive HTML-Textauswertung bleibt unverändert, weil sie nicht als HTML-Sicherheitsfilter oder Sanitizer verwendet wird.

## Scope

- `js/control-center/shared.js`
- `js/control-center/app.js`
- `intern/index.html`
- `intern/work.html`
- obsolete Badegewässer-Proof-/Discovery-Artefakte
- `tests/control_center_frontend_contract_test.mjs`
- kanonischer Workpackstatus

## Nicht in diesem Workpack

- keine Secret- oder Environment-Migration;
- keine Live-Einstellungen;
- keine künstliche CodeQL-Unterdrückung für aktive reine Textparser;
- keine Änderung des Deploy-Workflows; dessen explizite Berechtigungs- und Environment-Härtung erfolgt zusammen mit der bereits geplanten Environment-Trennung.

## Validierung

- JavaScript-Syntax und Frontend-Contract;
- vollständiger `PR Gate`;
- CodeQL auf dem Pull Request;
- genau ein normaler Staging-Deploy;
- read-only Login-/Weiterleitungs-Smoke auf Staging.

## Rollback

Revert des Feature-PRs; externe Daten und Secrets werden durch diesen Workpack nicht verändert.
