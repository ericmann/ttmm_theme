#!/usr/bin/env bash
# Mechanical checks for docs/SPEC.md §3. Exit non-zero on any hit. Extend as the spec grows.
set -euo pipefail
fail=0
hit() { echo "::error::$1"; fail=1; }
g() { grep -rnE --include='*.php' --include='*.js' --include='*.html' --include='*.mjs' "$@" 2>/dev/null || true; }

# 3.1.1 theme never owns data
out=$(g 'register_taxonomy|register_post_type|register_post_meta|register_term_meta|update_option\(|add_option\(|get_term_meta|get_post_meta|new WP_Query|get_posts\(|wp_remote_' themes/ttm-theme || true)
[ -n "$out" ] && { echo "$out"; hit "theme owns data (SPEC 3.1.1)"; }

# 3.2.6 no front-end network requests
out=$(g 'fetch\(|XMLHttpRequest|apiFetch|admin-ajax\.php|/wp-json/' themes/ttm-theme/assets/js plugins/ttm-core/blocks --include='view.js' || true)
[ -n "$out" ] && { echo "$out"; hit "front-end network request (SPEC 3.2.6)"; }

# 3.2.7 no nonces on cacheable output
out=$(g 'wp_create_nonce|wp_nonce_field|wp_nonce_url' themes/ttm-theme plugins/ttm-core/blocks || true)
[ -n "$out" ] && { echo "$out"; hit "nonce in cacheable output (SPEC 3.2.7)"; }

# 3.2.8 no per-visitor markup
out=$(g 'is_user_logged_in|wp_get_current_user|get_current_user_id|\$_COOKIE|\$_SESSION' themes/ttm-theme plugins/ttm-core/blocks || true)
[ -n "$out" ] && { echo "$out"; hit "per-visitor markup (SPEC 3.2.8)"; }

# 3.2.9 one clock
out=$(g '\btime\(\)|\bdate\(|wp_date\(|current_time\(|current_datetime\(|new \\?DateTime' plugins/ttm-core/src plugins/ttm-core/blocks themes/ttm-theme | grep -v 'src/Support/Clock.php' || true)
[ -n "$out" ] && { echo "$out"; hit "clock read outside Support/Clock.php (SPEC 3.2.9)"; }

# 3.2.12 bounded queries
out=$(g "posts_per_page'?\s*=>\s*-1|nopaging'?\s*=>\s*true|numberposts'?\s*=>\s*-1" plugins/ttm-core themes/ttm-theme || true)
[ -n "$out" ] && { echo "$out"; hit "unbounded query (SPEC 3.2.12)"; }

# 3.3.15 dangerous PHP
out=$(g '\beval\(|\bunserialize\(|\bextract\(|create_function\(|\bassert\(|\bsystem\(|\bexec\(|shell_exec\(|passthru\(|proc_open\(|curl_[a-z_]+\(|file_get_contents\(\s*.http|fopen\(\s*.http' plugins/ttm-core themes/ttm-theme || true)
[ -n "$out" ] && { echo "$out"; hit "dangerous PHP (SPEC 3.3.15)"; }

# 3.3.16 unsafe remote
out=$(g 'wp_remote_(get|post|request)\(' plugins/ttm-core | grep -v 'wp_safe_remote' || true)
[ -n "$out" ] && { echo "$out"; hit "use wp_safe_remote_* (SPEC 3.3.16)"; }

# 3.1.2 plugin inline styles limited to allow-list
out=$(g 'style="' plugins/ttm-core/blocks plugins/ttm-core/src | grep -vE 'style="(grid-column|aspect-ratio|--ttm-)[^"]*"' || true)
[ -n "$out" ] && { echo "$out"; hit "plugin inline style outside allow-list (SPEC 3.1.2)"; }

[ "$fail" = 0 ] && echo "forbidden-patterns: clean"
exit $fail
