# Startpartner-Domäne

Dieses Verzeichnis besitzt den First-Party-Kandidatenprozess, die geschützte interne Review-/Kommunikationslogik sowie die Gate-2-/Gate-3-/Gate-4-Owner des Startpartner-Wachstumspiloten. Der öffentliche `/startpartner/`-Funnel schreibt über den First-Party-Intake in dieselbe Kandidatendomäne.

## Runtimegrenze

Die internen Startpartner-Endpunkte sind review-geschützt und staging-/dev-orientiert. Externe Wirkungen sind eng auf eigene Kommunikationsowner begrenzt:

- `_public_intake.php` darf bei einer neu angelegten Self-Service-Anfrage genau eine Eingangsbestätigung versuchen;
- `_review_communication.php` darf nach einer bewussten Betreiberentscheidung die passende Review-Nachricht senden;
- `_gate3_communication.php` darf nach der bewussten Aktion `send_pilot_terms` genau die gebundene Pilotbedingungen-Fassung an den Hauptkontakt senden.

Automatisierte Tests und Deploy-Smokes dürfen keine dieser Mails real auslösen. Kein Startpartner-Kommunikationsowner erzeugt Magic Links, Portal-Sessions, Stripe-/Payment-/Subscription-Objekte, Submissions oder Veröffentlichungen.

Gate 3 darf nach dokumentierter ausdrücklicher Partnerbestätigung einen Organizer deterministisch verknüpfen oder neu anlegen. Diese Organizer-Anlage bleibt Bestandteil derselben atomaren internen Transaktion und besitzt noch keine Portal- oder Kommunikationswirkung. Ausschließlich `_gate3_domain.php` besitzt diesen Organizer-Insert.

## Source of Truth

### Kandidat und Gate 2

- `startpartner_candidates`: Organisationsidentität, Herkunft, Status, Revision, Retention-Review, Formular-/Datenschutzversion und Idempotenzidentität.
- `startpartner_candidate_contacts`: Kontakte mit genau einem Hauptkontakt.
- `startpartner_candidate_qualifications`: Legacy-/Kompatibilitätsdimensionen der früheren manuellen Qualifizierung.
- `startpartner_candidate_decisions`: append-only Entscheidungen.
- `startpartner_candidate_reservations`: historisierte Kapazitätsreservierungen.
- `startpartner_candidate_waitlist`: normalisierte Warteliste.
- `startpartner_candidate_operations`: payloadgebundene, revisionsgesicherte Mutationsoperationen und Replayresultate.
- `startpartner_candidate_events`: append-only Kandidatenaudit einschließlich Review- und Bedingungenkommunikation.

### Gate 3

- Der Operator pflegt keine technischen Vertrags-, Hash-, Tarif-, Kohorten- oder Laufzeitfelder mehr.
- `send_pilot_terms` erzeugt aus Kandidatenscope und kanonischem Standardvertrag einen unveränderlich per SHA-256 gebundenen Terms-Snapshot und sendet ihn kontrolliert an den Hauptkontakt.
- Erst ein erfolgreich auditierter Versand schaltet die Aktion `confirm_pilot_terms_simple` frei.
- Die bewusste Bestätigungsaktion bedeutet: Eine ausdrückliche Partnerbestätigung liegt vor. Der Kanal wird standardmäßig als `email_reply` protokolliert; alle technischen Gate-3-Felder werden serverseitig aus exakt dem versendeten Snapshot und dem aktuellen Kandidaten abgeleitet.
- Ändert sich die Kandidatenrevision nach dem Versand, muss die Bedingungenfassung neu gesendet werden; eine alte Fassung kann nicht stillschweigend bestätigt werden.
- `startpartner_pilot_terms_acceptances`: unveränderliche Bedingungenbestätigung mit Version, Referenz, Digest, Person, Organisation, Zeitpunkt, Kanal und bestätigtem Scope.
- `organizers`: Anbieteridentität; wird nur eindeutig verknüpft oder ohne vorhandenen Treffer neu erzeugt.
- `startpartner_pilots`: genau ein Pilot je Kandidat im Zustand `onboarding`; referenziert die weiterhin aktive Kandidatenreservierung.
- `startpartner_pilot_scopes`: normalisierte Event-, Aktivitäts-, Quellenpflege-, Service-, Portal-, Mess- und Reichweitenscopes.
- `startpartner_pilot_entitlements`: fail-closed Pilotgrant. Gate 3 erzeugt ausschließlich `pending_activation` ohne `starts_at`, `ends_at` oder aktuelle Veröffentlichungswirkung.
- `startpartner_pilot_events`: append-only Pilotaudit.
- `control_cases`: ausschließlich operative Projektion, keyed durch `source_system=startpartner_candidate` und Candidate-ID.

