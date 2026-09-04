#!/usr/bin/env python3
"""Persist Search Console history for the existing Growth Intelligence run.

The Growth workflow already reads query x page rows once. This module reuses those
captured rows and performs exactly one additional dimensionless Search Console
request so site-wide totals are not distorted by query anonymisation or row limits.
"""
from __future__ import annotations

import hashlib
import os
from typing import Any, Callable, Dict, List, Mapping, Sequence

SITE_HISTORY_TAB = os.environ.get("GSC_SITE_HISTORY_TAB", "Growth_GSC_Site_History").strip() or "Growth_GSC_Site_History"
QUERY_PAGE_HISTORY_TAB = os.environ.get("GSC_QUERY_PAGE_HISTORY_TAB", "Growth_GSC_Query_Page_History").strip() or "Growth_GSC_Query_Page_History"

SITE_HISTORY_HEADER = [
    "snapshot_id",
    "captured_at",
    "period_start",
    "period_end",
    "site_url",
    "clicks",
    "impressions",
    "ctr",
    "avg_position",
    "query_page_rows",
    "query_page_clicks",
    "query_page_impressions",
    "run_id",
    "run_attempt",
    "commit_sha",
    "branch",
    "status",
    "diagnostic",
]

QUERY_PAGE_HISTORY_HEADER = [
    "snapshot_id",
    "row_index",
    "captured_at",
    "period_start",
    "period_end",
    "site_url",
    "query",
    "page",
    "clicks",
    "impressions",
    "ctr",
    "position",
    "run_id",
    "run_attempt",
    "commit_sha",
    "branch",
]


def _run_metadata(env: Mapping[str, str] | None = None) -> Dict[str, str]:
    source = env if env is not None else os.environ
    return {
        "run_id": str(source.get("GITHUB_RUN_ID", "")).strip(),
        "run_attempt": str(source.get("GITHUB_RUN_ATTEMPT", "")).strip() or "1",
        "commit_sha": str(source.get("GITHUB_SHA", "")).strip(),
        "branch": str(source.get("GITHUB_REF_NAME", "")).strip() or str(source.get("BE_ENVIRONMENT", "")).strip(),
    }


def build_snapshot_id(
    site_url: str,
    period_start: str,
    period_end: str,
    captured_at: str,
    run_id: str = "",
    run_attempt: str = "1",
) -> str:
    """Return a run-scoped, stable-enough identifier for append-only history."""
    run_token = str(run_id).strip()
    attempt_token = str(run_attempt).strip() or "1"
    if run_token:
        return f"gsc-{period_end}-{run_token}-{attempt_token}"
    digest = hashlib.sha256(
        f"{site_url}|{period_start}|{period_end}|{captured_at}".encode("utf-8")
    ).hexdigest()[:16]
    return f"gsc-{period_end}-{digest}"


def fetch_site_totals(search_console, site_url: str, period_start: str, period_end: str) -> Dict[str, float]:
    """Read exact site-level GSC totals without query/page dimensions."""
    body = {
        "startDate": period_start,
        "endDate": period_end,
        "rowLimit": 1,
        "startRow": 0,
    }
    response = search_console.searchanalytics().query(siteUrl=site_url, body=body).execute()
    rows = response.get("rows", []) or []
    if not rows:
        return {"clicks": 0.0, "impressions": 0.0, "ctr": 0.0, "avg_position": 0.0}
    row = rows[0]
    return {
        "clicks": float(row.get("clicks", 0) or 0),
        "impressions": float(row.get("impressions", 0) or 0),
        "ctr": float(row.get("ctr", 0) or 0),
        "avg_position": float(row.get("position", 0) or 0),
    }


def build_query_page_history_rows(
    gsc_rows: Sequence[Mapping[str, Any]],
    *,
    snapshot_id: str,
    captured_at: str,
    period_start: str,
    period_end: str,
    site_url: str,
    run_meta: Mapping[str, str],
) -> List[Dict[str, Any]]:
    rows: List[Dict[str, Any]] = []
    for index, source in enumerate(gsc_rows, start=1):
        rows.append({
            "snapshot_id": snapshot_id,
            "row_index": index,
            "captured_at": captured_at,
            "period_start": period_start,
            "period_end": period_end,
            "site_url": site_url,
            "query": str(source.get("query", "")),
            "page": str(source.get("page", "")),
            "clicks": float(source.get("clicks", 0) or 0),
            "impressions": float(source.get("impressions", 0) or 0),
            "ctr": float(source.get("ctr", 0) or 0),
            "position": float(source.get("position", 0) or 0),
            "run_id": run_meta.get("run_id", ""),
            "run_attempt": run_meta.get("run_attempt", ""),
            "commit_sha": run_meta.get("commit_sha", ""),
            "branch": run_meta.get("branch", ""),
        })
    return rows


