#!/usr/bin/env python3
from pathlib import Path

path = Path(".github/workflows/deploy-strato.yml")
text = path.read_text(encoding="utf-8")


def replace_once(old: str, new: str, label: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected exactly one match, found {count}")
    text = text.replace(old, new, 1)


replace_once(
    '''        run: |
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
''',
    '''        run: |
          set -euo pipefail
          bash scripts/run_strato_sftp_phase.sh deploy-assets 8 deploy-delete.lftp
''',
    "asset upload phase",
)

replace_once(
    '''        run: |
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
''',
    '''        run: |
          set -euo pipefail
          bash scripts/run_strato_sftp_phase.sh deploy-entry 6
''',
    "HTML upload phase",
)

replace_once(
    '''      - name: Publish build marker after HTML verification
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
''',
    '''      - name: Publish build marker after HTML verification
        timeout-minutes: 15
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          bash scripts/run_strato_sftp_phase.sh deploy-marker 1
''',
    "build marker phase",
)

replace_once(
    '''      - name: Publish version-stamped service worker last
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
''',
    '''      - name: Publish version-stamped service worker last
        timeout-minutes: 15
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          bash scripts/run_strato_sftp_phase.sh deploy-worker 1
''',
    "service-worker phase",
)

replace_once(
    '''      - name: Publish deploy manifest after release verification
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
          mirror -R --verbose deploy-manifest/ .
          bye
          LFTP
''',
    '''      - name: Publish deploy manifest after release verification
        timeout-minutes: 15
        shell: bash
        env:
          STRATO_FTP_SERVER: ${{ secrets.STRATO_FTP_SERVER }}
          STRATO_FTP_USERNAME: ${{ secrets.STRATO_FTP_USERNAME }}
          STRATO_FTP_PASSWORD: ${{ secrets.STRATO_FTP_PASSWORD }}
        run: |
          set -euo pipefail
          bash scripts/run_strato_sftp_phase.sh deploy-manifest 1
''',
    "manifest phase",
)

required_calls = [
    "bash scripts/run_strato_sftp_phase.sh deploy-assets 8 deploy-delete.lftp",
    "bash scripts/run_strato_sftp_phase.sh deploy-entry 6",
    "bash scripts/run_strato_sftp_phase.sh deploy-marker 1",
    "bash scripts/run_strato_sftp_phase.sh deploy-worker 1",
    "bash scripts/run_strato_sftp_phase.sh deploy-manifest 1",
]
for call in required_calls:
    if text.count(call) != 1:
        raise SystemExit(f"postcondition failed for workflow call: {call}")

path.write_text(text, encoding="utf-8")
print("STRATO SFTP phase workflow patch: OK")
