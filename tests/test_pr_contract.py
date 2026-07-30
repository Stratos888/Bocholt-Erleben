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


def toml_block(values: dict) -> str:
    lines: list[str] = []
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
    return "\n".join(lines)


def issue_body(values: dict) -> str:
    return (
        f"{contract.WORKPACK_START}\n```toml\n"
        f"{toml_block(values)}\n```\n{contract.WORKPACK_END}"
    )


def v2_contract() -> dict:
    return {
        "schema_version": 2,
        "workpack_issue": 245,
        "branch": "agent/ai-workflow-simplification-245",
        "objective": "Simplify and serialize the AI workflow.",
        "allowed_paths": [
            "scripts/validate_pr_contract.py",
            "tests/test_pr_contract.py",
            ".github/workflows/pr-gate.yml",
        ],
        "locked_paths": ["api/**", "main-only.txt"],
        "external_access": "none",
        "required_tests": ["python3 tests/test_pr_contract.py"],
        "done": [
            "Exactly one feature PR targets staging.",
            "The PR head equals the declared branch.",
        ],
        "forbidden_effects": ["No runtime or data write."],
        "staging_smoke": "No runtime smoke required.",
    }


def v1_contract() -> dict:
    values = {
        "schema_version": 1,
        "workpack_issue": 245,
        "contract_revision": 1,
        "work_branch": "agent/ai-workflow-simplification-245",
        "objective": "Migrate governance.",
        "scope_classes": ["governance", "ci"],
        "allowed_paths": ["scripts/validate_pr_contract.py", "tests/test_pr_contract.py"],
        "locked_paths": ["api/**"],
        "implementation_external_access": "none",
        "required_tests": ["python3 tests/test_pr_contract.py"],
        "staging_smoke": "No runtime smoke required.",
        "evidence_scope": ["Validator behavior"],
        "not_proven": ["No runtime change."],
        "rollback": "Revert migration PR.",
    }
    values["contract_hash"] = contract.canonical_contract_hash(values)
    return values


def legacy_pr_body(values: dict | None = None) -> str:
    issue = values or v1_contract()
    evidence = {
        "schema_version": 1,
        "workpack_issue": issue["workpack_issue"],
        "contract_revision": issue["contract_revision"],
        "contract_hash": issue["contract_hash"],
        "tests": list(issue["required_tests"]),
        "evidence_scope": ["Validator behavior"],
        "not_proven": list(issue["not_proven"]),
        "rollback": issue["rollback"],
    }
    return (
        "Workpack: #245\n\n"
        f"{contract.PR_START}\n```toml\n{toml_block(evidence)}\n```\n{contract.PR_END}"
    )


def issue_object(values: dict | None = None, *, title: str = "[ACTIVE WORKPACK] Test", state: str = "open") -> dict:
    values = values or v2_contract()
    return {
        "number": values["workpack_issue"],
        "title": title,
        "state": state,
        "body": issue_body(values),
    }


