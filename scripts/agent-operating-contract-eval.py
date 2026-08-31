#!/usr/bin/env python3
"""Zero-incremental-cost behavioral contract checks for Bocholt erleben Agent OS.

This runner does not call any model, API, web search, repository write, database,
mail, payment or publication endpoint. It validates the immutable historical V1
scenario corpus, evaluates the current contract against its behavior markers,
rejects adversarial fixtures, and emits a machine-readable baseline summary.
"""

from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
from typing import Any

DEFAULT_CORPUS = Path("tests/agent_operating_contract_eval_cases.json")
DEFAULT_CONTRACT = Path("AGENTS.md")
VALID_STATIC_ASSESSMENTS = {"covered", "partial", "gap"}
VALID_EVIDENCE_RESULTS = {"covered", "pass", "partial", "gap", "unknown"}
REQUIRED_CASE_KEYS = {
    "id",
    "title",
    "scenario",
    "expected_decision",
    "critical_failures",
    "contract_markers",
    "static_assessment",
    "evidence",
    "adversarial_decisions",
}


class ContractEvalError(ValueError):
    pass


def git_blob_sha_bytes(data: bytes) -> str:
    header = f"blob {len(data)}\0".encode("utf-8")
    return hashlib.sha1(header + data).hexdigest()


def load_json(path: Path) -> dict[str, Any]:
    try:
        data = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise ContractEvalError(f"Cannot load valid JSON from {path}: {exc}") from exc
    if not isinstance(data, dict):
        raise ContractEvalError("Corpus root must be an object.")
    return data


def _require_nonempty_string(value: Any, label: str) -> None:
    if not isinstance(value, str) or not value.strip():
        raise ContractEvalError(f"{label} must be a non-empty string.")


