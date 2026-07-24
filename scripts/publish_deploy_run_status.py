#!/usr/bin/env python3
"""Publish a stable commit-status locator for one STRATO deploy workflow run."""

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from dataclasses import dataclass
from typing import Callable

RETRYABLE_HTTP = {408, 429, 500, 502, 503, 504}
FAILURE_CONCLUSIONS = {"failure", "timed_out", "startup_failure", "action_required"}
ERROR_CONCLUSIONS = {"cancelled", "neutral", "skipped", "stale"}
ALLOWED_BRANCHES = {"main", "staging"}
ALLOWED_EVENTS = {"push", "schedule", "workflow_dispatch"}
SHA_RE = re.compile(r"^[0-9a-f]{40}$")
REPOSITORY_RE = re.compile(r"^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$")


class DeployStatusError(RuntimeError):
    """Raised when the deploy status cannot be safely derived or published."""


@dataclass(frozen=True)
class DeployRunStatus:
    repository: str
    sha: str
    branch: str
    event: str
    run_id: int
    run_url: str
    state: str
    description: str

    @property
    def context(self) -> str:
        return f"deploy-strato/{self.branch}/{self.event}"

    def request_body(self) -> dict[str, str]:
        return {
            "state": self.state,
            "target_url": self.run_url,
            "description": self.description,
            "context": self.context,
        }


def require_non_empty(name: str, value: str) -> str:
    value = value.strip()
    if not value:
        raise DeployStatusError(f"{name} is required")
    return value


def map_state(action: str, conclusion: str) -> tuple[str, str]:
    action = require_non_empty("workflow action", action)
    conclusion = conclusion.strip()

    if action == "in_progress":
        return "pending", "Deploy is in progress"
    if action != "completed":
        raise DeployStatusError(f"unsupported workflow action: {action}")

    if conclusion == "success":
        return "success", "Deploy completed successfully"
    if conclusion in FAILURE_CONCLUSIONS:
        return "failure", f"Deploy completed with {conclusion}"
    if conclusion in ERROR_CONCLUSIONS:
        return "error", f"Deploy ended with {conclusion}"
    raise DeployStatusError(f"unsupported completed conclusion: {conclusion or 'missing'}")


def build_status(
    *,
    repository: str,
    sha: str,
    branch: str,
    event: str,
    run_id: str | int,
    run_url: str,
    action: str,
    conclusion: str,
) -> DeployRunStatus:
    repository = require_non_empty("repository", repository)
    sha = require_non_empty("head sha", sha).lower()
    branch = require_non_empty("head branch", branch)
    event = require_non_empty("workflow event", event)
    run_url = require_non_empty("run url", run_url)

    if not REPOSITORY_RE.fullmatch(repository):
        raise DeployStatusError(f"invalid repository: {repository}")
    if not SHA_RE.fullmatch(sha):
        raise DeployStatusError(f"invalid head sha: {sha}")
    if branch not in ALLOWED_BRANCHES:
        raise DeployStatusError(f"unsupported deploy branch: {branch}")
    if event not in ALLOWED_EVENTS:
        raise DeployStatusError(f"unsupported deploy event: {event}")
    try:
        parsed_run_id = int(run_id)
    except (TypeError, ValueError) as exc:
        raise DeployStatusError(f"invalid workflow run id: {run_id}") from exc
    if parsed_run_id <= 0:
        raise DeployStatusError(f"invalid workflow run id: {run_id}")

    expected_suffix = f"/{repository}/actions/runs/{parsed_run_id}"
    if not run_url.startswith("https://") or not run_url.endswith(expected_suffix):
        raise DeployStatusError("run url does not match repository and run id")

    state, description = map_state(action, conclusion)
    return DeployRunStatus(
        repository=repository,
        sha=sha,
        branch=branch,
        event=event,
        run_id=parsed_run_id,
        run_url=run_url,
        state=state,
        description=f"{description} (run {parsed_run_id})"[:140],
    )


