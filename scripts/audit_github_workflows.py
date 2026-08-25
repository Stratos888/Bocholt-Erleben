#!/usr/bin/env python3
"""Fail closed when the tracked GitHub Actions workflow set drifts."""

from __future__ import annotations

from pathlib import Path


WORKFLOW_DIR = Path(".github/workflows")
EXPECTED_WORKFLOWS = {
    "bathing-water-guard.yml",
    "content-quality-audit.yml",
    "deploy-run-status.yml",
    "deploy-strato.yml",
    "growth-intelligence-backlog.yml",
    "inbox-cleanup.yml",
    "pr-gate.yml",
    "weekly-ki-websearch-to-manual-inbox.yml",
}


def main() -> int:
    if not WORKFLOW_DIR.is_dir():
        print(f"Workflow directory missing: {WORKFLOW_DIR}")
        return 1

    actual = {
        path.name
        for path in WORKFLOW_DIR.iterdir()
        if path.is_file() and path.suffix.lower() in {".yml", ".yaml"}
    }
    unexpected = sorted(actual - EXPECTED_WORKFLOWS)
    missing = sorted(EXPECTED_WORKFLOWS - actual)

    print("Tracked GitHub Actions workflows:")
    for name in sorted(actual):
        print(f"- {name}")

    if unexpected:
        print("Unexpected workflow files:")
        for name in unexpected:
            print(f"- {name}")
    if missing:
        print("Missing required workflow files:")
        for name in missing:
            print(f"- {name}")

    if unexpected or missing:
        return 1

    print("GitHub Actions workflow inventory: OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