def persist_gsc_snapshot(
    *,
    gsc_rows: Sequence[Mapping[str, Any]],
    search_console,
    sheets,
    spreadsheet_id: str,
    site_url: str,
    period_start: str,
    period_end: str,
    captured_at: str,
    ensure_sheet: Callable[[Any, str, str, List[str]], None],
    append_records: Callable[[Any, str, str, List[str], List[Dict[str, Any]]], None],
    env: Mapping[str, str] | None = None,
) -> Dict[str, Any]:
    """Persist one append-only GSC snapshot and return its summary metadata.

    Query x page rows are appended first. The site-history row is written last and
    therefore acts as the completion marker for the snapshot.
    """
    site_url = str(site_url or "").strip()
    if not site_url:
        return {
            "status": "skipped",
            "diagnostic": "GSC site URL missing; history snapshot skipped.",
            "additional_gsc_calls": 0,
            "query_page_rows": 0,
        }

    run_meta = _run_metadata(env)
    snapshot_id = build_snapshot_id(
        site_url,
        period_start,
        period_end,
        captured_at,
        run_meta.get("run_id", ""),
        run_meta.get("run_attempt", "1"),
    )

    ensure_sheet(sheets, spreadsheet_id, QUERY_PAGE_HISTORY_TAB, QUERY_PAGE_HISTORY_HEADER)
    ensure_sheet(sheets, spreadsheet_id, SITE_HISTORY_TAB, SITE_HISTORY_HEADER)

    history_rows = build_query_page_history_rows(
        gsc_rows,
        snapshot_id=snapshot_id,
        captured_at=captured_at,
        period_start=period_start,
        period_end=period_end,
        site_url=site_url,
        run_meta=run_meta,
    )

    status = "ok"
    diagnostic = "Exact site totals and query-page history persisted."
    try:
        totals = fetch_site_totals(search_console, site_url, period_start, period_end)
    except Exception as exc:
        totals = {"clicks": "", "impressions": "", "ctr": "", "avg_position": ""}
        status = "partial"
        diagnostic = f"Exact site totals failed: {type(exc).__name__}: {exc}"

    append_records(
        sheets,
        spreadsheet_id,
        QUERY_PAGE_HISTORY_TAB,
        QUERY_PAGE_HISTORY_HEADER,
        history_rows,
    )

    query_page_clicks = sum(float(row.get("clicks", 0) or 0) for row in gsc_rows)
    query_page_impressions = sum(float(row.get("impressions", 0) or 0) for row in gsc_rows)
    site_row: Dict[str, Any] = {
        "snapshot_id": snapshot_id,
        "captured_at": captured_at,
        "period_start": period_start,
        "period_end": period_end,
        "site_url": site_url,
        "clicks": totals["clicks"],
        "impressions": totals["impressions"],
        "ctr": totals["ctr"],
        "avg_position": totals["avg_position"],
        "query_page_rows": len(history_rows),
        "query_page_clicks": query_page_clicks,
        "query_page_impressions": query_page_impressions,
        "run_id": run_meta.get("run_id", ""),
        "run_attempt": run_meta.get("run_attempt", ""),
        "commit_sha": run_meta.get("commit_sha", ""),
        "branch": run_meta.get("branch", ""),
        "status": status,
        "diagnostic": diagnostic,
    }
    append_records(
        sheets,
        spreadsheet_id,
        SITE_HISTORY_TAB,
        SITE_HISTORY_HEADER,
        [site_row],
    )

    return {
        "snapshot_id": snapshot_id,
        "status": status,
        "diagnostic": diagnostic,
        "additional_gsc_calls": 1,
        "query_page_rows": len(history_rows),
        "site_clicks": totals["clicks"],
        "site_impressions": totals["impressions"],
        "site_ctr": totals["ctr"],
        "site_avg_position": totals["avg_position"],
        "site_history_tab": SITE_HISTORY_TAB,
        "query_page_history_tab": QUERY_PAGE_HISTORY_TAB,
    }
