#!/usr/bin/env python3
from __future__ import annotations

import datetime as dt
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"
if str(SCRIPTS) not in sys.path:
    sys.path.insert(0, str(SCRIPTS))

import content_coverage_metrics as metrics


class Obj:
    def __init__(self, **kwargs):
        self.__dict__.update(kwargs)


class ContentCoverageMetricsTest(unittest.TestCase):
    def test_response_telemetry_reads_usage_and_web_search_calls(self):
        response = Obj(
            usage=Obj(
                input_tokens=120,
                output_tokens=30,
                total_tokens=150,
                input_tokens_details=Obj(cached_tokens=20),
                output_tokens_details=Obj(reasoning_tokens=9),
            ),
            output=[
                Obj(
                    type="web_search_call",
                    action=Obj(sources=[Obj(url="https://a.example/x"), Obj(url="https://b.example/y")]),
                ),
                Obj(type="web_search_call", action=Obj(sources=[Obj(url="https://a.example/x")])),
                Obj(
                    type="message",
                    content=[Obj(annotations=[Obj(url_citation=Obj(url="https://c.example/z"))])],
                ),
            ],
        )
        actual = metrics.response_telemetry(response)
        self.assertEqual(actual["responses_api_calls"], 1)
        self.assertEqual(actual["web_search_calls"], 2)
        self.assertEqual(actual["cited_web_sources"], 3)
        self.assertEqual(actual["total_tokens"], 150)
        self.assertEqual(actual["cached_input_tokens"], 20)
        self.assertEqual(actual["reasoning_tokens"], 9)

    def test_weekly_augmentation_adds_yield_lead_time_and_known_target_baseline(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "diag.json"
            path.write_text(
                json.dumps(
                    {
                        "generated_at_utc": "2026-08-25T05:44:50Z",
                        "model": "gpt-5.4",
                        "raw_candidates_returned": 3,
                        "raw_source_candidates_returned": 2,
                        "source_candidates_added": 1,
                        "source_candidate_reasons": {"added": 1, "known_domain": 1},
                        "selected_candidates": 2,
                        "dropped_candidates": 1,
                        "candidate_diagnostics": [
                            {"reason": "selected", "source_url": "https://city.example/a"},
                            {"reason": "past_event", "source_url": "https://city.example/b"},
                            {"reason": "selected", "source_url": "https://other.example/c"},
                        ],
                        "selected_candidate_summaries": [
                            {"date": "2026-08-30"},
                            {"date": "2026-09-24"},
                        ],
                        "coverage_audit": [
                            {"id": "a", "status": "IN_EVENTS"},
                            {"id": "b", "status": "MISSING_FROM_RAW"},
                            {"id": "c", "status": "PAST_TARGET"},
                        ],
                    }
                ),
                encoding="utf-8",
            )
            measurement = metrics.augment_weekly_diagnostics(
                path,
                {"responses_api_calls": 1, "web_search_calls": 2, "total_tokens": 100},
            )
            self.assertEqual(measurement["candidate_yield"]["selected_yield"], 0.6667)
            self.assertEqual(measurement["source_learning_yield"]["added_yield"], 0.5)
            self.assertEqual(measurement["discovery_lead_time_days"]["min"], 5)
            self.assertEqual(measurement["discovery_lead_time_days"]["max"], 30)
            self.assertEqual(measurement["known_target_baseline"]["eligible_targets"], 2)
            self.assertEqual(measurement["known_target_baseline"]["missing_target_ids"], ["b"])
            self.assertEqual(
                measurement["cost_and_cadence"]["additional_paid_calls_added_by_measurement"],
                0,
            )

    def test_workflows_keep_existing_cadence_and_wire_measurement(self):
        weekly = (
            ROOT / ".github" / "workflows" / "weekly-ki-websearch-to-manual-inbox.yml"
        ).read_text(encoding="utf-8")
        growth = (
            ROOT / ".github" / "workflows" / "growth-intelligence-backlog.yml"
        ).read_text(encoding="utf-8")
        self.assertEqual(weekly.count('- cron: "15 5 * * 2"'), 1)
        self.assertEqual(growth.count('- cron: "20 5 * * 1"'), 1)
        self.assertIn("response_telemetry", weekly)
        self.assertIn("augment_weekly_diagnostics", weekly)
        self.assertIn("build_portfolio_coverage", growth)
        self.assertIn("augment_growth_summary", growth)
        self.assertNotIn("OPENAI_API_KEY", growth)

    def test_growth_portfolio_coverage_finds_activity_and_known_target_gaps(self):
        gsc_rows = [
            {"query": "indoor kinder bocholt", "impressions": 100, "clicks": 5},
            {"query": "schwimmen bocholt", "impressions": 80, "clicks": 4},
        ]
        ga4_rows = [
            {"landing_page": "/aktivitaeten/", "channel": "Organic Search", "sessions": 12},
        ]
        value_rows = [
            {"metric_key": "activity_detail_view", "entity_title": "Indoor spielen", "total": 9},
        ]
        events = [
            ["id", "title", "date", "location", "source_url"],
            ["e1", "Familientag", "2026-09-01", "Bocholt", "https://city.example/familientag"],
        ]
        offers = {
            "offers": [
                {
                    "id": "o1",
                    "title": "Indoor spielen",
                    "kategorie": "Freizeit & Familie",
                    "tags": ["Kinder", "Indoor"],
                    "filter": {"proximity": ["Direkt in Bocholt"]},
                },
            ]
        }
        targets = [
            {"id": "t1", "title": "Familientag", "expected_date": "2026-09-01"},
            {"id": "t2", "title": "Stadtfest", "expected_date": "2026-09-10"},
        ]

        def infer(query):
            if "schwimmen" in query:
                return {"key": "swimming", "label": "Schwimmen"}
            return {"key": "family-kids", "label": "Familie"}

        result = metrics.build_portfolio_coverage(
            gsc_rows,
            events,
            offers,
            targets,
            ga4_rows=ga4_rows,
            value_rows=value_rows,
            infer_intent=infer,
            canonical_topic=lambda value: value,
            min_impressions=40,
            today=dt.date(2026, 8, 30),
        )
        clusters = {row["intent_key"]: row for row in result["demand_clusters"]}
        self.assertEqual(clusters["family-kids"]["coverage_state"], "covered")
        self.assertEqual(clusters["swimming"]["coverage_state"], "gap")
        self.assertEqual(result["demand_gap_intents"], ["swimming"])
        self.assertEqual(result["usage_evidence"]["organic_or_unassigned_sessions"], 12)
        self.assertEqual(result["usage_evidence"]["value_metric_totals"]["activity_detail_view"], 9)
        self.assertEqual(result["event_inventory"]["known_targets_present"], 1)
        self.assertEqual(result["event_inventory"]["known_targets_missing"], 1)
        self.assertEqual(result["cost"]["recurring_cost_delta"], "neutral")


if __name__ == "__main__":
    unittest.main()
