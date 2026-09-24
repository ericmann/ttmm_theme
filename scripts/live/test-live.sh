#!/usr/bin/env bash
# npm run test:live (SPEC §6.10, §7, rule 48, P0-01). Runs only when the live import has
# produced its screen list and the live spec exists; otherwise prints a skip line and exits 0
# so CI never fails for lack of the export.
set -euo pipefail

if [ ! -f docs/fixtures/live/screens.json ] || [ ! -f tests/e2e/live.spec.mjs ]; then
	echo "test:live skipped: no live import"
	exit 0
fi

WP_BASE_URL="${LIVE_HOST:-http://localhost:8888}" npx wp-scripts test-playwright --config tests/e2e/playwright.config.mjs --project live
