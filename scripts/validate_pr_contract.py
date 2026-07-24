#!/usr/bin/env python3
"""Validate a pull request against the frozen contract in its active workpack issue."""

from __future__ import annotations

import argparse
import fnmatch
import hashlib
import json
import os
import re
import subprocess
import sys
import tomllib
import urllib.error
import urllib.parse
import urllib.request
from pathlib import PurePosixPath
from typing import Any, Callable, Iterable

WORKPACK_START = "<!-- WORKPACK_CONTRACT_START -->"
WORKPACK_END = "<!-- WORKPACK_CONTRACT_END -->"
PR_START = "<!-- PR_EVIDENCE_START -->"
PR_END = "<!-- PR_EVIDENCE_END -->"
ACTIVE_MARKER = "[ACTIVE WORKPACK]"
HEX_64 = re.compile(r"^[0-9a-f]{64}$")
GIT_SHA = re.compile(r"^[0-9a-f]{40,64}$")
CONTROLLED_WRITES = {"controlled-staging-write", "controlled-live-admin"}
EXTERNAL_ACCESS = {"none", "read-only", *CONTROLLED_WRITES}


class ContractError(ValueError):
    """Raised when a PR or workpack contract is invalid."""


def require(condition: bool, message: str) -> None:
    if not condition:
        raise ContractError(message)


def non_empty_string(value: Any) -> bool:
    return isinstance(value, str) and bool(value.strip())


def non_empty_string_list(value: Any) -> bool:
    return isinstance(value, list) and bool(value) and all(non_empty_string(item) for item in value)


def extract_toml_block(text: str, start: str, end: str, label: str) -> dict[str, Any]:
    require(isinstance(text, str), f"{label} text is unavailable")
    require(text.count(start) == 1, f"{label} must contain exactly one start marker")
    require(text.count(end) == 1, f"{label} must contain exactly one end marker")
    start_pos = text.index(start) + len(start)
    end_pos = text.index(end)
    require(start_pos < end_pos, f"{label} markers are out of order")
    payload = text[start_pos:end_pos].strip()
    match = re.fullmatch(r"```toml\s*\n(?P<body>.*)\n```", payload, flags=re.DOTALL)
    require(match is not None, f"{label} must wrap exactly one fenced TOML block")
    try:
        parsed = tomllib.loads(match.group("body"))
    except tomllib.TOMLDecodeError as exc:
        raise ContractError(f"{label} contains invalid TOML: {exc}") from exc
    require(isinstance(parsed, dict), f"{label} TOML must be a table")
    return parsed


def canonical_contract_hash(contract: dict[str, Any]) -> str:
    payload = dict(contract)
    payload.pop("contract_hash", None)
    canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"), ensure_ascii=False)
    return hashlib.sha256(canonical.encode("utf-8")).hexdigest()


def validate_relative_path(value: str, label: str, *, allow_glob: bool) -> None:
    require(non_empty_string(value), f"{label} contains an empty path")
    require("\\" not in value, f"{label} must use POSIX separators: {value}")
    require("\x00" not in value, f"{label} contains NUL")
    require(not value.startswith("/"), f"{label} contains an absolute path: {value}")
    require(not re.match(r"^[A-Za-z]:", value), f"{label} contains a drive path: {value}")
    require(not value.startswith("./"), f"{label} contains a non-normalized path: {value}")
    parts = PurePosixPath(value).parts
    require(".." not in parts and "." not in parts, f"{label} contains unsafe traversal: {value}")
    if allow_glob:
        require(value not in {"*", "**", "**/*"}, f"{label} contains an unbounded root wildcard: {value}")
    else:
        require(not any(char in value for char in "*?["), f"{label} changed path contains a wildcard: {value}")


def validate_pattern_list(value: Any, label: str) -> list[str]:
    require(non_empty_string_list(value), f"{label} must be a non-empty string list")
    patterns = [item.strip() for item in value]
    for pattern in patterns:
        validate_relative_path(pattern, label, allow_glob=True)
    return patterns


