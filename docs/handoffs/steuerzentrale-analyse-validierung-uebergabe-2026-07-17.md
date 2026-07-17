# Steuerzentrale – Übergabe für Analyse und Validierung

Stand: 2026-07-17  
Branch: `agent/control-center-governance-hardening`  
PR: #80, Draft, nicht gemergt  
Status: Arbeitsstopp; keine weitere funktionale Änderung oder Datenaktion

## Zweck dieser Übergabe

Dieses Dokument hält ausschließlich den aktuellen nachweisbaren Stand, offene Fragen und die Grenze der bisherigen Untersuchung fest. Es enthält keine freigegebene Lösung. Ein Folgechat soll den separat abgelegten Vorschlag erneut unabhängig analysieren, gegen Repository, Deploymodell und reale Arbeitsweise validieren und bei Bedarf verwerfen oder grundlegend ändern.

## Repository- und Deploystand

- PR #78 `Implement premium exception-based event review` wurde nach `staging` gemergt.
  - Merge-Commit: `14fcf144fe98f6317e456d39ebff0a24246b48a1`
- PR #79 `Harden staging inbox isolation and time evidence` wurde nach `staging` gemergt.
  - Merge-Commit: `992986e3a3069fd63178e27b7c17becba64c5170`
- `staging` enthält damit die ausnahmebasierte Eventprüfung und die nachträgliche Trennung `Inbox_Staging` / `Inbox`.
- `main` wurde im Rahmen dieses Workpacks nicht verändert.
- PR #80 enthält ausschließlich einen Governance-Prototyp und Dokumentation.
  - PR #80 bleibt Draft.
  - PR #80 darf nicht ohne erneute Analyse, Validierung und ausdrückliche Entscheidung gemergt werden.

## Zuletzt beobachteter realer Staging-Zustand

CityArt-Fall:

`Bocholter Kulturtage 2026 - Kunstmarkt CityArt und künstlerische Mitmach-Stände für Kinder und Jugendliche`

Auf den zuletzt vorliegenden mobilen Screenshots zeigte die Staging-Steuerzentrale:

- Bereich `Neue Inhalte (2)`;
- CityArt als Fall `1 von 2`;
- Status `Entscheidung erforderlich`;
- Block `Noch nicht übernehmbar`;
- Blocker `Pflichtangabe fehlt: visual_motif`;
- Blocker `Fehlende Uhrzeit muss fachlich erklärt sein`;
- Datum `30.08.2026`;
- Uhrzeit leer;
- Ort `Markt vor dem Historischen Rathaus · Bocholt`;
- Kategorie `Kinder & Familie`;
- Quelle `Stadt Bocholt`;
- `visual_key = art_exhibition_gallery`;
- Motiv leer;
- Hauptaktion `Bearbeiten und übernehmen` trotz gleichzeitigem Hinweis `Noch nicht übernehmbar`.

Damit ist der Zielzustand der ausnahmebasierten Prüfung nicht abgenommen. Insbesondere erscheinen nicht mehr die zuvor sichtbaren zwei fokussierten Einzelaufgaben, sondern allgemeine Blocker und ein Vollbearbeitungsweg.

## Bereits sicher belegte Prozessprobleme

1. Codeänderung, reale Staging-Datenänderung und Abnahme wurden in derselben Schleife durchgeführt.
2. Die ursprüngliche Dokumentation verlangte eine reale Staging-Abnahme vor dem Merge nach `staging`, obwohl erst `staging` auf die Staging-Website deployt wird.
3. Grüne CI bestand überwiegend aus Verträgen, Fixtures und statischen Markern und wurde zu stark als vollständige E2E-Evidence interpretiert.
4. Vor der ersten realen Schreibprobe waren Zieltab, stabile Quellzeile, lokaler Fall, Vorherzustand und Rollback nicht als gemeinsames Evidence-Artefakt dokumentiert.
5. Nach Entdeckung der früheren Hartverdrahtung auf `Inbox` wurde nicht sofort vollständig auf Forensik umgestellt.
6. Eine Datenkorrektur für die Uhrzeit wurde vorgenommen, bevor die komplette Entstehungs- und Identitätskette belegt war.

