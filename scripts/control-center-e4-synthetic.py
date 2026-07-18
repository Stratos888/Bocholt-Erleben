#!/usr/bin/env python3
"""One-shot E4 staging proof for the Control Center Inbox -> Events writeback.

The script mutates only synthetic rows in Inbox_Staging / Events_Staging and
synthetic records in the staging control-center database. It always attempts
cleanup and records a machine-readable evidence artifact.
"""

from __future__ import annotations

import argparse
import datetime as dt
import hashlib
import json
import os
import time
import urllib.error
import urllib.parse
import urllib.request
from dataclasses import dataclass
from pathlib import Path
from typing import Any, Iterable

E4_PREFIX = "be-e4-synthetic"
INBOX_TAB = "Inbox_Staging"
EVENTS_TAB = "Events_Staging"
LIVE_INBOX_TAB = "Inbox"
LIVE_EVENTS_TAB = "Events"
EVENT_COLUMNS = [
    "id", "title", "date", "endDate", "time", "city", "location",
    "kategorie", "url", "description", "visual_key",
]
REQUIRED_INBOX_HEADERS = {
    "status", "id_suggestion", "title", "date", "time", "city", "location",
    "kategorie_suggestion", "url", "description", "source_name", "source_url",
    "created_at", "visual_key", "visual_motif", "visual_asset_id",
    "visual_asset_role", "time_status", "time_details",
}


class E4Error(RuntimeError):
    pass


def utc_now() -> str:
    return dt.datetime.now(dt.timezone.utc).replace(microsecond=0).isoformat().replace("+00:00", "Z")


def canonical_hash(value: Any) -> str:
    raw = json.dumps(value, ensure_ascii=False, sort_keys=True, separators=(",", ":"))
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


def payload_hash(payload: dict[str, Any]) -> str:
    return canonical_hash(payload)


def column_letter(index: int) -> str:
    if index < 0:
        raise ValueError("negative column")
    letters = ""
    index += 1
    while index:
        index, mod = divmod(index - 1, 26)
        letters = chr(65 + mod) + letters
    return letters


def normalize_rows(values: list[list[Any]]) -> list[list[str]]:
    return [[str(cell or "").strip() for cell in row] for row in values]


def header_index(values: list[list[Any]]) -> tuple[list[str], dict[str, int]]:
    if not values:
        raise E4Error("Sheet tab has no header.")
    header = [str(cell or "").strip() for cell in values[0]]
    index = {name: pos for pos, name in enumerate(header) if name}
    if len(index) != len([name for name in header if name]):
        raise E4Error("Sheet tab contains duplicate headers.")
    return header, index


def row_dict(header: list[str], row: list[Any], row_number: int) -> dict[str, str]:
    result = {name: str(row[pos] if pos < len(row) else "").strip() for pos, name in enumerate(header) if name}
    result["row_number"] = str(row_number)
    return result


def find_exact_rows(values: list[list[Any]], field: str, wanted: str) -> list[dict[str, str]]:
    header, index = header_index(values)
    if field not in index:
        raise E4Error(f"Header missing: {field}")
    result = []
    for offset, raw in enumerate(values[1:], start=2):
        candidate = str(raw[index[field]] if index[field] < len(raw) else "").strip()
        if candidate == wanted:
            result.append(row_dict(header, raw, offset))
    return result


def filter_test_rows(values: list[list[Any]], run_key: str) -> list[list[str]]:
    if not values:
        return []
    normalized = normalize_rows(values)
    return [normalized[0]] + [row for row in normalized[1:] if run_key not in "\n".join(row)]


def candidate_payload(event_id: str, title: str, date: str, run_key: str) -> dict[str, str]:
    source_url = f"https://staging.bocholt-erleben.de/e4/{event_id}"
    return {
        "status": "review",
        "ablehnungsgrund": "",
        "id_suggestion": event_id,
        "title": title,
        "date": date,
        "endDate": "",
        "time": "19:00",
        "city": "Bocholt",
        "location": "E4-Synthetischer Testort",
        "kategorie_suggestion": "Kultur & Kunst",
        "url": source_url,
        "description": (
            "Synthetischer E4-Testdatensatz zur technischen Prüfung des isolierten "
            "Staging-Writebacks. Kein reales Event und nicht zur Veröffentlichung bestimmt."
        ),
        "source_name": "Bocholt Erleben E4",
        "source_url": source_url,
        "match_score": "",
        "matched_event_id": "",
        "notes": f"{E4_PREFIX} {run_key}; automatic cleanup required",
        "created_at": utc_now().replace("T", " ").replace("Z", ""),
        "visual_key": "art_exhibition_gallery",
        "visual_motif": "art_market",
        "visual_asset_id": "motif-gap-art-market-01",
        "visual_asset_role": "specific",
        "time_status": "fixed_time",
        "time_details": "19:00–20:00 Uhr",
    }


