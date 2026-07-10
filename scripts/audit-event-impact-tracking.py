#!/usr/bin/env python3
"""Static guard for the Event Impact Backbone.

The guard verifies that public event detail pages, in-app event interactions,
first-party value metrics and organizer reporting stay wired to the same
object-exact impact contract.
"""

from __future__ import annotations

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

CHECKS = [
    (
        "api/value-track.php",
        [
            "source_context VARCHAR(64)",
            "idx_value_metric_daily_source_context",
            "'event_share_click'",
            "'event_copy_link'",
            "$sourceContext",
        ],
    ),
    (
        "config.js",
        [
            "source_context: sourceContext",
            "function trackShareAction",
            "window.BEAnalytics.trackShareAction",
        ],
    ),
    (
        "scripts/build-event-detail-pages.py",
        [
            "/config.js?v=2026-07-03-event-impact-v1",
            "data-impact-object-type=\"event\"",
            "data-impact-source-context=\"public_detail_page\"",
            "trackValueMetric('event_detail_view'",
            "trackValueMetric('event_copy_link'",
            "data-impact-outbound-type=\"website\"",
            "data-impact-outbound-type=\"maps\"",
        ],
    ),
    (
        "js/details.js",
        [
            "sourceContext: \"event_panel\"",
            "event_share_click",
            "event_copy_link",
        ],
    ),
    (
        "js/events.js",
        [
            "sourceContext: \"event_card\"",
            "event_share_click",
            "event_copy_link",
        ],
    ),
    (
        "js/today-home.js",
        [
            "sourceContext: \"today_card\"",
        ],
    ),
    (
        "intern/seo-dashboard/index.php",
        [
            "source_context VARCHAR(64)",
            "idx_value_metric_daily_source_context",
            "share_clicks",
            "copy_link_clicks",
        ],
    ),
    (
        "api/organizer-portal/me.php",
        [
            "share_clicks",
            "copy_link_clicks",
            "opm_fetch_impact_items",
            "opm_attach_submission_impact",
            "impact_metrics",
        ],
    ),
    (
        "js/organizer-portal.js",
        [
            "impactShareTotal",
            "buildOrganizerImpactScopes",
            "renderImpactScopeControl",
            "Gesamtwirkung deiner Inhalte",
            "renderSubmissionImpactHtml",
            "organizerHasEventImpactContext",
            "buildOrganizerSubmissionGroups",
            "setSubmissionsListOpen",
            "organizer-dashboard-submissions-toggle",
            "hasEventImpactContext",
            "Teilungen",
            "Website/Info",
            "Keine Aktion erforderlich",
            "Aktion erforderlich",
            "Offene Aktionen anzeigen",
            "Entwürfe anzeigen",
            "Entwürfe",
            "Weiterbearbeiten",
            "organizer-dashboard-new-submission-cta",
            "dataset.organizerOpenSubmissions",
            "draftOpen",
            "actionRequired",
            "const defaultOpen = Boolean(isSingleStatusView);",
            "published_content",
            "Wirkung dieses Inhalts",
            "organizer-impact-total",
            "renderImpactExplainerDetails",
            "Was wird gezählt?",
            "organizer-tariff-details",
            "buildOrganizerIncludedCompactRows",
        ],
    ),
    (
        "css/pages.css",
        [
            "ORGANIZER_DASHBOARD_STATUS_SEMANTICS_FINAL_V1",
            "#organizer-dashboard-submissions-list[hidden]",
            "organizer-impact-scope__select",
            "#organizer-dashboard-next-step:not([hidden])",
        ],
    ),
    (
        "fuer-veranstalter/dashboard/index.html",
        [
            "Deine Wirkung",
            "auf Bocholt erleben",
            "organizer-dashboard-impact-card",
            "organizer-dashboard-submissions-toggle",
            "2026-07-06-organizer-dashboard-status-semantics-v1",
        ],
    ),
]


def main() -> int:
    errors: list[str] = []
    for rel_path, needles in CHECKS:
        path = ROOT / rel_path
        if not path.exists():
            errors.append(f"missing file: {rel_path}")
            continue
        text = path.read_text(encoding="utf-8")
        for needle in needles:
            if needle not in text:
                errors.append(f"{rel_path}: missing {needle!r}")

    if errors:
        print("❌ Event Impact Tracking Guard failed")
        for error in errors:
            print(f"- {error}")
        return 1

    print("✅ Event Impact Tracking Guard OK")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
