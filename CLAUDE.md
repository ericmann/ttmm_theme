# These Things Matter — repository guide

Block theme `themes/ttm-theme` (presentation only) + companion plugin `plugins/ttm-core` (all data, blocks, bindings, cron, CLI, migration) for eric.mann.blog. The site is served from cache (Cloudflare + Batcache): every page must be a correct static document.

## Principles
- [ ] Theme never owns data; plugin never owns presentation (SPEC §3.1).
- [ ] Every page renders correctly as a cached static document: no per-visitor markup, no nonces on cacheable output, no front-end network requests (§3.2).
- [ ] All "now" reads go through `TTM\Core\Support\Clock` (§3.2 rule 9).
- [ ] Every tunable is a `Config` key; ⚠️ ASSUMPTION values live only in `Config.php` / `scripts/check-budget.mjs` (§3.4 rule 24, rule 30).
- [ ] Every fallback row in `docs/06-fallbacks.md` is a server-side branch with a test; blocks never render empty wrappers or placeholder copy.
- [ ] Security by construction: sanitize on read, escape on output, capability + nonce on every write, one outbound URL per integration, secrets are constants (§3.3).
- [ ] Tests are the acceptance criteria; a behavioural change without a failing-then-passing test is rejected (§3.4 rule 27).
- [ ] Every user-facing string is translatable (`ttm-core` / `ttm-theme`).
- [ ] 100% block editor authoring; no shortcodes, widgets, classic templates, dark mode, comments UI.

## Commands
- `composer lint` — php -l + PHPCS (WordPress-Extra, Docs, VIP-Go, PHPCompatibilityWP; security sniffs are errors)
- `composer test:unit` — PHPUnit + Brain\Monkey, `tests/unit` (no WordPress)
- `npm run lint` — ESLint, stylelint, theme.json check, block.json contract, CSS budget
- `npm run test:unit` — Jest via wp-scripts
- `npm run build` — wp-scripts build → `plugins/ttm-core/build/` (editor bundle + `blocks/<name>/`)
- `bash scripts/forbidden-patterns.sh` — SPEC §3 greps
- `npm run test:integration` — wp-env (WP 7.1.1 / PHP 8.3) + PHPUnit `tests/integration` (needs Docker; `npx wp-env start` first)
- `npm run check:theme-json` — presets vs the token sheet
- `npm run test:e2e` — Playwright + axe against seeded wp-env (Phase 8)
- `npm run env:seed` / `npm run env:cli -- ttm seed --state=quiet|empty --reset` — demo content