`subscriptions` bleibt Owner regulärer Stripe-Mitgliedschaften. Die bestehenden `publication_entitlements` bleiben Owner regulärer Veröffentlichungsberechtigungen und werden in Gate 3 nicht beschrieben.

## Gate-3-Standardableitung

Der normale Pilotumfang wird aus dem bereits entschiedenen Inhaltsumfang abgeleitet:

- `events` → Zielmodell `active`, 8 Veranstaltungen je Pilotmonat;
- `activities` → Zielmodell `activity_basic`, 1 gleichzeitig aktive Aktivität;
- `both` → Kombination aus `active` und `activity_basic`.

Die Kohorte wird systemseitig aus dem aktuellen Halbjahr gebildet. Quellen-, Pflege- und Reichweitengrundsätze sind Bestandteil der gebundenen Bedingungenfassung; konkrete operative Details werden im Onboarding vor der Aktivierung ergänzt. Eine automatische kostenpflichtige Verlängerung, Zahlungsart oder Stripe-Subscription ist ausgeschlossen.

Geplante Aktivierungsdaten werden in Gate 3 nicht erfasst. Der tatsächliche Start und das Ende der sechsmonatigen Pilotphase gehören ausschließlich zum späteren Gate-4-Aktivierungsvertrag.

## Endpunkte und Domainowner

- `_contract.php`: Normalisierung, Validierung und Gate-1-Zustandsautomat.
- `_repository.php`: Kandidatenreads/-writes und Control-Center-Projektion.
- `_domain.php`: transaktionaler Intake, Idempotenz, Dublettenbehandlung und Triage.
- `_gate2_domain.php`: Qualifizierung, Entscheidung, Kapazität, Reservierung und Warteliste.
- `_review_decision_domain.php`: KI-gestützte Vorprüfung mit verbindlicher menschlicher Reviewentscheidung.
- `_review_communication.php`: kontrollierte Reviewkommunikation und Rückfragezyklus.
- `_gate3_domain.php`: Bedingungenbestätigung, Organizer-Auflösung, Pilot, Scopes, `pending_activation`-Pilotgrant, Replay und Gate-3-Readback.
- `_gate3_communication.php`: kanonischer Terms-Snapshot, Bedingungenmail, Versand-Audit und serverseitige Ableitung der vereinfachten Bestätigung.
- `_gate3_presentation.php`: Startpartner-spezifische List-/Detailaktionen der geschützten Steuerzentrale.
- `intake.php`: öffentlicher Self-Service- bzw. geschützter Targeted-Outreach-Intake.
- `candidates.php`: review-geschützter GET für Liste und vollständigen Kandidaten-/Gate-3-Readback.
- `profile.php`: revisionsgesicherte Profilmutation.
- `qualification.php`: revisionsgesicherte Legacy-Gate-2-Qualifikation.
- `action.php`: revisionsgesicherte Gate-2-/Gate-3-Aktionen; `send_pilot_terms` sendet die gebundene Bedingungenfassung, `confirm_pilot_terms_simple` nutzt ausschließlich den zuletzt erfolgreich versendeten Snapshot. Der alte technische `confirm_pilot_terms`-Pfad bleibt nur als interne Kompatibilitätsoberfläche bestehen und wird in der normalen UI nicht mehr angeboten.
- `pilot.php`: review-geschützter Pilot-, Scope-, Organizer-, Bedingungen- und Grant-Readback.

## Schema und Migrationsgrenze

Die Domain führt kein Runtime-DDL aus. Für diese Operatorvereinfachung ist keine neue Migration erforderlich. Die bestehende Gate-3-Datenstruktur bleibt unverändert.

## Operations- und Kapazitätsvertrag

Jede Gate-2-/Gate-3-Mutation benötigt `operation_id`, `expected_revision` und `operator_name`. Ein identischer abgeschlossener Retry liefert das gespeicherte Ergebnis; eine veraltete Revision endet mit Konflikt ohne Teilmutation. Der Bedingungenversand ist zusätzlich über Candidate-Revision, Operation-ID und auditierte Terms-Snapshots gebunden und erzeugt bei identischem aktuellem Snapshot keine zweite Mail.

Die aktive Gate-2-Reservierung bleibt während Gate 3 bestehen und ist der einzige Kapazitätsowner. Gate 3 erzeugt keinen zweiten Kapazitätszähler und darf die Reservierung nach Pilotanlage weder verlängern noch freigeben. Die endgültige Überführung in einen aktiven Pilotplatz gehört zum späteren Aktivierungsgate.

## Weiterhin getrennt

Magic Link, Portal-Session, Partner-Onboardingabschluss, aktive Berechtigung, sechsmonatiger Laufzeitbeginn, Submission, Event-/Aktivitätsveröffentlichung, Messung, Distribution, Stripe und Konversion bleiben getrennte spätere Schritte bzw. Gates. Insbesondere startet die Gate-3-Bestätigung noch keine Pilotlaufzeit und keine Veröffentlichung.
