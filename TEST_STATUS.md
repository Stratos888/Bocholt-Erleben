# Aktueller Proofindex – Bocholt erleben

Stand: 2026-07-24

Diese Datei enthält dauerhafte Prooffähigkeiten und aktuell relevante Evidence-Grenzen. Operative Zwischenstände, vollständige Logs und laufende Run-IDs stehen im jeweiligen Workpack-Issue und in GitHub Actions.

## Evidence-Legende

| Stufe | Bedeutung |
|---|---|
| E1 | aktueller Code oder Diff |
| E2 | automatisierter lokaler/CI-Test |
| E3 | deployter read-only Staging-Nachweis |
| E4 | begrenzter realer Staging-Write mit Rücklesen und Cleanup |
| E5 | echter fachlicher Staging-Fall |
| E6 | read-only Live-Smoke |

## Aktueller Proofstand

| Bereich | Evidence | Dauerhaft belegter Stand |
|---|---:|---|
| Branch- und Deployrouting | E2 | nur `staging` und `main` dürfen deployen; Releasepfad `Feature-Branch -> staging -> main` |
| Aktiver Workpack | E2 | genau ein offenes Issue mit `[ACTIVE WORKPACK]`; kein oder mehrere Treffer stoppen Repository-Writes fail-closed |
| PR-Scope-Vertrag | E2 | PR referenziert Revision und Hash des aktiven Issue-Vertrags; vollständiger Diff einschließlich Löschungen wird gegen `allowed_paths` und `locked_paths` geprüft |
| PR-Integration | E2 | ein Required Check `PR Gate` führt Contract-, Diff-, Repository- und synthetische Browserprüfung aus |
| Checkout-neutrale Browser-Evidence | E2 | synthetische Event-Navigation läuft in temporärem Verzeichnis und verändert den Repository-Checkout nicht |
| Mobile Ausnahmeprüfung | E2 | begrenzte Playwright-Fixture prüft 360×780 und 390×844 auf priorisierten Treffervergleich, genau eine unmittelbare Entscheidungsebene, eingeklappte Evidence, Überlauf und Navigationsüberdeckung sowie 1440×900 auf unveränderte Desktopstruktur |
| Deploy-Run-Locator | E2/E6 | `Publish Deploy Run Status` schreibt branch- und eventbezogen `pending`, `success`, `failure` oder `error` mit exaktem Actions-Link auf den Commit |
| Automatische Main-Run-Auffindbarkeit | E6 | Main-SHA `6e3d869ba65d4c9d27970a3c325896ccd00702c1` wurde ohne Codespace über `deploy-strato/main/push` dem Run `30076982983` zugeordnet; Status wechselte `pending -> success` |
| Release-Kohärenz | E2/E3/E6 | Assets, HTML, Build-Marker, Service Worker und Manifest werden geordnet veröffentlicht und vor Abschluss gegeneinander geprüft |
| STRATO-SFTP-Härtung | E2/E3/E6 | phasenbezogene Retries, IPv4, maximal zwei parallele Transfers und wiederverwendete SSH-Verbindung sind deterministisch und real belegt |
| Aktueller Live-Deploy | E6 | Run `30076982983` bestand alle 65 Schritte; Browser-Smoke 26/26 OK, 0 Fehler, 0 Warnungen |
| Branch-Inhaltsgleichheit | E1 | `main` und `staging` besitzen nach dem Release denselben Dateiinhalt; Unterschiede dürfen nur aus Merge-Historie bestehen |
| Responsive Event-Grid | E2/E6 | eine Spalte bis `1099.98 CSS px`, zwei Spalten ab `1100 CSS px`, keine dritte Spalte und kein horizontaler Überlauf |
| Warm Service Worker | E6 | aktiver produktiver Controller, aktueller Worker-Stempel und ausschließlich aktueller Cache wurden real bestätigt |
| SEO Intent und statisches Rendering | E2/E3/E6 | initiales HTML, progressive Anreicherung, Landingpages, Canonicals, Robots und Sitemap folgen demselben aktuellen Daten- und Auswahlvertrag |
| Event-/Offer-Vertrag | E2/E6 | Event-JSON-LD nur auf geeigneten Detailseiten; kostenlose und kostenpflichtige Offers ausschließlich aus belegten Daten; unbekannte Werte bleiben fail-closed |
| Structured-Data-Warnungen | E2/E6 | URL- und quellenbezogene Korrektur ohne erfundene Organizer-, Performer-, Preis-, Währungs-, `validFrom`-, Availability- oder Ticketwerte |
| Event-Identität und Dubletten-Preflight | E2 | gemeinsamer Python-/PHP-Vertrag, Same-ID-Resume, Staging-Overlay und Approval-Wiring im PR Gate |
| Control-Center-Writeback | E4 | Success, Replay, kontrollierter Fehler, Resume, Rücklesen und Cleanup synthetisch belegt; Live blieb unverändert |
| Event-Builder-Kompatibilität | E2 | vom Control-Center-Writer erzeugte Zeitformate werden vom normalen Event-Builder verarbeitet |
| Externe Live-Writes | Grenze | keine Live-Testschreibaktion; echte Live-Admin-Mutation nur nach ausdrücklicher Freigabe und Write-Vertrag |

## Prozessnachweis

Die Einführung des issue-verankerten PR-Vertrags stoppte zwei reale Fehler vor dem Merge:

1. einen unvollständigen PR-Evidence-Block;
2. einen neuen, noch nicht im fail-closed Workflow-Inventar registrierten Workflow.

Erst nach expliziter Vertragsrevision und vollständigem Neu-Lauf wurde freigegeben. Damit ist belegt, dass der Prozess echten Scope und Verhalten prüft statt nur Dokumentationsmarker.

## Artifact-Grenze

GitHub-Actions-Artefakte bleiben interne maschinelle Evidence. Chat oder Codex prüft sie nur, wenn Summary und Logs nicht ausreichen. Nutzerseitig werden Ergebnisse und Grenzen berichtet; ZIP-Dateien oder Downloadlinks werden ohne ausdrücklichen Auftrag nicht geliefert.

## Zeitversetzte Evidence

- Search-Console-Neubewertungen und SEO-Wirkung bleiben externe zeitversetzte Betriebsaufgaben.
- Eine technische Veröffentlichung beweist keine bereits eingetretene Rankingverbesserung.
- Weekly-KI-, Content-, Search- und Visualsignale werden anhand der jeweils owning Fachläufe bewertet.

## Pflege

`TEST_STATUS.md` wird nur geändert, wenn sich eine dauerhafte Testabdeckung, Prooffähigkeit oder Evidence-Grenze ändert. Keine kompletten Logs, Patchchroniken oder allgemeinen Workpack-Abschlüsse hier ablegen.
