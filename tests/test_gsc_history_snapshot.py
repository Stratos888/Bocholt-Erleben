#!/usr/bin/env python3
from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"
if str(SCRIPTS) not in sys.path:
    sys.path.insert(0, str(SCRIPTS))

from gsc_history_snapshot import (  # noqa: E402
    QUERY_PAGE_HISTORY_TAB,
    SITE_HISTORY_TAB,
    persist_gsc_snapshot,
)


class FakeRequest:
    def __init__(self, response=None, error=None):
        self.response = response
        self.error = error

    def execute(self):
        if self.error:
            raise self.error
        return self.response


class FakeSearchAnalytics:
    def __init__(self, response=None, error=None):
        self.response = response
        self.error = error
        self.calls = []

    def query(self, *, siteUrl, body):
        self.calls.append({"siteUrl": siteUrl, "body": body})
        return FakeRequest(self.response, self.error)


class FakeSearchConsole:
    def __init__(self, response=None, error=None):
        self.api = FakeSearchAnalytics(response=response, error=error)

    def searchanalytics(self):
        return self.api


def _sample_rows():
    return [
        {
            "query": "bocholt heute",
            "page": "https://bocholt-erleben.de/",
            "clicks": 4,
            "impressions": 100,
            "ctr": 0.04,
            "position": 5.2,
        },
        {
            "query": "veranstaltungen bocholt",
            "page": "https://bocholt-erleben.de/events/",
            "clicks": 3,
            "impressions": 80,
            "ctr": 0.0375,
            "position": 6.1,
        },
    ]


def test_persists_query_page_rows_then_exact_site_completion_row():
    ensured = []
    appended = []

    def ensure_sheet(_sheets, _sheet_id, tab, header):
        ensured.append((tab, list(header)))

    def append_records(_sheets, _sheet_id, tab, header, rows):
        appended.append((tab, list(header), [dict(row) for row in rows]))

    search_console = FakeSearchConsole(
        response={
            "rows": [
                {
                    "clicks": 11,
                    "impressions": 250,
                    "ctr": 0.044,
                    "position": 4.75,
                }
            ]
        }
    )
    result = persist_gsc_snapshot(
        gsc_rows=_sample_rows(),
        search_console=search_console,
        sheets=object(),
        spreadsheet_id="sheet-1",
        site_url="sc-domain:bocholt-erleben.de",
        period_start="2026-08-01",
        period_end="2026-08-30",
        captured_at="2026-08-31T05:00:00Z",
        ensure_sheet=ensure_sheet,
        append_records=append_records,
        env={
            "GITHUB_RUN_ID": "12345",
            "GITHUB_RUN_ATTEMPT": "2",
            "GITHUB_SHA": "abcdef",
            "GITHUB_REF_NAME": "main",
        },
    )

    assert [tab for tab, _ in ensured] == [QUERY_PAGE_HISTORY_TAB, SITE_HISTORY_TAB]
    assert [tab for tab, _, _ in appended] == [QUERY_PAGE_HISTORY_TAB, SITE_HISTORY_TAB]

    raw_rows = appended[0][2]
    assert len(raw_rows) == 2
    assert raw_rows[0]["snapshot_id"] == "gsc-2026-08-30-12345-2"
    assert raw_rows[0]["row_index"] == 1
    assert raw_rows[1]["row_index"] == 2
    assert raw_rows[1]["page"] == "https://bocholt-erleben.de/events/"

    site_row = appended[1][2][0]
    assert site_row["clicks"] == 11.0
    assert site_row["impressions"] == 250.0
    assert site_row["query_page_clicks"] == 7.0
    assert site_row["query_page_impressions"] == 180.0
    assert site_row["query_page_rows"] == 2
    assert site_row["status"] == "ok"

    assert len(search_console.api.calls) == 1
    call = search_console.api.calls[0]
    assert call["siteUrl"] == "sc-domain:bocholt-erleben.de"
    assert "dimensions" not in call["body"]
    assert call["body"]["rowLimit"] == 1
    assert result["additional_gsc_calls"] == 1
    assert result["site_impressions"] == 250.0


def test_exact_total_failure_keeps_raw_history_and_marks_snapshot_partial():
    appended = []

    def append_records(_sheets, _sheet_id, tab, _header, rows):
        appended.append((tab, [dict(row) for row in rows]))

    result = persist_gsc_snapshot(
        gsc_rows=_sample_rows(),
        search_console=FakeSearchConsole(error=RuntimeError("quota transient")),
        sheets=object(),
        spreadsheet_id="sheet-1",
        site_url="sc-domain:bocholt-erleben.de",
        period_start="2026-08-01",
        period_end="2026-08-30",
        captured_at="2026-08-31T05:00:00Z",
        ensure_sheet=lambda *_args: None,
        append_records=append_records,
        env={"GITHUB_RUN_ID": "12345", "GITHUB_RUN_ATTEMPT": "1"},
    )

    assert [tab for tab, _ in appended] == [QUERY_PAGE_HISTORY_TAB, SITE_HISTORY_TAB]
    assert len(appended[0][1]) == 2
    site_row = appended[1][1][0]
    assert site_row["status"] == "partial"
    assert site_row["clicks"] == ""
    assert "RuntimeError" in site_row["diagnostic"]
    assert result["status"] == "partial"
    assert result["additional_gsc_calls"] == 1


def test_missing_site_url_is_a_noop():
    calls = []
    result = persist_gsc_snapshot(
        gsc_rows=_sample_rows(),
        search_console=FakeSearchConsole(),
        sheets=object(),
        spreadsheet_id="sheet-1",
        site_url="",
        period_start="2026-08-01",
        period_end="2026-08-30",
        captured_at="2026-08-31T05:00:00Z",
        ensure_sheet=lambda *args: calls.append(("ensure", args)),
        append_records=lambda *args: calls.append(("append", args)),
    )
    assert calls == []
    assert result["status"] == "skipped"
    assert result["additional_gsc_calls"] == 0


if __name__ == "__main__":
    test_persists_query_page_rows_then_exact_site_completion_row()
    test_exact_total_failure_keeps_raw_history_and_marks_snapshot_partial()
    test_missing_site_url_is_a_noop()
    print("test_gsc_history_snapshot.py: OK")
