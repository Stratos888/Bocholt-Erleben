# Aktueller Proofindex – Bocholt erleben

Stand: 2026-09-03

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
| Adaptiver Arbeitsweg | E2/E3 | normale Änderungen benötigen kein Workpack; der reale issuefreie Dokumentationsweg wurde mit Plan `docs`, Merge nach `staging` und normalem Deploy abgenommen; unabhängige PRs dürfen parallel laufen, während Dateiüberschidungen und doppelte PRs desselben Workpacks fail-closed blockiert werden |
| Optionaler Workpack | E2 | große, riskante, mehrchatfähige oder zentrale Governance-/Deploymentänderungen nutzen genau ein offenes `[ACTIVE WORKPACK]`-Issue, einen deklarierten Branch und einen PR |
| PR-Scope-Vertrag | E2 | Workpack-PRs referenzieren nur ihr Issue; der aktuelle Live-Vertrag wird bei jedem PR-Gate-Lauf neu geladen und der vollständige Diff einschließlich Löschungen gegen `allowed_paths` und `locked_paths` geprüft; Hashkopien entfallen |
| Adaptive PR-Integration | E2 | der Required Check `PR Gate` wählt `docs`, `quick`, `backend`, `frontend` oder `full`; kleine Backend- und Browserläufe werden auf betroffene Komponenten begrenzt, unklare oder große Änderungen fallen konservativ auf `full` zurück |
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
| Startpartner Gate-2-Schema | E2/E4 | versionierte Kette `008` bis `010`, reine Runtime-Schemaprüfung, frische MariaDB 11.4 und realer Staging-Preflight auf MySQL 8.0.36; sichere Wiederholung, Fremdschlüssel, Kaskaden, Candidate-Revision und unveränderte gesperrte Tabellen belegt |
| Startpartner Gate-2-Domäne | E2/E4 | 14 Dimensionen, Readiness-Blocker, State Machine, stale-write `409`, payloadgebundener Replay, Profil-Deduplizierung, Entscheidungen, Warteliste, Reservierung, Verlängerung, Soft-Stop sechs, begründete siebte Reservierung, Hard-Stop acht, atomare Projektion und Cleanup real auf Staging belegt |
| Startpartner interne Premium-UI | E2/E4 | eigener Review-Bereich ohne neue Hauptnavigation; Status, Blocker, Hauptaktion, Fälligkeit, Bearbeiter und Kapazität bei 360×780, 390×844, 768×1024 und 1440×900; Konflikt-, Readback-, No-JS-, Modul- und API-Fehlerzustände sowie aktuelle Control-Center-Revision belegt |
| Startpartner Gate-2-Staging-Lifecycle | E4 | finaler Lifecycle-Versuch 2 über den normalen Staging-Deploy erfolgreich: Migrationen `009`/`010`, Profilrevision, Replay, Payload-Konflikt, stale `409`, 14 Dimensionen, Entscheidung, Reservierungshistorie, Warteliste, Soft-/Hard-Stop und Control-Center-Readback; gesperrte Tabellen unverändert, Kapazität wieder null und vollständiges Zero-Residue-Cleanup. Completion-Marker und sämtliche temporären Evidence-Endpunkte wurden anschließend entfernt; beide ehemaligen URLs lieferten HTTP 404. |
| Startpartner Gate-3-Schema | E2/E4 | Migration `011` ist versioniert, auf frischem MySQL 8 und MariaDB 11.4 wiederholbar geprüft und real auf Staging von `0` auf `1` angewendet; sechs Statements, keine Schema-Gaps und unveränderte reguläre Subscription-, Portal-, Submission- und Publication-Owner |
| Startpartner Gate-3-Domäne | E2/E4 | ausdrückliche versionierte Bedingungenbestätigung, deterministische Organizer-Auflösung, genau ein `onboarding`-Pilot, sieben normalisierte Scopes und ein fail-closed `pending_activation`-Pilotgrant; Replay, geänderter Payload, stale Revision, unveränderte aktive Reservierung als Kapazitätsowner und autoritativer Readback real auf Staging belegt |
| Startpartner Gate-3-Staging-Lifecycle und Rückbau | E4 | einmaliger synthetischer Lifecycle in Deploy Run `30347617144` erfolgreich; `residue.total = 0`, Kapazität zurück auf null und gesperrte Counts unverändert. Marker danach kontrolliert `1 -> 0`; temporäre Migrations-/Lifecycle-Owner entfernt und beide ehemaligen URLs im Deploy Run `30392440244` mit HTTP 404 belegt. Der generische Deploy-Smoke enthält anschließend kein Gate-3-Evidence- oder Review-Secret-Wiring mehr. |
| Startpartner Gate-4-Schema und Domäne | E2/E4 | Migration `012` ist auf Staging angewendet. Die dauerhaften Gate-4-Owner bilden 14 Onboardingpunkte, Pilot-/Submission-Verknüpfung für Event und Aktivität, Messpreflight mit Owner `value_metric_daily`, Distributionsbereitschaft, Pilotnutzung sowie lokales Aktivierungs- und Enddatum ab. Reguläre Subscription-, Stripe-, Zahlungs-, Mail-, Magic-Link-, Veröffentlichungs- und Entitlement-Owner bleiben getrennt und unverändert. |
| Startpartner Gate-4-Staging-Lifecycle und Rückbau | E4 | der authentifizierte No-Send-Lifecycle in Deploy Run `30714196723` belegte Replay/Idempotenz, Konfliktgrenzen, aktivierungsgebundene Kalenderdaten, unveränderte gesperrte Runtime-Owner und unveränderte Startpartner-Kapazität; Cleanup endete mit `residue.total = 0`. Der Completion-Marker wurde in Run `30714760725` kontrolliert `1 -> 0` entfernt, ohne den Lifecycle erneut auszuführen. PR `#265` entfernte anschließend exakt die drei temporären Evidence-Dateien und sämtliches temporäres Wiring; Removal-Deploy `30715216900`, Build `37c31add98f0`, HTTP-Smoke erfolgreich, Browser-Smoke 26/26 OK bei 0 Fehlern und 0 Warnungen. Eine permanente Negativgrenze verbietet die Dateien sowie Marker- und Lock-Tokens. Am 2026-08-17 bestätigte ein separater read-only HTTP-Statuscheck für alle drei ehemaligen Evidence-URLs jeweils HTTP `404` bei `0` Redirects; damit ist der vollständige Evidence-Rückbau auch auf HTTP-Ebene belegt. |
| Event-Builder-Kompatibilität | E2 | vom Control-Center-Writer erzeugte Zeitformate werden vom normalen Event-Builder verarbeitet |
| Stripe KI-/MCP Live-Read | E6 | Getrennte Sandbox- und Live-Umgebungen sind direkt adressierbar; Live wurde read-only für Produkte, Preise, Kunden, Subscriptions, Checkout Sessions und Webhook Endpoints belegt. Die harte KI-/MCP-Safety-Grenze ist der Stripe-seitige Live-`read-only`-Scope. ChatGPT-interne Bestätigungsmodi sind nur Zusatzschutz, da ein kontrollierter Sandbox-Write nicht zuverlässig einen separaten Bestätigungsdialog erzwang. Live-Write wurde nicht getestet und bleibt als Testinstrument unzulässig. |
| Externe Live-Writes | Grenze | keine Live-Testschreibaktion; echte Live-Admin-Mutation nur nach ausdrücklicher Freigabe und Write-Vertrag |

