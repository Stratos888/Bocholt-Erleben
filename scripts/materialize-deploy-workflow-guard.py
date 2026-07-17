#!/usr/bin/env python3
from __future__ import annotations

import hashlib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / ".github/workflows/deploy-strato.yml"
TARGET = ROOT / "docs/evidence/generated/deploy-strato-patched.yml"

CHECKOUT_BLOCK = """      - name: Checkout
        uses: actions/checkout@v4
"""

AUTHORIZATION_BLOCK = """      - name: Checkout
        uses: actions/checkout@v4

      # === BEGIN BLOCK: FAIL_CLOSED_DEPLOY_BRANCH_ROUTING_V1 | Zweck: autorisiert ausschließlich main/live und staging/staging vor Python, Secrets oder externen Zugriffen; Umfang: zentrale Branch-, Environment- und Inbox-Tab-Aufloesung ===
      - name: Authorize deploy branch before external access
        shell: bash
        run: |
          set -euo pipefail
          bash scripts/resolve-deploy-target.sh "${GITHUB_REF_NAME}" "${GITHUB_ENV}"
      # === END BLOCK: FAIL_CLOSED_DEPLOY_BRANCH_ROUTING_V1 ===
"""

LEGACY_INBOX_ROUTING = """          if [ "${GITHUB_REF_NAME}" = "staging" ]; then
            INBOX_TAB_NAME="Inbox_Staging"
          else
            INBOX_TAB_NAME="Inbox"
          fi

          echo "BE_SHEET_ID=$BE_SHEET_ID" >> "$GITHUB_ENV"
          echo "INBOX_TAB_NAME=$INBOX_TAB_NAME" >> "$GITHUB_ENV"
"""

SAFE_INBOX_VALIDATION = """          case "${INBOX_TAB_NAME:-}" in
            Inbox|Inbox_Staging) ;;
            *)
              echo "❌ INBOX_TAB_NAME wurde nicht durch den autorisierten Deploy-Resolver gesetzt."
              exit 1
              ;;
          esac

          echo "BE_SHEET_ID=$BE_SHEET_ID" >> "$GITHUB_ENV"
"""

LEGACY_DEPLOY_TARGET_BLOCK = """      # === BEGIN BLOCK: DEPLOY_ENV_DECISION_V4 | Zweck: main/live und staging nutzen denselben funktionierenden SFTP-Server/User; nur das Zielverzeichnis unterscheidet sich wieder ===
      - name: Decide deploy target
        shell: bash
        run: |
          set -e

          if [ "${GITHUB_REF_NAME}" = "staging" ]; then
            TARGET_DIR="staging"
            ENV_NAME="staging"
          else
            TARGET_DIR="."
            ENV_NAME="live"
          fi

          echo "DEPLOY_TARGET_DIR=$TARGET_DIR" >> "$GITHUB_ENV"
          echo "DEPLOY_ENV_NAME=$ENV_NAME" >> "$GITHUB_ENV"

          echo "Branch: ${GITHUB_REF_NAME}"
          echo "Deploy target dir: $TARGET_DIR"
          echo "Environment: $ENV_NAME"
      # === END BLOCK: DEPLOY_ENV_DECISION_V4 ===

"""


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected exactly one source block, found {count}")
    return text.replace(old, new, 1)


def main() -> None:
    source = SOURCE.read_text(encoding="utf-8")
    patched = replace_once(source, CHECKOUT_BLOCK, AUTHORIZATION_BLOCK, "checkout authorization")
    patched = replace_once(patched, LEGACY_INBOX_ROUTING, SAFE_INBOX_VALIDATION, "inbox routing")
    patched = replace_once(patched, LEGACY_DEPLOY_TARGET_BLOCK, "", "deploy target routing")

    if "Authorize deploy branch before external access" not in patched:
        raise SystemExit("authorization step missing after patch")
    if "Decide deploy target" in patched:
        raise SystemExit("legacy deploy target step remains after patch")
    if 'if [ "${GITHUB_REF_NAME}" = "staging" ]; then\n            TARGET_DIR=' in patched:
        raise SystemExit("legacy else-live target decision remains")

    TARGET.parent.mkdir(parents=True, exist_ok=True)
    TARGET.write_text(patched, encoding="utf-8")

    digest = hashlib.sha256(patched.encode("utf-8")).hexdigest()
    print(f"materialized={TARGET.relative_to(ROOT)}")
    print(f"bytes={len(patched.encode('utf-8'))}")
    print(f"sha256={digest}")


if __name__ == "__main__":
    main()
