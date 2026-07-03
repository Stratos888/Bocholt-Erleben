#!/usr/bin/env python3
"""Fail-fast CSS governance audit for Bocholt erleben.

The audit intentionally checks structure, not visual quality:
- /css/style.css stays the only public CSS entrypoint.
- style.css remains import-only and owns the CSS owner order.
- HTML files do not drift to old cache-busters or direct split-CSS links.
- large CSS owner files cannot grow silently without an explicit threshold decision.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
CSS_ENTRY_VERSION = "2026-06-22-css-governance-v1"
STYLE_HREF = f"/css/style.css?v={CSS_ENTRY_VERSION}"

EXPECTED_IMPORTS = [
    "base.css",
    "pages.css",
    "components.css",
    "home.css",
    "today.css",
    "overlays.css",
]

CSS_LINE_LIMITS = {
    "style.css": 40,
    "base.css": 950,
    "pages.css": 3925,
    "components.css": 2200,
    "home.css": 5000,
    "today.css": 1600,
    "overlays.css": 3600,
    "legacy.css": 50,
}

BINARY_SUFFIXES = {
    ".webp", ".png", ".jpg", ".jpeg", ".gif", ".ico", ".pdf", ".zip",
    ".woff", ".woff2", ".ttf", ".otf",
}

STYLESHEET_LINK_RE = re.compile(
    r"<link\b(?=[^>]*\brel=[\"\']stylesheet[\"\'])(?=[^>]*\bhref=[\"\']([^\"\']+)[\"\'])[^>]*>",
    re.IGNORECASE,
)

IMPORT_RE = re.compile(
    r'^@import\s+url\(\"/css/([^\"]+)\?v=([^\"]+)\"\);$'
)


def rel(path: Path) -> str:
    return path.relative_to(ROOT).as_posix()


def strip_css_comments(css: str) -> str:
    return re.sub(r"/\*.*?\*/", "", css, flags=re.DOTALL)


def iter_html_files() -> list[Path]:
    return sorted(
        p for p in ROOT.rglob("*.html")
        if ".git" not in p.parts
    )


def audit_style_entrypoint(errors: list[str]) -> None:
    path = ROOT / "css" / "style.css"
    if not path.exists():
        errors.append("Missing css/style.css")
        return

    raw = path.read_text(encoding="utf-8")
    stripped = strip_css_comments(raw)
    non_empty_lines = [
        line.strip()
        for line in stripped.splitlines()
        if line.strip()
    ]

    imports: list[tuple[str, str]] = []
    for line in non_empty_lines:
        match = IMPORT_RE.match(line)
        if not match:
            errors.append(
                f"css/style.css must contain only @import lines and comments; invalid line: {line}"
            )
            continue
        imports.append((match.group(1), match.group(2)))

    import_names = [name for name, _version in imports]
    if import_names != EXPECTED_IMPORTS:
        errors.append(
            "css/style.css import order drifted. "
            f"Expected {EXPECTED_IMPORTS}, got {import_names}"
        )

    for name, version in imports:
        if version != CSS_ENTRY_VERSION:
            errors.append(
                f"css/style.css import for {name} uses ?v={version}, expected ?v={CSS_ENTRY_VERSION}"
            )


def audit_html_stylesheet_links(errors: list[str]) -> None:
    for html in iter_html_files():
        text = html.read_text(encoding="utf-8", errors="ignore")
        stylesheet_hrefs = [match.group(1) for match in STYLESHEET_LINK_RE.finditer(text)]

        if not stylesheet_hrefs:
            # theme-lab and other deliberate no-style helper pages may exist.
            continue

        if stylesheet_hrefs != [STYLE_HREF]:
            errors.append(
                f"{rel(html)} must load exactly {STYLE_HREF} as its only stylesheet; "
                f"found {stylesheet_hrefs}"
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


def audit_no_direct_split_css_links(errors: list[str]) -> None:
    direct_css_link_re = re.compile(r'href=[\"\'](/css/(?!style\.css)[^\"\']+\.css(?:\?v=[^\"\']*)?)[\"\']')
    for html in iter_html_files():
        text = html.read_text(encoding="utf-8", errors="ignore")
        for match in direct_css_link_re.finditer(text):
            errors.append(
                f"{rel(html)} links split CSS directly: {match.group(1)}. "
                "Public pages must use /css/style.css as the entrypoint."
            )


def main() -> int:
    errors: list[str] = []

    audit_style_entrypoint(errors)
    audit_html_stylesheet_links(errors)
    audit_no_direct_split_css_links(errors)
    audit_css_line_budgets(errors)

    if errors:
        print("=== CSS Governance Audit: FAILED ===")
        for error in errors:
            print(f"- {error}")
        return 1

    print("=== CSS Governance Audit: OK ===")
    print(f"CSS entrypoint: {STYLE_HREF}")
    print("Imports:", ", ".join(EXPECTED_IMPORTS))
    return 0


if __name__ == "__main__":
    sys.exit(main())
