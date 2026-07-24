#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
import os
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location("validate_pr_contract", ROOT / "scripts/validate_pr_contract.py")
contract = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(contract)


def fenced(start: str, end: str, values: dict) -> str:
    lines = []
    for key, value in values.items():
        if isinstance(value, str):
            lines.append(f"{key} = {json.dumps(value, ensure_ascii=False)}")
        elif isinstance(value, bool):
            lines.append(f"{key} = {'true' if value else 'false'}")
        elif isinstance(value, int):
            lines.append(f"{key} = {value}")
        elif isinstance(value, list):
            lines.append(f"{key} = {json.dumps(value, ensure_ascii=False)}")
        elif isinstance(value, dict):
            lines.append(f"\n[{key}]")
            for child_key, child_value in value.items():
                if isinstance(child_value, str):
                    lines.append(f"{child_key} = {json.dumps(child_value, ensure_ascii=False)}")
                elif isinstance(child_value, bool):
                    lines.append(f"{child_key} = {'true' if child_value else 'false'}")
                elif isinstance(child_value, list):
                    lines.append(f"{child_key} = {json.dumps(child_value, ensure_ascii=False)}")
                else:
                    raise AssertionError(f"unsupported child value: {child_key}")
        else:
            raise AssertionError(f"unsupported value: {key}")
    return f"{start}\n```toml\n" + "\n".join(lines) + f"\n```\n{end}"


def base_issue_contract() -> dict:
    value = {
        "schema_version": 1,
        "workpack_issue": 172,
        "contract_revision": 1,
        "objective": "Bounded governance change",
        "scope_classes": ["governance", "ci"],
        "allowed_paths": ["scripts/validate_pr_contract.py", "tests/test_pr_contract.py"],
        "locked_paths": ["data/**", "README.md"],
        "implementation_external_access": "none",
        "required_tests": ["python3 tests/test_pr_contract.py"],
        "staging_smoke": "Normal staging smoke",
        "evidence_scope": ["Contract behavior"],
        "not_proven": ["Real event quality"],
        "rollback": "Revert the governance PR",
    }
    value["contract_hash"] = contract.canonical_contract_hash(value)
    return value


def base_pr_evidence(issue: dict | None = None) -> dict:
    issue = issue or base_issue_contract()
    return {
        "schema_version": 1,
        "workpack_issue": issue["workpack_issue"],
        "contract_revision": issue["contract_revision"],
        "contract_hash": issue["contract_hash"],
        "tests": list(issue["required_tests"]),
        "evidence_scope": ["Unit tests passed"],
        "not_proven": list(issue["not_proven"]),
        "rollback": issue["rollback"],
    }


def issue_object(values: dict | None = None, *, state: str = "open", title: str = "[ACTIVE WORKPACK] Test") -> dict:
    values = values or base_issue_contract()
    return {
        "number": values["workpack_issue"],
        "state": state,
        "title": title,
        "body": fenced(contract.WORKPACK_START, contract.WORKPACK_END, values),
    }


def pr_body(values: dict | None = None) -> str:
    return fenced(contract.PR_START, contract.PR_END, values or base_pr_evidence())