def event_from_payload(payload: dict[str, str]) -> dict[str, str]:
    return {
        "id": payload["id_suggestion"],
        "title": payload["title"],
        "date": payload["date"],
        "endDate": payload.get("endDate", ""),
        "time": payload["time_details"] if payload.get("time_details") else payload["time"],
        "city": payload["city"],
        "location": payload["location"],
        "kategorie": payload["kategorie_suggestion"],
        "url": payload["source_url"],
        "description": payload["description"],
        "visual_key": payload["visual_key"],
    }


def operation_payload(operation_id: str, note: str) -> dict[str, Any]:
    return {
        "decision_class": "accepted",
        "decision_note": note,
        "suppress_until": "",
        "recheck_at": "",
        "reopen_policy": "",
        "source_fingerprint": "",
        "content_fingerprint": "",
        "event_updates": [],
        "operation_id": operation_id,
        "legacy_operation_id": False,
    }


@dataclass
class ApiResponse:
    status: int
    payload: dict[str, Any]


class HttpClient:
    def __init__(self, review_password: str):
        self.review_password = review_password

    def json_request(self, url: str, method: str = "GET", body: dict[str, Any] | None = None, timeout: int = 60) -> ApiResponse:
        data = None
        headers = {
            "Accept": "application/json",
            "X-BE-Review-Password": self.review_password,
            "User-Agent": "Bocholt-Erleben-E4/1.0",
        }
        if body is not None:
            data = json.dumps(body, ensure_ascii=False).encode("utf-8")
            headers["Content-Type"] = "application/json; charset=utf-8"
        request = urllib.request.Request(url, data=data, headers=headers, method=method)
        try:
            with urllib.request.urlopen(request, timeout=timeout) as response:
                raw = response.read().decode("utf-8")
                return ApiResponse(response.status, json.loads(raw))
        except urllib.error.HTTPError as error:
            raw = error.read().decode("utf-8", errors="replace")
            try:
                payload = json.loads(raw)
            except json.JSONDecodeError:
                payload = {"status": "error", "message": raw[:500]}
            return ApiResponse(error.code, payload)

    @staticmethod
    def public_json(url: str, timeout: int = 60) -> Any:
        request = urllib.request.Request(url, headers={"Accept": "application/json", "User-Agent": "Bocholt-Erleben-E4/1.0"})
        with urllib.request.urlopen(request, timeout=timeout) as response:
            return json.loads(response.read().decode("utf-8"))


class SheetClient:
    def __init__(self, spreadsheet_id: str, service_account_json: str):
        from google.oauth2 import service_account
        from googleapiclient.discovery import build
        info = json.loads(service_account_json)
        credentials = service_account.Credentials.from_service_account_info(
            info, scopes=["https://www.googleapis.com/auth/spreadsheets"],
        )
        self.spreadsheet_id = spreadsheet_id
        self.service = build("sheets", "v4", credentials=credentials, cache_discovery=False)

    def values(self, tab: str, columns: str) -> list[list[Any]]:
        result = self.service.spreadsheets().values().get(
            spreadsheetId=self.spreadsheet_id, range=f"{tab}!{columns}", majorDimension="ROWS",
        ).execute(num_retries=4)
        return result.get("values", []) or []

    def sheet_ids(self, required_tabs: Iterable[str]) -> dict[str, int]:
        metadata = self.service.spreadsheets().get(
            spreadsheetId=self.spreadsheet_id, fields="sheets(properties(sheetId,title))",
        ).execute(num_retries=4)
        available = {
            str(sheet.get("properties", {}).get("title", "")): int(sheet.get("properties", {}).get("sheetId"))
            for sheet in metadata.get("sheets", [])
            if sheet.get("properties", {}).get("title") is not None
            and sheet.get("properties", {}).get("sheetId") is not None
        }
        missing = [tab for tab in required_tabs if tab not in available]
        if missing:
            raise E4Error(f"Required Sheet tabs are missing: {missing}")
        return {tab: available[tab] for tab in required_tabs}

    def append_rows(self, tab: str, rows: list[list[str]]) -> None:
        self.service.spreadsheets().values().append(
            spreadsheetId=self.spreadsheet_id, range=f"{tab}!A1",
            valueInputOption="RAW", insertDataOption="INSERT_ROWS",
            body={"majorDimension": "ROWS", "values": rows},
        ).execute(num_retries=4)

    def delete_rows(self, sheet_id: int, row_numbers: Iterable[int]) -> None:
        requests = []
        for row_number in sorted({int(value) for value in row_numbers if int(value) > 1}, reverse=True):
            requests.append({"deleteDimension": {"range": {
                "sheetId": sheet_id, "dimension": "ROWS",
                "startIndex": row_number - 1, "endIndex": row_number,
            }}})
        if requests:
            self.service.spreadsheets().batchUpdate(
                spreadsheetId=self.spreadsheet_id, body={"requests": requests},
            ).execute(num_retries=4)