## Fachlicher Datenstand und Lernsignal

- Auf der bereits geprüften offiziellen CityArt-Seite ist eine Zeitspanne `11:00–18:00 Uhr` vorhanden.
- Der in der Steuerzentrale sichtbare führende Datensatz enthält weiterhin keine verwendbare Uhrzeit.
- Der bestehende Staging-Feedbackeintrag `event_source_has_time_but_dataset_missing_time` wurde um CityArt als zweites Beispiel ergänzt und auf Zähler 2 aktualisiert.
- Belegt ist damit ein Qualitätsmuster: Quellenzeit vorhanden, strukturiertes Feld leer.
- Noch nicht belegt ist, ob die Ursache in KI-Extraktion, Mapping, Writeback, Zeilenauswahl, Synchronisation oder lokaler Fallkopie liegt.

## Nicht abschließend geklärte Risiken und Hypothesen

Die folgenden Punkte dürfen nicht als Tatsachen behandelt werden:

- Die erste Bildbestätigung könnte wegen der damaligen Hartverdrahtung den Live-Tab `Inbox` verändert haben. Das ist plausibel, aber noch nicht forensisch durch Vorher-/Nachherdaten belegt.
- Die spätere manuelle Zeitkorrektur könnte eine andere oder doppelte CityArt-Zeile betroffen haben. Nicht belegt.
- Der sichtbare lokale Fall könnte eine veraltete oder anders identifizierte Quellkopie verwenden. Nicht belegt.
- Zwischen `Inbox_Staging`, lokalem Control-Center-Fall und sichtbarem API-Payload könnten mehrere Identitäten oder Aktualisierungsstände existieren. Nicht belegt.
- Es ist noch nicht entschieden, welche Teile aus PR #78 und #79 korrekt bleiben können und welche zurückgebaut oder neu entworfen werden müssen.

## Aktueller Arbeitsstopp

Bis zur Entscheidung im Analyse- und Validierungs-Chat:

- keine weiteren Buttons im CityArt-Fall betätigen;
- keine manuellen Korrekturen in `Inbox` oder `Inbox_Staging`;
- kein weiterer Funktionspatch;
- kein Merge von PR #80;
- keine Branch-Protection-Änderung auf Basis des bisherigen Vorschlags;
- kein Merge nach `main`;
- keine Behauptung, dass der Governance-Prototyp bereits der richtige Zielzustand ist.

## Separat abgelegter, nicht validierter Vorschlag

Der bisherige Ansatz für einen verbesserten Arbeits- und Abnahmemodus liegt ausschließlich als Vorschlag hier:

`docs/proposals/steuerzentrale-arbeitsweise-governance-vorschlag-2026-07-17.md`

Der Folgechat soll diesen Vorschlag nicht übernehmen, sondern zunächst gegen folgende Fragen validieren:

- Löst er die tatsächlichen Root Causes oder erzeugt er nur zusätzliche Bürokratie?
- Ist das vorgeschlagene Change-Manifest die richtige technische Sperre?
- Welche isolierten E2E-Tests sind für dieses Repository realistisch und ausreichend?
- Wie müssen Feature-Branch, `staging`, Staging-Website und `main` korrekt zusammenspielen?
- Welche GitHub-Branch-Protection ist sinnvoll und welche wäre überzogen?
- Wie wird verhindert, dass mehrere Chats oder Branches gleichzeitig dieselben Code- oder Datenpfade verändern?
- Muss PR #80 geändert, geteilt, geschlossen oder vollständig verworfen werden?

## Empfohlene erste Tätigkeit des Folgechats

Nur Analyse und Validierung. Keine Repository-Änderung, kein Merge und keine Datenaktion, bevor der Nutzer den validierten Zielprozess ausdrücklich freigibt.
