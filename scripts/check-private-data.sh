#!/usr/bin/env bash
# SPEC rule 47: private data (the WXR export, database dumps, uploads, audit output) never
# enters the repository. It lives under docs/fixtures/live/ (gitignored) or as a *.xml/*.sql/
# *.sql.gz/*.tar.gz/*.csv file directly under docs/ (also gitignored) -- fail if any of those
# was ever committed anyway, whether directly under docs/ or nested in a subdirectory.
#
# Runs against the current working directory's git repository (rule 47, R1-08): called with no
# args from scripts/forbidden-patterns.sh against this repo; scripts/test/private-data.test.js
# also runs it (via `bash <path-to-this-script>`, from inside a throwaway temp repo it `cd`s
# into first) to exercise both the "committed" and "clean" outcomes without touching this repo.
set -euo pipefail

out=$(git ls-files -- \
	'docs/*.xml' 'docs/**/*.xml' \
	'docs/*.sql' 'docs/**/*.sql' \
	'docs/*.sql.gz' 'docs/**/*.sql.gz' \
	'docs/*.tar.gz' 'docs/**/*.tar.gz' \
	'docs/*.csv' 'docs/**/*.csv' \
	'docs/fixtures/live/' \
	2>/dev/null || true)

if [ -n "$out" ]; then
	echo "$out"
	echo "check-private-data: private data committed under docs/ (SPEC rule 47)"
	exit 1
fi

echo "check-private-data: clean"
exit 0
