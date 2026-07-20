#!/usr/bin/env python3
"""Server-mediated entrypoint for the one-shot synthetic E4 proof.

The validated proof flow remains in ``control_center_e4_synthetic_core``.
Only its database transport is replaced: GitHub-hosted runners use the
authenticated staging HTTPS contract and never connect to MySQL directly.
"""
from __future__ import annotations

from typing import Any, Iterable

import control_center_e4_synthetic_core as core


class ServerMediatedDatabase:
    def __init__(
        self,
        http: core.HttpClient | None = None,
        base_url: str | None = None,
        target_sha: str | None = None,
        run_key: str | None = None,
    ):
        self.http = http or core.HttpClient(core.require_env("REVIEW_PASSWORD"))
        self.base_url = (base_url or core.require_env("BASE_URL")).rstrip("/")
        self.target_sha = target_sha or core.validate_target_sha(
            core.require_env("E4_TARGET_SHA")
        )
        self.run_key = run_key or (
            f"{self.target_sha[:12]}-{core.require_env('GITHUB_RUN_ID')}"
        )
        self.endpoint = self.base_url + "/api/control-center/e4-synthetic.php"

    def _call(self, mode: str, **payload: Any) -> dict[str, Any]:
        response = self.http.json_request(
            self.endpoint,
            method="POST",
            body={
                "mode": mode,
                "target_sha": self.target_sha,
                "run_key": self.run_key,
                **payload,
            },
        )
        return core.assert_api_ok(
            response,
            f"E4 server runtime {mode}",
        )["data"]

    def preflight(self) -> dict[str, Any]:
        data = self._call("preflight")
        expected = self.target_sha[:12]
        checks = {
            "mutation_false": data.get("mutation") is False,
            "environment": data.get("environment") == "staging",
            "build": data.get("build") == expected,
            "schema": data.get("schema_present") is True,
            "global_absent": data.get("global_synthetic")
            == {"cases": 0, "operations": 0},
            "run_absent": data.get("run_synthetic")
            == {"cases": 0, "operations": 0},
        }
        failed = [name for name, passed in checks.items() if not passed]
        if failed:
            raise core.E4Error(
                f"Server-mediated database preflight failed: {failed}; {data}"
            )
        return {
            "connected": True,
            "cases_table": True,
            "operations_table": True,
            "synthetic_before": data["global_synthetic"],
            "transport": "authenticated_staging_https_to_private_pdo",
            "assertions": checks,
        }

    def seed_failed_operation(
        self,
        operation_id: str,
        case_id: str,
        note: str,
    ) -> None:
        data = self._call(
            "seed_failed_operation",
            operation_id=operation_id,
            case_id=case_id,
            decision_note=note,
        )
        if (
            data.get("mutation") is not True
            or data.get("operation_id") != operation_id
            or data.get("case_id") != case_id
            or data.get("status") != "failed"
        ):
            raise core.E4Error(
                f"Controlled failure seed was not confirmed: {data}"
            )

    def _states(
        self,
        case_ids: list[str],
        operation_ids: list[str],
    ) -> dict[str, Any]:
        data = self._call(
            "states",
            case_ids=case_ids,
            operation_ids=operation_ids,
        )
        if data.get("mutation") is not False:
            raise core.E4Error("E4 state lookup was not read-only.")
        states = data.get("states")
        if not isinstance(states, dict):
            raise core.E4Error("E4 state lookup returned no states.")
        return states

    def case_state(self, case_id: str) -> dict[str, Any] | None:
        value = self._states([case_id], []).get("cases", {}).get(case_id)
        return value if isinstance(value, dict) else None

    def operation_state(self, operation_id: str) -> dict[str, Any] | None:
        value = self._states([], [operation_id]).get("operations", {}).get(
            operation_id
        )
        return value if isinstance(value, dict) else None

    def cleanup(
        self,
        case_ids: Iterable[str],
        operation_ids: Iterable[str],
        run_key: str,
    ) -> dict[str, int]:
        del case_ids, operation_ids
        if run_key != self.run_key:
            raise core.E4Error("Cleanup escaped the active E4 run key.")
        data = self._call("cleanup")
        if (
            data.get("remaining") != {"cases": 0, "operations": 0}
            or data.get("global_remaining")
            != {"cases": 0, "operations": 0}
        ):
            raise core.E4Error(
                f"Server-mediated database cleanup was incomplete: {data}"
            )
        deleted = data.get("deleted", {})
        return {
            "cases": int(deleted.get("cases", 0)),
            "operations": int(deleted.get("operations", 0)),
        }

    def _counts(self) -> dict[str, Any]:
        data = self._call("counts")
        if data.get("mutation") is not False:
            raise core.E4Error("E4 count lookup was not read-only.")
        return data

    def assert_run_absent(self, run_key: str) -> dict[str, int]:
        if run_key != self.run_key:
            raise core.E4Error("Run absence check escaped the active run key.")
        counts = self._counts().get("run", {})
        return {
            "cases": int(counts.get("cases", -1)),
            "operations": int(counts.get("operations", -1)),
        }

    def synthetic_counts(self) -> dict[str, int]:
        counts = self._counts().get("global", {})
        return {
            "cases": int(counts.get("cases", -1)),
            "operations": int(counts.get("operations", -1)),
        }


