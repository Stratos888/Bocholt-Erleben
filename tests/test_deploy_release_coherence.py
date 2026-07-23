#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "deploy_plan", ROOT / "scripts" / "prepare_deploy_delta.py"
)
deploy_plan = importlib.util.module_from_spec(SPEC)
assert SPEC.loader is not None
SPEC.loader.exec_module(deploy_plan)


def write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


def require(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


def run_full_plan(temp: Path, build_id: str = "abc123") -> tuple[Path, dict]:
    source = temp / "deploy"
    write(source / "index.html", f'<link href="/css/style.css?v={build_id}">')
    write(source / "events/index.html", f'<script src="/js/main.js?v={build_id}"></script>')
    write(source / "css/style.css", f'@import url("home.css?v={build_id}");')
    write(source / "css/home.css", ".event-card{}")
    write(source / "service-worker.js", "self.addEventListener('install', () => {});\n")
    write(source / "meta/build.txt", f"{build_id}\n")
    write(source / "api/_config.php", "<?php return [];\n")
    remote = temp / "remote.json"
    write(remote, "{}\n")
    summary = deploy_plan.prepare_deploy_plan(
        source=source,
        remote_manifest=remote,
        mode="full",
        build_id=build_id,
        environment="live",
        output_root=temp,
    )
    return source, summary


def require_workflow_release_order() -> None:
    workflow = (ROOT / ".github/workflows/deploy-strato.yml").read_text(encoding="utf-8")
    ordered_steps = [
        "- name: Publish HTML entry files",
        "- name: Verify HTML asset keys before build cutover",
        "- name: Publish build marker after HTML verification",
        "- name: Verify public build marker",
        "- name: Publish version-stamped service worker last",
        "- name: Verify service-worker build stamp",
        "- name: Publish deploy manifest after release verification",
    ]
    positions = []
    for step in ordered_steps:
        require(workflow.count(step) == 1, f"workflow must contain exactly one step: {step}")
        positions.append(workflow.index(step))
    require(positions == sorted(positions), "deploy release steps must remain in fail-closed order")

    phase_calls = [
        "bash scripts/run_strato_sftp_phase.sh deploy-assets 8 deploy-delete.lftp",
        "bash scripts/run_strato_sftp_phase.sh deploy-entry 6",
        "bash scripts/run_strato_sftp_phase.sh deploy-marker 1",
        "bash scripts/run_strato_sftp_phase.sh deploy-worker 1",
        "bash scripts/run_strato_sftp_phase.sh deploy-manifest 1",
    ]
    for call in phase_calls:
        require(workflow.count(call) == 1, f"workflow must use resilient SFTP runner exactly once: {call}")


def main() -> None:
    with tempfile.TemporaryDirectory(prefix="be-deploy-plan-") as temp_name:
        temp = Path(temp_name)
        source, summary = run_full_plan(temp)

        require((temp / "deploy-assets/css/style.css").is_file(), "CSS must be in assets phase")
        require((temp / "deploy-assets/api/_config.php").is_file(), "private config must be in assets phase")
        require((temp / "deploy-entry/index.html").is_file(), "HTML must be in entry phase")
        require((temp / "deploy-entry/events/index.html").is_file(), "nested HTML must be in entry phase")
        require((temp / "deploy-marker/meta/build.txt").is_file(), "build marker must be isolated")
        require((temp / "deploy-worker/service-worker.js").is_file(), "service worker must be isolated")
        require(
            (temp / "deploy-manifest/meta/deploy-manifest.json").is_file(),
            "manifest must be isolated for last publication",
        )
        require(
            not (temp / "deploy-entry/meta/deploy-manifest.json").exists(),
            "HTML phase must not publish the success manifest",
        )
        require(not (temp / "deploy-entry/meta/build.txt").exists(), "entry phase must not publish build marker")
        require(not (temp / "deploy-assets/service-worker.js").exists(), "assets phase must not publish service worker")

        worker = (temp / "deploy-worker/service-worker.js").read_text(encoding="utf-8")
        require("// DEPLOY_BUILD_ID: abc123" in worker, "service worker must change for every build")
        require(summary["phases"]["marker"] == ["meta/build.txt"], "marker phase must contain only build.txt")
        require(summary["phases"]["worker"] == ["service-worker.js"], "worker phase must contain only service worker")
        require(
            summary["phases"]["manifest"] == ["meta/deploy-manifest.json"],
            "manifest phase must contain only the success manifest",
        )

        first_manifest = json.loads(
            (temp / "deploy-manifest/meta/deploy-manifest.json").read_text(encoding="utf-8")
        )
        require(first_manifest["build"] == "abc123", "manifest build must match deploy build")
        require(
            first_manifest["files"]["service-worker.js"] == deploy_plan.sha256(source / "service-worker.js"),
            "manifest must hash stamped worker",
        )

        remote = temp / "remote-same.json"
        write(remote, json.dumps(first_manifest) + "\n")
        same_root = temp / "same"
        same_root.mkdir()
        same_summary = deploy_plan.prepare_deploy_plan(
            source=source,
            remote_manifest=remote,
            mode="delta",
            build_id="abc123",
            environment="staging",
            output_root=same_root,
        )
        require(same_summary["phases"]["marker"] == [], "same successful build need not republish marker")
        require(same_summary["phases"]["worker"] == [], "same successful build need not republish worker")
        require("api/_config.php" in same_summary["phases"]["assets"], "private config must always refresh")
        require(
            same_summary["phases"]["manifest"] == ["meta/deploy-manifest.json"],
            "every completed run must have a final manifest payload",
        )

        write(source / "meta/build.txt", "def456\n")
        write(source / "index.html", '<link href="/css/style.css?v=def456">')
        write(source / "events/index.html", '<script src="/js/main.js?v=def456"></script>')
        write(source / "css/style.css", '@import url("home.css?v=def456");')
        next_root = temp / "next"
        next_root.mkdir()
        next_summary = deploy_plan.prepare_deploy_plan(
            source=source,
            remote_manifest=remote,
            mode="delta",
            build_id="def456",
            environment="staging",
            output_root=next_root,
        )
        require("index.html" in next_summary["phases"]["entry"], "new build must publish HTML before marker")
        require("meta/build.txt" in next_summary["phases"]["marker"], "new build must publish marker separately")
        require("service-worker.js" in next_summary["phases"]["worker"], "new build must publish a changed worker")
        require(
            "// DEPLOY_BUILD_ID: def456" in (next_root / "deploy-worker/service-worker.js").read_text(encoding="utf-8"),
            "next worker must carry next build marker",
        )

    require_workflow_release_order()
    print("Deploy release coherence contract: OK")


if __name__ == "__main__":
    main()
