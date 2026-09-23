#!/usr/bin/env bash
# npm run env:drill (SPEC §6.9, P5-01): proves backup.sh/restore.sh round-trip the seeded site
# without loss. Seeds a known state, backs it up, records five pages' title+body hash, wipes the
# site, restores the backup, and re-records the same five pages -- any difference in post count
# or any hash is a real data-loss bug, not tolerated (exit 1). Runs in CI's integration job
# after test:integration (SPEC §7).
set -euo pipefail

WP=(npx wp-env run cli wp)
BASE_URL="${WP_BASE_URL:-http://localhost:8888}"
URLS=(
	"/"
	"/signing-your-options-table/"
	"/category/security/"
	"/series/"
	"/writing/"
)

echo "drill.sh: seeding a known state"
"${WP[@]}" ttm seed --reset

echo "drill.sh: backing up"
DIR=$(bash scripts/live/backup.sh | tail -n1)
before_count=$("${WP[@]}" post list --post_type=post --post_status=publish --format=count)

declare -a before_hashes=()
for url in "${URLS[@]}"; do
	before_hashes+=("$(node scripts/live/hash-body.mjs "${BASE_URL}${url}")")
done
echo "drill.sh: recorded ${#before_hashes[@]} page hash(es) before wipe"

echo "drill.sh: wiping the site"
"${WP[@]}" site empty --uploads --yes

echo "drill.sh: restoring $DIR"
bash scripts/live/restore.sh "$DIR" "--host=${BASE_URL}"

after_count=$("${WP[@]}" post list --post_type=post --post_status=publish --format=count)

status=0
if [ "$before_count" != "$after_count" ]; then
	echo "drill.sh: FAIL post count changed ($before_count -> $after_count)" >&2
	status=1
fi

for i in "${!URLS[@]}"; do
	url="${URLS[$i]}"
	after_hash=$(node scripts/live/hash-body.mjs "${BASE_URL}${url}")
	if [ "${before_hashes[$i]}" != "$after_hash" ]; then
		echo "drill.sh: FAIL $url hash changed" >&2
		status=1
	fi
done

if [ "$status" -eq 0 ]; then
	echo "drill.sh: OK ($before_count posts, ${#URLS[@]} page hash(es) unchanged)"
else
	echo "drill.sh: FAILED -- backup/restore lost or changed data" >&2
fi

exit "$status"
