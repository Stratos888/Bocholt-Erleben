#!/usr/bin/env python3
"""Semantic CSS architecture audit for Bocholt erleben.

The audit protects the architecture without coupling it to one deployment path:
- css/style.css remains the single public CSS entrypoint.
- style.css remains import-only and owns the CSS owner order.
- Root deployments and subdirectory deployments may use equivalent relative paths.
- HTML files do not drift to old cache-busters or direct owner-CSS links.
- Large CSS owner files cannot grow silently without an explicit threshold decision.
"""

from __future__ import annotations

import posixpath
import re
import sys
from pathlib import Path
from urllib.parse import parse_qs, urlsplit

ROOT = Path(__file__).resolve().parents[1]
CSS_ENTRY_VERSION = "2026-06-22-css-governance-v1"
STYLE_TARGET = "css/style.css"
EVENT_DETAIL_PAGE_CSS_VERSION = "2026-07-03-event-detail-scroll-share-v1"
EVENT_DETAIL_PAGE_TARGET = "css/pages.css"

EXPECTED_IMPORTS = [
    "base.css",
    "pages.css",
    "components.css",
    "home.css",
    "today.css",
    "overlays.css",
    "control-center.css",
    "control-center-final.css",
    "control-center-editorial.css",
]

CSS_LINE_LIMITS = {
    "style.css": 40,
    "base.css": 950,
    "pages.css": 3950,
    "components.css": 2200,
    "home.css": 5000,
    "today.css": 1600,
    "overlays.css": 3600,
    "control-center.css": 1200,
    "control-center-final.css": 300,
    "control-center-editorial.css": 100,
    "legacy.css": 50,
}

STYLESHEET_LINK_RE = re.compile(
    r"<link\b(?=[^>]*\brel=[\"']stylesheet[\"'])(?=[^>]*\bhref=[\"']([^\"']+)[\"'])[^>]*>",
    re.IGNORECASE,
)

# Accept equivalent CSS-local forms such as ./base.css and /css/base.css.
# The target is normalized and validated below; arbitrary remote imports remain forbidden.
IMPORT_RE = re.compile(
    r"^@import\s+(?:url\()?\s*[\"']?([^\"')\s]+)[\"']?\s*\)?\s*;$",
    re.IGNORECASE,
)


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def strip_css_comments(css: str) -> str:
    return re.sub(r"/\*.*?\*/", "", css, flags=re.DOTALL)


def iter_html_files() -> list[Path]:
    return sorted(p for p in ROOT.rglob("*.html") if ".git" not in p.parts)


def split_asset_reference(value: str) -> tuple[str, str]:
    parsed = urlsplit(value)
    versions = parse_qs(parsed.query).get("v", [])
    version = versions[0] if len(versions) == 1 else ""
    return parsed.path, version


def normalize_html_asset(html: Path, href: str) -> tuple[str, str]:
    path, version = split_asset_reference(href)
    if path.startswith("//") or urlsplit(href).scheme:
        return f"external:{href}", version
    if path.startswith("/"):
        normalized = posixpath.normpath(path.lstrip("/"))
    else:
        normalized = posixpath.normpath(posixpath.join(rel(html.parent), path))
    return normalized, version


def normalize_import_target(value: str) -> tuple[str, str]:
    path, version = split_asset_reference(value)
    if path.startswith("//") or urlsplit(value).scheme:
        return f"external:{value}", version
    if path.startswith("/css/"):
        normalized = posixpath.normpath(path.removeprefix("/css/"))
    else:
        normalized = posixpath.normpath(path.removeprefix("./"))
    return normalized, version


def audit_style_entrypoint(errors: list[str]) -> None:
    path = ROOT / STYLE_TARGET
    if not path.exists():
        errors.append(f"Missing {STYLE_TARGET}")
        return

    raw = path.read_text(encoding="utf-8")
    stripped = strip_css_comments(raw)
    non_empty_lines = [line.strip() for line in stripped.splitlines() if line.strip()]

    imports: list[tuple[str, str]] = []
    for line in non_empty_lines:
        match = IMPORT_RE.fullmatch(line)
        if not match:
            errors.append(
                "css/style.css must contain only @import lines and comments; "
                f"invalid line: {line}"
            )
            continue
        target, version = normalize_import_target(match.group(1))
        if target.startswith("external:") or "/" in target or target.startswith(".."):
            errors.append(f"css/style.css import escapes the css owner directory: {match.group(1)}")
            continue
        imports.append((target, version))

    import_names = [name for name, _version in imports]
    if import_names != EXPECTED_IMPORTS:
        errors.append(
            "css/style.css import order drifted. "
            f"Expected {EXPECTED_IMPORTS}, got {import_names}"
        )

    for name, version in imports:
        if version != CSS_ENTRY_VERSION:
            errors.append(
                f"css/style.css import for {name} uses ?v={version or '<missing>'}, "
                f"expected ?v={CSS_ENTRY_VERSION}"
            )
        if not (ROOT / "css" / name).is_file():
            errors.append(f"css/style.css imports missing owner file css/{name}")


def is_generated_event_detail_page(html: Path) -> bool:
    return html.name == "index.html" and (html.parent / ".generated-event-detail").exists()


def audit_html_stylesheet_links(errors: list[str]) -> None:
    for html in iter_html_files():
        text = html.read_text(encoding="utf-8", errors="ignore")
        raw_hrefs = [match.group(1) for match in STYLESHEET_LINK_RE.finditer(text)]
        if not raw_hrefs:
            # theme-lab and other deliberate no-style helper pages may exist.
            continue

        resolved = [normalize_html_asset(html, href) for href in raw_hrefs]
        expected = [(STYLE_TARGET, CSS_ENTRY_VERSION)]
        if is_generated_event_detail_page(html):
            expected.append((EVENT_DETAIL_PAGE_TARGET, EVENT_DETAIL_PAGE_CSS_VERSION))

        if resolved != expected:
            errors.append(
                f"{rel(html)} must load stylesheet targets {expected}; "
                f"found {list(zip(raw_hrefs, resolved))}"
            )


def audit_css_line_budgets(errors: list[str]) -> None:
    css_dir = ROOT / "css"
    for name, max_lines in CSS_LINE_LIMITS.items():
        path = css_dir / name
        if not path.exists():
            errors.append(f"Missing css/{name}")
            continue

        line_count = len(path.read_text(encoding="utf-8", errors="ignore").splitlines())
        if line_count > max_lines:
            errors.append(
                f"css/{name} has {line_count} lines, allowed max is {max_lines}. "
                "Create/choose a clearer owner or consciously raise the limit with documentation."
            )


def main() -> int:
    errors: list[str] = []
    audit_style_entrypoint(errors)
    audit_html_stylesheet_links(errors)
    audit_css_line_budgets(errors)

    if errors:
        print("=== CSS Governance Audit: FAILED ===")
        for error in errors:
            print(f"- {error}")
        return 1

    print("=== CSS Governance Audit: OK ===")
    print(f"CSS entrypoint target: {STYLE_TARGET}?v={CSS_ENTRY_VERSION}")
    print("Accepted HTML paths: root-relative or deployment-relative equivalents")
    print("Imports:", ", ".join(EXPECTED_IMPORTS))
    return 0


if __name__ == "__main__":
    sys.exit(main())
