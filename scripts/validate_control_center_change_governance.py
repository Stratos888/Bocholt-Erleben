#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
RUNTIME_PREFIXES = (
    "api/control-center/",
    "js/control-center/",
    "js/control-center.js",
    "css/control-center",
    "steuerzentrale/",
    "data/control_center_",
    "data/event_visual_",
)
CONTROL_CENTER_PREFIXES = RUNTIME_PREFIXES + (
    ".github/workflows/control-center-",
    "tests/control_center",
    "scripts/audit_control_center",
    "scripts/validate_control_center",
    "docs/steuerzentrale",
    "STEUERZENTRALE_",
    ".github/pull_request_template.md",
    ".github/CODEOWNERS",
)
EXTERNAL_DATA_MARKERS = (
    "writeback",
    "sheet",
    "source.php",
    "action.php",
    "_config.php",
    "environment",
    "inbox_tab",
)


def run(*args: str) -> str:
    completed = subprocess.run(args, cwd=ROOT, check=True, text=True, capture_output=True)
    return completed.stdout.strip()


def changed_files(base: str) -> list[str]:
    try:
        run("git", "fetch", "origin", base, "--depth=1")
    except subprocess.CalledProcessError:
        pass
    candidates = [f"origin/{base}...HEAD", f"{base}...HEAD"]
    for candidate in candidates:
        try:
            output = run("git", "diff", "--name-only", candidate)
            return [line.strip() for line in output.splitlines() if line.strip()]
        except subprocess.CalledProcessError:
            continue
    raise RuntimeError(f"Geänderte Dateien gegen {base} konnten nicht bestimmt werden.")


def slug(branch: str) -> str:
    return branch.replace("/", "__")


def require(condition: bool, message: str, errors: list[str]) -> None:
    if not condition:
        errors.append(message)


