#!/usr/bin/env python3
"""Stable CLI entrypoint for the event builder.

The implementation lives in ``event_builder.py`` so the real build path can be
exercised by local contract tests without touching generated repository files.
"""
from __future__ import annotations

import os
import re
from pathlib import Path

import event_builder


EVENT_TIME_RE = re.compile(
    r"^\s*(\d{1,2})[:.](\d{2})(?:\s*[-–]\s*(\d{1,2})[:.](\d{2}))?(?:\s*Uhr)?\s*$",
    re.IGNORECASE,
)


def configure() -> None:
    """Apply the public time contract and optional test-only file overrides."""
    event_builder.RE_TIME = EVENT_TIME_RE

    tsv_override = os.environ.get("BE_EVENT_BUILDER_TSV_PATH", "").strip()
    json_override = os.environ.get("BE_EVENT_BUILDER_JSON_PATH", "").strip()
    if tsv_override:
        event_builder.TSV_PATH = Path(tsv_override)
    if json_override:
        event_builder.OUT_JSON_PATH = Path(json_override)


def main() -> None:
    configure()
    event_builder.main()


if __name__ == "__main__":
    main()
