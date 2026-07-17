#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path


WORKFLOW = Path('.github/workflows/deploy-strato.yml')


def replace_once(text: str, old: str, new: str, label: str) -> str:
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected exactly one match, found {count}')
    return text.replace(old, new, 1)


def main() -> None:
    text = WORKFLOW.read_text(encoding='utf-8')
    if 'scripts.merge_events_overlay import merge_event_rows' in text:
        print('Deploy workflow is already materialized.')
        return

    old_target = '''          case "${INBOX_TAB_NAME:-}" in
            Inbox|Inbox_Staging) ;;
            *)
              echo "❌ INBOX_TAB_NAME wurde nicht durch den autorisierten Deploy-Resolver gesetzt."
              exit 1
              ;;
          esac

          echo "BE_SHEET_ID=$BE_SHEET_ID" >> "$GITHUB_ENV"
          echo "✅ Google Sheet target resolved for ${GITHUB_REF_NAME}."
          echo "Inbox tab: $INBOX_TAB_NAME"
'''
    new_target = '''          case "${DEPLOY_ENV_NAME:-}:${INBOX_TAB_NAME:-}:${EVENTS_TAB_NAME:-}" in
            live:Inbox:Events|staging:Inbox_Staging:Events_Staging) ;;
            *)
              echo "❌ Sheet targets were not authorized as one environment-consistent tuple."
              echo "Environment=${DEPLOY_ENV_NAME:-missing} Inbox=${INBOX_TAB_NAME:-missing} Events=${EVENTS_TAB_NAME:-missing}"
              exit 1
              ;;
          esac

          echo "BE_SHEET_ID=$BE_SHEET_ID" >> "$GITHUB_ENV"
          echo "✅ Google Sheet targets resolved for ${GITHUB_REF_NAME}."
          echo "Inbox tab: $INBOX_TAB_NAME"
          echo "Events tab: $EVENTS_TAB_NAME"
'''
    text = replace_once(text, old_target, new_target, 'sheet target block')

    start_marker = '      - name: Export Events tab to data/events.tsv (fail-fast, retry-hardened)\n'
    end_marker = '          test -s data/events.tsv\n'
    start = text.find(start_marker)
    if start < 0:
        raise SystemExit('events export start marker not found')
    end = text.find(end_marker, start)
    if end < 0:
        raise SystemExit('events export end marker not found')
    end += len(end_marker)
    if text.find(start_marker, start + 1) >= 0:
        raise SystemExit('events export start marker is not unique')

    new_export = '''      - name: Export environment-safe Events feed to data/events.tsv
        timeout-minutes: 6
        shell: bash
        env:
          SHEET_ID: ${{ env.BE_SHEET_ID }}
          EVENTS_TAB_NAME: ${{ env.EVENTS_TAB_NAME }}
          GOOGLE_SERVICE_ACCOUNT_JSON: ${{ secrets.GOOGLE_SERVICE_ACCOUNT_JSON }}
        run: |
          set -euo pipefail
          python - <<'PY'
          import csv
          import json
          import os
          import socket
          import ssl
          import sys
          import time

          from google.oauth2 import service_account
          from googleapiclient.discovery import build
          from googleapiclient.errors import HttpError

          from scripts.merge_events_overlay import merge_event_rows


          def fail(message):
              print(f"❌ {message}", file=sys.stderr)
              sys.exit(1)


          sheet_id = os.environ.get("SHEET_ID", "").strip()
          events_tab = os.environ.get("EVENTS_TAB_NAME", "").strip()
          service_account_json = os.environ.get("GOOGLE_SERVICE_ACCOUNT_JSON", "").strip()

          if not sheet_id:
              fail("ENV SHEET_ID fehlt.")
          if events_tab not in {"Events", "Events_Staging"}:
              fail(f"Nicht autorisiertes Events-Ziel: {events_tab or 'missing'}")
          if not service_account_json:
              fail("GOOGLE_SERVICE_ACCOUNT_JSON fehlt.")

          try:
              service_account_info = json.loads(service_account_json)
          except Exception:
              fail("GOOGLE_SERVICE_ACCOUNT_JSON ist kein gültiges JSON.")

          socket.setdefaulttimeout(90)
          credentials = service_account.Credentials.from_service_account_info(
              service_account_info,
              scopes=["https://www.googleapis.com/auth/spreadsheets.readonly"],
          )
          service = build("sheets", "v4", credentials=credentials, cache_discovery=False)


          def fetch_values_with_retry(range_name, label, attempts=4):
              last_error = None
              for attempt in range(1, attempts + 1):
                  try:
                      print(f"↻ {label}: Versuch {attempt}/{attempts}")
                      response = service.spreadsheets().values().get(
                          spreadsheetId=sheet_id,
                          range=range_name,
                          majorDimension="ROWS",
                      ).execute(num_retries=4)
                      return response.get("values", []) or []
                  except HttpError as error:
                      status = getattr(error.resp, "status", None)
                      last_error = error
                      if status not in (408, 429, 500, 502, 503, 504) or attempt >= attempts:
                          raise
                  except (TimeoutError, socket.timeout, ssl.SSLError, OSError) as error:
                      last_error = error
                      if attempt >= attempts:
                          raise
                  wait_seconds = attempt * 5
                  print(f"⚠️ {label}: Retry in {wait_seconds}s", file=sys.stderr)
                  time.sleep(wait_seconds)
              raise last_error or RuntimeError(f"{label}: unbekannter Exportfehler")


          base_values = fetch_values_with_retry("Events!A:ZZ", "Events-Basis")
          if not base_values:
              fail("Events-Basis ist leer oder nicht lesbar.")

          overlay_values = None
          if events_tab == "Events_Staging":
              overlay_values = fetch_values_with_retry("Events_Staging!A:ZZ", "Events-Staging-Overlay")
              if not overlay_values:
                  fail("Events_Staging ist leer oder nicht lesbar.")

          try:
              header, rows, stats = merge_event_rows(
                  base_values[0],
                  base_values[1:],
                  overlay_values[0] if overlay_values is not None else None,
                  overlay_values[1:] if overlay_values is not None else None,
              )
          except ValueError as error:
              fail(str(error))

          if not rows:
              fail("Der zusammengeführte Events-Bestand enthält keine Datenzeilen.")

          with open("data/events.tsv", "w", encoding="utf-8", newline="") as handle:
              writer = csv.writer(handle, delimiter="\\t", lineterminator="\\n")
              writer.writerow(header)
              writer.writerows(rows)

          print(
              "✅ Events export OK: "
              f"target={events_tab} base={stats['base']} overlay={stats['overlay']} "
              f"replaced={stats['replaced']} appended={stats['appended']} total={len(rows)}"
          )
          PY
          test -s data/events.tsv
'''
    text = text[:start] + new_export + text[end:]
    WORKFLOW.write_text(text, encoding='utf-8')
    print('Materialized isolated staging Events deploy workflow.')


if __name__ == '__main__':
    main()