class _FakeHttp:
    def __init__(self):
        self.calls: list[dict[str, Any]] = []

    def json_request(
        self,
        url: str,
        method: str = "GET",
        body: dict[str, Any] | None = None,
        timeout: int = 60,
    ) -> core.ApiResponse:
        del url, method, timeout
        request = dict(body or {})
        self.calls.append(request)
        mode = request.get("mode")
        if mode == "preflight":
            data = {
                "mode": mode,
                "mutation": False,
                "environment": "staging",
                "build": "a" * 12,
                "schema_present": True,
                "global_synthetic": {"cases": 0, "operations": 0},
                "run_synthetic": {"cases": 0, "operations": 0},
            }
        elif mode == "counts":
            data = {
                "mode": mode,
                "mutation": False,
                "run": {"cases": 0, "operations": 0},
                "global": {"cases": 0, "operations": 0},
            }
        elif mode == "states":
            data = {
                "mode": mode,
                "mutation": False,
                "states": {"cases": {}, "operations": {}},
            }
        elif mode == "cleanup":
            data = {
                "mode": mode,
                "mutation": True,
                "deleted": {"cases": 0, "operations": 0},
                "remaining": {"cases": 0, "operations": 0},
                "global_remaining": {"cases": 0, "operations": 0},
            }
        elif mode == "seed_failed_operation":
            data = {
                "mode": mode,
                "mutation": True,
                "case_id": request.get("case_id"),
                "operation_id": request.get("operation_id"),
                "status": "failed",
            }
        else:
            return core.ApiResponse(
                422,
                {"status": "error", "message": "unsupported"},
            )
        return core.ApiResponse(200, {"status": "ok", "data": data})


def adapter_self_test() -> None:
    target_sha = "a" * 40
    run_key = "aaaaaaaaaaaa-123456789"
    fake = _FakeHttp()
    adapter = ServerMediatedDatabase(
        fake,
        "https://staging.bocholt-erleben.de",
        target_sha,
        run_key,
    )
    assert adapter.preflight()["transport"] == (
        "authenticated_staging_https_to_private_pdo"
    )
    adapter.seed_failed_operation(
        f"e4:{run_key}:partial-failed",
        "123e4567-e89b-42d3-a456-426614174000",
        f"E4 synthetic partial failure {run_key}",
    )
    assert adapter.assert_run_absent(run_key) == {
        "cases": 0,
        "operations": 0,
    }
    assert adapter.synthetic_counts() == {
        "cases": 0,
        "operations": 0,
    }
    assert adapter.cleanup([], [], run_key) == {
        "cases": 0,
        "operations": 0,
    }
    assert all(
        call.get("target_sha") == target_sha
        and call.get("run_key") == run_key
        for call in fake.calls
    )
    print("Control Center server-mediated E4 adapter self-test: OK")


core.Database = ServerMediatedDatabase
_original_self_test = core.self_test


def combined_self_test() -> None:
    _original_self_test()
    adapter_self_test()


core.self_test = combined_self_test

if __name__ == "__main__":
    raise SystemExit(core.main())
