# SESSION OPEN — BOCHOLT ERLEBEN (UI FIRST)

Kontext:
Wir arbeiten am Produktionsprojekt „Bocholt erleben“. MASTER.md ist SINGLE SOURCE OF TRUTH und muss strikt befolgt werden. ENGINEERING.md ebenfalls.

Session-Regeln (verbindlich):
1) Lies zu Beginn MASTER.md vollständig.
2) Lies zu Beginn ENGINEERING.md vollständig.
3) Arbeite NUR an CURRENT SPRINT Tasks. Keine neuen Prioritäten.
4) Frozen Areas dürfen nicht verändert werden.
5) UI-Arbeit läuft als „Enterprise Gate Closure Workpacks“ gegen Golden Screens (Rubric >= 9/10).
6) UI-Änderungen: CSS-only, one-file, diff-statt-snippet. Große UI-Sprünge sind als UI-Batch-Mode erlaubt (tokens + component mapping) – aber weiterhin nur ein File (i.d.R. css/style.css).
7) Keine Spekulation: erst Rubric-Gap-Liste (Top 3 FAIL) für den Zielscreen definieren, dann Patch.
8) Output-Format für Code-Änderungen: Nur konkrete Replace-Anweisungen (Datei + exakte BEGIN/END-Zeilen) + Replacement-Block. Keine Zusatz-Erklärungen.

Ziel dieser Session:
Wir priorisieren UI.
1) Erst GS-01 (Event Feed) prüfen: ist frozen, daher keine optischen Änderungen. Nur wenn echter Bug mit Proof.
2) Danach GS-02 (Detailpanel) auf Enterprise Gate bringen (Rubric >= 9/10) und dann als ENTERPRISE-FROZEN markieren (nur via MASTER-Decision; kein visuelles Extra).
3) Arbeitsweise: Workpack-Loop:
   - Wähle Golden Screen (start: GS-02)
   - Rubric-Gap-Liste: Top 3 FAIL mit konkreter Beschreibung (woran sichtbar / woran gemessen)
   - Ein konsolidierter CSS-only Patch (css/style.css) als UI-Batch, der diese 3 Punkte systemisch löst
   - Re-Score Rubric (PASS/FAIL) und dokumentiere die Delta-Punkte in Chat

Input:
Ich poste dir als nächstes:
- MASTER.md (aktueller Stand)
- ENGINEERING.md (aktueller Stand)
- Den aktuellen Inhalt von css/style.css

Deine erste Antwort soll:
1) Bestätigen, dass du MASTER + ENGINEERING gelesen hast (kurz).
2) GS-02 auswählen und die Rubric-Gap-Liste (Top 3 FAIL) erstellen, basierend auf dem aktuellen UI (keine Vermutungen ohne Bezug auf CSS/Struktur).
3) Danach direkt den ersten CSS Patch liefern (Replace-Block Anweisung), der die 3 FAILs adressiert.