def validate_corpus(corpus: dict[str, Any]) -> None:
    if corpus.get("schema_version") != 1:
        raise ContractEvalError("schema_version must be 1.")

    contract = corpus.get("contract")
    if not isinstance(contract, dict):
        raise ContractEvalError("contract must be an object.")
    _require_nonempty_string(contract.get("name"), "contract.name")
    expected_blob = contract.get("expected_git_blob_sha")
    if not isinstance(expected_blob, str) or len(expected_blob) != 40:
        raise ContractEvalError("contract.expected_git_blob_sha must be a 40-character Git blob SHA.")
    _require_nonempty_string(contract.get("evidence_limit"), "contract.evidence_limit")

    fields = corpus.get("decision_fields")
    if not isinstance(fields, list) or len(fields) < 3 or any(not isinstance(v, str) or not v for v in fields):
        raise ContractEvalError("decision_fields must contain at least three non-empty strings.")
    if len(fields) != len(set(fields)):
        raise ContractEvalError("decision_fields must be unique.")

    levels = corpus.get("allowed_evidence_levels")
    if not isinstance(levels, list) or not levels or any(not isinstance(v, str) or not v for v in levels):
        raise ContractEvalError("allowed_evidence_levels must be a non-empty string list.")
    allowed_levels = set(levels)

    cases = corpus.get("cases")
    if not isinstance(cases, list) or len(cases) < 15:
        raise ContractEvalError("Corpus must contain at least 15 cases.")

    seen_ids: set[str] = set()
    for index, case in enumerate(cases):
        label = f"cases[{index}]"
        if not isinstance(case, dict):
            raise ContractEvalError(f"{label} must be an object.")
        missing = REQUIRED_CASE_KEYS - set(case)
        if missing:
            raise ContractEvalError(f"{label} missing keys: {sorted(missing)}")

        case_id = case["id"]
        _require_nonempty_string(case_id, f"{label}.id")
        if case_id in seen_ids:
            raise ContractEvalError(f"Duplicate case id: {case_id}")
        seen_ids.add(case_id)
        _require_nonempty_string(case["title"], f"{case_id}.title")
        _require_nonempty_string(case["scenario"], f"{case_id}.scenario")

        expected = case["expected_decision"]
        if not isinstance(expected, dict) or set(expected) != set(fields):
            raise ContractEvalError(
                f"{case_id}.expected_decision must contain exactly decision_fields {fields}."
            )
        for field in fields:
            _require_nonempty_string(expected[field], f"{case_id}.expected_decision.{field}")

        critical = case["critical_failures"]
        if not isinstance(critical, list) or not critical or any(not isinstance(v, str) or not v for v in critical):
            raise ContractEvalError(f"{case_id}.critical_failures must be a non-empty string list.")
        if len(critical) != len(set(critical)):
            raise ContractEvalError(f"{case_id}.critical_failures must be unique.")

        markers = case["contract_markers"]
        if not isinstance(markers, list) or not markers or any(not isinstance(v, str) or not v for v in markers):
            raise ContractEvalError(f"{case_id}.contract_markers must be a non-empty string list.")

        if case["static_assessment"] not in VALID_STATIC_ASSESSMENTS:
            raise ContractEvalError(
                f"{case_id}.static_assessment must be one of {sorted(VALID_STATIC_ASSESSMENTS)}."
            )

        evidence = case["evidence"]
        if not isinstance(evidence, list) or not evidence:
            raise ContractEvalError(f"{case_id}.evidence must be a non-empty list.")
        for ev_index, item in enumerate(evidence):
            if not isinstance(item, dict):
                raise ContractEvalError(f"{case_id}.evidence[{ev_index}] must be an object.")
            if item.get("level") not in allowed_levels:
                raise ContractEvalError(
                    f"{case_id}.evidence[{ev_index}].level is not an allowed evidence level."
                )
            if item.get("result") not in VALID_EVIDENCE_RESULTS:
                raise ContractEvalError(
                    f"{case_id}.evidence[{ev_index}].result is invalid."
                )
            _require_nonempty_string(item.get("ref"), f"{case_id}.evidence[{ev_index}].ref")
            _require_nonempty_string(item.get("note"), f"{case_id}.evidence[{ev_index}].note")

        bad = case["adversarial_decisions"]
        if not isinstance(bad, list) or not bad:
            raise ContractEvalError(f"{case_id}.adversarial_decisions must be non-empty.")
        for bad_index, decision in enumerate(bad):
            if not isinstance(decision, dict):
                raise ContractEvalError(f"{case_id}.adversarial_decisions[{bad_index}] must be an object.")
            missing_fields = set(fields) - set(decision)
            if missing_fields:
                raise ContractEvalError(
                    f"{case_id}.adversarial_decisions[{bad_index}] missing decision fields: "
                    f"{sorted(missing_fields)}"
                )
            anti_patterns = decision.get("anti_patterns")
            if not isinstance(anti_patterns, list) or not anti_patterns:
                raise ContractEvalError(
                    f"{case_id}.adversarial_decisions[{bad_index}].anti_patterns must be non-empty."
                )
            if not (set(anti_patterns) & set(critical)):
                raise ContractEvalError(
                    f"{case_id}.adversarial_decisions[{bad_index}] must exercise at least one critical failure."
                )


def evaluate_decision(
    case: dict[str, Any],
    decision: dict[str, Any],
    decision_fields: list[str],
) -> dict[str, Any]:
    expected = case["expected_decision"]
    mismatches = {
        field: {"expected": expected[field], "actual": decision.get(field)}
        for field in decision_fields
        if decision.get(field) != expected[field]
    }
    anti_patterns = set(decision.get("anti_patterns") or [])
    critical_hits = sorted(anti_patterns & set(case["critical_failures"]))
    passed = not mismatches and not critical_hits
    return {
        "case_id": case["id"],
        "passed": passed,
        "mismatches": mismatches,
        "critical_failures": critical_hits,
    }


def contract_marker_status(case: dict[str, Any], contract_text: str) -> dict[str, Any]:
    missing = [marker for marker in case["contract_markers"] if marker not in contract_text]
    return {
        "case_id": case["id"],
        "declared_assessment": case["static_assessment"],
        "markers_total": len(case["contract_markers"]),
        "markers_found": len(case["contract_markers"]) - len(missing),
        "missing_markers": missing,
    }


