#!/usr/bin/env python3
"""Validate an adaptive PR workflow for normal changes, workpacks and releases."""

from __future__ import annotations

import argparse
import fnmatch
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
ACTIVE_MARKER = "[ACTIVE WORKPACK]"
WORKPACK_REFERENCE = re.compile(r"(?mi)^[ \t]*Workpack:[ \t]*#([1-9][0-9]*)[ \t]*$")
GIT_SHA = re.compile(r"^[0-9a-f]{40,64}$")
BRANCH = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._/-]*$")
CONTROLLED_WRITES = {"controlled-staging-write", "controlled-live-admin"}
EXTERNAL_ACCESS = {"none", "read-only", *CONTROLLED_WRITES}

WORKPACK_REQUIRED_PATTERNS = (
    ".github/workflows/**",
    "AI_ENTRYPOINT.md",
    "AGENTS.md",
    "ENGINEERING.md",
    "docs/workpacks/active/CURRENT_WORKPACK.md",
    "scripts/validate_pr_contract.py",
    "tests/test_pr_contract.py",
    "api/sql/**",
    "api/stripe/**",
    "api/webhooks/**",
    "api/organizer-portal/request-magic-link.php",
    "api/organizer-portal/consume-magic-link.php",
    "api/organizer-portal/create-billing-portal-session.php",
    "api/submissions/release-payment.php",
)

DOC_PATTERNS = ("*.md", "*.txt", "docs/**")
FRONTEND_PATTERNS = (
    "*.html",
    "*.css",
    "*.js",
    "*.mjs",
    "js/**",
    "css/**",
    "icons/**",
    "assets/**",
    "tests/*.mjs",
    "tests/**/*.mjs",
)
BACKEND_PATTERNS = (
    "*.php",
    "api/**/*.php",
    "tests/*.php",
    "tests/**/*.php",
    "tests/run_*mysql*.sh",
)
QUICK_PATTERNS = (
    "scripts/**",
    "tools/**",
    "tests/*.py",
    "tests/**/*.py",
    "tests/*.sh",
    "tests/**/*.sh",
    "*.json",
)


class ContractError(ValueError):
    """Raised when a PR violates the adaptive workflow."""


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


def optional_workpack_reference(text: str) -> int | None:
    matches = WORKPACK_REFERENCE.findall(text or "")
    require(len(matches) <= 1, "PR body may contain at most one line: Workpack: #<issue>")
    return int(matches[0]) if matches else None


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


def validate_branch(value: Any) -> str:
    require(non_empty_string(value), "workpack branch is required")
    branch = value.strip()
    require(BRANCH.fullmatch(branch) is not None, "workpack branch contains invalid characters")
    require(branch not in {"staging", "main"}, "workpack branch must be a feature branch")
    require(".." not in branch and "//" not in branch, "workpack branch is not normalized")
    return branch


def validate_issue_contract(
    contract: dict[str, Any], *, issue_number: int, issue_state: str, issue_title: str
) -> None:
    require(contract.get("schema_version") == 2, "workpack schema_version must equal 2")
    require(issue_state == "open", "referenced workpack issue must be open")
    require(ACTIVE_MARKER in issue_title, "referenced issue is missing the active-workpack marker")
    require(contract.get("workpack_issue") == issue_number, "workpack issue number does not match the loaded issue")
    validate_branch(contract.get("branch"))
    require(non_empty_string(contract.get("objective")), "workpack objective is required")
    validate_pattern_list(contract.get("allowed_paths"), "allowed_paths")
    validate_pattern_list(contract.get("locked_paths"), "locked_paths")
    access = contract.get("external_access")
    require(access in EXTERNAL_ACCESS, "external_access is invalid")
    require(non_empty_string_list(contract.get("required_tests")), "required_tests must be a non-empty string list")
    require(non_empty_string_list(contract.get("done")), "done must be a non-empty string list")
    require(non_empty_string_list(contract.get("forbidden_effects")), "forbidden_effects must be a non-empty string list")
    require(non_empty_string(contract.get("staging_smoke")), "staging_smoke is required")
    if access in CONTROLLED_WRITES:
        write = contract.get("external_write")
        require(isinstance(write, dict), "controlled external write requires external_write")
        for field in ("resource", "identity", "before", "mutation", "readback", "cleanup"):
            require(non_empty_string(write.get(field)), f"external_write.{field} is required")