class Database:
    def __init__(self):
        import pymysql
        self.connection = pymysql.connect(
            host=require_env("STAGING_DB_HOST"),
            port=int(os.environ.get("STAGING_DB_PORT", "3306") or "3306"),
            user=require_env("STAGING_DB_USER"), password=require_env("STAGING_DB_PASSWORD"),
            database=require_env("STAGING_DB_NAME"), charset="utf8mb4", autocommit=True,
            connect_timeout=15, read_timeout=30, write_timeout=30,
            cursorclass=pymysql.cursors.DictCursor,
        )

    def preflight(self) -> dict[str, Any]:
        with self.connection.cursor() as cursor:
            cursor.execute("SELECT DATABASE() AS database_name, 1 AS ok")
            row = cursor.fetchone()
            cursor.execute("SHOW TABLES LIKE 'control_cases'")
            exists = cursor.fetchone() is not None
        if not exists:
            raise E4Error("Staging control-center schema is missing.")
        return {"connected": bool(row and row["ok"] == 1), "schema_present": exists}

    def seed_failed_operation(self, operation_id: str, case_id: str, note: str) -> None:
        payload = operation_payload(operation_id, note)
        with self.connection.cursor() as cursor:
            cursor.execute(
                "INSERT INTO control_operations (operation_id,case_id,action,payload_hash,status,error_text,completed_at) "
                "VALUES (%s,%s,'approve',%s,'failed',%s,NOW())",
                (operation_id, case_id, payload_hash(payload), "E4 controlled partial state after confirmed Event write."),
            )

    def case_state(self, case_id: str) -> dict[str, Any] | None:
        with self.connection.cursor() as cursor:
            cursor.execute("SELECT id,state,source_system,source_reference,decision_ready FROM control_cases WHERE id=%s", (case_id,))
            return cursor.fetchone()

    def operation_state(self, operation_id: str) -> dict[str, Any] | None:
        with self.connection.cursor() as cursor:
            cursor.execute("SELECT operation_id,case_id,action,status,error_text FROM control_operations WHERE operation_id=%s", (operation_id,))
            return cursor.fetchone()

    def cleanup(self, case_ids: Iterable[str], operation_ids: Iterable[str], run_key: str) -> dict[str, int]:
        case_ids = sorted({item for item in case_ids if item})
        operation_ids = sorted({item for item in operation_ids if item})
        deleted_operations = deleted_cases = 0
        with self.connection.cursor() as cursor:
            if operation_ids:
                placeholders = ",".join(["%s"] * len(operation_ids))
                deleted_operations += cursor.execute(f"DELETE FROM control_operations WHERE operation_id IN ({placeholders})", operation_ids)
            if case_ids:
                placeholders = ",".join(["%s"] * len(case_ids))
                deleted_operations += cursor.execute(f"DELETE FROM control_operations WHERE case_id IN ({placeholders})", case_ids)
                deleted_cases += cursor.execute(f"DELETE FROM control_cases WHERE id IN ({placeholders})", case_ids)
            deleted_operations += cursor.execute("DELETE FROM control_operations WHERE operation_id LIKE %s", (f"%{run_key}%",))
            deleted_cases += cursor.execute(
                "DELETE FROM control_cases WHERE source_system='inbox_feed' AND source_reference LIKE %s",
                (f"{E4_PREFIX}-{run_key}-%",),
            )
        return {"operations": deleted_operations, "cases": deleted_cases}

    def assert_absent(self, run_key: str) -> dict[str, int]:
        with self.connection.cursor() as cursor:
            cursor.execute("SELECT COUNT(*) AS count_value FROM control_cases WHERE source_reference LIKE %s", (f"{E4_PREFIX}-{run_key}-%",))
            cases = int(cursor.fetchone()["count_value"])
            cursor.execute("SELECT COUNT(*) AS count_value FROM control_operations WHERE operation_id LIKE %s", (f"%{run_key}%",))
            operations = int(cursor.fetchone()["count_value"])
        return {"cases": cases, "operations": operations}


