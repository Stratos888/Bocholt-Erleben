from __future__ import annotations

import json
import os
import re
import sys
from collections import Counter
from datetime import datetime
from pathlib import Path
from typing import Any, Iterable
from urllib.parse import parse_qsl, urlencode, urlparse, urlunparse

from google.oauth2 import service_account
from googleapiclient.discovery import build

from event_description_quality import evaluate_event_description
from event_identity import apply_event_identity_match, event_rows_from_sheet_values, find_best_event_match
from event_visual_keys import infer_event_visual_key, normalize_event_visual_key
from event_visual_motifs import infer_event_visual_fit

ROOT = Path(__file__).resolve().parents[1]
MANUAL_JSON_PATH = ROOT / "data" / "inbox_manual.json"
MANUAL_INTAKE_SUMMARY_PATH = ROOT / "data" / "manual-ki-intake-summary.json"
EVENT_VISUAL_POOL_PATH = ROOT / "data" / "event_visual_pool.json"

INBOX_TAB = os.environ.get("INBOX_TAB", "Inbox").strip() or "Inbox"
EVENTS_TAB = os.environ.get("EVENTS_TAB", "Events").strip() or "Events"

INBOX_COLUMNS = [
    "status",
    "id_suggestion",
    "title",
    "date",
    "endDate",
    "time",
    "city",
    "location",
    "kategorie_suggestion",
    "url",
    "description",
    "source_name",
    "source_url",
    "match_score",
    "matched_event_id",
    "notes",
    "visual_key",
    "created_at",
]

OPTIONAL_VISUAL_INBOX_COLUMNS = ["visual_motif", "visual_asset_status"]
OPTIONAL_IDENTITY_INBOX_COLUMNS = [
    "duplicate_status",
    "duplicate_confidence",
    "duplicate_reason",
    "duplicate_match_type",
    "matched_event_title",
    "matched_event_date",
    "matched_event_location",
    "matched_event_url",
    "event_identity_status",
]

REQUIRED_JSON_KEYS = ["title", "date", "city", "location", "source_name", "source_url"]


def fail(message: str) -> None:
    print(f"❌ {message}", file=sys.stderr)
    raise SystemExit(1)


def info(message: str) -> None:
    print(f"ℹ️  {message}")


def set_output(name: str, value: str) -> None:
    output_path = os.environ.get("GITHUB_OUTPUT", "").strip()
    if output_path:
        with open(output_path, "a", encoding="utf-8") as handle:
            handle.write(f"{name}={value}\n")


def write_intake_summary(**values: Any) -> None:
    payload = {
        "generated_at_utc": datetime.utcnow().replace(microsecond=0).isoformat() + "Z",
        "source": "manual-ki-intake",
        **values,
    }
    MANUAL_INTAKE_SUMMARY_PATH.parent.mkdir(parents=True, exist_ok=True)
    MANUAL_INTAKE_SUMMARY_PATH.write_text(
        json.dumps(payload, ensure_ascii=False, indent=2) + "\n",
        encoding="utf-8",
    )


def norm(value: Any) -> str:
    return str(value or "").strip()


def norm_key(value: Any) -> str:
    return re.sub(r"\s+", " ", norm(value)).lower()


def canonical_url(raw: Any) -> str:
    value = re.sub(r"\s+", "", norm(raw))
    if not value:
        return ""
    if "://" not in value:
        value = "https://" + value
    try:
        parsed = urlparse(value)
    except Exception:
        return value.rstrip("/")
    query_pairs = []
    for key, item_value in parse_qsl(parsed.query, keep_blank_values=True):
        lower = key.lower().strip()
        if lower.startswith("utm_") or lower in {"fbclid", "gclid", "mc_cid", "mc_eid", "ref", "ref_src"}:
            continue
        query_pairs.append((key, item_value))
    cleaned = parsed._replace(
        scheme=(parsed.scheme or "https").lower(),
        netloc=parsed.netloc.lower(),
        query=urlencode(query_pairs, doseq=True),
        fragment="",
    )
    return urlunparse(cleaned).rstrip("/")