def path_matches(path: str, patterns: Iterable[str]) -> bool:
    return any(fnmatch.fnmatchcase(path, pattern) for pattern in patterns)


def validate_changed_paths(changed_paths: Iterable[str], contract: dict[str, Any] | None = None) -> list[str]:
    paths = list(dict.fromkeys(changed_paths))
    require(bool(paths), "PR diff contains no changed paths")
    errors: list[str] = []
    for path in paths:
        try:
            validate_relative_path(path, "changed_paths", allow_glob=False)
        except ContractError as exc:
            errors.append(str(exc))
            continue
        if contract is not None:
            if path_matches(path, contract["locked_paths"]):
                errors.append(f"changed path is locked: {path}")
            elif not path_matches(path, contract["allowed_paths"]):
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


def classify_changed_paths(paths: Iterable[str], *, workpack: bool) -> str:
    values = list(paths)
    if workpack:
        return "full"

    categories: set[str] = set()
    for path in values:
        if path_matches(path, DOC_PATTERNS):
            categories.add("docs")
        elif path_matches(path, FRONTEND_PATTERNS):
            categories.add("frontend")
        elif path_matches(path, BACKEND_PATTERNS):
            categories.add("backend")
        elif path_matches(path, QUICK_PATTERNS):
            categories.add("quick")
        else:
            categories.add("full")

    categories.discard("docs")
    if not categories:
        return "docs"
    if "full" in categories or {"backend", "frontend"} <= categories:
        return "full"
    if categories == {"quick"}:
        return "quick"
    if categories <= {"frontend", "quick"}:
        return "frontend"
    if categories <= {"backend", "quick"}:
        return "backend"
    return "full"


def select_test_targets(paths: Iterable[str], *, plan: str) -> tuple[str, bool, bool]:
    values = list(paths)
    if plan == "full":
        return "all", True, True

    backend_components: set[str] = set()
    for path in values:
        if path_matches(path, ("api/startpartner/**", "api/organizer-portal/**", "tests/startpartner_*", "tests/run_startpartner_*")):
            backend_components.add("startpartner")
        elif path_matches(path, ("api/control-center/**", "tests/control_center*", "js/control-center/**", "steuerzentrale/**")):
            backend_components.add("control-center")
        elif path_matches(path, ("api/submissions/**", "tests/submission_*", "events-veroeffentlichen/**", "js/publish-funnel.js")):
            backend_components.add("submissions")
        elif path_matches(path, BACKEND_PATTERNS):
            backend_components.add("all")

    if "all" in backend_components or not backend_components:
        backend = "all"
    else:
        backend = ",".join(sorted(backend_components))

    event_browser = plan == "frontend" and any(
        path_matches(path, ("index.html", "events/**", "heute/**", "aktivitaeten/**", "js/app.js", "js/events*.js", "css/style.css"))
        for path in values
    )
    control_browser = plan == "frontend" and any(
        path_matches(path, ("steuerzentrale/**", "js/control-center.js", "js/control-center/**", "css/style.css"))
        for path in values
    )
    if plan == "frontend" and not event_browser and not control_browser:
        event_browser = True
        control_browser = True
    return backend, event_browser, control_browser


def validate_parallel_prs(
    *,
    pr_number: int,
    paths: list[str],
    workpack_issue: int | None,
    open_prs: Iterable[dict[str, Any]],
) -> None:
    current = set(paths)
    for value in open_prs:
        if not isinstance(value, dict):
            continue
        other_number = value.get("number")
        if other_number == pr_number:
            continue
        if not isinstance(other_number, int):
            continue

        body = str(value.get("body") or "")
        other_refs = [int(item) for item in WORKPACK_REFERENCE.findall(body)]
        if workpack_issue is not None and workpack_issue in other_refs:
            raise ContractError(f"workpack #{workpack_issue} already has open PR #{other_number}")

        other_paths = {
            item
            for item in value.get("changed_paths", [])
            if isinstance(item, str) and item
        }
        overlap = sorted(current & other_paths)
        if overlap:
            raise ContractError(
                f"changed paths overlap open PR #{other_number}: {', '.join(overlap)}"
            )


