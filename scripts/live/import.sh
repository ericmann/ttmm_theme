#!/usr/bin/env bash
# npm run env:live, step 1 of 2 (SPEC §6.6, §6.9 rule 48, P0-01/P2-06). Resolves the WXR export
# to import: the LIVE_WXR env var, else the newest docs/*.xml, else the newest
# docs/fixtures/live/*.xml. When none exists this prints a skip line and exits 0 so CI never
# fails for lack of the export (rule 48). Every `wp` call runs inside the dev wp-env container
# (`npx wp-env run cli wp …`); nothing under docs/fixtures/live/ is ever staged (rule 47, already
# gitignored). Non-interactive; each step is printed with a count and wall time.
set -euo pipefail

LIVE_HOST="${LIVE_HOST:-http://localhost:8888}"
WP=(npx wp-env run cli wp)

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

step() {
	local label="$1"
	shift
	local t0 t1
	t0=$(date +%s)
	"$@"
	t1=$(date +%s)
	echo "import.sh: ${label} ($((t1 - t0))s)"
}

if ! wxr=$(resolve_wxr); then
	echo "env:live skipped: no export"
	exit 0
fi

npx wp-env start

mkdir -p docs/fixtures/live
cp "$wxr" docs/fixtures/live/import.xml
echo "import.sh: using $wxr"

# R2-03, SPEC §6.7, §9 Q2: the WXR's own <wp:category> term_id -> slug map, for
# `primary:assign --from-yoast --term-map=` (plan.sh) to translate the *source* site's Yoast
# primary-category id to a slug it can resolve on *this* site, whether that category was reused
# or freshly created by `wp import`. Never committed (rule 47, already under
# docs/fixtures/live/).
node scripts/live/term-map.mjs docs/fixtures/live/import.xml docs/fixtures/live/term-map.json

step "wp site empty" "${WP[@]}" site empty --uploads --yes
step "wp ttm stats:flush" "${WP[@]}" ttm stats:flush
step "wp ttm seed --starter-only" "${WP[@]}" ttm seed --starter-only

if ! "${WP[@]}" plugin is-active wordpress-importer >/dev/null 2>&1; then
	step "wp plugin install wordpress-importer" "${WP[@]}" plugin install wordpress-importer --activate
fi

import_args=(import wp-content/ttm-fixtures/live/import.xml --authors=create)
if [ "${LIVE_SKIP_ATTACHMENTS:-}" = "1" ]; then
	import_args+=(--skip=attachment)
fi
step "wp import" "${WP[@]}" "${import_args[@]}"

# Any imported page whose slug collides with a starter page (SPEC §6.5, Q10: today only
# "writing") lands at the next free slug via WP's own wp_unique_post_slug() -- "<slug>-2" for
# the first collision -- since the starter page (step above) already owns the bare slug.
# Rename it "<slug>-legacy" and set it to draft; the starter page keeps the slug.
for slug in series writing newsletter about; do
	collided_id=$("${WP[@]}" post list --post_type=page --name="${slug}-2" --post_status=publish --field=ID --format=ids 2>/dev/null | tr -d '\r')
	if [ -n "$collided_id" ]; then
		# The imported page's own `_wp_page_template` meta usually names a template this theme
		# doesn't have (it's the live theme's, and this page is being demoted to a draft
		# nobody renders anyway) -- `wp post update` still applies the rename/status change but
		# prints "Warning: Invalid page template." and exits 1 for it. Tolerated: the update
		# itself succeeds regardless (verified: post_name/post_status are correct after).
		"${WP[@]}" post update "$collided_id" --post_name="${slug}-legacy" --post_status=draft || true
		echo "import.sh: renamed colliding page ${collided_id} to ${slug}-legacy (draft)"
	fi
done

step "wp search-replace" "${WP[@]}" search-replace 'https://eric.mann.blog' "$LIVE_HOST" --all-tables --precise --skip-columns=guid

echo "import.sh: done ($wxr -> $LIVE_HOST)"
