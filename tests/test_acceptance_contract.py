#!/usr/bin/env python3
import copy
import importlib.util
import json
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("acceptance", ROOT / "scripts/validate_acceptance_contract.py")
acceptance = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(acceptance)
BASE = json.loads((ROOT / "docs/workpacks/active/acceptance-contract.json").read_text(encoding="utf-8"))


class AcceptanceContractTests(unittest.TestCase):
    def assertRejected(self, mutate, message):
        contract = copy.deepcopy(BASE)
        mutate(contract)
        with self.assertRaisesRegex(acceptance.ContractError, message):
            acceptance.validate_contract(contract, ["scripts/validate-repo.sh"])

    def test_complete_bounded_governance_contract_passes(self):
        acceptance.validate_contract(BASE, BASE["allowed_paths"])

    def test_unresolved_desktop_mobile_contract_fails(self):
        def mutate(contract):
            contract["scope_classes"]["rendering"] = True
            contract["visible_contract"].update(viewports=[])
        self.assertRejected(mutate, "visible_contract.viewports")

    def test_fixture_without_real_feed_evidence_fails(self):
        def mutate(contract):
            contract["scope_classes"]["rendering"] = True
            contract["real_evidence"] = []
        self.assertRejected(mutate, "real_evidence")

    def test_file_outside_scope_fails(self):
        with self.assertRaisesRegex(acceptance.ContractError, "outside allowed scope"):
            acceptance.validate_contract(BASE, ["README.md"])

    def test_locked_file_fails_even_if_added_to_allowed_scope(self):
        contract = copy.deepcopy(BASE)
        contract["allowed_paths"].append("data/events.json")
        with self.assertRaisesRegex(acceptance.ContractError, "changed file is locked"):
            acceptance.validate_contract(contract, ["data/events.json"])

    def test_second_real_correction_beyond_budget_fails(self):
        self.assertRejected(lambda c: c["error_budget"].update(real_corrections=2), "error budget exceeded")

    def test_external_write_without_full_contract_fails(self):
        def mutate(contract):
            contract["scope_classes"]["external_resources"] = True
            contract["external_writes"] = [{"resource": "Events", "access_class": "controlled-live-admin"}]
        self.assertRejected(mutate, "external_writes\\[0\\] missing")

    def test_completion_without_not_proven_or_scope_fails(self):
        def mutate(contract):
            contract["completion"]["not_proven"] = []
            contract["completion"]["evidence_scope"] = []
        self.assertRejected(mutate, "completion boundary missing")

    def test_completed_with_conflicting_router_queue_proof_fails(self):
        def mutate(contract):
            contract["status"] = "COMPLETED"
            contract["completion"]["claimed"] = True
        self.assertRejected(mutate, "COMPLETED contradicts")

    def test_gate_a_must_be_explicit(self):
        self.assertRejected(lambda c: c.update(gate_a="PENDING"), "Gate A")


if __name__ == "__main__":
    unittest.main()
