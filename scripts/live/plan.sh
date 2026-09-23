#!/usr/bin/env bash
# npm run env:live, step 2 of 2 (SPEC §6.6 step 4, §6.9 rule 48, P0-01/P2-06). Runs the whole
# migration plan against the wp-env dev site import.sh just populated. Skips (exit 0, rule 48)
# when import.sh had nothing to import -- detected by the marker file import.sh only writes
# when it actually ran. Every `wp ttm` step that supports `--dry-run` runs it first and aborts
# the whole script on a non-zero exit (`set -e`); the whole plan is re-runnable from any state.
set -euo pipefail

if [ ! -f docs/fixtures/live/import.xml ]; then
	echo "env:live skipped: no export"
	exit 0
fi

WP=(npx wp-env run cli wp)

step() {
	local label="$1"
	shift
	local t0 t1
	t0=$(date +%s)
	"$@"
	t1=$(date +%s)
	echo "plan.sh: ${label} ($((t1 - t0))s)"
}

# Runs a `wp ttm …` step with `--dry-run` first (aborting the script on a non-zero exit, same as
# every other step), then for real.
dry_then_run() {
	local label="$1"
	shift
	"${WP[@]}" "$@" --dry-run
	step "$label" "${WP[@]}" "$@"
}

dry_then_run "migrate:politics" ttm migrate:politics
dry_then_run "primary:assign --from-yoast" ttm primary:assign --from-yoast
dry_then_run "primary:assign" ttm primary:assign

# One `series:assign` per docs/migration/series.json entry (SPEC §6.6 step 4, §9 Q1).
# `IFS='|'` (not a tab, series-args.mjs's FIELD_SEPARATOR): a tab is bash `read`'s own IFS
# *whitespace*, so two adjacent tabs (an empty `total` field) silently collapse into one
# delimiter and shift every field after it -- `|` doesn't have that problem.
# Read every line into an array first, rather than `while read ... done < <(...)`: each
# `${WP[@]}` call inside the loop shares this process's stdin with `npx`/`docker exec`, which
# consumes bytes from the same process-substitution pipe the loop's own `read` is consuming --
# silently truncating the loop to its first iteration only (verified: a real env:live run with
# two series.json entries only ever ran the first one and never even reported the second).
mapfile -t series_lines < <(node scripts/live/series-args.mjs docs/migration/series.json)
for series_line in "${series_lines[@]}"; do
	IFS='|' read -r slug tags form status total name <<<"$series_line"
	series_args=(ttm series:assign "$slug" "--from-tags=${tags}" "--form=${form}" "--status=${status}" "--name=${name}")
	if [ -n "$total" ]; then
		series_args+=("--total=${total}")
	fi
	dry_then_run "series:assign ${slug}" "${series_args[@]}"
done

step "recount --all" "${WP[@]}" ttm recount --all
dry_then_run "migrate:excerpts --from=yoast" ttm migrate:excerpts --from=yoast
dry_then_run "migrate:images" ttm migrate:images

step "convert:export --all-classic" "${WP[@]}" ttm convert:export --all-classic --out=wp-content/ttm-fixtures/live/classic.ndjson
step "convert-classic.mjs" node scripts/convert-classic.mjs docs/fixtures/live/classic.ndjson docs/fixtures/live/blocks.ndjson --allow-freeform
dry_then_run "convert:import" ttm convert:import wp-content/ttm-fixtures/live/blocks.ndjson --allow-freeform

dry_then_run "migrate:close-comments" ttm migrate:close-comments

step "series:rebuild" "${WP[@]}" ttm series:rebuild
step "stats:flush" "${WP[@]}" ttm stats:flush
step "rewrite flush" "${WP[@]}" rewrite flush

# Network; failure tolerated (SPEC §6.6 step 4).
"${WP[@]}" ttm verse fetch || true

mkdir -p docs/fixtures/live
"${WP[@]}" ttm audit --format=csv >docs/fixtures/live/audit.csv
echo "plan.sh: audit.csv written ($(wc -l <docs/fixtures/live/audit.csv) line(s))"

if [ -f scripts/live/screens.mjs ]; then
	step "screens.mjs" node scripts/live/screens.mjs
else
	echo "screens.mjs: lands in P2-07"
fi

echo "plan.sh: done"