def dedupe_fp(title: Any, date_value: Any, location: Any) -> str:
    return "|".join([norm_key(title), norm(date_value), norm_key(location)])


def sheet_rows(values: Iterable[Iterable[Any]]) -> tuple[list[str], list[dict[str, str]]]:
    rows = [list(row) for row in values if isinstance(row, (list, tuple))]
    if not rows:
        fail(f"{INBOX_TAB}-Tab ist leer oder nicht lesbar.")
    header = [norm(value) for value in rows[0]]
    result: list[dict[str, str]] = []
    for raw in rows[1:]:
        row = [norm(raw[index]) if index < len(raw) else "" for index in range(len(header))]
        if any(row):
            result.append(dict(zip(header, row)))
    return header, result


def normalized_candidate(item: dict[str, Any], created_at: str, pool_data: dict[str, Any]) -> dict[str, str]:
    normalized: dict[str, str] = {}
    for key, value in item.items():
        if value is None:
            normalized[str(key)] = ""
        elif isinstance(value, (str, int, float, bool)):
            normalized[str(key)] = str(value).strip()
        else:
            raise ValueError(f"Feld '{key}' ist kein skalarer Wert.")

    normalized["status"] = "review"
    normalized["created_at"] = norm(normalized.get("created_at")) or created_at
    normalized["url"] = canonical_url(normalized.get("url"))
    normalized["source_url"] = canonical_url(normalized.get("source_url")) or normalized["url"]

    local_visual_key = infer_event_visual_key(
        title=normalized.get("title", ""),
        description=normalized.get("description", ""),
        category=normalized.get("kategorie_suggestion", ""),
        location=normalized.get("location", ""),
    )
    model_visual_key = normalize_event_visual_key(normalized.get("visual_key", ""))
    visual_key_for_fit = local_visual_key or model_visual_key
    fit = infer_event_visual_fit(
        title=normalized.get("title", ""),
        description=normalized.get("description", ""),
        category=normalized.get("kategorie_suggestion", ""),
        location=normalized.get("location", ""),
        visual_key=visual_key_for_fit,
        visual_motif=normalized.get("visual_motif", ""),
        pool_payload=pool_data,
    )
    normalized["visual_key"] = fit.get("visual_key", "") or visual_key_for_fit
    normalized["visual_motif"] = fit.get("visual_motif", "")
    normalized["visual_asset_status"] = fit.get("visual_asset_status", "")
    return normalized


def validate_candidate(candidate: dict[str, str], index: int) -> str:
    missing = [key for key in REQUIRED_JSON_KEYS if not norm(candidate.get(key))]
    if missing:
        fail(f"Element #{index}: missing_required={','.join(missing)}")
    date_value = norm(candidate.get("date"))
    if not re.fullmatch(r"\d{4}-\d{2}-\d{2}", date_value):
        fail(f"Element #{index}: invalid_date_format")
    end_date = norm(candidate.get("endDate"))
    if end_date and not re.fullmatch(r"\d{4}-\d{2}-\d{2}", end_date):
        fail(f"Element #{index}: invalid_endDate_format")

    description = evaluate_event_description(
        {
            "title": candidate.get("title", ""),
            "description": candidate.get("description", ""),
            "date": date_value,
            "time": candidate.get("time", ""),
            "city": candidate.get("city", ""),
            "location": candidate.get("location", ""),
            "kategorie": candidate.get("kategorie_suggestion", ""),
        }
    )
    if description.blocking:
        return "description_quality:" + ",".join(description.issue_codes[:3])
    return ""


