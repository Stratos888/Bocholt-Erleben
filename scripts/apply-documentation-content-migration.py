#!/usr/bin/env python3
"""One-time content migration after role-based documentation moves."""
from __future__ import annotations

import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]

OLD_TO_NEW = {
    "DEBUG.md": "docs/playbooks/ui-debug.md",
    "EVENT_DESCRIPTION_STANDARD.md": "docs/contracts/event-description-standard.md",
    "VISUAL_WORKFLOW.md": "docs/reference/visual/legacy-visual-workflow.md",
    "ACTIVITY_SEASONAL_HIGHLIGHTS.md": "docs/reference/activities/seasonal-highlights.md",
    "ACTIVITY_VISUAL_PROMPT_KIT.md": "docs/reference/visual/activity-prompt-kit.md",
    "ACTIVITY_VISUAL_SOURCING_MATRIX.md": "docs/reference/visual/activity-sourcing-matrix.md",
    "ACTIVITY_VISUAL_WORKFLOW.md": "docs/reference/visual/activity-workflow.md",
    "BATHING_WATER_GUARD_V1.md": "docs/reference/bathing-water/guard-v1.md",
    "BATHING_WATER_SOURCE_DISCOVERY_V2.md": "docs/reference/bathing-water/source-discovery-v2.md",
    "BATHING_WATER_STATUS_PROOF.md": "docs/evidence/bathing-water-status-proof.md",
    "BROWSER_SMOKE_SYSTEM.md": "docs/contracts/browser-smoke-system.md",
    "EVENT_IMPACT_TRACKING.md": "docs/contracts/event-impact-tracking.md",
    "MAIL_SYSTEM.md": "docs/reference/mail/legacy-mail-system.md",
    "bocholt-erleben_eventsuche_regelwerk_v3.md": "docs/reference/event-search/legacy-rulebook-v3.md",
    "eventsuche_quellenregister_v1.md": "docs/reference/event-search/source-registry-v1.md",
    "STEUERZENTRALE_ABNAHMEMATRIX.md": "docs/reference/control-center/acceptance-matrix.md",
    "STEUERZENTRALE_BACKEND_GAP_ANALYSE.md": "docs/reference/control-center/backend-gap-analysis.md",
    "STEUERZENTRALE_FREEZE_2026-07-10.md": "docs/archive/control-center/freeze-2026-07-10.md",
    "STEUERZENTRALE_GESAMTPROJEKT_INTEGRATION.md": "docs/reference/control-center/project-integration.md",
    "STEUERZENTRALE_IMPLEMENTIERUNGSSTATUS.md": "docs/archive/control-center/implementation-status-2026-07-10.md",
    "STEUERZENTRALE_INFORMATIONARCHITEKTUR.md": "docs/reference/control-center/information-architecture.md",
    "STEUERZENTRALE_SCREENVERTRAG.md": "docs/reference/control-center/screen-contract.md",
    "STEUERZENTRALE_SIMULATIONSBERICHT.md": "docs/archive/control-center/simulation-report-2026-07-10.md",
    "STEUERZENTRALE_VORGANGSKATALOG.md": "docs/reference/control-center/case-catalog.md",
    "STEUERZENTRALE_ZIELBILD.md": "docs/reference/control-center/target-state.md",
}


def tracked_files() -> list[str]:
    return subprocess.run(
        ["git", "ls-files"], cwd=ROOT, check=True, capture_output=True, text=True
    ).stdout.splitlines()


def replace_paths() -> None:
    for relative in tracked_files():
        path = ROOT / relative
        if not path.is_file() or path.name == Path(__file__).name:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except UnicodeDecodeError:
            continue
        updated = text
        for old, new in OLD_TO_NEW.items():
            updated = updated.replace(old, new)
        if updated != text:
            path.write_text(updated, encoding="utf-8")


def prepend(relative: str, marker: str, text: str) -> None:
    path = ROOT / relative
    current = path.read_text(encoding="utf-8")
    if marker not in current:
        path.write_text(f"> {text}\n<!-- {marker} -->\n\n{current}", encoding="utf-8")