def validate_pull_request(
    *,
    pr_number: int,
    pr_body: str,
    repository: str,
    base_ref: str,
    head_ref: str,
    changed_paths: Iterable[str],
    issue_loader: Callable[[int], dict[str, Any]],
    open_feature_pr_loader: Callable[[], list[dict[str, Any]]],
) -> tuple[dict[str, Any] | None, list[str], str, str]:
    require(non_empty_string(repository) and "/" in repository, "repository must be owner/name")
    paths = validate_changed_paths(changed_paths)

    if base_ref == "main":
        require(head_ref == "staging", "release PR must use staging -> main")
        return None, paths, "release", "full"

    require(base_ref == "staging", "feature PR must target staging")
    require(validate_branch(head_ref) == head_ref, "feature PR head branch is invalid")

    workpack_issue = optional_workpack_reference(pr_body)
    contract: dict[str, Any] | None = None

    if workpack_issue is not None:
        issue = issue_loader(workpack_issue)
        require(isinstance(issue, dict), "loaded issue is invalid")
        contract = extract_toml_block(str(issue.get("body", "")), WORKPACK_START, WORKPACK_END, "workpack contract")
        validate_issue_contract(
            contract,
            issue_number=workpack_issue,
            issue_state=str(issue.get("state", "")),
            issue_title=str(issue.get("title", "")),
        )
        require(head_ref == contract["branch"], f"PR head branch must equal workpack branch: {contract['branch']}")
        paths = validate_changed_paths(paths, contract)
    else:
        risky = [path for path in paths if path_matches(path, WORKPACK_REQUIRED_PATTERNS)]
        require(
            not risky,
            "workpack is required for high-risk paths: " + ", ".join(sorted(risky)),
        )

    open_prs = open_feature_pr_loader()
    validate_parallel_prs(
        pr_number=pr_number,
        paths=paths,
        workpack_issue=workpack_issue,
        open_prs=open_prs,
    )

    plan = classify_changed_paths(paths, workpack=contract is not None)
    mode = "workpack" if contract is not None else "normal"
    return contract, paths, mode, plan


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
    value = github_get_json(f"{api_url.rstrip('/')}/repos/{repository}/issues/{issue_number}", token)
    require(isinstance(value, dict), "GitHub issue response is invalid")
    return value


def load_pull_paths(api_url: str, repository: str, pr_number: int, token: str) -> list[str]:
    paths: list[str] = []
    for page in range(1, 11):
        query = urllib.parse.urlencode({"per_page": 100, "page": page})
        values = github_get_json(
            f"{api_url.rstrip('/')}/repos/{repository}/pulls/{pr_number}/files?{query}",
            token,
        )
        require(isinstance(values, list), "GitHub pull request files response is invalid")
        for value in values:
            if not isinstance(value, dict):
                continue
            filename = value.get("filename")
            previous = value.get("previous_filename")
            if isinstance(filename, str):
                paths.append(filename)
            if isinstance(previous, str):
                paths.append(previous)
        if len(values) < 100:
            break
    else:
        raise ContractError(f"pull request #{pr_number} file scan exceeded ten pages")
    return list(dict.fromkeys(paths))


def load_open_feature_prs(api_url: str, repository: str, token: str) -> list[dict[str, Any]]:
    pulls: list[dict[str, Any]] = []
    for page in range(1, 11):
        query = urllib.parse.urlencode({"state": "open", "base": "staging", "per_page": 100, "page": page})
        values = github_get_json(f"{api_url.rstrip('/')}/repos/{repository}/pulls?{query}", token)
        require(isinstance(values, list), "GitHub pull request response is invalid")
        for value in values:
            if not isinstance(value, dict):
                continue
            number = value.get("number")
            if not isinstance(number, int):
                continue
            pulls.append(
                {
                    "number": number,
                    "body": value.get("body") or "",
                    "changed_paths": load_pull_paths(api_url, repository, number, token),
                }
            )
        if len(values) < 100:
            break
    else:
        raise ContractError("open feature PR scan exceeded ten pages")
    return pulls


