#!/usr/bin/env bash
# npm run env:live, step 2 of 2 (SPEC §6.6 step 4, §6.9 rule 48, P0-01/P2-06). Runs the whole
# migration plan against the wp-env dev site import.sh just populated. Skips (exit 0, rule 48)
# when import.sh had nothing to import -- detected by the marker file import.sh only writes
# when it actually ran. Every `wp ttm` step that supports `--dry-run` runs it first and aborts
# the whole script on a non-zero exit (`set -e`); the whole plan is re-runnable from any state.
#
# Other targets (the k3s beta, DEPLOYMENT.md §10, MIGRATION.md §3.2), all optional; with none set
# the script behaves exactly as it always has against wp-env:
#   TTM_WP              the WP-CLI command, word-split on whitespace
#                       (e.g. "kubectl -n blog exec -i deploy/wordpress -- wp --allow-root").
#   TTM_KUBECTL_TARGET  <namespace>/<pod> or <namespace>/deploy/<name>: move the conversion files
#                       with `kubectl exec` instead of the wp-env mount, skip the import.xml
#                       precondition and the wp-env-only screens.mjs step.
#   TTM_KUBECTL         the kubectl command (default "kubectl"); TTM_KUBECTL_CONTAINER picks -c.
#   TTM_POD_WP_ROOT     WordPress root inside the pod (default /var/www/html); WP-CLI is assumed
#                       to run from it, so the relative wp-content/ttm-fixtures/live paths match.
#   TTM_SKIP_TERM_MAP   1 = primary:assign --from-yoast without --term-map (default 1 with a
#                       kubectl target, where the site is a database restore with the live term
#                       IDs; 0 otherwise, where the WXR import renumbers terms).
set -euo pipefail

K8S_TARGET="${TTM_KUBECTL_TARGET:-}"

if [ -z "$K8S_TARGET" ] && [ ! -f docs/fixtures/live/import.xml ]; then
	echo "env:live skipped: no export"
	exit 0
fi

if [ -n "${TTM_WP:-}" ]; then
	read -r -a WP <<<"$TTM_WP"
else
	WP=(npx wp-env run cli wp)
fi

if [ -n "$K8S_TARGET" ]; then
	SKIP_TERM_MAP="${TTM_SKIP_TERM_MAP:-1}"
else
	SKIP_TERM_MAP="${TTM_SKIP_TERM_MAP:-0}"
fi

# Where WP-CLI sees the conversion files (relative to the WordPress root), and the matching
# host-side directory (wp-env mounts docs/fixtures at wp-content/ttm-fixtures).
REMOTE_FIXTURES=wp-content/ttm-fixtures/live
LOCAL_FIXTURES=docs/fixtures/live

KEXEC=()
if [ -n "$K8S_TARGET" ]; then
	read -r -a KUBECTL <<<"${TTM_KUBECTL:-kubectl}"
	k8s_namespace="${K8S_TARGET%%/*}"
	k8s_resource="${K8S_TARGET#*/}"
	if [ -z "$k8s_namespace" ] || [ -z "$k8s_resource" ] || [ "$k8s_namespace" = "$K8S_TARGET" ]; then
		echo "plan.sh: TTM_KUBECTL_TARGET must be <namespace>/<pod> or <namespace>/deploy/<name>, got '${K8S_TARGET}'" >&2
		exit 1
	fi
	KEXEC=("${KUBECTL[@]}" -n "$k8s_namespace" exec -i "$k8s_resource")
	if [ -n "${TTM_KUBECTL_CONTAINER:-}" ]; then
		KEXEC+=(-c "$TTM_KUBECTL_CONTAINER")
	fi
	KEXEC+=(--)
	POD_FIXTURES="${TTM_POD_WP_ROOT:-/var/www/html}/${REMOTE_FIXTURES}"
fi

# Host file -> the directory WP-CLI reads. A no-op under wp-env (the mount already shares it).
push_fixture() {
	local name="$1"
	if [ -n "$K8S_TARGET" ]; then
		# The single quotes are deliberate: "$1" expands inside the pod's shell, not this one.
		# shellcheck disable=SC2016
		"${KEXEC[@]}" sh -c 'mkdir -p "$(dirname "$1")" && cat >"$1"' sh "${POD_FIXTURES}/${name}" <"${LOCAL_FIXTURES}/${name}"
	fi
}

# The directory WP-CLI wrote -> host file. A no-op under wp-env.
pull_fixture() {
	local name="$1"
	if [ -n "$K8S_TARGET" ]; then
		mkdir -p "$LOCAL_FIXTURES"
		"${KEXEC[@]}" cat "${POD_FIXTURES}/${name}" </dev/null >"${LOCAL_FIXTURES}/${name}"
	fi
}

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
if [ "$SKIP_TERM_MAP" = "1" ]; then
	dry_then_run "primary:assign --from-yoast" ttm primary:assign --from-yoast
else
	push_fixture term-map.json
	dry_then_run "primary:assign --from-yoast" ttm primary:assign --from-yoast --term-map=wp-content/ttm-fixtures/live/term-map.json
fi
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

if [ -n "$K8S_TARGET" ]; then
	"${KEXEC[@]}" mkdir -p "$POD_FIXTURES" </dev/null
fi
step "convert:export --all-classic" "${WP[@]}" ttm convert:export --all-classic --out=wp-content/ttm-fixtures/live/classic.ndjson
pull_fixture classic.ndjson
# --allow-text-mismatch (R1-03, SPEC §1.3 "Done", §6.6): a handful of real classic posts don't
# round-trip a perfect text match (pre-existing content-quality issues, not conversion bugs --
# see docs/feedback/phase-4/LIVE-TRIAGE.md); the whole plan must still run non-interactively, so
# this step reports every mismatch but exits 0, and `convert:import` (next) skips converting
# those specific records rather than the run aborting.
step "convert-classic.mjs" node scripts/convert-classic.mjs docs/fixtures/live/classic.ndjson docs/fixtures/live/blocks.ndjson --allow-freeform --allow-text-mismatch
push_fixture blocks.ndjson
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

# screens.mjs discovers URLs through wp-env for `npm run test:live`; it has no other target.
if [ -n "$K8S_TARGET" ]; then
	echo "plan.sh: screens.mjs skipped (wp-env only)"
else
	step "screens.mjs" node scripts/live/screens.mjs
fi

echo "plan.sh: done"
