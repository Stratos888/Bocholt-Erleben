## Workpack

- **Zielzustand:**
- **Ausgangs-SHA von `staging`:**
- **Risikoklasse:** `R1` / `R2` / `R3`
- **Aktuelles Gate:** `A` / `B` / `C` / `D`
- **Erforderliche Evidence:** `E1` / `E2` / `E3` / `E4` / `E5` / `E6`
- **Belegte Ursache oder klar markierte Evidence-Lücke:**

> Dieses Template gilt für den Standardpfad über einen PR nach `staging`. Direkte Main-Hotfixes folgen ausschließlich dem Ausnahmevertrag aus `AI_ENTRYPOINT.md` und werden nicht als Feature-PR umgangen.

## Scope und Owner

- **Erlaubte Dateien/Pfade:**
- **Gesperrte Dateien/Pfade:**
- **Owner-/Code-Lock:**
- **Abhängige oder konkurrierende Änderungen:**
- **Warum ist dies der kleinste nachhaltige Zielzustand?**

## Externe Ressourcen

- **Ressourcen:**
- **Zugriff:** `none` / `read-only` / `controlled-staging-write`
- **Stabile Test-/Objektidentität:**
- **Ressourcen-Lock:**
- **Vorherzustand / erwartete Mutation / Rücklesen / Cleanup:**

## Beweise

- **E1 – Code/Diff:**
- **E2 – Tests/CI/Replay:**
- **E3 – deployter Runtime-Preflight, falls R2/R3:**
- **E4 – isolierter synthetischer Staging-Write, falls R3:**
- **E5 – echter Staging-Fall, falls erforderlich:**
- **Offene Beweise:**

## Dokumentation

- **Geänderte kanonische Dokumente:**
- **IST/ZIEL/HISTORIE sauber getrennt:**
- **`CURRENT_WORKPACK.md` aktualisiert, falls operativer Status betroffen:**

## Nutzeraktion

- **Erforderlich:** nein / ja
- **Warum nicht durch die KI ausführbar:**
- **Genau ein gebündelter Schritt:**

## Sicherheit und Abschluss

- **Rollback/Revert:**
- [ ] PR zielt auf `staging`
- [ ] Kein Feature-Branch-Deploy
- [ ] Keine Live-Testschreibaktion
- [ ] Diff entspricht dem dokumentierten Scope
- [ ] Aktueller `staging`-Stand vor Integration einbezogen
- [ ] Bei unerwartetem realen Verhalten ohne Wiederholung gestoppt
- [ ] Erforderliche Gates und Evidence erfüllt
- [ ] Nach Staging-Merge Deploy und Postconditions geprüft
- [ ] Für späteres `staging -> main` freigegeben oder bewusst nicht freigegeben
