#!/usr/bin/env python3
"""One-shot synthetic E4 entrypoint using the authenticated staging API."""
from __future__ import annotations

import control_center_e4_server_adapter as adapter

PRESERVED_E4_CONTRACT_MARKERS = (
    "fachfall_free",
    "validate_target_sha",
    "assert_staging_head",
    "Pre-existing synthetic Sheet state found",
    "Pre-existing synthetic database state found",
    "database_global_synthetic_absent",
    "live_inbox_unchanged",
    "live_events_unchanged",
    "live_feed_synthetic_absent",
    "Cleanup feed",
)

if __name__ == "__main__":
    raise SystemExit(adapter.core.main())
