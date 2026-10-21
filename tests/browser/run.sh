#!/usr/bin/env bash
# C3.9 browser acceptance: fresh fixture → PHP built-in server → Playwright/Chromium flows.
# Usage: tests/browser/run.sh   (env: PHP_EXT_DIR, BCRM_BROWSER_RUNTIME, BCRM_SHOTS, PLAYWRIGHT_PATH, PORT)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PORT="${PORT:-8099}"
export BCRM_BROWSER_RUNTIME="${BCRM_BROWSER_RUNTIME:-$(mktemp -d)}"
export BCRM_SHOTS="${BCRM_SHOTS:-$BCRM_BROWSER_RUNTIME/shots}"
export BCRM_BASE="http://127.0.0.1:$PORT"
EXT="${PHP_EXT_DIR:-$(php -r 'echo ini_get("extension_dir");')}"
PHP=(php -n -d "extension_dir=$EXT" -d extension=pdo -d extension=pdo_sqlite -d extension=mbstring -d upload_max_filesize=8M -d post_max_size=10M -d file_uploads=1)
"${PHP[@]}" "$ROOT/tests/browser/seed.php" > /dev/null
"${PHP[@]}" -S "127.0.0.1:$PORT" -t "$ROOT" "$ROOT/tests/browser/router.php" > "$BCRM_BROWSER_RUNTIME/server.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
for _ in $(seq 1 50); do curl -s "$BCRM_BASE/__test/fixture" > /dev/null && break; sleep 0.1; done
node "$ROOT/tests/browser/ownership-flows.js"
echo "screenshots: $BCRM_SHOTS"
