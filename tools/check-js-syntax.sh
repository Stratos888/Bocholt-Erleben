#!/usr/bin/env bash
# BEGIN: FILE_HEADER_CHECK_JS_SYNTAX
# Datei: tools/check-js-syntax.sh
# Zweck:
# - Schneller Syntax-Check für alle JS-Dateien im Projekt
# - Verhindert Deploys mit "Unexpected token/string" usw.
#
# Verwendung:
# - im Projektroot ausführen:  bash tools/check-js-syntax.sh
#
# Ergebnis:
# - Exit 0 = alles ok
# - Exit 1 = mindestens eine Datei hat Syntaxfehler
# END: FILE_HEADER_CHECK_JS_SYNTAX

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "== JS Syntax Check =="
echo "Root: ${ROOT_DIR}"
echo

# Sammle alle JS Dateien (ohne node_modules etc. – gibt es hier ohnehin nicht)
mapfile -t JS_FILES < <(cd "$ROOT_DIR" && find . -type f -name "*.js" -not -path "./node_modules/*" | sort)

if [[ ${#JS_FILES[@]} -eq 0 ]]; then
  echo "No .js files found."
  exit 0
fi

FAIL=0

for f in "${JS_FILES[@]}"; do
  # node --check prüft nur Syntax (keine Ausführung)
  if node --check "$ROOT_DIR/$f" >/dev/null 2>&1; then
    echo "[OK]   $f"
  else
    echo "[FAIL] $f"
    node --check "$ROOT_DIR/$f" || true
    FAIL=1
  fi
done

echo
if [[ $FAIL -eq 0 ]]; then
  echo "All JS files passed syntax check."
  exit 0
else
  echo "JS syntax check failed. Fix the files marked [FAIL]."
  exit 1
fi
