# Startpartner Gate 1

This directory owns the first-party candidate domain introduced by workpack #194.

## Runtime boundary

Gate 1 is staging-only and requires review access. The public `/startpartner/` form continues to write exclusively to Formspree. No route in this directory creates an organizer, sends mail, calls Stripe, grants an entitlement, creates a submission, or publishes content.

## Source of truth

- `startpartner_candidates`: organization identity, origin, Gate-1 status, explicit retention-review checkpoint, privacy/form versions and idempotency identity.
- `startpartner_candidate_contacts`: one or more contacts with exactly one primary contact enforced by the domain service and unique database key.
- `startpartner_candidate_events`: append-only audit events written in the same transaction as candidate and status mutations.
- `control_cases`: operational projection only, keyed by `source_system=startpartner_candidate` and `source_reference=<candidate UUID>`.

## Endpoints

- `_contract.php`: normalization, validation and the frozen state machine.
- `_repository.php`: database reads/writes and the Control-Center projection.
- `_domain.php`: transactional intake, idempotency, duplicate handling and triage.
- `intake.php`: review-protected POST for synthetic `self_service` or manual `targeted_outreach` input.
- `candidates.php`: review-protected GET list/detail.
- `triage.php`: review-protected POST for the frozen Gate-1 state transitions.

The domain performs no runtime DDL. Apply `api/sql/007_runtime_schema_reconciliation.sql` and `api/sql/008_startpartner_candidates.sql` through the controlled migration path before activating the endpoints on staging.

`retention_review_at` is an explicit operational review checkpoint supplied by the controlled caller. Gate 1 does not define a legal retention period and must not infer one from code.

An `Idempotency-Key` may be replayed only with the same normalized request. Reuse with a different payload is a conflict and must not silently return the previous candidate.

## Explicitly deferred

Public cutover, Formspree migration, acceptance, organizer/account creation, mail, pilot entitlement, onboarding, publication, measurement checkpoints, partner distribution, tariff conversion and any live write are separate later gates.