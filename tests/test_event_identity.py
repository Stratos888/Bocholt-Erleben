from __future__ import annotations

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "scripts"))

from event_identity import apply_event_identity_match, event_rows_from_sheet_values, find_best_event_match  # noqa: E402


def main() -> None:
    payload = json.loads((ROOT / "tests" / "fixtures" / "event_identity_cases.json").read_text(encoding="utf-8"))
    for case in payload["cases"]:
        match = find_best_event_match(case["candidate"], case["existing"])
        assert match["status"] == case["expected_status"], (case["name"], match)
        assert match["matched_event_id"] == case["expected_id"], (case["name"], match)
        assert float(match["score"]) >= float(case["minimum_score"]), (case["name"], match)

    cityart = payload["cases"][0]
    match = find_best_event_match(cityart["candidate"], cityart["existing"])
    enriched = apply_event_identity_match(cityart["candidate"], match)
    assert enriched["hard_duplicate"] is True
    assert enriched["duplicate_status"] == "review"

    distinct = dict(cityart["candidate"], matched_event_id=cityart["expected_id"], duplicate_status="distinct")
    distinct_enriched = apply_event_identity_match(distinct, match)
    assert distinct_enriched["hard_duplicate"] is False
    assert distinct_enriched["duplicate_status"] == "distinct"

    resume = next(case for case in payload["cases"] if case["name"] == "same_id_resume")
    resume_match = find_best_event_match(resume["candidate"], resume["existing"])
    stale_resume = dict(resume["candidate"], matched_event_id="stale", duplicate_status="review", duplicate_reason="stale")
    resume_enriched = apply_event_identity_match(stale_resume, resume_match, allow_same_identity=True)
    assert resume_enriched["hard_duplicate"] is False
    assert resume_enriched["matched_event_id"] == ""
    assert resume_enriched["duplicate_status"] == ""

    no_match = apply_event_identity_match(
        {"matched_event_id": "stale", "duplicate_status": "review", "duplicate_reason": "stale"},
        {"status": "none"},
    )
    assert no_match["matched_event_id"] == "" and no_match["duplicate_status"] == ""

    intake_script_text = (ROOT / "scripts" / "manual_ki_event_intake.py").read_text(encoding="utf-8")
    assert '{"possible", "exact", "same_identity", "identity_conflict"}' in intake_script_text

    rows = event_rows_from_sheet_values([
        ["id", "title", "date", "city", "location", "url"],
        ["x", "Test", "2026-10-10", "Bocholt", "Ort", "https://example.test/x"],
    ])
    assert rows[0]["id"] == "x"

    workflow = (ROOT / ".github" / "workflows" / "weekly-ki-websearch-to-manual-inbox.yml").read_text(encoding="utf-8")
    intake_script = (ROOT / "scripts" / "manual_ki_event_intake.py").read_text(encoding="utf-8")
    assert "python scripts/manual_ki_event_intake.py" in workflow
    assert "EVENTS_TAB: Events" in workflow
    assert "find_best_event_match(candidate, existing_events)" in intake_script
    assert "apply_event_identity_match(candidate, event_match" in intake_script

    sheet_source = (ROOT / "api" / "control-center" / "_sheet_inbox_source.php").read_text(encoding="utf-8")
    current_source = (ROOT / "api" / "control-center" / "_inbox_decision_writeback.php").read_text(encoding="utf-8")
    action = (ROOT / "api" / "control-center" / "action.php").read_text(encoding="utf-8")
    assert "be_cc_event_identity_enrich($payload, $eventInventory, true)" in sheet_source
    assert "be_cc_event_identity_enrich_current(array_replace($stored, $row), true)" in current_source
    assert "$action === 'approve'" in action and "be_cc_inbox_direct_current_source($case)" in action

    print("Event identity Python contract: OK")


if __name__ == "__main__":
    main()