class GitHubDeploy:
    def __init__(self, token: str, repository: str, sha: str):
        self.token, self.repository, self.sha = token, repository, sha
        self.api = os.environ.get("GITHUB_API_URL", "https://api.github.com").rstrip("/")

    def request(self, path: str, method: str = "GET", body: dict[str, Any] | None = None) -> Any:
        data = json.dumps(body).encode("utf-8") if body is not None else None
        request = urllib.request.Request(
            f"{self.api}{path}", data=data, method=method,
            headers={
                "Accept": "application/vnd.github+json", "Authorization": f"Bearer {self.token}",
                "X-GitHub-Api-Version": "2022-11-28", "User-Agent": "Bocholt-Erleben-E4/1.0",
                **({"Content-Type": "application/json"} if body is not None else {}),
            },
        )
        with urllib.request.urlopen(request, timeout=60) as response:
            raw = response.read()
            return json.loads(raw.decode("utf-8")) if raw else None

    def list_runs(self, event: str | None = None) -> list[dict[str, Any]]:
        query = {"branch": "staging", "per_page": "30"}
        if event:
            query["event"] = event
        payload = self.request(f"/repos/{self.repository}/actions/workflows/deploy-strato.yml/runs?{urllib.parse.urlencode(query)}")
        return payload.get("workflow_runs", []) if isinstance(payload, dict) else []

    def wait_push_deploy(self, timeout_seconds: int = 1800) -> dict[str, Any]:
        deadline, last = time.time() + timeout_seconds, None
        while time.time() < deadline:
            matches = [run for run in self.list_runs("push") if run.get("head_sha") == self.sha]
            if matches:
                matches.sort(key=lambda item: item.get("created_at", ""), reverse=True)
                last = matches[0]
                if last.get("status") == "completed":
                    if last.get("conclusion") != "success":
                        raise E4Error(f"Initial staging deploy failed: run {last.get('id')} conclusion={last.get('conclusion')}")
                    return last
            time.sleep(10)
        raise E4Error(f"Initial staging deploy did not complete. Last={last}")

    def dispatch_and_wait(self, label: str, timeout_seconds: int = 1800) -> dict[str, Any]:
        before = {int(run["id"]) for run in self.list_runs("workflow_dispatch")}
        self.request(
            f"/repos/{self.repository}/actions/workflows/deploy-strato.yml/dispatches",
            method="POST", body={"ref": "staging", "inputs": {"full_repair": "false"}},
        )
        deadline, selected = time.time() + timeout_seconds, None
        while time.time() < deadline:
            for run in self.list_runs("workflow_dispatch"):
                if int(run["id"]) not in before and run.get("head_sha") == self.sha:
                    selected = run
                    break
            if selected and selected.get("status") == "completed":
                if selected.get("conclusion") != "success":
                    raise E4Error(f"{label} deploy failed: run {selected.get('id')} conclusion={selected.get('conclusion')}")
                return selected
            time.sleep(10)
            if selected:
                refreshed = [run for run in self.list_runs("workflow_dispatch") if int(run["id"]) == int(selected["id"])]
                selected = refreshed[0] if refreshed else selected
        raise E4Error(f"{label} deploy did not complete. Last={selected}")