def prepare_rows(
    raw_items: list[dict[str, Any]],
    header: list[str],
    inbox_rows: list[dict[str, str]],
    existing_events: list[dict[str, str]],
    pool_data: dict[str, Any],
    created_at: str,
) -> tuple[list[list[str]], list[dict[str, str]], Counter[str]]:
    exact_inbox_fps = {
        fingerprint
        for row in inbox_rows
        if (fingerprint := dedupe_fp(row.get("title"), row.get("date"), row.get("location"))) != "||"
    }
    rows_to_append: list[list[str]] = []
    skipped_details: list[dict[str, str]] = []
    reasons: Counter[str] = Counter()

    def skip(item: dict[str, str], reason: str) -> None:
        reasons[reason] += 1
        skipped_details.append(
            {
                "reason": reason,
                "title": norm(item.get("title")),
                "date": norm(item.get("date")),
                "source_url": canonical_url(item.get("source_url")),
            }
        )

    for index, raw in enumerate(raw_items, start=1):
        if not isinstance(raw, dict):
            fail(f"Element #{index} ist kein Objekt.")
        try:
            candidate = normalized_candidate(raw, created_at, pool_data)
        except ValueError as error:
            fail(f"Element #{index}: {error}")

        invalid_reason = validate_candidate(candidate, index)
        if invalid_reason:
            skip(candidate, invalid_reason)
            continue

        event_match = find_best_event_match(candidate, existing_events)
        event_match_status = norm(event_match.get("status"))
        if event_match_status in {"exact", "same_identity"}:
            skip(candidate, "duplicate_existing_event:" + norm(event_match.get("match_type")))
            continue
        if event_match_status in {"possible", "identity_conflict"}:
            candidate = apply_event_identity_match(candidate, event_match, allow_same_identity=False)

        inbox_match = find_best_event_match(candidate, inbox_rows)
        if norm(inbox_match.get("status")) in {"exact", "same_identity"}:
            skip(candidate, "duplicate_open_inbox:" + norm(inbox_match.get("match_type")))
            continue

        fingerprint = dedupe_fp(candidate.get("title"), candidate.get("date"), candidate.get("location"))
        if fingerprint != "||" and fingerprint in exact_inbox_fps:
            skip(candidate, "duplicate_title_date_location")
            continue

        row_map = {column: candidate.get(column, "") for column in header}
        rows_to_append.append([row_map[column] for column in header])
        if fingerprint != "||":
            exact_inbox_fps.add(fingerprint)
        inbox_rows.append(candidate)

    return rows_to_append, skipped_details, reasons


