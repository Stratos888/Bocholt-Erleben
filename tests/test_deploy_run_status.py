#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import io
import json
import sys
import urllib.error
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SPEC = importlib.util.spec_from_file_location(
    "deploy_status", ROOT / "scripts/publish_deploy_run_status.py"
)
deploy_status = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = deploy_status
assert SPEC.loader is not None
SPEC.loader.exec_module(deploy_status)

SHA = "a" * 40
REPOSITORY = "Stratos888/Bocholt-Erleben"
RUN_ID = 30074842835
RUN_URL = f"https://github.com/{REPOSITORY}/actions/runs/{RUN_ID}"


class FakeResponse:
    status = 201

    def __enter__(self):
        return self

    def __exit__(self, *_args):
        return False

    def getcode(self):
        return self.status


class DeployRunStatusTests(unittest.TestCase):
    def build(self, **overrides):
        values = {
            "repository": REPOSITORY,
            "sha": SHA,
            "branch": "staging",
            "event": "push",
            "run_id": RUN_ID,
            "run_url": RUN_URL,
            "action": "in_progress",
            "conclusion": "",
        }
        values.update(overrides)
        return deploy_status.build_status(**values)

    def test_in_progress_maps_to_pending(self):
        status = self.build()
        self.assertEqual(status.state, "pending")
        self.assertEqual(status.context, "deploy-strato/staging/push")

    def test_completed_success_maps_to_success(self):
        self.assertEqual(
            self.build(action="completed", conclusion="success").state,
            "success",
        )

    def test_failed_conclusions_map_to_failure(self):
        for conclusion in sorted(deploy_status.FAILURE_CONCLUSIONS):
            with self.subTest(conclusion=conclusion):
                self.assertEqual(
                    self.build(action="completed", conclusion=conclusion).state,
                    "failure",
                )

    def test_cancel_like_conclusions_map_to_error(self):
        for conclusion in sorted(deploy_status.ERROR_CONCLUSIONS):
            with self.subTest(conclusion=conclusion):
                self.assertEqual(
                    self.build(action="completed", conclusion=conclusion).state,
                    "error",
                )

    def test_unsupported_action_fails(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "unsupported workflow action"):
            self.build(action="requested")

    def test_missing_completed_conclusion_fails(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "unsupported completed conclusion"):
            self.build(action="completed", conclusion="")

    def test_branch_and_event_are_separate_context_dimensions(self):
        status = self.build(branch="main", event="workflow_dispatch")
        self.assertEqual(status.context, "deploy-strato/main/workflow_dispatch")

    def test_unsupported_branch_fails(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "unsupported deploy branch"):
            self.build(branch="feature")

    def test_unsupported_event_fails(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "unsupported deploy event"):
            self.build(event="pull_request")

    def test_invalid_sha_fails(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "invalid head sha"):
            self.build(sha="1234")

    def test_mismatched_run_url_fails(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "run url does not match"):
            self.build(run_url="https://github.com/other/repo/actions/runs/1")

    def test_publish_posts_exact_status_payload(self):
        captured = {}

        def opener(request, timeout):
            captured["url"] = request.full_url
            captured["method"] = request.get_method()
            captured["timeout"] = timeout
            captured["headers"] = dict(request.header_items())
            captured["body"] = json.loads(request.data.decode("utf-8"))
            return FakeResponse()

        status = self.build(action="completed", conclusion="success")
        deploy_status.publish_status(
            status,
            token="test-token",
            api_url="https://api.github.com",
            opener=opener,
            sleeper=lambda _seconds: None,
        )
        self.assertEqual(
            captured["url"],
            f"https://api.github.com/repos/{REPOSITORY}/statuses/{SHA}",
        )
        self.assertEqual(captured["method"], "POST")
        self.assertEqual(captured["timeout"], 20)
        self.assertEqual(captured["headers"]["Authorization"], "Bearer test-token")
        self.assertEqual(
            captured["body"],
            {
                "state": "success",
                "target_url": RUN_URL,
                "description": f"Deploy completed successfully (run {RUN_ID})",
                "context": "deploy-strato/staging/push",
            },
        )

    def test_transient_http_failure_is_retried(self):
        calls = []
        sleeps = []

        def opener(_request, timeout):
            calls.append(timeout)
            if len(calls) < 3:
                raise urllib.error.HTTPError(
                    "https://api.github.com", 503, "unavailable", {}, io.BytesIO()
                )
            return FakeResponse()

        deploy_status.publish_status(
            self.build(),
            token="token",
            api_url="https://api.github.com",
            attempts=4,
            retry_base_seconds=2,
            opener=opener,
            sleeper=sleeps.append,
        )
        self.assertEqual(len(calls), 3)
        self.assertEqual(sleeps, [2, 4])

    def test_non_retryable_http_failure_stops_immediately(self):
        calls = []

        def opener(_request, timeout):
            calls.append(timeout)
            raise urllib.error.HTTPError(
                "https://api.github.com", 403, "forbidden", {}, io.BytesIO()
            )

        with self.assertRaisesRegex(deploy_status.DeployStatusError, "HTTP 403"):
            deploy_status.publish_status(
                self.build(),
                token="token",
                api_url="https://api.github.com",
                opener=opener,
                sleeper=lambda _seconds: None,
            )
        self.assertEqual(len(calls), 1)

    def test_missing_token_fails_before_network(self):
        with self.assertRaisesRegex(deploy_status.DeployStatusError, "status token is required"):
            deploy_status.publish_status(
                self.build(),
                token="",
                api_url="https://api.github.com",
                opener=lambda *_args, **_kwargs: self.fail("network must not be called"),
            )

    def test_environment_loader_keeps_token_out_of_status_payload(self):
        env = {
            "DEPLOY_STATUS_TOKEN": "secret",
            "DEPLOY_RUN_REPOSITORY": REPOSITORY,
            "DEPLOY_RUN_SHA": SHA,
            "DEPLOY_RUN_BRANCH": "staging",
            "DEPLOY_RUN_EVENT": "push",
            "DEPLOY_RUN_ID": str(RUN_ID),
            "DEPLOY_RUN_URL": RUN_URL,
            "DEPLOY_RUN_ACTION": "in_progress",
            "DEPLOY_RUN_CONCLUSION": "",
            "GITHUB_API_URL": "https://api.github.com",
        }
        status, options = deploy_status.load_from_environment(env)
        self.assertNotIn("secret", json.dumps(status.request_body()))
        self.assertEqual(options["token"], "secret")

    def test_observer_workflow_has_minimal_permissions_and_exact_trigger(self):
        text = (ROOT / ".github/workflows/deploy-run-status.yml").read_text(encoding="utf-8")
        required = [
            'workflows: ["Deploy to STRATO"]',
            "types: [in_progress, completed]",
            "contents: read",
            "statuses: write",
            "github.event.workflow_run.repository.full_name == github.repository",
            "github.event.workflow_run.head_branch == 'main'",
            "github.event.workflow_run.head_branch == 'staging'",
            "python3 scripts/publish_deploy_run_status.py",
        ]
        for marker in required:
            with self.subTest(marker=marker):
                self.assertIn(marker, text)
        self.assertNotIn("pull_request_target", text)
        self.assertNotIn("contents: write", text)
        self.assertNotIn("issues: write", text)

    def test_observer_uses_run_payload_not_default_branch_sha(self):
        text = (ROOT / ".github/workflows/deploy-run-status.yml").read_text(encoding="utf-8")
        self.assertIn("${{ github.event.workflow_run.head_sha }}", text)
        self.assertIn("${{ github.event.workflow_run.html_url }}", text)
        self.assertNotIn("${{ github.sha }}", text)

    def test_repository_validation_runs_status_tests_once(self):
        text = (ROOT / "scripts/validate-repo.sh").read_text(encoding="utf-8")
        self.assertEqual(text.count("python3 tests/test_deploy_run_status.py"), 1)

    def test_existing_deploy_workflow_remains_named_for_observer(self):
        text = (ROOT / ".github/workflows/deploy-strato.yml").read_text(encoding="utf-8")
        self.assertIn("name: Deploy to STRATO", text)


if __name__ == "__main__":
    unittest.main()
