# Security Workpack – CodeQL Browser-Speicherbereinigung

Stand: 2026-07-21  
Status: abgeschlossen  
Branch: `agent/security-codeql-session-cleanup`  
PR: `#139`

## Belegter Anlass

- Ein historisch öffentlich gewordener Airtable-PAT wurde außerhalb des Repositorys widerrufen und der Secret-Scanning-Fund als `revoked` geschlossen.
- CodeQL meldete persistente Klartextspeicherung des Review-Passworts in der aktuellen Steuerzentrale und in zwei abgelösten internen Oberflächen.
- Zwei abgeschlossene Badegewässer-Proof-Skripte erzeugten weiterhin CodeQL-Funde, obwohl ihre Workflows bereits entfernt waren.

## Umgesetzter Zielzustand

1. Das Review-Passwort wird nicht mehr in `localStorage` oder `sessionStorage` geschrieben.
2. In der aktuellen Steuerzentrale existiert es nur während der geöffneten Browserseite im Arbeitsspeicher.
3. Alte gespeicherte Passwortschlüssel werden vor dem App-Start best effort entfernt.
4. `/intern/` und `/intern/work.html` führen ohne eigene Authentifizierungslogik zur kanonischen `/steuerzentrale/`.
5. Abgeschlossene Badegewässer-Proof-/Discovery-Skripte und ihre historischen Arbeitsdokumente sind aus dem aktuellen Arbeitsbaum entfernt.
6. Ein eigener Frontend-Contract verhindert die erneute persistente Passwortspeicherung.
7. Aktive HTML-Textauswertung blieb unverändert, weil sie nicht als HTML-Sicherheitsfilter oder Sanitizer verwendet wird.

## Geänderte Owner

- `js/control-center/shared.js`
- `js/control-center/app.js`
- `js/control-center.js`
- `steuerzentrale/index.html`
- `intern/index.html`
- `intern/work.html`
- abgeschlossene Badegewässer-Proof-/Discovery-Artefakte
- `tests/control_center_browser_secret_contract_test.mjs`
- `tests/control_center_presentation_contract_test.php`
- `scripts/validate-repo.sh`

## Bewusste Abgrenzung

- keine Secret- oder Environment-Migration;
- keine Live-Einstellungen;
- keine künstliche CodeQL-Unterdrückung für aktive reine Textparser;
- keine dauerhafte Diagnoseinfrastruktur;
- keine Änderung des Deploy-Workflows; dessen explizite Berechtigungs- und Environment-Härtung erfolgt zusammen mit der geplanten Environment-Trennung.

## Validierung

- Repository-, Backend-, Frontend- und neuer Browser-Speicher-Contract im vollständigen `PR Gate` grün;
- Ready-for-review-Lauf `PR Gate` #199 grün;
- temporäre Backend-Diagnose nach Ursachenklärung vollständig entfernt;
- keine externen Daten- oder Secret-Schreibaktionen;
- CodeQL Default Setup bewertet den integrierten Stand nach dem Merge auf den geschützten Branch erneut.

## Rollback

Revert des Feature-PRs; externe Daten und Secrets wurden durch diesen Workpack nicht verändert.
