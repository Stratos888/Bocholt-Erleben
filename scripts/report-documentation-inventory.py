#!/usr/bin/env python3
"""Inventory and validate every tracked Markdown document in the repository."""
from __future__ import annotations

import argparse
import json
import re
import subprocess
import sys
from collections import Counter
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[1]

EXACT_ROLES = {
    ".github/pull_request_template.md": "pull_request_template",
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
    "VISUAL_WORKFLOW.md": "legacy_visual_reference",
    "ACTIVITY_SEASONAL_HIGHLIGHTS.md": "activity_reference",
    "ACTIVITY_VISUAL_PROMPT_KIT.md": "visual_reference",
    "ACTIVITY_VISUAL_SOURCING_MATRIX.md": "visual_reference",
    "ACTIVITY_VISUAL_WORKFLOW.md": "visual_reference",
    "BATHING_WATER_GUARD_V1.md": "operations_reference",
    "BATHING_WATER_SOURCE_DISCOVERY_V2.md": "operations_reference",
    "BATHING_WATER_STATUS_PROOF.md": "evidence_reference",
    "BROWSER_SMOKE_SYSTEM.md": "browser_smoke_contract",
    "EVENT_IMPACT_TRACKING.md": "event_impact_contract",
    "MAIL_SYSTEM.md": "mail_reference",
    "bocholt-erleben_eventsuche_regelwerk_v3.md": "event_search_reference",
    "eventsuche_quellenregister_v1.md": "event_search_source_registry",
    "STEUERZENTRALE_ABNAHMEMATRIX.md": "control_center_reference",
    "STEUERZENTRALE_BACKEND_GAP_ANALYSE.md": "control_center_reference",
    "STEUERZENTRALE_FREEZE_2026-07-10.md": "historical_freeze",
    "STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md": "control_center_reference",
    "STEUERZENTRALE_IMPLEMENTIERUNGSSTATUS.md": "historical_status",
    "STEUERZENTRALE_INFORMATIONARCHITEKTUR.md": "control_center_reference",
    "STEUERZENTRALE_SCREENVERTRAG.md": "control_center_reference",
    "STEUERZENTRALE_SIMULATIONSBERICHT.md": "historical_evidence",
    "STEUERZENTRALE_VORGANGSKATALOG.md": "control_center_reference",
    "STEUERZENTRALE_ZIELBILD.md": "control_center_target_reference",
    "docs/README.md": "documentation_governance",
    "docs/DOCUMENT_REGISTRY.md": "documentation_registry",
    "docs/architecture/SYSTEM_MAP.md": "system_architecture",
    "docs/external-resource-matrix.md": "external_resource_contract",
    "docs/github-actions-trigger-policy.md": "github_actions_policy",
    "docs/workpacks/active/CURRENT_WORKPACK.md": "current_workpack",
    "docs/workpacks/queued/INDEX.md": "workpack_queue",
}

MUTABLE_CANONICAL_ROLES = {
    "repository_entrypoint",
    "ai_execution_router",
    "engineering_guardrails",
    "product_north_star",
    "implemented_product_contract",
    "commercial_strategy",
    "product_roadmap",
    "current_proof_index",
    "documentation_governance",
    "documentation_registry",
    "system_architecture",
    "external_resource_contract",
    "github_actions_policy",
    "current_workpack",
    "workpack_queue",
    "event_description_contract",
    "browser_smoke_contract",
    "event_impact_contract",
}

STATUS_ALLOWED_ROLES = {
    "current_workpack",
    "current_proof_index",
    "dated_forensics",
    "evidence",
    "evidence_reference",
    "completed_workpack",
    "historical_status",
    "historical_evidence",
    "historical_freeze",
}

STABLE_NO_DYNAMIC_PR_ROLES = {
    "repository_entrypoint",
    "ai_execution_router",
    "engineering_guardrails",
    "product_north_star",
    "implemented_product_contract",
    "commercial_strategy",
    "product_roadmap",
    "documentation_governance",
    "documentation_registry",
    "system_architecture",
    "external_resource_contract",
    "github_actions_policy",
}

