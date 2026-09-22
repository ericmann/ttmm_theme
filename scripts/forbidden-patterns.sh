#!/usr/bin/env bash
# Mechanical checks for docs/SPEC.md §3. Exit non-zero on any hit. Extend as the spec grows.
set -euo pipefail
fail=0
hit() { echo "::error::$1"; fail=1; }
g() { grep -rnE --include='*.php' --include='*.js' --include='*.html' --include='*.mjs' "$@" 2>/dev/null || true; }

# Rule 1: theme never owns data (also get_terms/wp_remote_; allowed reads via
# get_category_by_slug/get_category_link/get_feed_link are plain function calls, not matched
# by these patterns, so no extra exclusion is needed).
out=$(g 'register_taxonomy|register_post_type|register_post_meta|register_term_meta|update_option\(|add_option\(|get_term_meta|get_post_meta|new WP_Query|get_posts\(|get_terms\(|wp_remote_' themes/ttm-theme || true)
[ -n "$out" ] && { echo "$out"; hit "theme owns data (SPEC rule 1)"; }

# Rule 3: every TTM\Core symbol / ttm_ function call under themes/ must be guarded by
# function_exists()/class_exists()/defined() on the same line or either of the two lines
# before it. A plain grep can't see "the previous two lines", so this is a small inline
# Python check instead (python3 is a safe cross-platform assumption for CI, same spirit as
# the existing scripts/check-*.mjs helpers).
out=$(python3 - << 'PYEOF' 2>/dev/null || true
import re, glob
pattern = re.compile(r'TTM\\Core|\bttm_[a-zA-Z_]*\(')
guard = re.compile(r'function_exists\(|class_exists\(|defined\(')
violations = []
for path in glob.glob('themes/ttm-theme/**/*.php', recursive=True):
    with open(path, encoding='utf-8') as f:
        lines = f.readlines()
    for i, line in enumerate(lines):
        if pattern.search(line):
            window = lines[max(0, i - 2):i + 1]
            if not any(guard.search(w) for w in window):
                violations.append(f"{path}:{i + 1}: {line.strip()}")
print('\n'.join(violations))
PYEOF
)
[ -n "$out" ] && { echo "$out"; hit "unguarded TTM\\Core/ttm_ reference in themes/ (SPEC rule 3)"; }

# Rule 5: options/meta/transients keyed under plugins/ttm-core/src must use the ttm_ prefix.
# Reads of pre-existing WordPress-core option names are allow-listed (we don't own the name,
# we're just consuming WordPress's own convention): default_comment_status,
# default_ping_status (both named explicitly in the migrate:close-comments task text),
# timezone_string, blogname (as scoped in the task text), plus sticky_posts (found live in
# Query/Lead.php, reading WP's native "Stick this post" feature).
allowed_core_options="default_comment_status|default_ping_status|timezone_string|blogname|blogdescription|sticky_posts"
out=$(grep -rnE "\b(add_option|update_option|get_option|set_transient|get_transient|register_post_meta|register_term_meta)\(\s*'[a-zA-Z_]+'" plugins/ttm-core/src --include='*.php' 2>/dev/null | grep -vE "\(\s*'(ttm_[a-zA-Z_]*|${allowed_core_options})'" || true)
[ -n "$out" ] && { echo "$out"; hit "non-ttm_ option/meta/transient name (SPEC rule 5)"; }

# Rule 6/3.2.6: no front-end network requests
out=$(g 'fetch\(|XMLHttpRequest|apiFetch|admin-ajax\.php|/wp-json/' themes/ttm-theme/assets/js plugins/ttm-core/blocks --include='view.js' || true)
[ -n "$out" ] && { echo "$out"; hit "front-end network request (SPEC rule 6)"; }

# Rule 7: no nonces on cacheable output -- every render.php, pattern, template part, template.
out=$(g 'wp_create_nonce|wp_nonce_field|wp_nonce_url' themes/ttm-theme/patterns themes/ttm-theme/parts themes/ttm-theme/templates plugins/ttm-core/blocks --include='render.php' --include='*.html' || true)
[ -n "$out" ] && { echo "$out"; hit "nonce in cacheable output (SPEC rule 7)"; }

# Rule 8: no per-visitor markup -- render.php/blocks/theme plus the plugin's own data layer
# (Bindings, Query, Blocks). Cache/Headers.php is the one allowed exception (it only ever
# skips caching for logged-in users, never varies markup).
out=$(g 'is_user_logged_in|wp_get_current_user|get_current_user_id|\$_COOKIE|\$_SESSION' themes/ttm-theme plugins/ttm-core/blocks plugins/ttm-core/src/Bindings plugins/ttm-core/src/Query plugins/ttm-core/src/Blocks | grep -v 'src/Cache/Headers.php' || true)
[ -n "$out" ] && { echo "$out"; hit "per-visitor markup (SPEC rule 8)"; }

# Rule 9: one clock
out=$(g '\btime\(\)|\bdate\(|wp_date\(|current_time\(|current_datetime\(|new \\?DateTime' plugins/ttm-core/src plugins/ttm-core/blocks themes/ttm-theme | grep -v 'src/Support/Clock.php' || true)
[ -n "$out" ] && { echo "$out"; hit "clock read outside Support/Clock.php (SPEC rule 9)"; }

# Rule 12: bounded queries (also number => 0 for term queries).
out=$(g "posts_per_page'?\s*=>\s*-1|nopaging'?\s*=>\s*true|numberposts'?\s*=>\s*-1|number'?\s*=>\s*0\b" plugins/ttm-core themes/ttm-theme --include='render.php' || true)
[ -n "$out" ] && { echo "$out"; hit "unbounded query (SPEC rule 12)"; }

