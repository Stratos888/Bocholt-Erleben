# Startpartner-Domäne

Dieses Verzeichnis besitzt den geschützten First-Party-Kandidatenprozess und die internen Gate-2-/Gate-3-Owner des Startpartner-Wachstumspiloten. Der öffentliche `/startpartner/`-Pfad bleibt weiterhin Formspree-owned.

## Runtimegrenze

Alle Endpunkte sind review-geschützt und staging-/dev-orientiert. Sie senden keine Mail, erzeugen keine Magic-Link- oder Portal-Session, rufen Stripe nicht auf, erzeugen keine Submission und veröffentlichen keine Inhalte.

Gate 3 darf einen Organizer deterministisch verknüpfen oder neu anlegen. Diese Organizer-Anlage bleibt Bestandteil derselben atomaren internen Transaktion und besitzt noch keine Portal- oder Kommunikationswirkung. Ausschließlich `_gate3_domain.php` besitzt diesen Organizer-Insert; der kanonische Startpartner-Side-Effect-Vertrag verbietet ihn weiterhin in allen anderen Runtime-Dateien und hält Mail-, Session-, Stripe-, Submission-, reguläre Entitlement- und Consumption-Writes gesperrt.

## Source of Truth

### Kandidat und Gate 2

- `startpartner_candidates`: Organisationsidentität, Herkunft, Status, Revision, Retention-Review, Formular-/Datenschutzversion und Idempotenzidentität.
- `startpartner_candidate_contacts`: Kontakte mit genau einem Hauptkontakt.
- `startpartner_candidate_qualifications`: 14 normalisierte Qualifikationsdimensionen.
- `startpartner_candidate_decisions`: append-only Entscheidungen.
- `startpartner_candidate_reservations`: historisierte Kapazitätsreservierungen.
- `startpartner_candidate_waitlist`: normalisierte Warteliste.
- `startpartner_candidate_operations`: payloadgebundene, revisionsgesicherte Mutationsoperationen und Replayresultate.
- `startpartner_candidate_events`: append-only Kandidatenaudit.

### Gate 3

- `startpartner_pilot_terms_acceptances`: unveränderliche, versionierte Bedingungenbestätigung mit Digest, Person, Organisation, Zeitpunkt, Kanal und bestätigtem Scope.
- `organizers`: Anbieteridentität; wird nur eindeutig verknüpft oder ohne vorhandenen Treffer neu erzeugt.
- `startpartner_pilots`: genau ein Pilot je Kandidat im Zustand `onboarding`; referenziert die weiterhin aktive Kandidatenreservierung.
- `startpartner_pilot_scopes`: normalisierte Event-, Aktivitäts-, Quellenpflege-, Service-, Portal-, Mess- und Reichweitenscopes.
- `startpartner_pilot_entitlements`: eigener fail-closed Pilotgrant. Gate 3 erzeugt ausschließlich `pending_activation` ohne `starts_at`, `ends_at` oder aktuelle Veröffentlichungswirkung.
- `startpartner_pilot_events`: append-only Pilotaudit.
- `control_cases`: ausschließlich operative Projektion, keyed durch `source_system=startpartner_candidate` und Candidate-ID.

`subscriptions` bleibt Owner regulärer Stripe-Mitgliedschaften. Die bestehenden `publication_entitlements` bleiben Owner regulärer Veröffentlichungsberechtigungen und werden in Gate 3 nicht beschrieben.

## Endpunkte und Domainowner

- `_contract.php`: Normalisierung, Validierung und Gate-1-Zustandsautomat.
- `_repository.php`: Kandidatenreads/-writes und Control-Center-Projektion.
- `_domain.php`: transaktionaler Intake, Idempotenz, Dublettenbehandlung und Triage.
- `_gate2_domain.php`: Qualifizierung, Entscheidung, Kapazität, Reservierung und Warteliste.
- `_gate3_domain.php`: Bedingungenbestätigung, Organizer-Auflösung, Pilot, Scopes, `pending_activation`-Pilotgrant, Replay und Gate-3-Readback.
- `_gate3_presentation.php`: Startpartner-spezifische List-/Detailaktionen für die vorhandene geschützte Steuerzentrale.
- `intake.php`: review-geschützter POST für synthetischen `self_service`- oder manuellen `targeted_outreach`-Input.
- `candidates.php`: review-geschützter GET für Liste und vollständigen Kandidaten-/Gate-3-Readback.
- `triage.php`: review-geschützter POST für Gate-1-Statusübergänge.
- `profile.php`: revisionsgesicherte Profilmutation.
- `qualification.php`: revisionsgesicherte Gate-2-Qualifikation.
- `action.php`: revisionsgesicherte Gate-2-/Gate-3-Aktionen; `confirm_pilot_terms` führt Gate 3 atomar aus.
- `pilot.php`: review-geschützter Pilot-, Scope-, Organizer-, Bedingungen- und Grant-Readback.

## Schema und Migrationsgrenze

Die Domain führt kein Runtime-DDL aus. Die kanonische Kette lautet:

- `008_startpartner_candidates.sql`;
- `009_control_center_runtime_schema.sql`;
- `010_startpartner_gate2_qualification_capacity.sql`;
- `011_startpartner_gate3_terms_organizer_entitlement.sql`.

Migration `011` ist für frische MySQL-8- und MariaDB-11.4-Instanzen sowie sichere Wiederholung vertraglich getestet.

## Operations- und Kapazitätsvertrag

Jede Gate-2-/Gate-3-Mutation benötigt `operation_id`, `expected_revision` und `operator_name`. Ein identischer Retry liefert das gespeicherte Ergebnis. Dieselbe Operations-ID mit verändertem Payload oder eine veraltete Revision endet mit Konflikt ohne Teilmutation.

Die aktive Gate-2-Reservierung bleibt während Gate 3 bestehen und ist der einzige Kapazitätsowner. Gate 3 erzeugt keinen zweiten Kapazitätszähler und darf die Reservierung nach Pilotanlage weder verlängern noch freigeben. Die endgültige Überführung in einen aktiven Pilotplatz gehört zum späteren Aktivierungsgate.

## Ausdrücklich verschoben

Öffentlicher First-Party-Cutover, Formspree-Abschaltung, Mail, Magic Link, Portal-Session, Partner-Onboardingabschluss, aktive Berechtigung, sechsmonatiger Laufzeitbeginn, Submission, Event-/Aktivitätsveröffentlichung, Messung, Distribution, Stripe und Konversion sind getrennte spätere Gates.
