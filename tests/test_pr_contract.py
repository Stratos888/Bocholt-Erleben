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


def workpack_contract() -> dict:
    return {
        "schema_version": 2,
        "workpack_issue": 300,
        "branch": "agent/adaptive-workflow-300",
        "objective": "Change a high-risk workflow through one bounded workpack.",
        "allowed_paths": [
            ".github/workflows/pr-gate.yml",
            "AI_ENTRYPOINT.md",
            "AGENTS.md",
            "ENGINEERING.md",
            "docs/workpacks/active/CURRENT_WORKPACK.md",
            "scripts/validate_pr_contract.py",
            "tests/test_pr_contract.py",
        ],
        "locked_paths": ["api/**", "main-only.txt"],
        "external_access": "none",
        "required_tests": ["python3 tests/test_pr_contract.py"],
        "done": ["Normal and workpack PR paths are both validated."],
        "forbidden_effects": ["No runtime or data write."],
        "staging_smoke": "No runtime smoke required.",
    }


def issue_object(values: dict | None = None, *, title: str = "[ACTIVE WORKPACK] Test", state: str = "open") -> dict:
    values = values or workpack_contract()
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
        pr_body: str = "",
        pr_number: int = 301,
        base_ref: str = "staging",
        head_ref: str = "agent/small-change",
        changed: list[str] | None = None,
        open_prs: list[dict] | None = None,
    ):
        issue = issue or issue_object()
        changed = changed or ["docs/note.md"]
        open_prs = open_prs if open_prs is not None else [
            {"number": pr_number, "body": pr_body, "changed_paths": changed}
        ]
        return contract.validate_pull_request(
            pr_number=pr_number,
            pr_body=pr_body,
            repository="Stratos888/Bocholt-Erleben",
            base_ref=base_ref,
            head_ref=head_ref,
            changed_paths=changed,
            issue_loader=lambda _: issue,
            open_feature_pr_loader=lambda: open_prs,
        )

    def assert_rejected(self, message: str, **kwargs):
        with self.assertRaisesRegex(contract.ContractError, message):
            self.validate(**kwargs)

    def test_normal_pr_without_workpack_passes(self):
        parsed, paths, mode, plan = self.validate()
        self.assertIsNone(parsed)
        self.assertEqual(paths, ["docs/note.md"])
        self.assertEqual(mode, "normal")
        self.assertEqual(plan, "docs")

    def test_workpack_pr_passes_and_forces_full_plan(self):
        parsed, paths, mode, plan = self.validate(
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md", "scripts/validate_pr_contract.py"],
        )
        self.assertEqual(parsed["workpack_issue"], 300)
        self.assertEqual(paths, ["AI_ENTRYPOINT.md", "scripts/validate_pr_contract.py"])
        self.assertEqual(mode, "workpack")
        self.assertEqual(plan, "full")

    def test_high_risk_path_requires_workpack(self):
        self.assert_rejected(
            "workpack is required",
            changed=[".github/workflows/pr-gate.yml"],
        )

    def test_workpack_branch_mismatch_is_rejected(self):
        self.assert_rejected(
            "head branch",
            pr_body="Workpack: #300",
            changed=["AI_ENTRYPOINT.md"],
        )

    def test_closed_or_unmarked_workpack_is_rejected(self):
        self.assert_rejected(
            "must be open",
            issue=issue_object(state="closed"),
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md"],
        )
        self.assert_rejected(
            "active-workpack marker",
            issue=issue_object(title="Prepared"),
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md"],
        )

    def test_duplicate_workpack_line_is_rejected(self):
        self.assert_rejected(
            "at most one line",
            pr_body="Workpack: #300\nWorkpack: #300",
        )

    def test_duplicate_pr_for_same_workpack_is_rejected(self):
        self.assert_rejected(
            "already has open PR",
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md"],
            open_prs=[
                {"number": 301, "body": "Workpack: #300", "changed_paths": ["AI_ENTRYPOINT.md"]},
                {"number": 302, "body": "Workpack: #300", "changed_paths": ["other.md"]},
            ],
        )

    def test_exact_file_overlap_is_rejected(self):
        self.assert_rejected(
            "overlap open PR #302",
            changed=["js/example.js"],
            open_prs=[
                {"number": 301, "body": "", "changed_paths": ["js/example.js"]},
                {"number": 302, "body": "", "changed_paths": ["js/example.js", "css/other.css"]},
            ],
        )

    def test_independent_parallel_pr_is_allowed(self):
        parsed, paths, mode, plan = self.validate(
            changed=["js/example.js"],
            open_prs=[
                {"number": 301, "body": "", "changed_paths": ["js/example.js"]},
                {"number": 302, "body": "", "changed_paths": ["api/other.php"]},
            ],
        )
        self.assertIsNone(parsed)
        self.assertEqual(paths, ["js/example.js"])
        self.assertEqual(mode, "normal")
        self.assertEqual(plan, "frontend")

    def test_workpack_scope_and_locked_paths_are_enforced(self):
        self.assert_rejected(
            "outside allowed scope",
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["README.md"],
        )
        values = workpack_contract()
        values["allowed_paths"].append("api/status.php")
        self.assert_rejected(
            "changed path is locked",
            issue=issue_object(values),
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["api/status.php"],
        )

    def test_controlled_write_requires_compact_write_contract(self):
        values = workpack_contract()
        values["external_access"] = "controlled-staging-write"
        self.assert_rejected(
            "external_write",
            issue=issue_object(values),
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md"],
        )
        values["external_write"] = {
            "resource": "Staging DB",
            "identity": "Synthetic UUID",
            "before": "No rows",
            "mutation": "One bounded row",
            "readback": "Exact row",
            "cleanup": "Delete row and prove zero residue",
        }
        self.validate(
            issue=issue_object(values),
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md"],
        )

    def test_release_path_accepts_only_staging_to_main(self):
        parsed, paths, mode, plan = self.validate(
            base_ref="main",
            head_ref="staging",
            changed=["docs/note.md"],
            open_prs=[],
        )
        self.assertIsNone(parsed)
        self.assertEqual(paths, ["docs/note.md"])
        self.assertEqual(mode, "release")
        self.assertEqual(plan, "full")
        self.assert_rejected(
            "release PR",
            base_ref="main",
            head_ref="agent/release",
            changed=["docs/note.md"],
            open_prs=[],
        )

    def test_rename_checks_old_and_new_path(self):
        paths = contract.parse_name_status_z(
            b"R100\0docs/old.md\0docs/new.md\0"
        )
        self.assertEqual(paths, ["docs/old.md", "docs/new.md"])

    def test_unbounded_root_wildcard_is_rejected(self):
        values = workpack_contract()
        values["allowed_paths"] = ["**/*"]
        self.assert_rejected(
            "unbounded root wildcard",
            issue=issue_object(values),
            pr_body="Workpack: #300",
            head_ref="agent/adaptive-workflow-300",
            changed=["AI_ENTRYPOINT.md"],
        )

    def test_plan_classification(self):
        cases = [
            (["README.md", "docs/note.md"], False, "docs"),
            (["js/example.js", "tests/example.mjs"], False, "frontend"),
            (["api/example.php", "tests/example.php"], False, "backend"),
            (["scripts/example.py", "tests/example.py"], False, "quick"),
            (["api/example.php", "js/example.js"], False, "full"),
            (["api/sql/013.sql"], True, "full"),
        ]
        for paths, is_workpack, expected in cases:
            with self.subTest(paths=paths):
                self.assertEqual(
                    contract.classify_changed_paths(paths, workpack=is_workpack),
                    expected,
                )

    def test_component_and_browser_target_selection(self):
        cases = [
            (["api/startpartner/action.php"], "backend", ("startpartner", False, False)),
            (["api/control-center/action.php"], "backend", ("control-center", False, False)),
            (["api/example.php"], "backend", ("all", False, False)),
            (["events/index.html"], "frontend", ("all", True, False)),
            (["steuerzentrale/index.html"], "frontend", ("control-center", False, True)),
            (["css/component.css"], "frontend", ("all", True, True)),
            (["api/startpartner/action.php", "js/control-center/startpartner.js"], "full", ("all", True, True)),
        ]
        for paths, plan, expected in cases:
            with self.subTest(paths=paths, plan=plan):
                self.assertEqual(contract.select_test_targets(paths, plan=plan), expected)

    def test_api_failure_fails_closed(self):
        with self.assertRaisesRegex(contract.ContractError, "simulated API failure"):
            contract.validate_pull_request(
                pr_number=301,
                pr_body="Workpack: #300",
                repository="Stratos888/Bocholt-Erleben",
                base_ref="staging",
                head_ref="agent/adaptive-workflow-300",
                changed_paths=["AI_ENTRYPOINT.md"],
                issue_loader=lambda _: (_ for _ in ()).throw(
                    contract.ContractError("simulated API failure")
                ),
                open_feature_pr_loader=lambda: [],
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
