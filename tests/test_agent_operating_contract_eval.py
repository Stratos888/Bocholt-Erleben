#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPT = ROOT / "scripts" / "agent-operating-contract-eval.py"
CORPUS = ROOT / "tests" / "agent_operating_contract_eval_cases.json"
AGENTS = ROOT / "AGENTS.md"
ENGINEERING = ROOT / "ENGINEERING.md"
HISTORICAL_V1_BLOB = "7d26122b9be063a20d6715c89703783482a4cc93"
FINAL_DIFF_MARKER = "Vor `DONE_VERIFIED` den finalen tatsächlichen Diff einmal als Ganzes"
EXECUTION_PREFLIGHT_MARKER = "### Execution Capability Preflight"

spec = importlib.util.spec_from_file_location("agent_contract_eval", SCRIPT)
assert spec and spec.loader
module = importlib.util.module_from_spec(spec)
sys.modules[spec.name] = module
spec.loader.exec_module(module)


class AgentOperatingContractEvalTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.corpus = module.load_json(CORPUS)

    def test_corpus_is_valid_and_representative(self) -> None:
        module.validate_corpus(self.corpus)
        self.assertGreaterEqual(len(self.corpus["cases"]), 15)

    def test_case_ids_are_unique(self) -> None:
        ids = [case["id"] for case in self.corpus["cases"]]
        self.assertEqual(len(ids), len(set(ids)))

    def test_historical_v1_baseline_identity_is_immutable(self) -> None:
        self.assertEqual(
            HISTORICAL_V1_BLOB,
            self.corpus["contract"]["expected_git_blob_sha"],
        )

    def test_current_contract_is_versioned_evolution_not_rewritten_v1(self) -> None:
        actual = module.git_blob_sha_bytes(AGENTS.read_bytes())
        self.assertNotEqual(HISTORICAL_V1_BLOB, actual)
        text = AGENTS.read_text(encoding="utf-8")
        self.assertTrue(text.startswith("# Agent Operating Contract V1.2 – Bocholt erleben"))

    def test_baseline_distinguishes_historical_v1_from_current_contract(self) -> None:
        baseline = module.build_baseline(self.corpus, AGENTS)
        self.assertEqual(HISTORICAL_V1_BLOB, baseline["contract"]["historical_v1_blob_sha"])
        self.assertFalse(baseline["contract"]["matches_historical_v1"])
        self.assertEqual(
            module.git_blob_sha_bytes(AGENTS.read_bytes()),
            baseline["contract"]["current_blob_sha"],
        )

    def test_baseline_uses_zero_external_calls(self) -> None:
        baseline = module.build_baseline(self.corpus, AGENTS)
        self.assertEqual(baseline["external_model_calls"], 0)
        self.assertEqual(baseline["external_api_calls"], 0)
        self.assertEqual(baseline["repository_writes"], 0)

    def test_current_contract_has_exactly_one_execution_capability_preflight(self) -> None:
        text = AGENTS.read_text(encoding="utf-8")
        self.assertEqual(1, text.count(EXECUTION_PREFLIGHT_MARKER))
        self.assertLess(text.index(EXECUTION_PREFLIGHT_MARKER), text.index("## 3. Current State"))
        self.assertIn("vor substantieller Repository-Mutation", text)
        for mode in ("READ_ONLY", "REMOTE_SMALL_WRITE", "CHECKOUT_REQUIRED"):
            self.assertIn(f"`{mode}`", text)
        self.assertIn("nicht der geschätzten Patchgröße", text)

    def test_checkout_handoff_is_early_and_reuses_existing_context(self) -> None:
        text = AGENTS.read_text(encoding="utf-8")
        self.assertIn("vor Implementation stoppen", text)
        self.assertIn("keine Remote-Einzelpatch-Kette", text)
        self.assertIn("vorhandenen Branch, PR, Workpack und Issue-Kontext weiterverwenden", text)
        self.assertIn("die bereits belegte Analyse nicht bei null wiederholen", text)
        for field in (
            "`Repo`",
            "`Branch`",
            "`Baseline-SHA`",
            "`Workpack/Issue`",
            "`OBJECTIVE`",
            "`INVARIANTS`",
            "`OWNERS / IMPACT`",
            "`Required Tests`",
            "`Resume Point`",
        ):
            self.assertIn(field, text)

    def test_engineering_checkout_rule_is_capability_based(self) -> None:
        text = ENGINEERING.read_text(encoding="utf-8")
        self.assertIn("nicht nach einer geschätzten Patchgröße", text)
        self.assertIn("repository-weite Implementierungssuche", text)
        self.assertIn("lokale Build-, Browser-, Runtime- oder Datenbanktests", text)
        self.assertIn("`REMOTE_SMALL_WRITE`", text)
        self.assertIn("Source-, Schema- oder Runtime-Patches beginnen erst im Checkout", text)

    def test_current_contract_has_exactly_one_final_diff_challenge(self) -> None:
        text = AGENTS.read_text(encoding="utf-8")
        self.assertEqual(1, text.count(FINAL_DIFF_MARKER))
        self.assertIn("Scope Drift", text)
        self.assertIn("Nur bei materiellem Delta die betroffene Evidence erneut validieren", text)

    def test_no_blanket_best_of_n_or_fixed_iteration_rule_was_added(self) -> None:
        text = AGENTS.read_text(encoding="utf-8")
        self.assertNotIn("Best-of-N", text)
        self.assertIn("NOT TO A FIXED COUNT", text)
        self.assertIn("keine feste Patch-Anzahl", text)

    def test_all_adversarial_fixtures_are_rejected(self) -> None:
        fields = self.corpus["decision_fields"]
        for case in self.corpus["cases"]:
            for decision in case["adversarial_decisions"]:
                with self.subTest(case=case["id"], decision=decision):
                    result = module.evaluate_decision(case, decision, fields)
                    self.assertFalse(result["passed"])
                    self.assertTrue(result["mismatches"] or result["critical_failures"])

    def test_expected_decision_passes_rubric(self) -> None:
        fields = self.corpus["decision_fields"]
        for case in self.corpus["cases"]:
            decision = dict(case["expected_decision"])
            decision["anti_patterns"] = []
            with self.subTest(case=case["id"]):
                self.assertTrue(module.evaluate_decision(case, decision, fields)["passed"])

    def test_covered_cases_have_all_declared_contract_markers(self) -> None:
        text = AGENTS.read_text(encoding="utf-8")
        for case in self.corpus["cases"]:
            if case["static_assessment"] != "covered":
                continue
            with self.subTest(case=case["id"]):
                result = module.contract_marker_status(case, text)
                self.assertEqual([], result["missing_markers"])

    def test_evidence_levels_are_explicit_and_non_runtime_claims_are_limited(self) -> None:
        allowed = set(self.corpus["allowed_evidence_levels"])
        self.assertNotIn("runtime_agent_proof", allowed)
        self.assertIn("not independent runtime-agent proof", self.corpus["contract"]["evidence_limit"])
        for case in self.corpus["cases"]:
            for evidence in case["evidence"]:
                self.assertIn(evidence["level"], allowed)

    def test_malformed_corpus_is_rejected(self) -> None:
        broken = json.loads(json.dumps(self.corpus))
        broken["cases"][0]["adversarial_decisions"][0]["anti_patterns"] = []
        with self.assertRaises(module.ContractEvalError):
            module.validate_corpus(broken)


if __name__ == "__main__":
    unittest.main()
