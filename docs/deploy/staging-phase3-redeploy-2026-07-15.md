# Staging Phase 3 Redeploy – 2026-07-15

Nach dem Merge von PR #58 zeigte der Staging-Build weiterhin den vorherigen Phase-2-Stand `c839a2ae071b` statt des Phase-3-Merge-Commits `2a22b5dec4d0`.

Dieser additive Dokumentationscommit erzeugt einen neuen Push nach `staging`, damit der bereits gemergte Phase-3-Stand erneut durch den bestehenden Deploy-Workflow ausgeliefert wird.

Grenzen:
- keine Änderung an Produktivlogik
- keine Änderung an `main`
- kein Live-Deploy
- Ziel ausschließlich `staging`

Nach erfolgreichem Deploy muss die Staging-Build-ID auf den neuen Staging-Commit zeigen. Danach wird die Steuerzentrale erneut read-only geprüft.
