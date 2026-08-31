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

    def test_v1_contract_is_byte_identical(self) -> None:
        actual = module.git_blob_sha_bytes(AGENTS.read_bytes())
        self.assertEqual(actual, self.corpus["contract"]["expected_git_blob_sha"])

    def test_baseline_uses_zero_external_calls(self) -> None:
        baseline = module.build_baseline(self.corpus, AGENTS)
        self.assertEqual(baseline["external_model_calls"], 0)
        self.assertEqual(baseline["external_api_calls"], 0)
        self.assertEqual(baseline["repository_writes"], 0)

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