def build_baseline(
    corpus: dict[str, Any],
    contract_path: Path,
) -> dict[str, Any]:
    validate_corpus(corpus)
    contract_bytes = contract_path.read_bytes()
    contract_text = contract_bytes.decode("utf-8")
    actual_blob = git_blob_sha_bytes(contract_bytes)
    historical_v1_blob = corpus["contract"]["expected_git_blob_sha"]
    matches_historical_v1 = actual_blob == historical_v1_blob

    fields = corpus["decision_fields"]
    marker_results = [contract_marker_status(case, contract_text) for case in corpus["cases"]]
    missing_required_markers = [
        result for result in marker_results
        if result["declared_assessment"] == "covered" and result["missing_markers"]
    ]
    if missing_required_markers:
        details = ", ".join(
            f"{item['case_id']} ({len(item['missing_markers'])} missing)"
            for item in missing_required_markers
        )
        raise ContractEvalError(f"Covered cases lost required contract markers: {details}")

    adversarial_results: list[dict[str, Any]] = []
    accidentally_accepted: list[str] = []
    for case in corpus["cases"]:
        for idx, decision in enumerate(case["adversarial_decisions"]):
            result = evaluate_decision(case, decision, fields)
            result["fixture_index"] = idx
            adversarial_results.append(result)
            if result["passed"]:
                accidentally_accepted.append(f"{case['id']}[{idx}]")
    if accidentally_accepted:
        raise ContractEvalError(
            "Adversarial decisions were incorrectly accepted: " + ", ".join(accidentally_accepted)
        )

    static_counts = {key: 0 for key in sorted(VALID_STATIC_ASSESSMENTS)}
    evidence_counts = {key: 0 for key in corpus["allowed_evidence_levels"]}
    historical_pass_refs: list[str] = []
    evidence_limits: list[str] = []
    for case in corpus["cases"]:
        static_counts[case["static_assessment"]] += 1
        if case["static_assessment"] in {"partial", "gap"}:
            evidence_limits.append(case["id"])
        for item in case["evidence"]:
            evidence_counts[item["level"]] = evidence_counts.get(item["level"], 0) + 1
            if item["level"] == "historical_replay" and item["result"] == "pass":
                historical_pass_refs.append(item["ref"])

    return {
        "schema_version": 1,
        "mode": "zero_incremental_cost",
        "contract": {
            "path": str(contract_path),
            "historical_v1_blob_sha": historical_v1_blob,
            "current_blob_sha": actual_blob,
            "matches_historical_v1": matches_historical_v1,
        },
        "cases_total": len(corpus["cases"]),
        "static_assessment": static_counts,
        "marker_checks": marker_results,
        "adversarial_fixtures_total": len(adversarial_results),
        "adversarial_fixtures_rejected": len(adversarial_results),
        "historical_pass_refs": sorted(set(historical_pass_refs)),
        "evidence_items_by_level": evidence_counts,
        "cases_requiring_stronger_evidence_or_contract_review": evidence_limits,
        "evidence_limit": corpus["contract"]["evidence_limit"],
        "external_model_calls": 0,
        "external_api_calls": 0,
        "repository_writes": 0,
    }


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--corpus", type=Path, default=DEFAULT_CORPUS)
    parser.add_argument("--contract", type=Path, default=DEFAULT_CONTRACT)
    parser.add_argument("--json", action="store_true", help="Emit full JSON baseline.")
    args = parser.parse_args()

    try:
        corpus = load_json(args.corpus)
        baseline = build_baseline(corpus, args.contract)
    except (ContractEvalError, OSError, UnicodeDecodeError) as exc:
        print(f"Agent contract eval: FAIL: {exc}")
        return 1

    if args.json:
        print(json.dumps(baseline, ensure_ascii=False, indent=2, sort_keys=True))
    else:
        print(
            "Agent contract eval: OK "
            f"({baseline['cases_total']} cases; "
            f"{baseline['adversarial_fixtures_rejected']} adversarial fixtures rejected; "
            f"static={baseline['static_assessment']}; "
            f"matches historical V1={baseline['contract']['matches_historical_v1']}; "
            "external calls=0)"
        )
        if baseline["cases_requiring_stronger_evidence_or_contract_review"]:
            print(
                "Evidence/contract review candidates: "
                + ", ".join(baseline["cases_requiring_stronger_evidence_or_contract_review"])
            )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