BEGIN_BLOCK = re.compile(r"<!--\s*===\s*BEGIN BLOCK:\s*([^|>]+?)\s*(?:\||===)", re.IGNORECASE)
DYNAMIC_PR = re.compile(r"\bPR\s*#\d+\b")
STATUS_HEADING = re.compile(
    r"^#{1,4}\s+(Aktueller Stand|Aktueller Status|Nächste Workstreams|"
    r"Naechste Workstreams|Nächste Maßnahmen|Naechste Massnahmen)\b",
    re.MULTILINE | re.IGNORECASE,
)
MARKDOWN_LINK = re.compile(r"(?<!!)\[[^\]]+\]\(([^)]+)\)")
FENCED_CODE = re.compile(r"```.*?```|~~~.*?~~~", re.DOTALL)
SCHEME = re.compile(r"^[a-zA-Z][a-zA-Z0-9+.-]*:")
DATED_NAME = re.compile(r"20\d{2}-\d{2}-\d{2}")

KNOWN_APPEND_BLOCKS = {
    "BROWSER_SMOKE_SYSTEM.md": {
        "BROWSER_SMOKE_ACTIVITY_FAVORITES_PREMIUM_2026_06_30",
        "BROWSER_SMOKE_MOBILE_QUICK_FILTER_RAIL_2026_07_01",
    },
    "COMMERCIAL_STRATEGY.md": {
        "COMMERCIAL_STARTPARTNER_GROWTH_PILOT_TARGET_2026_07_18",
    },
    "Produktvertrag.md": {
        "PRODUKTVERTRAG_PUBLIC_LEGAL_ALIGNMENT_2026_06_29",
    },
}

STATUS_HEADING_ALLOWLIST = {
    "MAIL_SYSTEM.md",
    "docs/github-actions-trigger-policy.md",
}


def git_output(*args: str) -> str:
    completed = subprocess.run(
        ["git", *args], cwd=ROOT, check=True, capture_output=True, text=True
    )
    return completed.stdout.strip()


def tracked_markdown() -> list[tuple[str, str, str]]:
    rows: list[tuple[str, str, str]] = []
    output = git_output("ls-files", "-s", "*.md")
    for line in output.splitlines():
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
    if path.startswith("docs/contracts/"):
        return "domain_contract"
    if path.startswith("docs/playbooks/"):
        return "playbook"
    if path.startswith("docs/reference/"):
        return "supporting_reference"
    if path.startswith("docs/archive/") or "/archive/" in path:
        return "archive"
    if path.startswith("docs/"):
        return "supporting_documentation"
    if DATED_NAME.search(path) or "FREEZE" in path.upper():
        return "dated_historical_or_target_reference"
    return "unclassified_root_document"


def clean_link_target(raw: str) -> str:
    target = raw.strip()
    if target.startswith("<") and ">" in target:
        target = target[1 : target.index(">")]
    else:
        target = re.split(r"\s+[\"']", target, maxsplit=1)[0]
    return unquote(target.strip())


def inspect_links(relative: str, text: str) -> list[dict[str, object]]:
    source = ROOT / relative
    without_code = FENCED_CODE.sub("", text)
    links: list[dict[str, object]] = []
    for raw in MARKDOWN_LINK.findall(without_code):
        target = clean_link_target(raw)
        if not target or target.startswith("#") or target.startswith("//") or SCHEME.match(target):
            continue
        path_part = target.split("#", 1)[0].split("?", 1)[0]
        if not path_part:
            continue
        resolved = (source.parent / path_part).resolve()
        try:
            repository_path = resolved.relative_to(ROOT).as_posix()
        except ValueError:
            links.append({"target": target, "resolved": None, "exists": False, "outside_repo": True})
            continue
        links.append(
            {
                "target": target,
                "resolved": repository_path,
                "exists": resolved.exists(),
                "outside_repo": False,
            }
        )
    return links


