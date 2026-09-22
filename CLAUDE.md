# These Things Matter — repository guide (phase 2: front-page fidelity)

Block theme `themes/ttm-theme` (presentation only) + companion plugin `plugins/ttm-core` (all data, blocks, bindings, cron, CLI, migration) for eric.mann.blog. The site is served from cache (Cloudflare + Batcache): every page must be a correct static document. This flight makes `/` match mock `2a` (1280) and `3a` (390) in `docs/Eric Mann Newspaper.dc.html`; `docs/SPEC.md §6.1` is the element contract and `§6.2` the assertion table.

## Principles
- [ ] Theme never owns data; plugin never owns presentation (SPEC §3.1). The theme's `ttm.css` styles every `ttm-*` class the markup emits (rule 34); one class per component, spelled once (rule 37).
- [ ] Every page renders correctly as a cached static document: no per-visitor markup, no nonces on cacheable output, no front-end network requests (§3.2). Markup never varies per viewport; CSS does.
- [ ] All "now" reads go through `TTM\Core\Support\Clock` (rule 9).
- [ ] Every tunable is a `Config` key read as `Config::get('key', <default equal to defaults()>)`; ⚠️ ASSUMPTION values live only in `Config.php` / `scripts/check-budget.mjs` (rule 24 amended, rule 30).
- [ ] Fidelity is a test: every §6.2 row is a Playwright test in `tests/e2e/fidelity.spec.mjs`; a front-page CSS change without its row is rejected (rule 38).
- [ ] Rules are full width; grid groups use `"layout":{"type":"default"}` and `ttm.css` owns layout (rules 35/36).
- [ ] Every fallback row in `docs/06-fallbacks.md` stays a server-side branch with a test; blocks never render empty wrappers or placeholder copy.
- [ ] Security by construction: sanitize on read, escape on output, capability + nonce on every write, one outbound URL per integration, secrets are constants (§3.3).
- [ ] Tests are the acceptance criteria; a behavioural change without a failing-then-passing test is rejected (rule 27). Every user-facing string is translatable (`ttm-core` / `ttm-theme`).
- [ ] Non-goals this flight: content model, taxonomies, meta, REST, CLI, migration, cache and verse fetching internals; inner-template redesign; dark mode; comments; analytics; search; new blocks; Jetpack connection in wp-env.

## Commands
- `composer lint` — php -l + PHPCS (WordPress-Extra, Docs, VIP-Go, PHPCompatibilityWP; security sniffs are errors)
- `composer test:unit` — PHPUnit + Brain\Monkey, `tests/unit` (no WordPress)
- `npm run lint` — ESLint, stylelint, theme.json check, block.json contract, CSS budget, CSS coverage (`scripts/check-css-coverage.mjs`, from P0-01)
- `npm run test:unit` — Jest via wp-scripts (`scripts/test`, plugin editor JS)
- `npm run build` — wp-scripts build → `plugins/ttm-core/build/` (editor bundle + `blocks/<name>/`); run it before opening any editor in wp-env
- `bash scripts/forbidden-patterns.sh` — SPEC §3 greps
- `npm run test:integration` — wp-env (WP 7.1.1 / PHP 8.3) + PHPUnit `tests/integration` (needs Docker)
- `npm run test:e2e` — starts wp-env, reseeds, runs Playwright + axe: phase 1 `specs/*.spec.mjs` (desktop/phone projects) and the `fidelity` project (`fidelity.spec.mjs`, `editors.spec.mjs`)
- `npm run screenshots` — writes `docs/feedback/phase-2/*.png` from the running seeded site (from P0-05)
- `npm run env:seed` / `npm run env:cli -- ttm seed --state=quiet|empty --reset` — demo content (`docs/fixtures/seed/*.json`)
- Stale wp-env mounts after a branch switch: `npx wp-env stop && npx wp-env start`

