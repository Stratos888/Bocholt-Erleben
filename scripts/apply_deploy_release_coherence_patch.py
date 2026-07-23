#!/usr/bin/env python3
from pathlib import Path
import re

PATH = Path('.github/workflows/deploy-strato.yml')

PLAN_BLOCK = '''      # === BEGIN BLOCK: ORDERED_DEPLOY_RELEASE_PLAN_V1 ===
      - name: Prepare ordered content-hash deploy plan
        shell: bash
        env:
          REQUEST_FULL_REPAIR: ${{ github.event_name == 'workflow_dispatch' && inputs.full_repair || false }}
        run: |
          set -euo pipefail
          MODE=full
          if [ "$DEPLOY_ENV_NAME" = staging ]; then
            MODE=delta
            [ "$REQUEST_FULL_REPAIR" = true ] && MODE=full_repair
          fi
          REMOTE=/tmp/deploy-manifest.json
          if [ "$MODE" = delta ]; then
            curl -fsSL --retry 2 --connect-timeout 10 "$SITE_ORIGIN/meta/deploy-manifest.json?run=$GITHUB_RUN_ID" -o "$REMOTE" || {
              echo '{}' > "$REMOTE"
              MODE=full_repair
            }
          else
            echo '{}' > "$REMOTE"
          fi
          echo "DEPLOY_MODE=$MODE" >> "$GITHUB_ENV"
          python3 scripts/prepare_deploy_delta.py \\
            --source deploy \\
            --remote-manifest "$REMOTE" \\
            --mode "$MODE" \\
            --build-id "$BUILD_ID" \\
            --environment "$DEPLOY_ENV_NAME"
          cat deploy-plan.json
      # === END BLOCK: ORDERED_DEPLOY_RELEASE_PLAN_V1 ==='''

UPLOAD_BLOCK = '''      # === BEGIN BLOCK: STRATO_ORDERED_RELEASE_UPLOAD_V1 ===
      - name: Upload changed assets to STRATO via SFTP
        timeout-minutes: 30
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          DEL="$(cat deploy-delete.lftp 2>/dev/null || true)"
          lftp -u "$STRATO_FTP_USERNAME","$STRATO_FTP_PASSWORD" "sftp://$STRATO_FTP_SERVER" <<LFTP
          set sftp:auto-confirm yes
          set cmd:fail-exit yes
          set net:timeout 25
          set net:max-retries 4
          set net:reconnect-interval-base 2
          set net:reconnect-interval-max 15
          cd $DEPLOY_TARGET_DIR
          mirror -R --parallel=8 --verbose deploy-assets/ .
          $DEL
          bye
          LFTP

      - name: Publish HTML and deploy manifest
        timeout-minutes: 30
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          lftp -u "$STRATO_FTP_USERNAME","$STRATO_FTP_PASSWORD" "sftp://$STRATO_FTP_SERVER" <<LFTP
          set sftp:auto-confirm yes
          set cmd:fail-exit yes
          set net:timeout 25
          set net:max-retries 4
          set net:reconnect-interval-base 2
          set net:reconnect-interval-max 15
          cd $DEPLOY_TARGET_DIR
          mirror -R --parallel=6 --verbose deploy-entry/ .
          bye
          LFTP

      - name: Verify HTML asset keys before build cutover
        shell: bash
        run: |
          set -euo pipefail
          for route in / /events/ /aktivitaeten/; do
            page="$(curl -fsSL --retry 4 --retry-delay 2 --connect-timeout 10 --max-time 30 "${SITE_ORIGIN}${route}?deploy_run=${GITHUB_RUN_ID}")"
            if ! grep -Fq "css/style.css?v=$BUILD_ID" <<<"$page"; then
              echo "HTML asset-key verification failed for $route" >&2
              exit 1
            fi
          done

      - name: Publish build marker after HTML verification
        timeout-minutes: 5
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          lftp -u "$STRATO_FTP_USERNAME","$STRATO_FTP_PASSWORD" "sftp://$STRATO_FTP_SERVER" <<LFTP
          set sftp:auto-confirm yes
          set cmd:fail-exit yes
          set net:timeout 25
          set net:max-retries 4
          cd $DEPLOY_TARGET_DIR
          mirror -R --verbose deploy-marker/ .
          bye
          LFTP

      - name: Verify public build marker
        shell: bash
        run: |
          set -euo pipefail
          for attempt in $(seq 1 12); do
            current="$(curl -fsSL --connect-timeout 10 --max-time 20 "${SITE_ORIGIN}/meta/build.txt?deploy_run=${GITHUB_RUN_ID}-${attempt}" | tr -d '\\r\\n' || true)"
            [ "$current" = "$BUILD_ID" ] && exit 0
            echo "Build marker attempt $attempt/12: '${current:-unavailable}', expected '$BUILD_ID'."
            sleep 5
          done
          echo "Public build marker did not reach $BUILD_ID." >&2
          exit 1

      - name: Publish version-stamped service worker last
        timeout-minutes: 5
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          lftp -u "$STRATO_FTP_USERNAME","$STRATO_FTP_PASSWORD" "sftp://$STRATO_FTP_SERVER" <<LFTP
          set sftp:auto-confirm yes
          set cmd:fail-exit yes
          set net:timeout 25
          set net:max-retries 4
          cd $DEPLOY_TARGET_DIR
          mirror -R --verbose deploy-worker/ .
          bye
          LFTP

      - name: Verify service-worker build stamp
        shell: bash
        run: |
          set -euo pipefail
          worker="$(curl -fsSL --retry 4 --retry-delay 2 --connect-timeout 10 --max-time 30 "${SITE_ORIGIN}/service-worker.js?deploy_run=${GITHUB_RUN_ID}")"
          if ! grep -Fq "// DEPLOY_BUILD_ID: $BUILD_ID" <<<"$worker"; then
            echo "Service-worker build stamp does not match $BUILD_ID." >&2
            exit 1
          fi
      # === END BLOCK: STRATO_ORDERED_RELEASE_UPLOAD_V1 ==='''


def replace_once(text: str, pattern: str, replacement: str, label: str) -> str:
    updated, count = re.subn(pattern, replacement, text, count=1, flags=re.S)
    if count != 1:
        raise SystemExit(f'Expected exactly one {label}, replaced {count}')
    return updated


def main() -> None:
    text = PATH.read_text(encoding='utf-8')
    text = replace_once(
        text,
        r'      # === BEGIN BLOCK: STAGING_CONTENT_HASH_DELTA_V1 ===.*?      # === END BLOCK: STAGING_CONTENT_HASH_DELTA_V1 ===',
        PLAN_BLOCK,
        'deploy-plan block',
    )
    text = replace_once(
        text,
        r'      # === BEGIN BLOCK: STRATO_UPLOAD_DELTA_V1 ===.*?      # === END BLOCK: STRATO_UPLOAD_DELTA_V1 ===',
        UPLOAD_BLOCK,
        'ordered upload block',
    )
    PATH.write_text(text, encoding='utf-8')


if __name__ == '__main__':
    main()