class PullRequestContractTests(unittest.TestCase):
    def validate(self, *, issue=None, evidence=None, changed=None, active=None):
        issue = issue or issue_object()
        evidence = evidence or base_pr_evidence()
        changed = ["scripts/validate_pr_contract.py"] if changed is None else changed
        active = active if active is not None else [{"number": 172, "title": "[ACTIVE WORKPACK] Test"}]
        return contract.validate_pull_request(
            pr_body=pr_body(evidence),
            repository="Stratos888/Bocholt-Erleben",
            changed_paths=changed,
            issue_loader=lambda _: issue,
            active_issue_loader=lambda: active,
        )

    def assertRejected(self, message: str, **kwargs):
        with self.assertRaisesRegex(contract.ContractError, message):
            self.validate(**kwargs)

    def test_valid_issue_contract_and_pr_evidence_pass(self):
        parsed, evidence, paths = self.validate()
        self.assertEqual(parsed["workpack_issue"], 172)
        self.assertEqual(evidence["contract_hash"], parsed["contract_hash"])
        self.assertEqual(paths, ["scripts/validate_pr_contract.py"])

    def test_missing_pr_block_fails(self):
        with self.assertRaisesRegex(contract.ContractError, "exactly one start marker"):
            contract.validate_pull_request(
                pr_body="no block",
                repository="Stratos888/Bocholt-Erleben",
                changed_paths=["scripts/validate_pr_contract.py"],
                issue_loader=lambda _: issue_object(),
                active_issue_loader=lambda: [{"number": 172}],
            )

    def test_duplicate_pr_block_fails(self):
        body = pr_body() + "\n" + pr_body()
        with self.assertRaisesRegex(contract.ContractError, "exactly one start marker"):
            contract.validate_pull_request(
                pr_body=body,
                repository="Stratos888/Bocholt-Erleben",
                changed_paths=["scripts/validate_pr_contract.py"],
                issue_loader=lambda _: issue_object(),
                active_issue_loader=lambda: [{"number": 172}],
            )

    def test_invalid_toml_fails(self):
        body = f"{contract.PR_START}\n```toml\nworkpack_issue = [\n```\n{contract.PR_END}"
        with self.assertRaisesRegex(contract.ContractError, "invalid TOML"):
            contract.extract_toml_block(body, contract.PR_START, contract.PR_END, "PR evidence")

    def test_closed_issue_fails(self):
        self.assertRejected("must be open", issue=issue_object(state="closed"))

    def test_missing_active_marker_fails(self):
        self.assertRejected("missing the active-workpack marker", issue=issue_object(title="Test"))

    def test_issue_number_conflict_fails(self):
        evidence = base_pr_evidence()
        evidence["workpack_issue"] = 173
        self.assertRejected("different issue", evidence=evidence)

    def test_api_failure_fails_closed(self):
        with self.assertRaisesRegex(contract.ContractError, "simulated API failure"):
            contract.validate_pull_request(
                pr_body=pr_body(),
                repository="Stratos888/Bocholt-Erleben",
                changed_paths=["scripts/validate_pr_contract.py"],
                issue_loader=lambda _: (_ for _ in ()).throw(contract.ContractError("simulated API failure")),
                active_issue_loader=lambda: [{"number": 172}],
            )

    def test_zero_or_multiple_active_issues_fail(self):
        self.assertRejected("exactly one", active=[])
        self.assertRejected("exactly one", active=[{"number": 172}, {"number": 173}])

    def test_empty_required_field_fails(self):
        values = base_issue_contract()
        values["objective"] = ""
        values["contract_hash"] = contract.canonical_contract_hash(values)
        self.assertRejected("objective is required", issue=issue_object(values))

    def test_file_outside_allowed_scope_fails(self):
        self.assertRejected("outside allowed scope", changed=["README.md.backup"])

    def test_deleted_file_outside_scope_is_parsed_and_fails(self):
        paths = contract.parse_name_status_z(b"D\0README.md.backup\0")
        self.assertEqual(paths, ["README.md.backup"])
        self.assertRejected("outside allowed scope", changed=paths)

    def test_locked_path_wins_over_allowed_path(self):
        values = base_issue_contract()
        values["allowed_paths"].append("README.md")
        values["contract_hash"] = contract.canonical_contract_hash(values)
        self.assertRejected("changed path is locked", issue=issue_object(values), evidence=base_pr_evidence(values), changed=["README.md"])

    def test_rename_checks_old_and_new_path(self):
        paths = contract.parse_name_status_z(b"R100\0scripts/validate_pr_contract.py\0README.md.backup\0")
        self.assertEqual(paths, ["scripts/validate_pr_contract.py", "README.md.backup"])
        self.assertRejected("outside allowed scope", changed=paths)

    def test_absolute_and_parent_paths_fail(self):
        self.assertRejected("absolute path", changed=["/tmp/file"])
        self.assertRejected("unsafe traversal", changed=["scripts/../README.md"])

    def test_unbounded_root_wildcard_fails(self):
        values = base_issue_contract()
        values["allowed_paths"] = ["**/*"]
        values["contract_hash"] = contract.canonical_contract_hash(values)
        self.assertRejected("unbounded root wildcard", issue=issue_object(values), evidence=base_pr_evidence(values))

    def test_incomplete_ui_contract_fails(self):
        values = base_issue_contract()
        values["scope_classes"].append("ui")
        values["contract_hash"] = contract.canonical_contract_hash(values)
        self.assertRejected("visible_contract", issue=issue_object(values), evidence=base_pr_evidence(values))

    def test_complete_ui_contract_passes(self):
        values = base_issue_contract()
        values["scope_classes"].append("rendering")
        values["visible_contract"] = {
            "reference": "Current UI",
            "viewports": ["1366x900", "390x844"],
            "above_the_fold": "No new element",
            "new_visible_elements": False,
            "javascript_states": ["enabled", "disabled"],
        }
        values["contract_hash"] = contract.canonical_contract_hash(values)
        self.validate(issue=issue_object(values), evidence=base_pr_evidence(values))

    def test_incomplete_external_write_contract_fails(self):
        values = base_issue_contract()
        values["implementation_external_access"] = "controlled-staging-write"
        values["external_write_contract"] = {"resource": "Events_Staging"}
        values["contract_hash"] = contract.canonical_contract_hash(values)
        self.assertRejected("stable_identity", issue=issue_object(values), evidence=base_pr_evidence(values))

    def test_missing_pr_evidence_scope_fails(self):
        evidence = base_pr_evidence()
        evidence["evidence_scope"] = []
        self.assertRejected("evidence_scope", evidence=evidence)

    def test_missing_pr_not_proven_fails(self):
        evidence = base_pr_evidence()
        evidence["not_proven"] = []
        self.assertRejected("not_proven", evidence=evidence)

    def test_missing_pr_rollback_fails(self):
        evidence = base_pr_evidence()
        evidence["rollback"] = ""
        self.assertRejected("rollback", evidence=evidence)

    def test_hash_and_revision_mismatch_fail(self):
        evidence = base_pr_evidence()
        evidence["contract_hash"] = "0" * 64
        self.assertRejected("contract_hash", evidence=evidence)
        evidence = base_pr_evidence()
        evidence["contract_revision"] = 2
        self.assertRejected("contract_revision", evidence=evidence)

    def test_changed_path_list_must_not_be_empty(self):
        self.assertRejected("no changed paths", changed=[])


