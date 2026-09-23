#!/usr/bin/env bash
# npm run env:live (SPEC §6.6, §6.9 rule 48, P0-01). Resolves the WXR export to import: the
# LIVE_WXR env var, else the newest docs/*.xml, else the newest docs/fixtures/live/*.xml. When
# none exists this prints a skip line and exits 0 so CI never fails for lack of the export
# (rule 48). The real import body lands in P2-06; until then this only resolves and reports.
set -euo pipefail

resolve_wxr() {
	if [ -n "${LIVE_WXR:-}" ]; then
		echo "$LIVE_WXR"
		return 0
	fi

	local newest
	newest=$(ls -t docs/*.xml 2>/dev/null | head -n 1 || true)
	if [ -n "$newest" ]; then
		echo "$newest"
		return 0
	fi

	newest=$(ls -t docs/fixtures/live/*.xml 2>/dev/null | head -n 1 || true)
	if [ -n "$newest" ]; then
		echo "$newest"
		return 0
	fi

	return 1
}

if ! wxr=$(resolve_wxr); then
	echo "env:live skipped: no export"
	exit 0
fi

echo "import.sh: lands in P2-06; nothing done ($wxr)"
exit 0