## Module map
Plugin `plugins/ttm-core/src/` (PSR-4 `TTM\Core\`), SPEC §4 table; the arrow points down and `Cache`, `Verse`, `Newsletter` never import `Blocks`:
- `Config.php` (imports nothing) · `Support/` Clock, Dates, Text, Html (imports Config)
- `Taxonomy/` Series, SeriesAdmin · `Meta/` PostMeta, PrimaryCategory, WordCount, Form, SeriesPosition
- `Query/` Lead, Cells (owns F9 stale-year end to end), Archive, SeriesIndex, Stats, JournalExcerpt (sentence-trimmed, hard-capped at `journal.excerpt_max_words`) · `Fiction/` Serials, Books
- `Cache/` Headers, Purge, Cloudflare, Batcache · `Verse/` Fetcher, Cron, Admin · `Newsletter/` Handler (dev-accept when `newsletter.endpoint` is empty outside production), Settings, Providers (chain: configured → custom-url dev-accept → mailto → none), Form (the one §6.3 form renderer), Provider/{Provider,Jetpack,CustomUrl,Mailto,None}
- `Bindings/` Sources (all binding sources incl. `ttm/verse-copyright`, `ttm/category-count format=entries`, `ttm/today format=footer`, `ttm/meta-line readingFormat`; pagination label filters), Values (pure formatters) · `Blocks/` Registrar (registers on `init`; missing build → committed `assets/editor-fallback.js` + admin notice, never a dropped `editorScript`), Helpers
- `Editor/` Sidebar, Checks, Columns, SeriesPartList · `Templates/` Hierarchy · `Nav/` CurrentSection (adds `current-section` + `current-menu-item`; front page marks the lead's section per `nav.front_current`; fills section labels from term names; F18) · `Rest/` SeriesController, VerseController, LeadController · `Cli/` Loader, Command, *Command, Seeder (prose from `docs/fixtures/seed/prose.json`; seeds `custom-url` newsletter, never Jetpack) · `Compat/` Theme · `Admin/` Page, General, BuildNotice (§6.7 editor-bundle-missing notice, reads `Blocks\Registrar::fallback_blocks()`) · `Plugin.php` composition root
- Blocks: `plugins/ttm-core/blocks/<name>/{block.json,render.php,index.js}` (19, fixed); editor JS `plugins/ttm-core/src/editor/`; entries from root `webpack.config.js`; the only `wp_safe_remote_post` for the newsletter is in `Newsletter/Provider/CustomUrl.php`.

Theme `themes/ttm-theme/`: `theme.json`, `style.css`, `functions.php`, `inc/{block-styles,patterns,image-sizes,bindings-compat,starter-content}.php` + `inc/pattern-templates/section-cell.php`, `templates/*.html`, `parts/{header-front,header-inner,rail,footer}.html`, `patterns/*.php`, `assets/{css/ttm.css,css/editor.css,js/nav.js,js/variations.js,fonts/}`. `ttm.css` is organised by `docs/01-design-language.md §4` component numbers, one comment header per component.

Tests: `tests/unit` (Brain\Monkey; `BoundariesTest` encodes §4, `ConfigFallbacksTest` encodes rule 24), `tests/integration` (WP suite in wp-env; `TTM_IntegrationTestCase` with `set_now()`, `seed()`, `render_template()`), `tests/e2e` (Playwright: `specs/*.spec.mjs`, `fidelity.spec.mjs`, `editors.spec.mjs`, `lib/{urls,presets,style}.mjs`). Fixtures: `docs/fixtures/` (mapped into wp-env at `wp-content/ttm-fixtures`). Screenshots: `docs/feedback/phase-2/`.

## Constraints
- No `register_taxonomy|register_post_type|register_post_meta|register_term_meta|add_option|update_option|get_term_meta|get_post_meta|WP_Query|get_posts|wp_remote_` under `themes/ttm-theme/`; this flight adds no data call to `themes/` — counts, copyright and the current-section mark come from plugin bindings/filters (rule 40).
- No `wp_enqueue_style` on `wp_enqueue_scripts`, no `<style`, and no `style="` outside `grid-column`, `aspect-ratio`, `--ttm-*` under `plugins/ttm-core/` (rule 2); markup fixes add or rename `ttm-*` classes only.
- Every `TTM\Core`/`ttm_` reference under `themes/` is guarded by `function_exists`/`class_exists`/`defined`; theme PHP references only `Config` and `Compat\Theme`.
- `TTM_CORE_API === 1` in `ttm-core.php`; the theme checks it and shows a notice, never a fatal.
- Prefixes: PHP `TTM\Core\`/`TTM\Theme\`, blocks `ttm/`, CSS `ttm-`, text domains `ttm-core`/`ttm-theme`, options/meta/transients `ttm_`.
- No `fetch(`, `XMLHttpRequest`, `apiFetch`, `admin-ajax.php`, `/wp-json/` under `themes/ttm-theme/assets/js/` or any block `view.js`; the only front-end script is `assets/js/nav.js` (+ core Navigation); `assets/editor-fallback.js` is editor-only and never enqueued on the front end.
- No `wp_create_nonce|wp_nonce_field|wp_nonce_url` in any `render.php`, pattern, part or template; the newsletter form uses the HMAC token (custom-url) or Jetpack's widget POST (no nonce).
- No `is_user_logged_in|wp_get_current_user|get_current_user_id|$_COOKIE|$_SESSION` in `render.php`, bindings, patterns, templates; only `src/Cache/Headers.php` may call `is_user_logged_in()`.
- No `time()`, `date(`, `wp_date(`, `current_time(`, `current_datetime(`, `new DateTime*` outside `src/Support/Clock.php`.
- No `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, `number => 0` under `plugins/ttm-core/src/` or `render.php` (request-time code; CLI may page with `cli.batch`).
- Derived data (series index, stats, top tags, lead id, verse) is read from options/transients, recomputed only on write hooks; front-end requests never write.
- `wp_safe_remote_*` only in `Verse/Fetcher.php`, `Cache/Cloudflare.php`, `Newsletter/Provider/CustomUrl.php`; targets never come from request input or admin-editable options except the validated `https` custom-url endpoint.
- No `eval|unserialize|extract|create_function|assert|system|exec|shell_exec|passthru|proc_open|curl_|file_get_contents('http|fopen('http` or variable-path `include`/`require` under `plugins/` or `themes/`; structured meta is schema-validated arrays.
- Secrets (`TTM_NEWSLETTER_API_KEY`, `TTM_CLOUDFLARE_API_TOKEN`, `TTM_CLOUDFLARE_ZONE_ID`) are constants: never in options, never `show_in_rest`, never printed.
- Every non-`GET` REST route and every `admin_post_*`/settings save checks a capability and a nonce; `admin_post_nopriv_ttm_subscribe` is protected by token + honeypot + rate limit; public `ttm/v1` routes are `GET` with `permission_callback => '__return_true'`.
- Every `ttm/*` block: `block.json` with `supports {html,align,color,typography,spacing}: false`, `render: file:./render.php`, `render.php`, `index.js` (ServerSideRender), `previewState` attribute, wrapper `ttm-<name>` + `data-ttm-block`, empty → `''`, a test in `tests/integration/Blocks/`, and a non-empty `editor_script_handles` in every editor context (§6.7).
- Every block-binding source has a test for its normal and empty value; `tests/unit` never loads WordPress.
- Rendered markup is deterministic: no random ids (newsletter ids come from a per-request counter), no timestamps except through `Clock`.
- Bare numeric literals outside `Config.php` are limited to HTTP status codes, structural arithmetic, array indices and `Config::get()` fallbacks equal to `defaults()` (`tests/unit/ConfigFallbacksTest.php`, `scripts/forbidden-patterns.sh`).
- `themes/ttm-theme/assets/css/ttm.css` ≤ `cssBudgetBytes` (41984 ⚠️ ASSUMPTION, `scripts/check-budget.mjs`) — plain CSS, presets via `var(--wp--preset--…)`, no hex literals, no framework.
- Every `ttm-*` class emitted by templates, parts, patterns, `inc/`, `render.php` or `src/` has a selector in `ttm.css`/`style.css`, and every `ttm-*` selector matches emitted markup; `scripts/css-coverage-allow.txt` has fewer than 10 lines (rules 34/37).
- `.is-style-rule-1`/`.is-style-rule-2` are `width: 100%; max-width: none; margin-left: 0; margin-right: 0` (rule 35); no `core/group` with `is-style-grid-*` uses `constrained` or `flow` layout (rule 36).
- `grep -ri lorem docs/fixtures/seed/` returns nothing (rule 39).
- `package.json` `dependencies` stays `{}`; everything is a devDependency; `composer.lock`/`package-lock.json` committed; `npm audit --audit-level=high` and `composer audit` pass.
- `uninstall.php` deletes `ttm_` options/transients only, and only when `TTM_REMOVE_DATA === true`.
- No `prefers-color-scheme`, dark palette or theme toggle; every colour is a `theme.json` preset. Text under 18.66px is never `accent` or `neutral-600` (use `accent-700` / `neutral-700`); the poster ghost button is the one 01 §2.1 exception.
- Landmarks per 04 §8; every whole-row link is one `<a>` whose accessible name is the headline; axe serious/critical = 0 on `/` (fidelity) and the eight seeded screens.

## Commit template
```
<ID>: <title>

Goal: <one sentence>
Tests: <test files/names added or changed; fidelity rows un-fixme'd>
Interpretation: <any SPEC reading you had to choose, or "none">
Measurement: <before/after for tuning tasks; omit otherwise>
Manual check: <what a human must open, or "none">
```

Precedence: `docs/SPEC.md` wins over `docs/PLAN.md`, which wins over code comments. Design docs (`docs/01`, `02 §A`, `06`, mock `2a`/`3a`) win on visuals; SPEC wins on engineering. `docs/phase-1/` is history, not instructions.
