#!/usr/bin/env python3
"""Fail-closed validation for the single active writing-workpack contract."""

from __future__ import annotations

import argparse
import fnmatch
import json
import os
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CANONICAL_CONTRACT = Path("docs/workpacks/active/acceptance-contract.json")
REQUIRED_NON_EMPTY = (
    "objective", "non_goals", "allowed_paths", "owners", "locked_paths",
    "allowed_visible_changes", "unchanged_contracts", "scope_classes",
    "real_evidence", "synthetic_evidence", "definition_of_done", "rollback",
    "error_budget", "completion", "completion_state",
)
EXTERNAL_FIELDS = (
    "resource", "access_class", "stable_identity", "before_state",
    "exact_mutation", "readback", "rollback",
)


class ContractError(ValueError):
    pass


def non_empty(value: object) -> bool:
    if value is None or value is False:
        return False
    if isinstance(value, (str, list, dict)):
        return bool(value)
    return True


def matches(path: str, patterns: list[str]) -> bool:
    return any(fnmatch.fnmatchcase(path, pattern) for pattern in patterns)


def validate_contract(contract: dict, changed_files: list[str], root: Path = ROOT) -> None:
    errors: list[str] = []
    if contract.get("schema_version") != 1:
        errors.append("schema_version must equal 1")
    if not isinstance(contract.get("workpack_issue"), int):
        errors.append("workpack_issue must be an integer")
    if contract.get("status") not in {"READY_FOR_CODEX", "ACTIVE", "COMPLETED"}:
        errors.append("status is not a writing-workpack state")
    if contract.get("gate_a") != "APPROVED":
        errors.append("Gate A is not explicitly APPROVED")
    for field in REQUIRED_NON_EMPTY:
        if not non_empty(contract.get(field)):
            errors.append(f"required field is empty: {field}")

    scopes = contract.get("scope_classes", {})
    expected_scopes = {"ui", "rendering", "data", "external_resources", "documentation"}
    if set(scopes) != expected_scopes or any(not isinstance(v, bool) for v in scopes.values()):
        errors.append("scope_classes must contain exactly five boolean classes")

    if scopes.get("ui") or scopes.get("rendering"):
        visible = contract.get("visible_contract", {})
        for field in ("reference", "viewports", "above_the_fold", "new_visible_elements", "javascript_states"):
            if field not in visible or (field != "new_visible_elements" and not non_empty(visible[field])):
                errors.append(f"UI/rendering contract missing: visible_contract.{field}")
        for evidence_field in ("real_evidence", "synthetic_evidence"):
            evidence = contract.get(evidence_field, [])
            if not evidence or any(not all(non_empty(item.get(k)) for k in ("name", "command", "evidence_scope")) for item in evidence):
                errors.append(f"UI/rendering scope requires complete {evidence_field}")
        if not contract.get("real_evidence"):
            errors.append("synthetic evidence can never be the sole rendering proof")

    external_writes = contract.get("external_writes")
    if not isinstance(external_writes, list) or not external_writes:
        errors.append("external_writes must explicitly declare access")
    else:
        for index, write in enumerate(external_writes):
            missing = [field for field in EXTERNAL_FIELDS if not non_empty(write.get(field))]
            if missing:
                errors.append(f"external_writes[{index}] missing: {', '.join(missing)}")
            if write.get("access_class") not in {"none", "read-only", "controlled-staging-write", "controlled-live-admin"}:
                errors.append(f"external_writes[{index}] has invalid access_class")
            if write.get("access_class") != "none" and write.get("exact_mutation") == "none":
                errors.append(f"external_writes[{index}] does not describe an exact mutation")
    if scopes.get("external_resources") and all(w.get("access_class") == "none" for w in external_writes or []):
        errors.append("external_resources scope contradicts access_class none")

    budget = contract.get("error_budget", {})
    maximum = budget.get("maximum_real_corrections")
    corrections = budget.get("real_corrections")
    if not isinstance(maximum, int) or maximum < 0 or not isinstance(corrections, int) or corrections < 0:
        errors.append("error budget values must be non-negative integers")
    elif corrections > maximum:
        errors.append("real correction error budget exceeded; return to Gate A")

    completion = contract.get("completion", {})
    for field in ("proven", "not_proven", "evidence_scope"):
        if not non_empty(completion.get(field)):
            errors.append(f"completion boundary missing: {field}")
    states = contract.get("completion_state", {})
    if contract.get("status") == "COMPLETED" or completion.get("claimed") is True:
        required_states = {"issue": "COMPLETED", "router": "COMPLETED", "queue": "NOT_QUEUED", "proof_index": "COMPLETED"}
        if states != required_states:
            errors.append("COMPLETED contradicts issue/router/queue/proof state")

    allowed = contract.get("allowed_paths", [])
    locked = contract.get("locked_paths", [])
    for changed in changed_files:
        if matches(changed, locked):
            errors.append(f"changed file is locked: {changed}")
        elif not matches(changed, allowed):
            errors.append(f"changed file is outside allowed scope: {changed}")

    if errors:
        raise ContractError("\n".join(f"- {error}" for error in errors))


def changed_files(base_ref: str, root: Path = ROOT) -> list[str]:
    result = subprocess.run(
        ["git", "diff", "--name-only", "--diff-filter=ACMR", f"{base_ref}...HEAD"],
        cwd=root, text=True, capture_output=True, check=False,
    )
    if result.returncode:
        raise ContractError(f"cannot determine PR diff from {base_ref}: {result.stderr.strip()}")
    return [line for line in result.stdout.splitlines() if line]


def load_contract(path: Path) -> dict:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise ContractError(f"closed canonical contract unavailable: {exc}") from exc
    if not isinstance(value, dict):
        raise ContractError("canonical contract must be a JSON object")
    return value


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--contract", type=Path, default=CANONICAL_CONTRACT)
    parser.add_argument("--base-ref", default=os.environ.get("CONTRACT_BASE_REF", ""))
    parser.add_argument("--changed-file", action="append", default=[])
    args = parser.parse_args()
    try:
        contract_path = args.contract if args.contract.is_absolute() else ROOT / args.contract
        contract = load_contract(contract_path)
        files = args.changed_file or changed_files(args.base_ref or "HEAD^", ROOT)
        validate_contract(contract, files, ROOT)
    except ContractError as exc:
        print(f"Acceptance contract: FAIL\n{exc}", file=sys.stderr)
        return 1
    print(f"Acceptance contract: OK ({len(files)} changed files)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