def build_report() -> dict[str, object]:
    files: list[dict[str, object]] = []
    warnings: list[str] = []
    notes: list[str] = []
    errors: list[str] = []

    for relative, mode, blob_sha in tracked_markdown():
        path = ROOT / relative
        text = path.read_text(encoding="utf-8")
        role = classify(relative)
        lines = len(text.splitlines())
        append_block_names = sorted({name.strip() for name in BEGIN_BLOCK.findall(text)})
        append_blocks = len(append_block_names)
        status_headings = len(STATUS_HEADING.findall(text))
        dynamic_prs = sorted(set(DYNAMIC_PR.findall(text)))
        links = inspect_links(relative, text)
        broken_markdown_links = [
            link for link in links
            if not link["exists"] and str(link["target"]).split("#", 1)[0].lower().endswith(".md")
        ]
        broken_other_links = [
            link for link in links
            if not link["exists"] and link not in broken_markdown_links
        ]
        archive_links = [
            link for link in links
            if isinstance(link["resolved"], str) and str(link["resolved"]).startswith("docs/archive/")
        ]

        files.append(
            {
                "path": relative,
                "role": role,
                "mode": mode,
                "blob_sha": blob_sha,
                "lines": lines,
                "bytes": path.stat().st_size,
                "append_blocks": append_blocks,
                "append_block_names": append_block_names,
                "status_headings": status_headings,
                "dynamic_pr_references": dynamic_prs,
                "broken_markdown_links": broken_markdown_links,
                "broken_other_links": broken_other_links,
                "archive_links": archive_links,
            }
        )

        if role == "unclassified_root_document":
            errors.append(f"unclassified root document: {relative}")
        if role in MUTABLE_CANONICAL_ROLES and append_blocks:
            allowed_blocks = KNOWN_APPEND_BLOCKS.get(relative, set())
            unexpected_blocks = sorted(set(append_block_names) - allowed_blocks)
            if unexpected_blocks:
                errors.append(
                    f"mutable canonical document uses unapproved append block(s): {relative}: "
                    + ", ".join(unexpected_blocks)
                )
            approved_blocks = sorted(set(append_block_names) & allowed_blocks)
            if approved_blocks:
                notes.append(
                    f"known legacy append block(s) retained until a safe full-file edit: {relative}: "
                    + ", ".join(approved_blocks)
                )
        if status_headings and role not in STATUS_ALLOWED_ROLES:
            if relative in STATUS_HEADING_ALLOWLIST:
                notes.append(
                    f"explicitly allowed implementation-status section in bounded contract/policy: {relative}"
                )
            else:
                errors.append(f"current-status heading outside current-status/evidence role: {relative}")
        if dynamic_prs and role in STABLE_NO_DYNAMIC_PR_ROLES:
            errors.append(f"dynamic PR reference in stable document: {relative}: {', '.join(dynamic_prs)}")
        if broken_markdown_links:
            for link in broken_markdown_links:
                errors.append(f"broken Markdown link in {relative}: {link['target']}")
        if broken_other_links:
            for link in broken_other_links:
                warnings.append(f"unresolved local link in {relative}: {link['target']}")
        if archive_links and role in MUTABLE_CANONICAL_ROLES:
            for link in archive_links:
                errors.append(f"canonical document links directly to archive: {relative} -> {link['target']}")
        if lines > 700 and role not in {
            "implemented_product_contract",
            "event_search_source_registry",
            "evidence",
            "archive",
            "dated_historical_or_target_reference",
            "historical_evidence",
            "historical_freeze",
            "supporting_reference",
        }:
            warnings.append(f"large mixed-role candidate: {relative} ({lines} lines, role={role})")

    role_counts = Counter(str(row["role"]) for row in files)
    return {
        "commit_sha": git_output("rev-parse", "HEAD"),
        "tree_sha": git_output("rev-parse", "HEAD^{tree}"),
        "markdown_files": len(files),
        "total_lines": sum(int(row["lines"]) for row in files),
        "role_counts": dict(sorted(role_counts.items())),
        "error_count": len(errors),
        "warning_count": len(warnings),
        "note_count": len(notes),
        "errors": errors,
        "warnings": warnings,
        "notes": notes,
        "files": files,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="exit non-zero when governance errors exist")
    parser.add_argument("--output", default="build/documentation-inventory.json")
    args = parser.parse_args()

    report = build_report()
    out = ROOT / args.output
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(report, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    print("Documentation inventory")
    print(f"- commit: {report['commit_sha']}")
    print(f"- tracked Markdown files: {report['markdown_files']}")
    print(f"- total Markdown lines: {report['total_lines']}")
    print(f"- errors: {report['error_count']}")
    print(f"- warnings: {report['warning_count']}")
    print(f"- notes: {report['note_count']}")
    for error in report["errors"]:
        print(f"  ERROR: {error}")
    for warning in report["warnings"]:
        print(f"  WARNING: {warning}")
    for note in report["notes"]:
        print(f"  NOTE: {note}")
    print(f"- report: {out.relative_to(ROOT)}")

    if args.check and report["errors"]:
        sys.exit(1)


if __name__ == "__main__":
    main()