def require_env(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise E4Error(f"Required environment variable is missing: {name}")
    return value


def assert_api_ok(response: ApiResponse, label: str, expected_status: int = 200) -> dict[str, Any]:
    if response.status != expected_status or response.payload.get("status") != "ok":
        raise E4Error(f"{label} failed: HTTP {response.status} {response.payload}")
    return response.payload


def build_row(header: list[str], payload: dict[str, str]) -> list[str]:
    missing = sorted(REQUIRED_INBOX_HEADERS - set(header))
    if missing:
        raise E4Error(f"Inbox_Staging headers missing: {missing}")
    return [payload.get(column, "") for column in header]


def contains_event_id(payload: Any, event_id: str) -> bool:
    if isinstance(payload, dict):
        return str(payload.get("id", "")) == event_id or any(contains_event_id(value, event_id) for value in payload.values())
    if isinstance(payload, list):
        return any(contains_event_id(value, event_id) for value in payload)
    return False


def assert_preflight(data: dict[str, Any], expected_build: str, allowed_target_statuses: set[str]) -> dict[str, Any]:
    runtime, resources = data.get("runtime", {}), data.get("resources", {})
    build, source, target = runtime.get("build", {}), data.get("source_snapshot", {}), data.get("target_snapshot", {})
    assertions = {
        "mutation_false": data.get("mutation") is False, "allowed": data.get("allowed") is True,
        "no_blockers": data.get("blockers") == [], "build": build.get("build_id") == expected_build,
        "manifest_build": build.get("manifest_build") == expected_build,
        "environment": runtime.get("resolved_environment") == "staging",
        "host": runtime.get("request", {}).get("host") == "staging.bocholt-erleben.de",
        "source_tab": resources.get("source_tab") == INBOX_TAB, "target_tab": resources.get("target_tab") == EVENTS_TAB,
        "writer": data.get("writer") == "be_cc_writeback_staging_inbox_approve_verified",
        "source_resolved": source.get("tab") == INBOX_TAB and source.get("resolution_status") == "resolved",
        "target_resolution": target.get("tab") == EVENTS_TAB and target.get("resolution_status") in allowed_target_statuses,
        "live_inbox_unused": resources.get("live_inbox") == "not_used", "live_events_unused": resources.get("live_events") == "not_used",
    }
    failures = [name for name, passed in assertions.items() if not passed]
    if failures:
        raise E4Error(f"Preflight assertions failed: {failures}; data={data}")
    return {
        "build": expected_build, "source_tab": resources.get("source_tab"), "target_tab": resources.get("target_tab"),
        "writer": data.get("writer"), "mutation": data.get("mutation"),
        "target_resolution": target.get("resolution_status"), "assertions": assertions,
    }


def self_test() -> None:
    assert column_letter(0) == "A" and column_letter(25) == "Z" and column_letter(26) == "AA"
    run_key = "deadbeef1234-99"
    event_id = f"{E4_PREFIX}-{run_key}-success"
    payload = candidate_payload(event_id, "Test", "2026-12-29", run_key)
    assert event_from_payload(payload)["time"] == "19:00–20:00 Uhr"
    op = operation_payload(f"e4:{run_key}:failed", "note")
    assert payload_hash(op) == payload_hash(dict(reversed(list(op.items()))))
    values = [["id_suggestion", "title"], [event_id, "Test"], ["other", run_key + " marker"]]
    assert len(find_exact_rows(values, "id_suggestion", event_id)) == 1
    assert len(filter_test_rows(values, run_key)) == 1
    assert contains_event_id({"items": [{"id": event_id}]}, event_id)
    print("Control Center E4 synthetic harness self-test: OK")


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--self-test", action="store_true")
    parser.add_argument("--evidence", default="artifacts/control-center-e4-evidence.json")
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return 0

    base_url, live_url = require_env("BASE_URL").rstrip("/"), require_env("LIVE_URL").rstrip("/")
    review_password, sheet_id = require_env("REVIEW_PASSWORD"), require_env("SHEET_ID")
    service_account_json, github_token = require_env("GOOGLE_SERVICE_ACCOUNT_JSON"), require_env("GH_TOKEN")
    repository, sha, run_id = require_env("GITHUB_REPOSITORY"), require_env("GITHUB_SHA"), require_env("GITHUB_RUN_ID")
    expected_build = sha[:12]
    run_key = f"{expected_build}-{run_id}"
    success_id, resume_id = f"{E4_PREFIX}-{run_key}-success", f"{E4_PREFIX}-{run_key}-resume"
    success_title, resume_title = f"E4 Synthetic Success {run_key}", f"E4 Synthetic Resume {run_key}"

    evidence_path = Path(args.evidence)
    evidence_path.parent.mkdir(parents=True, exist_ok=True)
    evidence: dict[str, Any] = {
        "schema": 1, "evidence_level": "E4", "started_at": utc_now(), "repository": repository,
        "commit_sha": sha, "expected_build": expected_build, "run_key": run_key,
        "synthetic_ids": [success_id, resume_id],
        "mutation_scope": [INBOX_TAB, EVENTS_TAB, "staging control-center DB"],
        "live_mutation_allowed": False, "steps": {}, "cleanup": {}, "result": "running",
    }

    sheets, db = SheetClient(sheet_id, service_account_json), Database()
    http, deploy = HttpClient(review_password), GitHubDeploy(github_token, repository, sha)
    case_ids: list[str] = []
    operation_ids: list[str] = []
    primary_error: Exception | None = None
    sheet_ids = sheets.sheet_ids([LIVE_EVENTS_TAB, LIVE_INBOX_TAB, INBOX_TAB, EVENTS_TAB])
    success_payload = candidate_payload(success_id, success_title, "2026-12-29", run_key)
    resume_payload = candidate_payload(resume_id, resume_title, "2026-12-30", run_key)
    resume_event = event_from_payload(resume_payload)

    try:
        db_status = db.preflight()
        initial_deploy = deploy.wait_push_deploy()
        marker = urllib.request.urlopen(f"{base_url}/meta/build.txt?e4={run_id}", timeout=30).read().decode().strip()
        if marker != expected_build:
            raise E4Error(f"Staging build mismatch: expected={expected_build} deployed={marker}")

        live_inbox_before, live_events_before = sheets.values(LIVE_INBOX_TAB, "A:AB"), sheets.values(LIVE_EVENTS_TAB, "A:AA")
        staging_inbox_before, staging_events_before = sheets.values(INBOX_TAB, "A:AB"), sheets.values(EVENTS_TAB, "A:K")
        inbox_header, _ = header_index(staging_inbox_before)
        event_header, _ = header_index(staging_events_before)
        if event_header != EVENT_COLUMNS:
            raise E4Error(f"Events_Staging header mismatch: {event_header}")
        for values, field, wanted, label in [
            (staging_inbox_before, "id_suggestion", success_id, "success inbox"),
            (staging_inbox_before, "id_suggestion", resume_id, "resume inbox"),
            (staging_events_before, "id", success_id, "success event"),
            (staging_events_before, "id", resume_id, "resume event"),
            (live_inbox_before, "id_suggestion", success_id, "live success inbox"),
            (live_inbox_before, "id_suggestion", resume_id, "live resume inbox"),
            (live_events_before, "id", success_id, "live success event"),
            (live_events_before, "id", resume_id, "live resume event"),
        ]:
            if find_exact_rows(values, field, wanted):
                raise E4Error(f"Pre-existing synthetic row found: {label}")
        cityart_rows = [
            row_dict(inbox_header, row, offset) for offset, row in enumerate(staging_inbox_before[1:], start=2)
            if "cityart" in "\n".join(str(cell or "").lower() for cell in row)
        ]
        if len(cityart_rows) != 1 or cityart_rows[0].get("status") != "review":
            raise E4Error("Frozen CityArt row is not uniquely present with status=review.")
        if any("cityart" in "\n".join(str(cell or "").lower() for cell in row) for row in staging_events_before[1:]):
            raise E4Error("CityArt unexpectedly exists in Events_Staging before E4.")
        public_staging_before = HttpClient.public_json(f"{base_url}/data/events.json?e4=before-{run_id}")
        public_live_before = HttpClient.public_json(f"{live_url}/data/events.json?e4=before-{run_id}")
        if contains_event_id(public_staging_before, success_id) or contains_event_id(public_staging_before, resume_id):
            raise E4Error("Synthetic IDs already exist in deployed staging feed.")
        if contains_event_id(public_live_before, success_id) or contains_event_id(public_live_before, resume_id):
            raise E4Error("Synthetic IDs unexpectedly exist in live feed.")
        evidence["steps"]["baseline"] = {
            "database": db_status, "initial_deploy_run": initial_deploy.get("id"), "build": marker,
            "live_inbox_hash": canonical_hash(normalize_rows(live_inbox_before)),
            "live_events_hash": canonical_hash(normalize_rows(live_events_before)),
            "staging_inbox_non_test_hash": canonical_hash(filter_test_rows(staging_inbox_before, run_key)),
            "staging_events_non_test_hash": canonical_hash(filter_test_rows(staging_events_before, run_key)),
            "cityart_row_number": int(cityart_rows[0]["row_number"]), "cityart_status": "review",
        }

        sheets.append_rows(INBOX_TAB, [build_row(inbox_header, success_payload), build_row(inbox_header, resume_payload)])
        sheets.append_rows(EVENTS_TAB, [[resume_event[column] for column in EVENT_COLUMNS]])
        seeded_inbox, seeded_events = sheets.values(INBOX_TAB, "A:AB"), sheets.values(EVENTS_TAB, "A:K")
        success_inbox_rows = find_exact_rows(seeded_inbox, "id_suggestion", success_id)
        resume_inbox_rows = find_exact_rows(seeded_inbox, "id_suggestion", resume_id)
        resume_event_rows = find_exact_rows(seeded_events, "id", resume_id)
        if len(success_inbox_rows) != 1 or len(resume_inbox_rows) != 1 or len(resume_event_rows) != 1:
            raise E4Error("Synthetic Sheet seed was not uniquely confirmed.")
        evidence["steps"]["seed"] = {
            "success_inbox_row": int(success_inbox_rows[0]["row_number"]),
            "resume_inbox_row": int(resume_inbox_rows[0]["row_number"]),
            "resume_event_row": int(resume_event_rows[0]["row_number"]),
            "resume_partial_state": "event_confirmed_inbox_review",
        }

        def create_case(payload: dict[str, str], row_number: str) -> str:
            source_payload = dict(payload)
            source_payload.update({"sheet_tab": INBOX_TAB, "row_number": row_number})
            response = http.json_request(f"{base_url}/api/control-center/cases.php", method="POST", body={
                "type": "intake", "state": "decision_required", "priority": "normal",
                "title": payload["title"], "reason": payload["description"], "next_action": "E4 synthetic proof",
                "object_type": "event_candidate", "object_id": payload["id_suggestion"], "object_title": payload["title"],
                "source_system": "inbox_feed", "source_reference": payload["id_suggestion"],
                "source_payload": source_payload, "decision_ready": True,
            })
            data = assert_api_ok(response, "Create synthetic case", expected_status=201)["data"]
            case_id = str(data.get("id", "")).strip()
            if not case_id:
                raise E4Error("Synthetic case response has no id.")
            case_ids.append(case_id)
            return case_id

        success_case = create_case(success_payload, success_inbox_rows[0]["row_number"])
        resume_case = create_case(resume_payload, resume_inbox_rows[0]["row_number"])

        def run_preflight(case_id: str, target_statuses: set[str]) -> dict[str, Any]:
            response = http.json_request(
                f"{base_url}/api/control-center/preflight.php", method="POST",
                body={"case_id": case_id, "action": "approve", "payload": {}},
            )
            return assert_preflight(assert_api_ok(response, "E4 runtime preflight")["data"], expected_build, target_statuses)

        evidence["steps"]["preflight"] = {
            "success": run_preflight(success_case, {"absent"}),
            "resume": run_preflight(resume_case, {"resolved"}),
        }

        success_operation, success_note = f"e4:{run_key}:success", f"E4 synthetic success {run_key}"
        operation_ids.append(success_operation)
        body = {"case_id": success_case, "action": "approve", "operation_id": success_operation,
                "payload": {"decision_class": "accepted", "decision_note": success_note}}
        success_result = assert_api_ok(http.json_request(f"{base_url}/api/control-center/action.php", method="POST", body=body), "E4 success action")["data"]
        if success_result.get("state") != "done" or success_result.get("writeback", {}).get("event", {}).get("mode") != "appended":
            raise E4Error(f"Success path not confirmed: {success_result}")
        replay = assert_api_ok(http.json_request(f"{base_url}/api/control-center/action.php", method="POST", body=body), "E4 idempotent replay")
        if replay.get("idempotent_replay") is not True:
            raise E4Error("Completed operation was not returned as idempotent replay.")

        failed_operation, failed_note = f"e4:{run_key}:partial-failed", f"E4 synthetic partial failure {run_key}"
        operation_ids.append(failed_operation)
        db.seed_failed_operation(failed_operation, resume_case, failed_note)
        blocked = http.json_request(f"{base_url}/api/control-center/action.php", method="POST", body={
            "case_id": resume_case, "action": "approve", "operation_id": failed_operation,
            "payload": {"decision_class": "accepted", "decision_note": failed_note},
        })
        if blocked.status != 409 or blocked.payload.get("status") != "error":
            raise E4Error(f"Failed operation id was not fail-closed: HTTP {blocked.status}")

        resume_operation, resume_note = f"e4:{run_key}:resume", f"E4 synthetic resume {run_key}"
        operation_ids.append(resume_operation)
        resume_result = assert_api_ok(http.json_request(f"{base_url}/api/control-center/action.php", method="POST", body={
            "case_id": resume_case, "action": "approve", "operation_id": resume_operation,
            "payload": {"decision_class": "accepted", "decision_note": resume_note},
        }), "E4 resume action")["data"]
        if resume_result.get("state") != "done" or resume_result.get("writeback", {}).get("event", {}).get("mode") != "existing":
            raise E4Error(f"Resume path not confirmed: {resume_result}")

        after_inbox, after_events = sheets.values(INBOX_TAB, "A:AB"), sheets.values(EVENTS_TAB, "A:K")
        success_events, resume_events = find_exact_rows(after_events, "id", success_id), find_exact_rows(after_events, "id", resume_id)
        success_inbox, resume_inbox = find_exact_rows(after_inbox, "id_suggestion", success_id), find_exact_rows(after_inbox, "id_suggestion", resume_id)
        if len(success_events) != 1 or len(resume_events) != 1 or len(success_inbox) != 1 or len(resume_inbox) != 1:
            raise E4Error("Synthetic identity or duplicate postcondition failed.")
        if success_inbox[0].get("status") != "übernommen" or resume_inbox[0].get("status") != "übernommen":
            raise E4Error("Synthetic Inbox statuses were not confirmed.")
        states = [db.case_state(success_case), db.case_state(resume_case)]
        ops = [db.operation_state(success_operation), db.operation_state(failed_operation), db.operation_state(resume_operation)]
        if any(not state or state.get("state") != "done" for state in states):
            raise E4Error("Local cases were not confirmed done.")
        if not ops[0] or ops[0].get("status") != "completed" or not ops[1] or ops[1].get("status") != "failed" or not ops[2] or ops[2].get("status") != "completed":
            raise E4Error("Operation postconditions failed.")
        evidence["steps"]["writeback"] = {
            "success": {"event_mode": "appended", "operation_replay": True, "event_rows": 1, "inbox_status": "übernommen"},
            "partial_failure": {"seeded_state": "event_confirmed_inbox_review", "failed_operation_status": "failed", "same_operation_retry_http": 409},
            "resume": {"event_mode": "existing", "event_rows": 1, "inbox_status": "übernommen"},
        }

        synthetic_deploy = deploy.dispatch_and_wait("Synthetic feed")
        staging_feed = HttpClient.public_json(f"{base_url}/data/events.json?e4=written-{run_id}")
        live_feed = HttpClient.public_json(f"{live_url}/data/events.json?e4=written-{run_id}")
        if not contains_event_id(staging_feed, success_id) or not contains_event_id(staging_feed, resume_id):
            raise E4Error("Generated staging feed does not contain both synthetic events.")
        if contains_event_id(live_feed, success_id) or contains_event_id(live_feed, resume_id):
            raise E4Error("Live feed contains synthetic E4 events.")
        evidence["steps"]["feed"] = {"deploy_run": synthetic_deploy.get("id"), "staging_contains_both": True, "live_contains_synthetic": False}

    except Exception as error:
        primary_error = error
        evidence["error"] = f"{type(error).__name__}: {error}"
    finally:
        cleanup_errors: list[str] = []
        try:
            current_inbox, current_events = sheets.values(INBOX_TAB, "A:AB"), sheets.values(EVENTS_TAB, "A:K")
            inbox_rows = [int(row["row_number"]) for event_id in (success_id, resume_id) for row in find_exact_rows(current_inbox, "id_suggestion", event_id)]
            event_rows = [int(row["row_number"]) for event_id in (success_id, resume_id) for row in find_exact_rows(current_events, "id", event_id)]
            sheets.delete_rows(sheet_ids[INBOX_TAB], inbox_rows)
            sheets.delete_rows(sheet_ids[EVENTS_TAB], event_rows)
        except Exception as error:
            cleanup_errors.append(f"sheet_cleanup: {type(error).__name__}: {error}")
        try:
            evidence["cleanup"]["database_deleted"] = db.cleanup(case_ids, operation_ids, run_key)
        except Exception as error:
            cleanup_errors.append(f"database_cleanup: {type(error).__name__}: {error}")
        try:
            cleanup_deploy = deploy.dispatch_and_wait("Cleanup feed")
            evidence["cleanup"]["deploy_run"] = cleanup_deploy.get("id")
            cleaned_feed = HttpClient.public_json(f"{base_url}/data/events.json?e4=clean-{run_id}")
            if contains_event_id(cleaned_feed, success_id) or contains_event_id(cleaned_feed, resume_id):
                raise E4Error("Synthetic events remain in deployed staging feed after cleanup.")
        except Exception as error:
            cleanup_errors.append(f"feed_cleanup: {type(error).__name__}: {error}")
        try:
            live_inbox_after, live_events_after = sheets.values(LIVE_INBOX_TAB, "A:AB"), sheets.values(LIVE_EVENTS_TAB, "A:AA")
            staging_inbox_after, staging_events_after = sheets.values(INBOX_TAB, "A:AB"), sheets.values(EVENTS_TAB, "A:K")
            db_absent = db.assert_absent(run_key)
            after_cityart = [
                row_dict(header_index(staging_inbox_after)[0], row, offset)
                for offset, row in enumerate(staging_inbox_after[1:], start=2)
                if "cityart" in "\n".join(str(cell or "").lower() for cell in row)
            ]
            cityart_events_after = [row for row in staging_events_after[1:] if "cityart" in "\n".join(str(cell or "").lower() for cell in row)]
            baseline = evidence.get("steps", {}).get("baseline", {})
            checks = {
                "live_inbox_unchanged": not baseline or canonical_hash(normalize_rows(live_inbox_after)) == baseline.get("live_inbox_hash"),
                "live_events_unchanged": not baseline or canonical_hash(normalize_rows(live_events_after)) == baseline.get("live_events_hash"),
                "staging_inbox_non_test_unchanged": not baseline or canonical_hash(filter_test_rows(staging_inbox_after, run_key)) == baseline.get("staging_inbox_non_test_hash"),
                "staging_events_non_test_unchanged": not baseline or canonical_hash(filter_test_rows(staging_events_after, run_key)) == baseline.get("staging_events_non_test_hash"),
                "synthetic_inbox_absent": not find_exact_rows(staging_inbox_after, "id_suggestion", success_id) and not find_exact_rows(staging_inbox_after, "id_suggestion", resume_id),
                "synthetic_events_absent": not find_exact_rows(staging_events_after, "id", success_id) and not find_exact_rows(staging_events_after, "id", resume_id),
                "database_absent": db_absent == {"cases": 0, "operations": 0},
                "cityart_review_unchanged": len(after_cityart) == 1 and after_cityart[0].get("status") == "review",
                "cityart_event_absent": not cityart_events_after,
            }
            evidence["cleanup"].update({"checks": checks, "database_remaining": db_absent})
            failed = [name for name, passed in checks.items() if not passed]
            if failed:
                cleanup_errors.append("cleanup_assertions: " + ", ".join(failed))
        except Exception as error:
            cleanup_errors.append(f"cleanup_verification: {type(error).__name__}: {error}")
        evidence["cleanup"]["errors"] = cleanup_errors
        evidence["completed_at"] = utc_now()
        evidence["result"] = "success" if primary_error is None and not cleanup_errors else "failure"
        evidence_path.write_text(json.dumps(evidence, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    if primary_error is not None:
        raise primary_error
    if evidence["cleanup"].get("errors"):
        raise E4Error("E4 cleanup failed: " + "; ".join(evidence["cleanup"]["errors"]))
    print("Control Center E4 synthetic write and resume proof: OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
