## Workpack

- **Zielzustand:**
- **Ausgangs-SHA von `staging`:**
- **Risikoklasse:** `R1` / `R2` / `R3`
- **Aktuelles Gate:** `A` / `B` / `C` / `D`
- **Erforderliche Evidence:** `E1` / `E2` / `E3` / `E4` / `E5` / `E6`
- **Belegte Ursache oder klar markierte Evidence-Lücke:**

## Scope

- **Erlaubte Dateien/Pfade:**
- **Gesperrte Dateien/Pfade:**
- **Owner-/Code-Lock:**
- **Abhängige oder konkurrierende PRs:**
- **Warum ist der Patch der kleinste nachhaltige Zielzustand?**

## Externe Ressourcen

- **Ressourcen:**
- **Zugriff:** `none` / `read-only` / `controlled-write`
- **Stabile Test-/Objektidentität:**
- **Ressourcen-Lock:**
- **Vorherzustand / erwartete Mutation / Rücklesen / Cleanup:**

## Beweise

- **E1 – Code/Diff:**
- **E2 – Tests/CI/Replay:**
- **E3 – deployter Runtime-Preflight, falls R2/R3:**
- **E4 – isolierter synthetischer Write, falls R3:**
- **E5 – echter Staging-Fall, falls erforderlich:**
- **Offene Beweise:**

## Nutzeraktion

- **Erforderlich:** nein / ja
- **Warum nicht durch die KI ausführbar:**
- **Gebündelter konkreter Schritt:**

## Sicherheit und Abschluss

- **Rollback/Revert:**
- [ ] Kein direkter Commit auf `main`
- [ ] Kein Feature-Branch-Deploy
- [ ] Keine Live-Testschreibaktion
- [ ] Diff entspricht dem dokumentierten Scope
- [ ] Aktueller `staging`-Stand vor Integration einbezogen
- [ ] Bei unerwartetem realen Verhalten wurde ohne Wiederholung gestoppt
- [ ] `CURRENT_WORKPACK.md` stimmt mit diesem PR überein
- [ ] Erforderliche Gates und Evidence sind erfüllt
- [ ] Nach Staging-Merge wurden Deploy und Postconditions geprüft
- [ ] Für `staging -> main` freigegeben oder bewusst nicht freigegeben