def validate_issue_contract(
    contract: dict[str, Any], *, issue_number: int, issue_state: str, issue_title: str
) -> None:
    require(issue_state == "open", "referenced workpack issue must be open")
    require(ACTIVE_MARKER in issue_title, "referenced issue is missing the active-workpack marker")
    require(contract.get("schema_version") == 1, "workpack schema_version must equal 1")
    require(contract.get("workpack_issue") == issue_number, "workpack issue number does not match the loaded issue")
    revision = contract.get("contract_revision")
    require(isinstance(revision, int) and revision >= 1, "contract_revision must be a positive integer")
    contract_hash = contract.get("contract_hash")
    require(isinstance(contract_hash, str) and HEX_64.fullmatch(contract_hash) is not None, "contract_hash must be a lowercase SHA-256")
    require(contract_hash == canonical_contract_hash(contract), "workpack contract_hash does not match normalized TOML")
    require(non_empty_string(contract.get("objective")), "workpack objective is required")
    require(non_empty_string_list(contract.get("scope_classes")), "scope_classes must be a non-empty string list")
    validate_pattern_list(contract.get("allowed_paths"), "allowed_paths")
    validate_pattern_list(contract.get("locked_paths"), "locked_paths")
    access = contract.get("implementation_external_access")
    require(access in EXTERNAL_ACCESS, "implementation_external_access is invalid")
    require(non_empty_string_list(contract.get("required_tests")), "required_tests must be a non-empty string list")
    require(non_empty_string(contract.get("staging_smoke")), "staging_smoke is required")
    require(non_empty_string_list(contract.get("evidence_scope")), "workpack evidence_scope is required")
    require(non_empty_string_list(contract.get("not_proven")), "workpack not_proven is required")
    require(non_empty_string(contract.get("rollback")), "workpack rollback is required")

    scopes = set(contract["scope_classes"])
    if scopes & {"ui", "rendering"}:
        visible = contract.get("visible_contract")
        require(isinstance(visible, dict), "UI/rendering scope requires visible_contract")
        require(non_empty_string(visible.get("reference")), "visible_contract.reference is required")
        require(non_empty_string_list(visible.get("viewports")), "visible_contract.viewports is required")
        require(non_empty_string(visible.get("above_the_fold")), "visible_contract.above_the_fold is required")
        require(isinstance(visible.get("new_visible_elements"), bool), "visible_contract.new_visible_elements must be boolean")
        require(non_empty_string_list(visible.get("javascript_states")), "visible_contract.javascript_states is required")

    if access in CONTROLLED_WRITES:
        write = contract.get("external_write_contract")
        require(isinstance(write, dict), "controlled external write requires external_write_contract")
        for field in ("resource", "stable_identity", "before_state", "exact_mutation", "readback", "rollback"):
            require(non_empty_string(write.get(field)), f"external_write_contract.{field} is required")


def validate_pr_evidence(evidence: dict[str, Any], contract: dict[str, Any]) -> None:
    require(evidence.get("schema_version") == 1, "PR evidence schema_version must equal 1")
    require(evidence.get("workpack_issue") == contract["workpack_issue"], "PR workpack_issue does not match issue contract")
    require(evidence.get("contract_revision") == contract["contract_revision"], "PR contract_revision does not match issue contract")
    require(evidence.get("contract_hash") == contract["contract_hash"], "PR contract_hash does not match issue contract")
    require(non_empty_string_list(evidence.get("tests")), "PR tests must be a non-empty string list")
    require(non_empty_string_list(evidence.get("evidence_scope")), "PR evidence_scope must be a non-empty string list")
    require(non_empty_string_list(evidence.get("not_proven")), "PR not_proven must be a non-empty string list")
    require(non_empty_string(evidence.get("rollback")), "PR rollback is required")

    missing_tests = [test for test in contract["required_tests"] if test not in evidence["tests"]]
    require(not missing_tests, f"PR evidence is missing required tests: {', '.join(missing_tests)}")
    missing_boundaries = [item for item in contract["not_proven"] if item not in evidence["not_proven"]]
    require(not missing_boundaries, "PR evidence omits a required not_proven boundary")
    require(evidence["rollback"] == contract["rollback"], "PR rollback must match the frozen workpack rollback")


