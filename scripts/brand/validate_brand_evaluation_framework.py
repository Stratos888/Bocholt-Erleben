#!/usr/bin/env python3
from pathlib import Path
import json
import sys

ROOT = Path(__file__).resolve().parents[2]
BASE = ROOT / "docs/brand/evaluation"
CONTRACT = BASE / "EVIDENCE_CONTRACT.json"
TEMPLATES = BASE / "templates"

required = [
    ROOT / "docs/brand/AI_BRAND_PROCESS_FAILURE_REPORT.md",
    ROOT / "docs/brand/AI_BRAND_PHASE3_EVALUATION_FRAMEWORK.md",
    BASE / "README.md",
    CONTRACT,
    TEMPLATES / "producer.json",
    TEMPLATES / "critic-a.json",
    TEMPLATES / "critic-b.json",
    TEMPLATES / "red-team.json",
    TEMPLATES / "consolidation.json",
]
errors = [f"missing: {path.relative_to(ROOT)}" for path in required if not path.is_file()]

def load(path):
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        errors.append(f"invalid JSON {path.relative_to(ROOT)}: {exc}")
        return {}

if not errors:
    contract = load(CONTRACT)
    boundary = contract.get("ci_boundary", {})
    if boundary.get("checks_aesthetic_quality") is not False:
        errors.append("CI must not check aesthetic quality")
    if boundary.get("certifies_premium") is not False:
        errors.append("CI must not certify premium quality")
    if boundary.get("candidate_specific_target_score") is not None:
        errors.append("candidate-specific target score must be null")

    score = contract.get("design_score", {})
    dimensions = score.get("dimensions", [])
    if sum(item.get("weight", 0) for item in dimensions) != 100:
        errors.append("design weights must sum to 100")
    if score.get("technical_points_included") is not False:
        errors.append("technical checks must not add design points")

    if [gate.get("id") for gate in contract.get("gates", [])] != ["P0", "P1", "P2", "P3", "P4", "P5"]:
        errors.append("gate order must be P0 through P5")

    roles = contract.get("roles", {})
    if roles.get("producer", {}).get("may_assign_final_design_score") is not False:
        errors.append("producer must not assign final design score")
    if roles.get("consolidator", {}).get("may_override_knockout") is not False:
        errors.append("consolidator must not override knockouts")
    if roles.get("consolidator", {}).get("may_use_ci_success_as_quality_argument") is not False:
        errors.append("CI success must not be used as quality evidence")

    expected_roles = {
        "producer.json": "producer",
        "critic-a.json": "critic_a",
        "critic-b.json": "critic_b",
        "red-team.json": "red_team",
        "consolidation.json": "consolidator",
    }
    evaluator_ids = set()
    for filename, expected_role in expected_roles.items():
        data = load(TEMPLATES / filename)
        if data.get("template") is not True:
            errors.append(f"{filename} must be a template")
        if data.get("candidate_id") != "candidate-000":
            errors.append(f"{filename} must use anonymous candidate-000")
        if data.get("role") != expected_role:
            errors.append(f"{filename} has wrong role")
        evaluator_id = data.get("evaluator_id")
        if not evaluator_id or evaluator_id in evaluator_ids:
            errors.append(f"{filename} needs a distinct evaluator placeholder")
        evaluator_ids.add(evaluator_id)

    producer = load(TEMPLATES / "producer.json")
    if producer.get("final_design_score") is not None:
        errors.append("producer template must not contain a final score")
    if producer.get("other_reviews_seen") is not False:
        errors.append("producer must declare other reviews unseen")

    for filename in ("critic-a.json", "critic-b.json"):
        critic = load(TEMPLATES / filename)
        for field in ("concept_title_seen", "rationale_seen", "prior_scores_seen"):
            if critic.get(field) is not False:
                errors.append(f"{filename} must keep {field}=false")

    if load(TEMPLATES / "red-team.json").get("mission") != "seek_rejection":
        errors.append("red team must seek rejection")

    consolidation = load(TEMPLATES / "consolidation.json")
    if consolidation.get("ci_certifies_aesthetic_quality") is not False:
        errors.append("consolidation must deny CI aesthetic certification")
    if consolidation.get("status") != "not_evaluated":
        errors.append("consolidation template must remain not_evaluated")

if errors:
    print("Brand evaluation framework validation: FAIL", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(1)

print("Brand evaluation framework validation: OK (evidence-only CI, separated roles, hard knockouts)")
