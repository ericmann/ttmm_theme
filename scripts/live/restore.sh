#!/usr/bin/env bash
# npm run env:restore -- <dir> [--host=<url>] (SPEC §6.9, P5-01). Restores a backup.sh backup
# into the dev wp-env site: db import, uploads untar, search-replace the manifest's own URL to
# --host (default the dev site's current siteurl), then the same post-import housekeeping
# import.sh/plan.sh already do (cache flush, series:rebuild, stats:flush, rewrite flush).
set -euo pipefail

DIR="${1:-}"
if [ -z "$DIR" ] || [ ! -f "$DIR/manifest.json" ]; then
	echo "restore.sh: usage: restore.sh <backup-dir> [--host=<url>] (no manifest.json found at '$DIR')" >&2
	exit 1
fi
shift

WP=(npx wp-env run cli wp)

HOST=""
for arg in "$@"; do
	case "$arg" in
	--host=*) HOST="${arg#--host=}" ;;
	esac
done

manifest_url=$(node -e "console.log(JSON.parse(require('fs').readFileSync('$DIR/manifest.json','utf8')).url)")
if [ -z "$HOST" ]; then
	HOST=$("${WP[@]}" option get siteurl)
fi

echo "restore.sh: restoring $DIR ($manifest_url -> $HOST)"

gunzip -c "$DIR/db.sql.gz" | "${WP[@]}" db import -
echo "restore.sh: db imported"

npx wp-env run cli tar -C wp-content -xzf - <"$DIR/uploads.tar.gz"
echo "restore.sh: uploads restored"

"${WP[@]}" search-replace "$manifest_url" "$HOST" --all-tables --precise --skip-columns=guid
"${WP[@]}" cache flush
"${WP[@]}" ttm series:rebuild
"${WP[@]}" ttm stats:flush
"${WP[@]}" rewrite flush

echo "restore.sh: done"
