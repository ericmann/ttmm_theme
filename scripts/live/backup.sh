#!/usr/bin/env bash
# npm run env:backup [-- <dir>] (SPEC §6.9, P5-01). Backs up the dev wp-env site's database and
# uploads into <dir> (default docs/fixtures/live/backups/<UTC stamp>/, rule 47: gitignored, never
# staged) alongside a manifest.json restore.sh reads back. The UTC stamp is `date -u` (rule 9:
# no PHP `date()`/`time()` outside src/Support/Clock.php -- this is host-side shell, not PHP).
set -euo pipefail

WP=(npx wp-env run cli wp)

DIR="${1:-docs/fixtures/live/backups/$(date -u +%Y%m%dT%H%M%SZ)}"
mkdir -p "$DIR"

echo "backup.sh: writing to $DIR"

"${WP[@]}" db export --single-transaction --default-character-set=utf8mb4 - | gzip >"$DIR/db.sql.gz"
echo "backup.sh: db.sql.gz written ($(du -h "$DIR/db.sql.gz" | cut -f1))"

npx wp-env run cli tar -C wp-content -czf - uploads >"$DIR/uploads.tar.gz"
echo "backup.sh: uploads.tar.gz written ($(du -h "$DIR/uploads.tar.gz" | cut -f1))"

sha256_of() {
	if command -v sha256sum >/dev/null 2>&1; then
		sha256sum "$1" | cut -d' ' -f1
	else
		shasum -a 256 "$1" | cut -d' ' -f1
	fi
}

url=$("${WP[@]}" option get siteurl)
wp_version=$("${WP[@]}" core version)
post_count=$("${WP[@]}" post list --post_type=post --post_status=publish --format=count)
db_sha=$(sha256_of "$DIR/db.sql.gz")
uploads_sha=$(sha256_of "$DIR/uploads.tar.gz")

cat >"$DIR/manifest.json" <<JSON
{
  "url": "$url",
  "wp_version": "$wp_version",
  "post_count": $post_count,
  "sha256": {
    "db": "$db_sha",
    "uploads": "$uploads_sha"
  }
}
JSON

echo "backup.sh: manifest.json written ($DIR/manifest.json)"
echo "$DIR"