# Rule 15: dangerous PHP.
out=$(g '\beval\(|\bunserialize\(|\bextract\(|create_function\(|\bassert\(|\bsystem\(|\bexec\(|shell_exec\(|passthru\(|proc_open\(|curl_[a-z_]+\(|file_get_contents\(\s*.http|fopen\(\s*.http' plugins/ttm-core themes/ttm-theme || true)
[ -n "$out" ] && { echo "$out"; hit "dangerous PHP (SPEC rule 15)"; }

# Rule 15 (continued): include/require of a variable path. A one-line-above marker comment
# "forbidden-patterns:allow-variable-include" documents a reviewed, fixed-path exception (PSR-4
# autoloading and a hardcoded build-asset path -- never request-derived); -B1 pulls that
# preceding line into the same block so it can be filtered out.
out=$(grep -rnE -B1 '\b(include|require)(_once)?\s*\(?\s*\$' plugins/ttm-core themes/ttm-theme --include='*.php' 2>/dev/null | awk 'BEGIN{RS="--\n"} $0 !~ /forbidden-patterns:allow-variable-include/ && $0 !~ /^[[:space:]]*$/' || true)
[ -n "$out" ] && { echo "$out"; hit "include/require of a variable path (SPEC rule 15)"; }

# Rule 16: one outbound URL per feature -- wp_safe_remote_(get|post) only in the three call
# sites that actually make one (the newsletter forward lives in Provider/CustomUrl.php, not
# Handler.php as an earlier plan draft assumed -- Handler only decides whether to forward).
out=$(g 'wp_safe_remote_(get|post)\(' plugins/ttm-core/src | grep -vE 'Verse/Fetcher\.php|Cache/Cloudflare\.php|Newsletter/Provider/CustomUrl\.php' || true)
[ -n "$out" ] && { echo "$out"; hit "wp_safe_remote_* outside its one allowed call site (SPEC rule 16)"; }
out=$(g 'wp_remote_(get|post|request)\(' plugins/ttm-core | grep -v 'wp_safe_remote' || true)
[ -n "$out" ] && { echo "$out"; hit "use wp_safe_remote_* (SPEC rule 16)"; }

# Rule 17: secrets are constants, never printed. Flag the constant name appearing outside a
# quoted string on an echo/printf line (mentioning the constant's *name* in admin help text,
# inside quotes, is fine -- printing its *value* is not).
out=$(g 'echo|printf' plugins/ttm-core/src | grep -E "TTM_(NEWSLETTER_API_KEY|CLOUDFLARE_API_TOKEN)" | grep -vE "'[^']*TTM_(NEWSLETTER_API_KEY|CLOUDFLARE_API_TOKEN)[^']*'" || true)
[ -n "$out" ] && { echo "$out"; hit "secret constant name outside a quoted string on an echo/printf line (SPEC rule 17)"; }

# Rule 24: every tunable is a Config key -- bare numeric literals >= 2 outside Config.php fail
# the build. One allow-list: HTTP status codes ('status' => 404), not a tunable -- it's the
# meaning of the response.
out=$( { grep -rnE "=>\s*[2-9][0-9]*\b|=>\s*[0-9]{2,}\b" plugins/ttm-core/src --include='*.php' 2>/dev/null | grep -v 'Config.php'; grep -rnE "=>\s*[2-9][0-9]*\b|=>\s*[0-9]{2,}\b" plugins/ttm-core/blocks --include='render.php' 2>/dev/null; } | grep -vE "'status'\s*=>\s*[0-9]{3}\b" || true)
[ -n "$out" ] && { echo "$out"; hit "hard-coded tunable outside Config.php (SPEC rule 24)"; }

# Rule 24 (cont.): the same class of literal hiding behind a plain assignment (`$x = 2;`)
# rather than an array-literal `=>`. Same HTTP-status allow-list.
out=$( { grep -rnE "=\s*[2-9][0-9]*\s*;|=\s*[0-9]{2,}\s*;" plugins/ttm-core/src --include='*.php' 2>/dev/null | grep -v 'Config.php'; grep -rnE "=\s*[2-9][0-9]*\s*;|=\s*[0-9]{2,}\s*;" plugins/ttm-core/blocks --include='render.php' 2>/dev/null; } | grep -vE "status\s*=\s*[0-9]{3}\s*;" || true)
[ -n "$out" ] && { echo "$out"; hit "hard-coded tunable outside Config.php (SPEC rule 24)"; }

# Rule 32: i18n -- bare strings echoed directly in render.php (excludes internal string
# comparisons like `echo 'x' === $y ? ... : ...`, which never reach the visitor as text).
out=$(g "echo '[A-Za-z]|esc_html\( '[A-Za-z]" plugins/ttm-core/blocks --include='render.php' | grep -vE '===|!==' || true)
[ -n "$out" ] && { echo "$out"; hit "bare string in render.php (SPEC rule 32)"; }

# Rule 3.3.15 / 2 carried over: plugin inline styles limited to allow-list.
out=$(g 'style="' plugins/ttm-core/blocks plugins/ttm-core/src | grep -vE 'style="(grid-column|aspect-ratio|--ttm-)[^"]*"' || true)
[ -n "$out" ] && { echo "$out"; hit "plugin inline style outside allow-list (SPEC rule 2)"; }

[ "$fail" = 0 ] && echo "forbidden-patterns: clean"
exit $fail