class SyntheticFixtureSmokeTests(unittest.TestCase):
    def make_repo(self, base: Path) -> Path:
        repo = base / "repo"
        (repo / "scripts").mkdir(parents=True)
        shutil.copy2(ROOT / "scripts/run-event-navigation-fixture-smoke.sh", repo / "scripts/run-event-navigation-fixture-smoke.sh")
        (repo / "scripts/build-event-detail-pages.py").write_text(
            "from pathlib import Path\n"
            "Path('events').mkdir(exist_ok=True)\n"
            "Path('events/index.html').write_text('<main id=event-cards>fixture</main>', encoding='utf-8')\n",
            encoding="utf-8",
        )
        (repo / "scripts/browser-smoke.mjs").write_text(
            "import fs from 'node:fs';\n"
            "if (process.env.MUTATE_ROOT) fs.writeFileSync(process.env.MUTATE_ROOT, 'changed\\n');\n",
            encoding="utf-8",
        )
        subprocess.run(["git", "init", "-q"], cwd=repo, check=True)
        subprocess.run(["git", "config", "user.email", "test@example.invalid"], cwd=repo, check=True)
        subprocess.run(["git", "config", "user.name", "Test"], cwd=repo, check=True)
        subprocess.run(["git", "add", "."], cwd=repo, check=True)
        subprocess.run(["git", "commit", "-qm", "fixture"], cwd=repo, check=True)
        return repo

    def run_smoke(self, repo: Path, runner_temp: Path, **extra_env):
        runner_temp.mkdir(parents=True, exist_ok=True)
        env = os.environ.copy()
        env.update({
            "RUNNER_TEMP": str(runner_temp),
            "SMOKE_OUT_DIR": str(runner_temp / "smoke-output"),
            **extra_env,
        })
        return subprocess.run(
            ["bash", "scripts/run-event-navigation-fixture-smoke.sh"],
            cwd=repo,
            env=env,
            text=True,
            capture_output=True,
            check=False,
            timeout=30,
        )

    def test_synthetic_fixture_smoke_is_checkout_neutral(self):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            repo = self.make_repo(base)
            result = self.run_smoke(repo, base / "runner")
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn("SYNTHETIC_BOUNDED_FIXTURE: OK", result.stdout)
            status = subprocess.check_output(["git", "status", "--porcelain"], cwd=repo, text=True)
            self.assertEqual(status, "")

    def test_synthetic_fixture_smoke_detects_checkout_mutation(self):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            repo = self.make_repo(base)
            mutation = repo / "mutated.txt"
            result = self.run_smoke(repo, base / "runner", MUTATE_ROOT=str(mutation))
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("checkout changed during smoke", result.stderr)


if __name__ == "__main__":
    unittest.main()
