#!/usr/bin/env python3
"""Inventory and classify all tracked Markdown documents.

This report is intentionally diagnostic. It identifies routing and maintenance
risks without deleting domain knowledge merely because a reference is large.
"""

from __future__ import annotations

import json
import re
import subprocess
from collections import Counter
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

EXACT_ROLES = {
    "README.md": "repository_entrypoint",
    "AI_ENTRYPOINT.md": "ai_execution_router",
    "ENGINEERING.md": "engineering_guardrails",
    "MASTER.md": "product_north_star",
    "Produktvertrag.md": "implemented_product_contract",
    "COMMERCIAL_STRATEGY.md": "commercial_strategy",
    "ROADMAP.md": "product_roadmap",
    "TEST_STATUS.md": "current_proof_index",
    "DEBUG.md": "ui_debug_playbook",
    "EVENT_DESCRIPTION_STANDARD.md": "event_description_contract",
    "VISUAL_WORKFLOW.md": "legacy_composite_visual_reference",
    "bocholt-erleben_eventsuche_regelwerk_v3.md": "legacy_composite_event_search_reference",
    "eventsuche_quellenregister_v1.md": "event_search_source_registry",
    "docs/README.md": "documentation_governance",
    "docs/architecture/SYSTEM_MAP.md": "system_architecture",
    "docs/external-resource-matrix.md": "external_resource_contract",
    "docs/github-actions-trigger-policy.md": "github_actions_policy",
    "docs/workpacks/active/CURRENT_WORKPACK.md": "current_workpack",
    "docs/workpacks/queued/INDEX.md": "workpack_queue",
}

MUTABLE_CANONICAL = {
    "README.md",
    "AI_ENTRYPOINT.md",
    "ENGINEERING.md",
    "MASTER.md",
    "Produktvertrag.md",
    "COMMERCIAL_STRATEGY.md",
    "ROADMAP.md",
    "TEST_STATUS.md",
    "DEBUG.md",
    "EVENT_DESCRIPTION_STANDARD.md",
    "docs/README.md",
    "docs/architecture/SYSTEM_MAP.md",
    "docs/external-resource-matrix.md",
    "docs/github-actions-trigger-policy.md",
    "docs/workpacks/active/CURRENT_WORKPACK.md",
    "docs/workpacks/queued/INDEX.md",
}

BEGIN_BLOCK = re.compile(r"<!--\s*===\s*BEGIN BLOCK:", re.IGNORECASE)
DYNAMIC_PR = re.compile(r"\bPR\s*#\d+\b")
STATUS_HEADING = re.compile(
    r"^#{1,4}\s+(Aktueller Stand|Aktueller Status|Nächste Workstreams|"
    r"Naechste Workstreams|Nächste Maßnahmen|Naechste Massnahmen)\b",
    re.MULTILINE | re.IGNORECASE,
)


def git_output(*args: str) -> str:
    completed = subprocess.run(
        ["git", *args],
        cwd=ROOT,
        check=True,
        capture_output=True,
        text=True,
    )
    return completed.stdout.strip()


def tracked_markdown() -> list[tuple[str, str, str]]:
    rows: list[tuple[str, str, str]] = []
    for line in git_output("ls-files", "-s", "*.md").splitlines():
        if not line:
            continue
        metadata, path = line.split("\t", 1)
        mode, blob_sha, _stage = metadata.split()
        rows.append((path, mode, blob_sha))
    return sorted(rows)


def classify(path: str) -> str:
    if path in EXACT_ROLES:
        return EXACT_ROLES[path]
    if path.startswith("docs/workpacks/active/"):
        return "active_workpack_support"
    if path.startswith("docs/workpacks/queued/"):
        return "queued_workpack"
    if path.startswith("docs/workpacks/completed/"):
        return "completed_workpack"
    if path.startswith("docs/decisions/"):
        return "dated_decision"
    if path.startswith("docs/forensics/"):
        return "dated_forensics"
    if path.startswith("docs/evidence/"):
        return "evidence"
    if path.startswith("docs/architecture/"):
        return "architecture_reference"
    if path.startswith("docs/archive/") or "/archive/" in path:
        return "archive"
    if "FREEZE" in path.upper() or re.search(r"20\d{2}-\d{2}-\d{2}", path):
        return "dated_historical_or_target_reference"
    if path.startswith("docs/"):
        return "supporting_documentation"
    return "unclassified_root_document"


def main() -> None:
    rows: list[dict[str, object]] = []
    warnings: list[str] = []

    for relative, mode, blob_sha in tracked_markdown():
        path = ROOT / relative
        text = path.read_text(encoding="utf-8")
        role = classify(relative)
        lines = len(text.splitlines())
        blocks = len(BEGIN_BLOCK.findall(text))
        status_headings = len(STATUS_HEADING.findall(text))
        prs = sorted(set(DYNAMIC_PR.findall(text)))

        row = {
            "path": relative,
            "role": role,
            "mode": mode,
            "blob_sha": blob_sha,
            "lines": lines,
            "bytes": path.stat().st_size,
            "append_blocks": blocks,
            "status_headings": status_headings,
            "dynamic_pr_references": prs,
        }
        rows.append(row)

        if role == "unclassified_root_document":
            warnings.append(f"unclassified root document: {relative}")
        if relative in MUTABLE_CANONICAL and blocks:
            warnings.append(f"mutable canonical document uses {blocks} append block(s): {relative}")
        if role.startswith("legacy_composite"):
            warnings.append(
                f"legacy composite reference should not be used as a current-status router: "
                f"{relative} ({lines} lines)"
            )
        if lines > 500 and role not in {
            "implemented_product_contract",
            "event_search_source_registry",
            "evidence",
            "archive",
            "dated_historical_or_target_reference",
        }:
            warnings.append(f"large mixed-role candidate: {relative} ({lines} lines, role={role})")
        if status_headings and role not in {
            "current_workpack",
            "current_proof_index",
            "dated_forensics",
            "evidence",
            "completed_workpack",
        }:
            warnings.append(
                f"current-status heading appears outside a current-status/evidence document: {relative}"
            )

    role_counts = Counter(row["role"] for row in rows)
    summary = {
        "commit_sha": git_output("rev-parse", "HEAD"),
        "tree_sha": git_output("rev-parse", "HEAD^{tree}"),
        "markdown_files": len(rows),
        "total_lines": sum(int(row["lines"]) for row in rows),
        "role_counts": dict(sorted(role_counts.items())),
        "warnings": warnings,
        "files": rows,
    }

    out = ROOT / "build" / "documentation-inventory.json"
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(summary, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print("Documentation inventory")
    print(f"- commit: {summary['commit_sha']}")
    print(f"- tree: {summary['tree_sha']}")
    print(f"- tracked Markdown files: {summary['markdown_files']}")
    print(f"- total Markdown lines: {summary['total_lines']}")
    print("- roles:")
    for role, count in summary["role_counts"].items():
        print(f"  - {role}: {count}")
    print(f"- warnings: {len(warnings)}")
    for warning in warnings:
        print(f"  - {warning}")
    print(f"- machine-readable report: {out.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