def load_event(path: str) -> dict[str, Any]:
    try:
        with open(path, "r", encoding="utf-8") as handle:
            value = json.load(handle)
    except (OSError, json.JSONDecodeError) as exc:
        raise ContractError(f"cannot read GitHub event payload: {exc}") from exc
    require(isinstance(value, dict), "GitHub event payload must be an object")
    return value


def write_github_output(
    path: str, *, mode: str, plan: str, workpack: int | None, changed_paths: Iterable[str]
) -> None:
    if not path:
        return
    backend, event_browser, control_browser = select_test_targets(changed_paths, plan=plan)
    with open(path, "a", encoding="utf-8") as handle:
        handle.write(f"mode={mode}\n")
        handle.write(f"plan={plan}\n")
        handle.write(f"backend_components={backend}\n")
        handle.write(f"browser_event={'true' if event_browser else 'false'}\n")
        handle.write(f"browser_control={'true' if control_browser else 'false'}\n")
        handle.write(f"browser={'true' if event_browser or control_browser else 'false'}\n")
        handle.write(f"workpack={workpack or ''}\n")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--event-path", default=os.environ.get("GITHUB_EVENT_PATH", ""))
    parser.add_argument("--token", default=os.environ.get("GITHUB_TOKEN", ""))
    parser.add_argument("--api-url", default=os.environ.get("GITHUB_API_URL", "https://api.github.com"))
    parser.add_argument("--root", default=".")
    parser.add_argument("--github-output", default=os.environ.get("GITHUB_OUTPUT", ""))
    args = parser.parse_args()

    try:
        require(non_empty_string(args.event_path), "GITHUB_EVENT_PATH is required")
        event = load_event(args.event_path)
        pr = event.get("pull_request")
        repository_data = event.get("repository")
        require(isinstance(pr, dict), "event does not contain pull_request")
        require(isinstance(repository_data, dict), "event does not contain repository")
        repository = str(repository_data.get("full_name", ""))
        pr_number = int(pr.get("number") or event.get("number") or 0)
        base_ref = str(pr.get("base", {}).get("ref", ""))
        head_ref = str(pr.get("head", {}).get("ref", ""))
        base_sha = str(pr.get("base", {}).get("sha", ""))
        head_sha = str(pr.get("head", {}).get("sha", ""))
        head_repository = str(pr.get("head", {}).get("repo", {}).get("full_name", ""))
        require(head_repository == repository, "feature and release PRs must originate from the canonical repository")
        pr_body = str(pr.get("body") or "")
        changed = git_changed_paths(base_sha, head_sha, args.root)
        contract, paths, mode, plan = validate_pull_request(
            pr_number=pr_number,
            pr_body=pr_body,
            repository=repository,
            base_ref=base_ref,
            head_ref=head_ref,
            changed_paths=changed,
            issue_loader=lambda number: load_issue(args.api_url, repository, number, args.token),
            open_feature_pr_loader=lambda: load_open_feature_prs(args.api_url, repository, args.token),
        )
    except (ContractError, ValueError, TypeError) as exc:
        print(f"PR contract: FAIL\n- {str(exc).replace(chr(10), chr(10) + '- ')}", file=sys.stderr)
        return 1

    workpack_issue = int(contract["workpack_issue"]) if contract is not None else None
    write_github_output(
        args.github_output,
        mode=mode,
        plan=plan,
        workpack=workpack_issue,
        changed_paths=paths,
    )
    print(
        f"PR contract: OK ({mode}, plan {plan}, {len(paths)} changed paths"
        + (f", workpack #{workpack_issue}" if workpack_issue else "")
        + ")"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