class PullRequestContractTests(unittest.TestCase):
    def validate(
        self,
        *,
        issue: dict | None = None,
        pr_body: str = "Workpack: #245",
        pr_number: int = 246,
        base_ref: str = "staging",
        head_ref: str = "agent/ai-workflow-simplification-245",
        changed: list[str] | None = None,
        active: list[dict] | None = None,
        open_prs: list[dict] | None = None,
    ):
        issue = issue or issue_object()
        changed = changed or ["scripts/validate_pr_contract.py"]
        active = active if active is not None else [{"number": 245, "title": "[ACTIVE WORKPACK] Test"}]
        open_prs = open_prs if open_prs is not None else [{"number": pr_number}]
        return contract.validate_pull_request(
            pr_number=pr_number,
            pr_body=pr_body,
            repository="Stratos888/Bocholt-Erleben",
            base_ref=base_ref,
            head_ref=head_ref,
            changed_paths=changed,
            issue_loader=lambda _: issue,
            active_issue_loader=lambda: active,
            open_feature_pr_loader=lambda: open_prs,
        )

    def assert_rejected(self, message: str, **kwargs):
        with self.assertRaisesRegex(contract.ContractError, message):
            self.validate(**kwargs)

    def test_valid_v2_contract_passes(self):
        parsed, paths, mode = self.validate()
        self.assertEqual(parsed["workpack_issue"], 245)
        self.assertEqual(paths, ["scripts/validate_pr_contract.py"])
        self.assertEqual(mode, "feature")

    def test_second_open_feature_pr_is_rejected(self):
        self.assert_rejected(
            "exactly one open feature PR",
            open_prs=[{"number": 246}, {"number": 247}],
        )

    def test_no_open_feature_pr_is_rejected(self):
        self.assert_rejected("exactly one open feature PR", open_prs=[])

    def test_branch_mismatch_is_rejected(self):
        self.assert_rejected("head branch", head_ref="agent/other-245")

    def test_missing_workpack_line_is_rejected(self):
        self.assert_rejected("exactly one line", pr_body="No reference")

    def test_duplicate_workpack_line_is_rejected(self):
        self.assert_rejected(
            "exactly one line",
            pr_body="Workpack: #245\nWorkpack: #245",
        )

    def test_wrong_workpack_issue_is_rejected(self):
        self.assert_rejected("different issue", pr_body="Workpack: #246")

    def test_zero_or_multiple_active_issues_are_rejected(self):
        self.assert_rejected("exactly one", active=[])
        self.assert_rejected(
            "exactly one",
            active=[{"number": 245}, {"number": 246}],
        )

    def test_closed_or_unmarked_issue_is_rejected(self):
        self.assert_rejected("must be open", issue=issue_object(state="closed"))
        self.assert_rejected(
            "active-workpack marker",
            issue=issue_object(title="Prepared"),
        )

    def test_outside_and_locked_paths_are_rejected(self):
        self.assert_rejected(
            "outside allowed scope",
            changed=["README.md"],
        )
        self.assert_rejected(
            "changed path is locked",
            changed=["api/status.php"],
        )

    def test_rename_checks_old_and_new_path(self):
        paths = contract.parse_name_status_z(
            b"R100\0scripts/validate_pr_contract.py\0README.md\0"
        )
        self.assertEqual(paths, ["scripts/validate_pr_contract.py", "README.md"])
        self.assert_rejected("outside allowed scope", changed=paths)

    def test_unbounded_root_wildcard_is_rejected(self):
        values = v2_contract()
        values["allowed_paths"] = ["**/*"]
        self.assert_rejected("unbounded root wildcard", issue=issue_object(values))

    def test_controlled_write_requires_compact_write_contract(self):
        values = v2_contract()
        values["external_access"] = "controlled-staging-write"
        self.assert_rejected("external_write", issue=issue_object(values))
        values["external_write"] = {
            "resource": "Staging DB",
            "identity": "Synthetic UUID",
            "before": "No rows",
            "mutation": "One bounded row",
            "readback": "Exact row",
            "cleanup": "Delete row and prove zero residue",
        }
        self.validate(issue=issue_object(values))

    def test_release_path_accepts_only_staging_to_main(self):
        parsed, paths, mode = self.validate(
            base_ref="main",
            head_ref="staging",
            pr_body="",
            active=[],
            open_prs=[],
        )
        self.assertEqual(parsed["schema_version"], 0)
        self.assertEqual(mode, "release")
        self.assertEqual(paths, ["scripts/validate_pr_contract.py"])
        self.assert_rejected(
            "release PR",
            base_ref="main",
            head_ref="agent/release",
            active=[],
            open_prs=[],
        )

    def test_legacy_migration_issue_245_passes(self):
        values = v1_contract()
        parsed, _, mode = self.validate(
            issue=issue_object(values),
            pr_body=legacy_pr_body(values),
        )
        self.assertEqual(parsed["schema_version"], 1)
        self.assertEqual(mode, "feature")

    def test_legacy_schema_is_rejected_for_other_issue(self):
        values = v1_contract()
        values["workpack_issue"] = 244
        values["contract_hash"] = contract.canonical_contract_hash(values)
        with self.assertRaisesRegex(contract.ContractError, "only allowed"):
            contract.validate_issue_contract(
                values,
                issue_number=244,
                issue_state="open",
                issue_title="[ACTIVE WORKPACK] Legacy",
            )

    def test_legacy_hash_mismatch_is_rejected(self):
        values = v1_contract()
        values["contract_hash"] = "0" * 64
        self.assert_rejected(
            "contract_hash",
            issue=issue_object(values),
            pr_body=legacy_pr_body(v1_contract()),
        )

    def test_api_failure_fails_closed(self):
        with self.assertRaisesRegex(contract.ContractError, "simulated API failure"):
            contract.validate_pull_request(
                pr_number=246,
                pr_body="Workpack: #245",
                repository="Stratos888/Bocholt-Erleben",
                base_ref="staging",
                head_ref="agent/ai-workflow-simplification-245",
                changed_paths=["scripts/validate_pr_contract.py"],
                issue_loader=lambda _: (_ for _ in ()).throw(
                    contract.ContractError("simulated API failure")
                ),
                active_issue_loader=lambda: [{"number": 245}],
                open_feature_pr_loader=lambda: [{"number": 246}],
            )


class SyntheticFixtureSmokeTests(unittest.TestCase):
    def make_repo(self, base: Path) -> Path:
        repo = base / "repo"
        (repo / "scripts").mkdir(parents=True)
        shutil.copy2(
            ROOT / "scripts/run-event-navigation-fixture-smoke.sh",
            repo / "scripts/run-event-navigation-fixture-smoke.sh",
        )
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
        subprocess.run(
            ["git", "config", "user.email", "test@example.invalid"],
            cwd=repo,
            check=True,
        )
        subprocess.run(
            ["git", "config", "user.name", "Test"],
            cwd=repo,
            check=True,
        )
        subprocess.run(["git", "add", "."], cwd=repo, check=True)
        subprocess.run(["git", "commit", "-qm", "fixture"], cwd=repo, check=True)
        return repo

    def run_smoke(self, repo: Path, runner_temp: Path, **extra_env):
        runner_temp.mkdir(parents=True, exist_ok=True)
        env = os.environ.copy()
        env.update(
            {
                "RUNNER_TEMP": str(runner_temp),
                "SMOKE_OUT_DIR": str(runner_temp / "smoke-output"),
                **extra_env,
            }
        )
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
            status = subprocess.check_output(
                ["git", "status", "--porcelain"],
                cwd=repo,
                text=True,
            )
            self.assertEqual(status, "")

    def test_synthetic_fixture_smoke_detects_checkout_mutation(self):
        with tempfile.TemporaryDirectory() as directory:
            base = Path(directory)
            repo = self.make_repo(base)
            mutation = repo / "mutated.txt"
            result = self.run_smoke(
                repo,
                base / "runner",
                MUTATE_ROOT=str(mutation),
            )
            self.assertNotEqual(result.returncode, 0)
            self.assertIn("checkout changed during smoke", result.stderr)


if __name__ == "__main__":
    unittest.main()