## Prozessnachweis

Der issue-verankerte Workpack-Vertrag stoppte zwei reale Fehler vor dem Merge:

1. einen unvollständigen PR-Evidence-Block;
2. einen neuen, noch nicht im fail-closed Workflow-Inventar registrierten Workflow.

Erst nach expliziter Vertragsrevision und vollständigem Neu-Lauf wurde freigegeben. Heute gilt derselbe Schutz nur für Workpacks. Normale Änderungen bleiben issuefrei; vor jedem Workpack-Merge wird über das PR-Ereignis `final-validation` ein letzter Lauf auf dem exakten Head ausgelöst, der den aktuellen Live-Issue-Vertrag erneut lädt.

## Artifact-Grenze

GitHub-Actions-Artefakte bleiben interne maschinelle Evidence. Chat oder Codex prüft sie nur, wenn Summary und Logs nicht ausreichen. Nutzerseitig werden Ergebnisse und Grenzen berichtet; ZIP-Dateien oder Downloadlinks werden ohne ausdrücklichen Auftrag nicht geliefert.

## Zeitversetzte Evidence

- Search-Console-Neubewertungen und SEO-Wirkung bleiben externe zeitversetzte Betriebsaufgaben.
- Eine technische Veröffentlichung beweist keine bereits eingetretene Rankingverbesserung.
- Weekly-KI-, Content-, Search- und Visualsignale werden anhand der jeweils owning Fachläufe bewertet.

## Pflege

`TEST_STATUS.md` wird nur geändert, wenn sich eine dauerhafte Testabdeckung, Prooffähigkeit oder Evidence-Grenze ändert. Keine kompletten Logs, Patchchroniken oder allgemeinen Workpack-Abschlüsse hier ablegen.