def path_matches(path: str, patterns: Iterable[str]) -> bool:
    return any(fnmatch.fnmatchcase(path, pattern) for pattern in patterns)


def validate_changed_paths(changed_paths: Iterable[str], contract: dict[str, Any]) -> list[str]:
    paths = list(dict.fromkeys(changed_paths))
    require(bool(paths), "PR diff contains no changed paths")
    allowed = contract["allowed_paths"]
    locked = contract["locked_paths"]
    errors: list[str] = []
    for path in paths:
        try:
            validate_relative_path(path, "changed_paths", allow_glob=False)
        except ContractError as exc:
            errors.append(str(exc))
            continue
        if path_matches(path, locked):
            errors.append(f"changed path is locked: {path}")
        elif not path_matches(path, allowed):
            errors.append(f"changed path is outside allowed scope: {path}")
    require(not errors, "\n".join(errors))
    return paths


def parse_name_status_z(raw: bytes) -> list[str]:
    fields = raw.split(b"\0")
    if fields and fields[-1] == b"":
        fields.pop()
    paths: list[str] = []
    index = 0
    while index < len(fields):
        status = fields[index].decode("ascii", errors="strict")
        index += 1
        require(bool(status), "git diff returned an empty status")
        if status[0] in {"R", "C"}:
            require(index + 1 < len(fields), f"git diff returned incomplete {status} record")
            paths.append(fields[index].decode("utf-8", errors="strict"))
            paths.append(fields[index + 1].decode("utf-8", errors="strict"))
            index += 2
        else:
            require(index < len(fields), f"git diff returned incomplete {status} record")
            paths.append(fields[index].decode("utf-8", errors="strict"))
            index += 1
    return list(dict.fromkeys(paths))


def git_changed_paths(base_sha: str, head_sha: str, root: str = ".") -> list[str]:
    require(GIT_SHA.fullmatch(base_sha) is not None, "base SHA must be a full lowercase Git SHA")
    require(GIT_SHA.fullmatch(head_sha) is not None, "head SHA must be a full lowercase Git SHA")
    result = subprocess.run(
        ["git", "diff", "--name-status", "-z", "--find-renames", f"{base_sha}...{head_sha}"],
        cwd=root,
        capture_output=True,
        check=False,
    )
    if result.returncode:
        raise ContractError(f"cannot determine PR diff: {result.stderr.decode('utf-8', errors='replace').strip()}")
    return parse_name_status_z(result.stdout)


def github_get_json(url: str, token: str) -> Any:
    require(non_empty_string(token), "GITHUB_TOKEN is required")
    request = urllib.request.Request(
        url,
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {token}",
            "User-Agent": "bocholt-erleben-pr-contract",
            "X-GitHub-Api-Version": "2022-11-28",
        },
    )
    try:
        with urllib.request.urlopen(request, timeout=20) as response:
            return json.load(response)
    except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError, json.JSONDecodeError) as exc:
        raise ContractError(f"GitHub API request failed: {exc}") from exc


def load_issue(api_url: str, repository: str, issue_number: int, token: str) -> dict[str, Any]:
    url = f"{api_url.rstrip('/')}/repos/{repository}/issues/{issue_number}"
    value = github_get_json(url, token)
    require(isinstance(value, dict), "GitHub issue response is invalid")
    return value