def existing_evidence(values: object, errors: list[str], label: str) -> list[str]:
    if not isinstance(values, list) or not values:
        errors.append(f"{label}: mindestens eine Evidence-Datei ist erforderlich.")
        return []
    files = [str(value).strip() for value in values if str(value).strip()]
    for file in files:
        path = ROOT / file
        if not path.is_file() or not path.read_text(encoding="utf-8").strip():
            errors.append(f"{label}: Evidence-Datei fehlt oder ist leer: {file}")
    return files


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base", required=True)
    parser.add_argument("--head", required=True)
    args = parser.parse_args()

    files = changed_files(args.base)
    relevant = [path for path in files if path.startswith(CONTROL_CENTER_PREFIXES)]
    if not relevant:
        print("=== Control Center Change Governance: NOT APPLICABLE ===")
        return 0

    manifest_path = ROOT / "docs/evidence/control-center/changes" / f"{slug(args.head)}.json"
    errors: list[str] = []
    if not manifest_path.is_file():
        print("=== Control Center Change Governance: FAILED ===")
        print(f"- Change-Manifest fehlt: {manifest_path.relative_to(ROOT)}")
        return 1

    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as error:
        print("=== Control Center Change Governance: FAILED ===")
        print(f"- Change-Manifest ist ungültig: {error}")
        return 1

    require(manifest.get("schema_version") == "control_center_change_governance_v1", "schema_version ist nicht v1.", errors)
    require(manifest.get("branch") == args.head, "Manifest-Branch stimmt nicht mit dem PR-Head überein.", errors)
    require(manifest.get("base") == args.base, "Manifest-Base stimmt nicht mit dem PR-Ziel überein.", errors)
    require(bool(str(manifest.get("change_id", "")).strip()), "change_id fehlt.", errors)

    analysis = manifest.get("analysis") or {}
    require(isinstance(analysis.get("root_causes"), list) and len(analysis["root_causes"]) >= 1, "Mindestens eine belegte Root Cause fehlt.", errors)
    require(isinstance(analysis.get("preventive_controls"), list) and len(analysis["preventive_controls"]) >= 1, "Mindestens eine präventive Kontrolle fehlt.", errors)
    existing_evidence(analysis.get("evidence_files"), errors, "Ursachenanalyse")

    actual_runtime = any(path.startswith(RUNTIME_PREFIXES) for path in files)
    actual_external = any(
        path.startswith(RUNTIME_PREFIXES) and any(marker in path.lower() for marker in EXTERNAL_DATA_MARKERS)
        for path in files
    )
    scope = manifest.get("scope") or {}
    require(scope.get("runtime") is actual_runtime, f"scope.runtime muss {str(actual_runtime).lower()} sein.", errors)
    require(scope.get("external_data") is actual_external, f"scope.external_data muss {str(actual_external).lower()} sein.", errors)
    require(scope.get("live_write_allowed") is False, "live_write_allowed muss immer false sein.", errors)

    rollback = str(manifest.get("rollback_plan", "")).strip()
    require(bool(rollback), "Rollback-Plan fehlt.", errors)

    isolated = manifest.get("isolated_integration") or {}
    environment = manifest.get("environment_isolation") or {}
    identity = manifest.get("data_identity") or {}
    staging_write = manifest.get("real_staging_write") or {}
    staging_acceptance = manifest.get("staging_acceptance") or {}
    merge = manifest.get("merge") or {}

    if actual_runtime:
        require(isolated.get("status") == "passed", "Runtime-Änderungen benötigen eine bestandene isolierte Integration.", errors)
        existing_evidence(isolated.get("evidence_files"), errors, "Isolierte Integration")
        require(bool(str(rollback)), "Runtime-Änderungen benötigen einen Rollback-Plan.", errors)
    else:
        require(isolated.get("status") in {"passed", "not_required"}, "isolated_integration.status ist ungültig.", errors)

    if actual_external:
        require(environment.get("status") == "verified", "Quell-/Writeback-Änderungen benötigen verifizierte Environment-Isolation.", errors)
        require(identity.get("status") == "verified", "Quell-/Writeback-Änderungen benötigen verifizierte Datenidentität.", errors)
        existing_evidence(environment.get("evidence_files"), errors, "Environment-Isolation")
        existing_evidence(identity.get("evidence_files"), errors, "Datenidentität")
    else:
        require(environment.get("status") in {"verified", "not_required"}, "environment_isolation.status ist ungültig.", errors)
        require(identity.get("status") in {"verified", "not_required"}, "data_identity.status ist ungültig.", errors)

    require(staging_write.get("authorized") is False, "Eine reale Staging-Schreibprobe darf im PR nicht vorab autorisiert werden.", errors)
    require(staging_write.get("status") in {"not_required", "pending_after_merge"}, "Reale Staging-Schreibprobe darf vor dem Merge nicht als abgeschlossen gelten.", errors)

    if args.base == "staging":
        require(merge.get("staging_allowed") is True, "PR nach staging muss staging_allowed=true deklarieren.", errors)
        require(merge.get("main_allowed") is False, "PR nach staging darf main_allowed nicht freigeben.", errors)
        require(staging_acceptance.get("status") in {"pending_after_merge", "not_required"}, "Staging-Abnahme ist vor dem Staging-Deploy nur pending oder nicht erforderlich.", errors)
    elif args.base == "main":
        require(merge.get("main_allowed") is True, "PR nach main benötigt main_allowed=true.", errors)
        require(staging_acceptance.get("status") == "passed", "PR nach main benötigt bestandene reale Staging-Abnahme.", errors)
        existing_evidence(staging_acceptance.get("evidence_files"), errors, "Reale Staging-Abnahme")
    else:
        errors.append(f"Nicht unterstützter Zielbranch für Steuerzentralen-Änderung: {args.base}")

    manifest_rel = str(manifest_path.relative_to(ROOT))
    require(manifest_rel in files, "Das Change-Manifest muss im aktuellen PR angelegt oder aktualisiert werden.", errors)

    if errors:
        print("=== Control Center Change Governance: FAILED ===")
        for error in errors:
            print(f"- {error}")
        print("Geänderte relevante Dateien:")
        for file in relevant:
            print(f"  - {file}")
        return 1

    print("=== Control Center Change Governance: OK ===")
    print(f"Manifest: {manifest_rel}")
    print(f"Runtime: {actual_runtime}; External data path: {actual_external}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