## Module map
Plugin `plugins/ttm-core/src/` (PSR-4 `TTM\Core\`), dependency arrow points down; `Cache`, `Verse`, `Newsletter` never import `Blocks`:
- `Config.php` (imports nothing) · `Support/` Clock, Dates, Text, Html (imports Config)
- `Taxonomy/` Series, SeriesAdmin · `Meta/` PostMeta, PrimaryCategory, WordCount, Form, SeriesPosition
- `Query/` Lead, Cells (owns F9 stale-year end to end: cached staleness, count, dek suppression), Archive, SeriesIndex, Stats, JournalExcerpt · `Fiction/` Serials, Books
- `Verse/` Fetcher, Cron, Admin · `Newsletter/` Handler, Settings, Providers, Provider/{Provider,Jetpack,CustomUrl,Mailto,None}
- `Cache/` Headers, Purge, Cloudflare, Batcache · `Admin/` Page, General · `Templates/Hierarchy` · `Nav/CurrentSection` · `Compat/Theme`
- `Bindings/` Sources (WP glue), Values (pure) · `Blocks/` Registrar, Helpers (registered `Plugin` module; owns the `ttm/archive-by-year` scope-flag hooks) · `Editor/` Sidebar, Checks, Columns, SeriesPartList
- `Rest/` SeriesController, VerseController, LeadController · `Cli/` Loader, Command, *Command, Seeder · `Plugin.php` composition root
- Blocks: `plugins/ttm-core/blocks/<name>/{block.json,render.php,index.js}`; editor JS `plugins/ttm-core/src/editor/`; entries from root `webpack.config.js`.

Theme `themes/ttm-theme/`: `theme.json`, `style.css`, `functions.php`, `inc/{block-styles,patterns,image-sizes,bindings-compat,starter-content}.php`, `templates/*.html`, `parts/{header-front,header-inner,rail,footer}.html`, `patterns/*.php`, `assets/{css/ttm.css,css/editor.css,js/nav.js,js/variations.js,fonts/}`.

Tests: `tests/unit` (Brain\Monkey), `tests/integration` (WP test suite in wp-env; `TTM_IntegrationTestCase`), `tests/e2e` (Playwright). Fixtures: `docs/fixtures/` (mapped into wp-env at `wp-content/ttm-fixtures`).

## Constraints
- No `register_taxonomy|register_post_type|register_post_meta|register_term_meta|add_option|update_option|get_term_meta|get_post_meta|WP_Query|get_posts|wp_remote_` under `themes/ttm-theme/`.
- No `wp_enqueue_style` on `wp_enqueue_scripts`, no `<style`, and no `style="` outside `grid-column`, `aspect-ratio`, `--ttm-*` under `plugins/ttm-core/`.
- Every `TTM\Core`/`ttm_` reference under `themes/` is guarded by `function_exists`/`class_exists`/`defined`.
- `TTM_CORE_API === 1` in `ttm-core.php`; the theme checks it and shows a notice, never a fatal.
- Prefixes: PHP `TTM\Core\`/`TTM\Theme\`, blocks `ttm/`, CSS `ttm-`, text domains `ttm-core`/`ttm-theme`, options/meta/transients `ttm_`.
- No `fetch(`, `XMLHttpRequest`, `apiFetch`, `admin-ajax.php`, `/wp-json/` under `themes/ttm-theme/assets/js/` or any block `view.js`; the only front-end script is `assets/js/nav.js` (+ core Navigation).
- No `wp_create_nonce|wp_nonce_field|wp_nonce_url` in any `render.php`, pattern, part or template.
- No `is_user_logged_in|wp_get_current_user|get_current_user_id|$_COOKIE|$_SESSION` in `render.php`, bindings, patterns, templates; only `src/Cache/Headers.php` may call `is_user_logged_in()`.
- No `time()`, `date(`, `wp_date(`, `current_time(`, `current_datetime(`, `new DateTime*` outside `src/Support/Clock.php`.
- No `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, `number => 0` under `plugins/ttm-core/src/` or `render.php`.
- Derived data (series index, stats, top tags, lead id, verse) is read from options/transients, recomputed only on write hooks.
- `wp_safe_remote_*` only in `Verse/Fetcher.php`, `Cache/Cloudflare.php`, `Newsletter/Provider/CustomUrl.php`; targets never come from request input or admin-editable options except the validated `https` custom-url endpoint.
- No `eval|unserialize|extract|create_function|assert|system|exec|shell_exec|passthru|proc_open|curl_|file_get_contents('http|fopen('http` or variable-path `include`/`require` under `plugins/` or `themes/`; structured meta is JSON via `wp_json_encode`/`json_decode(..., true)`.
- Secrets (`TTM_NEWSLETTER_API_KEY`, `TTM_CLOUDFLARE_API_TOKEN`, `TTM_CLOUDFLARE_ZONE_ID`) are constants: never in options, never `show_in_rest`, never printed.
- Every non-`GET` REST route and every `admin_post_*`/settings save checks a capability and a nonce; public `ttm/v1` routes are `GET` with `permission_callback => '__return_true'`.
- Every `register_post_meta`/`register_term_meta` passes `type`, `single`, `sanitize_callback`, `auth_callback`, `show_in_rest` schema.
- Every `ttm/*` block: `block.json` with `supports {html,align,color,typography,spacing}: false`, `render: file:./render.php`, `render.php`, `index.js` (ServerSideRender), `previewState` attribute, wrapper `ttm-<name>` + `data-ttm-block`, empty → `''`, and a test in `tests/integration/Blocks/` covering every `06` row naming it.
- Every block-binding source has a unit test for its normal and empty value.
- `tests/unit` never loads WordPress; anything needing `WP_Query`, templates or REST is in `tests/integration`.
- Rendered markup is deterministic: no random ids, no timestamps except through `Clock`.
- `themes/ttm-theme/assets/css/ttm.css` ≤ `cssBudgetBytes` (33200, `scripts/check-budget.mjs`) — plain CSS, custom properties from `theme.json` presets, no framework, no hex literals.
- `package.json` `dependencies` stays `{}`; everything is a devDependency; `composer.lock`/`package-lock.json` committed; `npm audit --audit-level=high` and `composer audit` pass.
- `uninstall.php` deletes `ttm_` options/transients only, and only when `TTM_REMOVE_DATA === true`.
- No `prefers-color-scheme`, dark palette or theme toggle; every colour is a `theme.json` preset.
- Landmarks per 04 §8; every whole-row link is one `<a>` whose accessible name is the headline; axe serious/critical = 0 on the seven seeded screens.

## Commit template
```
<ID>: <title>

Goal: <one sentence>
Tests: <test files/names added or changed>
Interpretation: <any SPEC reading you had to choose, or "none">
Measurement: <before/after for tuning tasks; omit otherwise>
Manual check: <what a human must open, or "none">
```

Precedence: `docs/SPEC.md` wins over `docs/PLAN.md`, which wins over code comments. Design docs (`docs/01`–`07`) win on visuals; SPEC wins on engineering.