def load_active_issues(api_url: str, repository: str, token: str) -> list[dict[str, Any]]:
    active: list[dict[str, Any]] = []
    for page in range(1, 11):
        query = urllib.parse.urlencode({"state": "open", "per_page": 100, "page": page})
        url = f"{api_url.rstrip('/')}/repos/{repository}/issues?{query}"
        values = github_get_json(url, token)
        require(isinstance(values, list), "GitHub issues response is invalid")
        for value in values:
            if not isinstance(value, dict) or "pull_request" in value:
                continue
            if ACTIVE_MARKER in str(value.get("title", "")):
                active.append(value)
        if len(values) < 100:
            break
    else:
        raise ContractError("active issue scan exceeded ten pages")
    return active


def validate_pull_request(
    *,
    pr_body: str,
    repository: str,
    changed_paths: Iterable[str],
    issue_loader: Callable[[int], dict[str, Any]],
    active_issue_loader: Callable[[], list[dict[str, Any]]],
) -> tuple[dict[str, Any], dict[str, Any], list[str]]:
    require(non_empty_string(repository) and "/" in repository, "repository must be owner/name")
    evidence = extract_toml_block(pr_body, PR_START, PR_END, "PR evidence")
    issue_number = evidence.get("workpack_issue")
    require(isinstance(issue_number, int) and issue_number > 0, "PR workpack_issue must be a positive integer")

    active_issues = active_issue_loader()
    require(len(active_issues) == 1, f"repository must contain exactly one open {ACTIVE_MARKER} issue")
    active_number = active_issues[0].get("number")
    require(active_number == issue_number, "PR references a different issue than the unique active workpack")

    issue = issue_loader(issue_number)
    require(isinstance(issue, dict), "loaded issue is invalid")
    contract = extract_toml_block(str(issue.get("body", "")), WORKPACK_START, WORKPACK_END, "workpack contract")
    validate_issue_contract(
        contract,
        issue_number=issue_number,
        issue_state=str(issue.get("state", "")),
        issue_title=str(issue.get("title", "")),
    )
    validate_pr_evidence(evidence, contract)
    paths = validate_changed_paths(changed_paths, contract)
    return contract, evidence, paths


def load_event(path: str) -> dict[str, Any]:
    try:
        with open(path, "r", encoding="utf-8") as handle:
            value = json.load(handle)
    except (OSError, json.JSONDecodeError) as exc:
        raise ContractError(f"cannot read GitHub event payload: {exc}") from exc
    require(isinstance(value, dict), "GitHub event payload must be an object")
    return value


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--event-path", default=os.environ.get("GITHUB_EVENT_PATH", ""))
    parser.add_argument("--token", default=os.environ.get("GITHUB_TOKEN", ""))
    parser.add_argument("--api-url", default=os.environ.get("GITHUB_API_URL", "https://api.github.com"))
    parser.add_argument("--root", default=".")
    args = parser.parse_args()

    try:
        require(non_empty_string(args.event_path), "GITHUB_EVENT_PATH is required")
        event = load_event(args.event_path)
        pr = event.get("pull_request")
        repository_data = event.get("repository")
        require(isinstance(pr, dict), "event does not contain pull_request")
        require(isinstance(repository_data, dict), "event does not contain repository")
        repository = str(repository_data.get("full_name", ""))
        base_sha = str(pr.get("base", {}).get("sha", ""))
        head_sha = str(pr.get("head", {}).get("sha", ""))
        pr_body = str(pr.get("body") or "")
        changed = git_changed_paths(base_sha, head_sha, args.root)
        contract, _, paths = validate_pull_request(
            pr_body=pr_body,
            repository=repository,
            changed_paths=changed,
            issue_loader=lambda number: load_issue(args.api_url, repository, number, args.token),
            active_issue_loader=lambda: load_active_issues(args.api_url, repository, args.token),
        )
    except ContractError as exc:
        print(f"PR contract: FAIL\n- {str(exc).replace(chr(10), chr(10) + '- ')}", file=sys.stderr)
        return 1

    print(
        "PR contract: OK "
        f"(issue #{contract['workpack_issue']}, revision {contract['contract_revision']}, "
        f"hash {contract['contract_hash'][:12]}, {len(paths)} changed paths)"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
