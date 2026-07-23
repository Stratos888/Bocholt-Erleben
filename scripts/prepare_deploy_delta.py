#!/usr/bin/env python3
"""Build a fail-closed, ordered STRATO deploy payload.

The deploy is split into five phases:
1. ordinary assets;
2. HTML entry files;
3. the public build marker;
4. the service worker, stamped per build so existing browsers install a new worker;
5. the deploy manifest, published only after the release phases were verified.
"""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import shutil
from pathlib import Path


EXCLUDED_FROM_MANIFEST = {"meta/deploy-manifest.json"}
BUILD_MARKER = "meta/build.txt"
SERVICE_WORKER = "service-worker.js"
DEPLOY_MANIFEST = "meta/deploy-manifest.json"


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def stamp_service_worker(path: Path, build_id: str) -> None:
    if not path.is_file():
        raise ValueError(f"service worker missing: {path}")
    marker = f"// DEPLOY_BUILD_ID: {build_id}"
    text = path.read_text(encoding="utf-8")
    pattern = re.compile(r"(?m)^// DEPLOY_BUILD_ID:.*$")
    if pattern.search(text):
        text = pattern.sub(marker, text, count=1)
    else:
        text = f"{text.rstrip()}\n\n{marker}\n"
    path.write_text(text, encoding="utf-8")


def load_remote_manifest(path: Path) -> dict[str, str]:
    try:
        payload = json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError):
        return {}
    files = payload.get("files", {}) if isinstance(payload, dict) else {}
    return files if isinstance(files, dict) else {}


def copy_payload(source: Path, destination: Path, relative_path: str) -> None:
    src = source / relative_path
    dst = destination / relative_path
    dst.parent.mkdir(parents=True, exist_ok=True)
    shutil.copy2(src, dst)


def prepare_deploy_plan(
    source: Path,
    remote_manifest: Path,
    mode: str,
    build_id: str,
    environment: str,
    output_root: Path,
) -> dict[str, object]:
    if mode not in {"delta", "full", "full_repair"}:
        raise ValueError(f"unsupported deploy mode: {mode}")
    if not build_id.strip():
        raise ValueError("build_id must not be empty")
    if not source.is_dir():
        raise ValueError(f"deploy source missing: {source}")
    if not (source / BUILD_MARKER).is_file():
        raise ValueError(f"build marker missing: {source / BUILD_MARKER}")

    stamp_service_worker(source / SERVICE_WORKER, build_id)

    destinations = {
        "assets": output_root / "deploy-assets",
        "entry": output_root / "deploy-entry",
        "marker": output_root / "deploy-marker",
        "worker": output_root / "deploy-worker",
        "manifest": output_root / "deploy-manifest",
    }
    for destination in destinations.values():
        shutil.rmtree(destination, ignore_errors=True)
        destination.mkdir(parents=True)

    current = {
        path.relative_to(source).as_posix(): sha256(path)
        for path in source.rglob("*")
        if path.is_file() and path.relative_to(source).as_posix() not in EXCLUDED_FROM_MANIFEST
    }
    previous = load_remote_manifest(remote_manifest)
    changed = (
        set(current)
        if mode != "delta"
        else {relative for relative, digest in current.items() if previous.get(relative) != digest}
    )
    deleted = set() if mode == "full" else set(previous) - set(current)

    if "api/_config.php" in current:
        changed.add("api/_config.php")

    phase_files: dict[str, list[str]] = {key: [] for key in destinations}
    for relative in sorted(changed):
        if relative == BUILD_MARKER:
            phase = "marker"
        elif relative == SERVICE_WORKER:
            phase = "worker"
        elif relative.endswith(".html"):
            phase = "entry"
        else:
            phase = "assets"
        copy_payload(source, destinations[phase], relative)
        phase_files[phase].append(relative)

    manifest = {
        "schema": 1,
        "build": build_id,
        "environment": environment,
        "files": current,
    }
    manifest_path = destinations["manifest"] / DEPLOY_MANIFEST
    manifest_path.parent.mkdir(parents=True, exist_ok=True)
    manifest_path.write_text(
        json.dumps(manifest, sort_keys=True, indent=2) + "\n",
        encoding="utf-8",
    )
    phase_files["manifest"].append(DEPLOY_MANIFEST)

    delete_lines = []
    for relative in sorted(deleted):
        if any(character in relative for character in ['"', "\n", "\r"]):
            raise ValueError(f"unsafe delete path: {relative!r}")
        delete_lines.append(f'rm -f "{relative}"')
    delete_file = output_root / "deploy-delete.lftp"
    delete_file.write_text(
        "\n".join(delete_lines) + ("\n" if delete_lines else ""),
        encoding="utf-8",
    )

    summary = {
        "mode": mode,
        "build": build_id,
        "environment": environment,
        "changed": len(changed),
        "deleted": len(deleted),
        "phases": phase_files,
    }
    (output_root / "deploy-plan.json").write_text(
        json.dumps(summary, sort_keys=True, indent=2) + "\n",
        encoding="utf-8",
    )
    return summary


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--source", type=Path, default=Path("deploy"))
    parser.add_argument("--remote-manifest", type=Path, required=True)
    parser.add_argument("--mode", required=True, choices=("delta", "full", "full_repair"))
    parser.add_argument("--build-id", required=True)
    parser.add_argument("--environment", required=True)
    parser.add_argument("--output-root", type=Path, default=Path("."))
    args = parser.parse_args()

    summary = prepare_deploy_plan(
        source=args.source,
        remote_manifest=args.remote_manifest,
        mode=args.mode,
        build_id=args.build_id,
        environment=args.environment,
        output_root=args.output_root,
    )
    print(json.dumps(summary, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