def main() -> None:
    replace_paths()

    product = ROOT / "Produktvertrag.md"
    text = product.read_text(encoding="utf-8")
    text = re.sub(r"\A<!-- === BEGIN FILE:.*?=== -->\s*", "", text, flags=re.S)
    text = re.sub(r"\s*<!-- === END FILE:.*?=== -->\s*\Z", "\n", text, flags=re.S)
    text = re.sub(
        r"\n<!-- === BEGIN BLOCK: PRODUKTVERTRAG_PUBLIC_LEGAL_ALIGNMENT_2026_06_29.*?"
        r"<!-- === END BLOCK: PRODUKTVERTRAG_PUBLIC_LEGAL_ALIGNMENT_2026_06_29 === -->\n",
        "\n", text, flags=re.S,
    )
    text = text.replace(
        "**Optimierung:** KI-lesbar, eindeutig befolgbar, produkt- und umsetzungsleitend",
        "**Rolle:** verbindlicher Vertrag der bereits gültigen Produktmechanik  \n"
        "**Pflege:** inhaltlich ersetzen, nicht durch datierte Statusblöcke erweitern",
    )
    product.write_text(text.strip() + "\n", encoding="utf-8")

    browser = ROOT / "docs/contracts/browser-smoke-system.md"
    text = browser.read_text(encoding="utf-8")
    text = re.sub(r"<!-- === (?:BEGIN|END) BLOCK:.*?=== -->\s*", "", text)
    browser.write_text(text.strip() + "\n", encoding="utf-8")

    debug = ROOT / "docs/playbooks/ui-debug.md"
    text = debug.read_text(encoding="utf-8")
    text = text.replace(
        "# DEBUG.md — Kanonische Proof-Snippets (KI-optimiert)",
        "# UI-/Layout-Debugging – kanonisches Proof-Kit",
    ).replace(
        "Dieses Dokument ist die **einzige** Quelle für Debug-Proofs im Projekt.",
        "Dieses Dokument ist das kanonische Proof-Kit für UI-, Layout- und Positionierungsfehler. Andere Fehlerklassen nutzen ihre jeweiligen Domain- und Runtime-Verträge.",
    )
    debug.write_text(text, encoding="utf-8")

    prepend("docs/reference/visual/legacy-visual-workflow.md", "ROLE_VISUAL_LEGACY", "**Rolle: historische Detailreferenz.** Nicht als aktuellen Status oder Arbeitsrouter lesen. Einstieg: `docs/domains/visual-system.md`.")
    prepend("docs/reference/event-search/legacy-rulebook-v3.md", "ROLE_SEARCH_LEGACY", "**Rolle: ausführliche Legacy- und Detailreferenz.** Einstieg: `docs/domains/event-search-system.md`; aktueller Code und Laufartefakte haben Vorrang.")
    prepend("docs/reference/event-search/source-registry-v1.md", "ROLE_SOURCE_REGISTRY", "**Rolle: operatives Quellenregister.** Es priorisiert Quellen, ersetzt aber nicht Systemvertrag oder aktuelle Laufzeitevidence.")
    prepend("docs/reference/mail/legacy-mail-system.md", "ROLE_MAIL_LEGACY", "**Rolle: historische und fachliche Mail-Referenz.** Einstieg: `docs/domains/operations.md`; Code und Tests sind für den Iststand maßgeblich.")

    for relative in [
        "docs/reference/visual/activity-prompt-kit.md",
        "docs/reference/visual/activity-sourcing-matrix.md",
        "docs/reference/visual/activity-workflow.md",
        "docs/reference/activities/seasonal-highlights.md",
    ]:
        prepend(relative, "ROLE_ACTIVITY_REFERENCE", "**Rolle: fachliche Detailreferenz.** Einstieg: `docs/domains/visual-system.md`; kein aktueller Workpackstatus.")
    for relative in [
        "docs/reference/bathing-water/guard-v1.md",
        "docs/reference/bathing-water/source-discovery-v2.md",
    ]:
        prepend(relative, "ROLE_BATHING_REFERENCE", "**Rolle: fachliche Detailreferenz.** Einstieg: `docs/domains/operations.md`; Beweise liegen unter `docs/evidence/`.")
    for relative in [
        "docs/reference/control-center/acceptance-matrix.md",
        "docs/reference/control-center/backend-gap-analysis.md",
        "docs/reference/control-center/project-integration.md",
        "docs/reference/control-center/information-architecture.md",
        "docs/reference/control-center/screen-contract.md",
        "docs/reference/control-center/case-catalog.md",
        "docs/reference/control-center/target-state.md",
    ]:
        prepend(relative, "ROLE_CONTROL_CENTER_REFERENCE", "**Rolle: Control-Center-Vertragsreferenz.** Status und Locks stehen nur in `docs/workpacks/active/CURRENT_WORKPACK.md`; Einstieg: `docs/domains/control-center.md`.")
    for relative in [
        "docs/archive/control-center/freeze-2026-07-10.md",
        "docs/archive/control-center/implementation-status-2026-07-10.md",
        "docs/archive/control-center/simulation-report-2026-07-10.md",
    ]:
        prepend(relative, "ROLE_CONTROL_CENTER_ARCHIVE", "**Rolle: historischer Beleg.** Nicht als aktuellen Projekt- oder Implementierungsstatus verwenden.")

    ai = ROOT / "AI_ENTRYPOINT.md"
    text = ai.read_text(encoding="utf-8")
    text = text.replace(
        "Dokumentrollen und Lesepfade stehen in `docs/README.md`.",
        "Dokumentrollen stehen in `docs/DOCUMENT_REGISTRY.md`; Lesepfade und Pflege stehen in `docs/README.md`.",
    ).replace(
        "4. fachliche Owner-Dateien im aktuellen Ref lesen;",
        "4. den zuständigen Einstieg aus `docs/domains/README.md` und danach nur die relevanten fachlichen Owner-Dateien lesen;",
    )
    ai.write_text(text, encoding="utf-8")

    current = ROOT / "docs/workpacks/active/CURRENT_WORKPACK.md"
    text = current.read_text(encoding="utf-8").replace(
        "Die Dokumentations-Governance ist konsolidiert.",
        "Die Dokumentations-Governance, Domain-Router, Root-Struktur und Vollinventur sind konsolidiert.",
    ).replace(
        "- Dokumentrollen und Rangfolge: `docs/README.md`",
        "- Dokumentrollen und Rangfolge: `docs/README.md`\n- vollständiger Pfad- und Rollenindex: `docs/DOCUMENT_REGISTRY.md`",
    )
    current.write_text(text, encoding="utf-8")

    status = ROOT / "TEST_STATUS.md"
    text = status.read_text(encoding="utf-8").replace(
        "Struktur-, Größen- und Widerspruchsaudit",
        "Struktur-, Root-Allowlist-, Größen-, Rollen- und Widerspruchsaudit plus Vollinventur",
    )
    status.write_text(text, encoding="utf-8")

    Path(__file__).unlink()


if __name__ == "__main__":
    main()