def main() -> None:
    if not MANUAL_JSON_PATH.exists():
        fail(f"Manual-JSON fehlt: {MANUAL_JSON_PATH}")
    try:
        raw = json.loads(MANUAL_JSON_PATH.read_text(encoding="utf-8"))
    except Exception as error:
        fail(f"Manual-JSON ist ungültig: {error}")
    if not isinstance(raw, list):
        fail("data/inbox_manual.json muss ein JSON-Array sein.")
    if not raw:
        info("data/inbox_manual.json ist leer. Nichts zu importieren.")
        write_intake_summary(input_count=0, appended_count=0, skipped_count=0, skip_reasons={}, skipped_details=[])
        set_output("appended_count", "0")
        return

    sheet_id = os.environ.get("SHEET_ID", "").strip()
    service_account_json = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON", "").strip()
    if not sheet_id:
        fail("ENV SHEET_ID fehlt.")
    if not service_account_json:
        fail("ENV GOOGLE_SERVICE_ACCOUNT_JSON fehlt.")
    try:
        service_account_info = json.loads(service_account_json)
    except Exception:
        fail("GOOGLE_SERVICE_ACCOUNT_JSON ist kein gültiges JSON.")

    credentials = service_account.Credentials.from_service_account_info(
        service_account_info,
        scopes=["https://www.googleapis.com/auth/spreadsheets"],
    )
    service = build("sheets", "v4", credentials=credentials, cache_discovery=False)

    inbox_response = service.spreadsheets().values().get(
        spreadsheetId=sheet_id,
        range=f"{INBOX_TAB}!A:ZZ",
    ).execute()
    header, inbox_rows = sheet_rows(inbox_response.get("values", []) or [])
    missing_columns = [column for column in INBOX_COLUMNS if column not in header]
    if missing_columns:
        fail(f"Inbox-Header unvollständig. Fehlende Spalten: {missing_columns}")

    missing_visual = [column for column in OPTIONAL_VISUAL_INBOX_COLUMNS if column not in header]
    if missing_visual:
        info(f"Optionale Visual-Review-Spalten fehlen noch und werden daher nicht befüllt: {missing_visual}")
    missing_identity = [column for column in OPTIONAL_IDENTITY_INBOX_COLUMNS if column not in header]
    if missing_identity:
        info(f"Optionale Dubletten-Evidence-Spalten fehlen und werden dynamisch in der Steuerzentrale ergänzt: {missing_identity}")

    try:
        pool_data = json.loads(EVENT_VISUAL_POOL_PATH.read_text(encoding="utf-8"))
    except Exception as error:
        fail(f"Event-Visual-Pool ist ungültig oder nicht lesbar: {error}")
    visual_key_options = sorted((pool_data.get("pools") or {}).keys())
    if not visual_key_options:
        fail("Event-Visual-Pool enthält keine auswählbaren Keys unter 'pools'.")

    sheet_meta = service.spreadsheets().get(spreadsheetId=sheet_id).execute()
    inbox_sheet_id = next(
        (
            sheet.get("properties", {}).get("sheetId")
            for sheet in sheet_meta.get("sheets", [])
            if sheet.get("properties", {}).get("title") == INBOX_TAB
        ),
        None,
    )
    if inbox_sheet_id is None:
        fail(f"{INBOX_TAB}-Tab wurde in der Sheet-Metadatenabfrage nicht gefunden.")
    visual_key_column = header.index("visual_key")
    service.spreadsheets().batchUpdate(
        spreadsheetId=sheet_id,
        body={
            "requests": [
                {
                    "setDataValidation": {
                        "range": {
                            "sheetId": inbox_sheet_id,
                            "startRowIndex": 1,
                            "startColumnIndex": visual_key_column,
                            "endColumnIndex": visual_key_column + 1,
                        },
                        "rule": {
                            "condition": {
                                "type": "ONE_OF_LIST",
                                "values": [{"userEnteredValue": key} for key in visual_key_options],
                            },
                            "strict": True,
                            "showCustomUi": True,
                        },
                    }
                }
            ]
        },
    ).execute()
    info(f"visual_key-Dropdown in {INBOX_TAB} gesetzt: {len(visual_key_options)} Optionen")

    events_response = service.spreadsheets().values().get(
        spreadsheetId=sheet_id,
        range=f"{EVENTS_TAB}!A:AZ",
    ).execute()
    try:
        existing_events = event_rows_from_sheet_values(events_response.get("values", []) or [])
    except ValueError as error:
        fail(f"{EVENTS_TAB}-Bestand ist nicht sicher auswertbar: {error}")
    info(f"Event-Identitätsbasis geladen: {len(existing_events)} Events")

    created_at = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    rows_to_append, skipped_details, skip_reasons = prepare_rows(
        raw,
        header,
        inbox_rows,
        existing_events,
        pool_data,
        created_at,
    )

    skipped_count = len(skipped_details)
    info(
        "Append vorbereitet: "
        f"appended={len(rows_to_append)} skipped={skipped_count} "
        f"skip_reasons={json.dumps(dict(skip_reasons), ensure_ascii=False)}"
    )
    for item in skipped_details[:40]:
        info(json.dumps(item, ensure_ascii=False))

    if rows_to_append:
        service.spreadsheets().values().append(
            spreadsheetId=sheet_id,
            range=f"{INBOX_TAB}!A1",
            valueInputOption="RAW",
            insertDataOption="INSERT_ROWS",
            body={"values": rows_to_append},
        ).execute()
        info(f"Append OK: appended={len(rows_to_append)} skipped={skipped_count}")
    else:
        info(f"Keine neuen Zeilen zum Appenden. skipped={skipped_count}")

    write_intake_summary(
        input_count=len(raw),
        appended_count=len(rows_to_append),
        skipped_count=skipped_count,
        skip_reasons=dict(skip_reasons),
        skipped_details=skipped_details[:80],
        event_identity_basis_count=len(existing_events),
    )
    set_output("appended_count", str(len(rows_to_append)))


if __name__ == "__main__":
    main()