def publish_status(
    status: DeployRunStatus,
    *,
    token: str,
    api_url: str,
    attempts: int = 4,
    retry_base_seconds: float = 1.0,
    opener: Callable[..., object] = urllib.request.urlopen,
    sleeper: Callable[[float], None] = time.sleep,
) -> None:
    token = require_non_empty("status token", token)
    api_url = require_non_empty("GitHub API URL", api_url).rstrip("/")
    if not api_url.startswith("https://"):
        raise DeployStatusError("GitHub API URL must use https")
    if attempts < 1:
        raise DeployStatusError("attempts must be at least 1")
    if retry_base_seconds < 0:
        raise DeployStatusError("retry base seconds must be non-negative")

    endpoint = f"{api_url}/repos/{status.repository}/statuses/{status.sha}"
    body = json.dumps(status.request_body(), ensure_ascii=False).encode("utf-8")
    headers = {
        "Accept": "application/vnd.github+json",
        "Authorization": f"Bearer {token}",
        "Content-Type": "application/json",
        "User-Agent": "bocholt-erleben-deploy-run-status",
        "X-GitHub-Api-Version": "2022-11-28",
    }

    last_error: BaseException | None = None
    for attempt in range(1, attempts + 1):
        request = urllib.request.Request(endpoint, data=body, headers=headers, method="POST")
        try:
            with opener(request, timeout=20) as response:
                code = int(getattr(response, "status", response.getcode()))
                if code != 201:
                    raise DeployStatusError(f"unexpected GitHub status response: HTTP {code}")
            print(
                "Deploy run status published: "
                f"context={status.context} state={status.state} run={status.run_id}"
            )
            return
        except urllib.error.HTTPError as exc:
            last_error = exc
            retryable = exc.code in RETRYABLE_HTTP
            if not retryable or attempt >= attempts:
                break
        except (urllib.error.URLError, TimeoutError, OSError) as exc:
            last_error = exc
            if attempt >= attempts:
                break

        delay = retry_base_seconds * attempt
        print(
            f"Deploy run status attempt {attempt}/{attempts} failed; retrying in {delay:g}s",
            file=sys.stderr,
        )
        sleeper(delay)

    if isinstance(last_error, urllib.error.HTTPError):
        raise DeployStatusError(
            f"cannot publish deploy run status: HTTP {last_error.code}"
        ) from last_error
    raise DeployStatusError(f"cannot publish deploy run status: {last_error}") from last_error


def load_from_environment(environment: dict[str, str] | None = None) -> tuple[DeployRunStatus, dict[str, object]]:
    env = os.environ if environment is None else environment
    status = build_status(
        repository=env.get("DEPLOY_RUN_REPOSITORY", ""),
        sha=env.get("DEPLOY_RUN_SHA", ""),
        branch=env.get("DEPLOY_RUN_BRANCH", ""),
        event=env.get("DEPLOY_RUN_EVENT", ""),
        run_id=env.get("DEPLOY_RUN_ID", ""),
        run_url=env.get("DEPLOY_RUN_URL", ""),
        action=env.get("DEPLOY_RUN_ACTION", ""),
        conclusion=env.get("DEPLOY_RUN_CONCLUSION", ""),
    )
    try:
        attempts = int(env.get("DEPLOY_STATUS_ATTEMPTS", "4"))
        retry_base_seconds = float(env.get("DEPLOY_STATUS_RETRY_BASE_SECONDS", "1"))
    except ValueError as exc:
        raise DeployStatusError("invalid deploy status retry configuration") from exc

    options: dict[str, object] = {
        "token": env.get("DEPLOY_STATUS_TOKEN", ""),
        "api_url": env.get("GITHUB_API_URL", "https://api.github.com"),
        "attempts": attempts,
        "retry_base_seconds": retry_base_seconds,
    }
    return status, options


def main() -> int:
    try:
        status, options = load_from_environment()
        publish_status(status, **options)
    except DeployStatusError as exc:
        print(f"Deploy run status: FAIL: {exc}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
