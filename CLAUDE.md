# These Things Matter — repository guide (phase 3: inner-template fidelity)

Block theme `themes/ttm-theme` (presentation only) + companion plugin `plugins/ttm-core` (all data, blocks, bindings, cron, CLI, migration) for eric.mann.blog. The site is served from cache (Cloudflare + Batcache): every page must be a correct static document. This flight makes the article (`2b`/`3b`), journal post (`2c`), Writing (`2d`), section archive (`1e`), series hub (`1f`), single series, journal archive, tag/date archive, search, 404 and static page match their mocks; `docs/SPEC.md §6` is the element contract and `§6.9` the assertion table.

## Principles
- [ ] Theme never owns data; plugin never owns presentation (SPEC §3.1). Every `ttm-*` class the markup emits has a rule in `ttm.css`, every `ttm-*`/`is-style-` selector matches a seeded screen, and the coverage allow-list is empty when the flight ends (rules 34, 37, 41).
- [ ] Every page renders correctly as a cached static document: no per-visitor markup, no nonces on cacheable output, no front-end network requests (§3.2). Markup never varies per viewport; CSS does.
- [ ] The page is a 1280px centred column; full-bleed elements break out with negative gutter margins (rule 42). The nav overlay is phone-only, breakpoint 720 (rule 43).
- [ ] Fidelity is a test: every §6.9 row is a Playwright test in `tests/e2e/fidelity.spec.mjs`; an inner-page CSS change without its row is rejected (rule 38). Rows below WCAG AA are asserted as `accent-700`/`neutral-700` (PLAN Decision "Colour vs a11y").
- [ ] Grids are `layout: default` groups and `ttm.css` owns layout; no `layout: constrained` inside a grid group (rule 36 extended). Slugs are the variables (rule 44).
- [ ] Every tunable is a `Config` key read as `Config::get('key', <default equal to defaults()>)`; ⚠️ ASSUMPTION values live only in `Config.php` / `scripts/check-budget.mjs`.
- [ ] Every fallback row in `docs/06-fallbacks.md` stays a server-side branch with a test; blocks never render empty wrappers or placeholder copy; seed images are neutral placeholders, never red (rule 45).
- [ ] Block wrappers keep phase 1's shape; a rename updates markup, CSS, lint lists, fidelity rows and PHP tests in the same commit (rule 46).
- [ ] Tests are the acceptance criteria (rule 27); unit tests never load WordPress; every binding source has normal and empty tests (rule 26); every user-facing string is translatable.
- [ ] Non-goals this flight: content model, taxonomies, meta, REST, CLI (except the Seeder), migration, cache, verse fetching; front-page changes beyond shared chrome; new blocks; dark mode; comments; analytics; Dependabot PRs #5/#10; Jetpack in wp-env.

## Commands
- `composer lint` — php -l + PHPCS (WordPress-Extra, Docs, VIP-Go, PHPCompatibilityWP; security sniffs are errors)
- `composer test:unit` — PHPUnit + Brain\Monkey, `tests/unit` (no WordPress)
- `npm run lint` — ESLint, stylelint, theme.json check (incl. preset-variable references), block.json contract, CSS budget, CSS coverage (`scripts/css-coverage-allow.txt` must stay empty; no `pending` line, per P5-02), fixme guard (`ALLOW_TAGGED = false`; no `test.fixme(` at all, per P5-02)
- `npm run test:unit` — Jest via wp-scripts (`scripts/test`, plugin editor JS)
- `npm run build` — wp-scripts build → `plugins/ttm-core/build/`; run it before opening any editor in wp-env
- `bash scripts/forbidden-patterns.sh` — SPEC §3 greps
- `npm run test:integration` — wp-env (WP 7.1.1 / PHP 8.3) + PHPUnit `tests/integration` (needs Docker)
- `npm run test:e2e` — starts wp-env, reseeds, runs Playwright + axe: phase 1 `specs/*.spec.mjs`, the `fidelity` project (`fidelity.spec.mjs`, `editors.spec.mjs`, `selectors.spec.mjs`)
- `npm run screenshots` — writes `docs/feedback/phase-3/*.png` from the running seeded site (from P0-12)
- `npm run env:seed` / `npm run env:cli -- ttm seed --state=quiet|empty --reset` — demo content (`docs/fixtures/seed/*.json`)
- Stale wp-env mounts after a branch switch: `npx wp-env stop && npx wp-env start`

## Module map
Plugin `plugins/ttm-core/src/` (PSR-4 `TTM\Core\`), SPEC §4 table with `BoundariesTest::KNOWN_EXCEPTIONS` accepted; the arrow points down and `Cache`, `Verse`, `Newsletter` never import `Blocks`:
- `Config.php` (imports nothing) · `Support/` Clock, Dates, Text, Html (imports Config)
- `Taxonomy/` Series, SeriesAdmin · `Meta/` PostMeta, PrimaryCategory (also orders category terms primary-first on `get_the_terms`), WordCount, Form, SeriesPosition
- `Query/` Lead, Cells, Archive, SeriesIndex, Stats, JournalExcerpt · `Fiction/` Serials, Books
- `Cache/` Headers, Purge, Cloudflare, Batcache · `Verse/` Fetcher, Cron, Admin · `Newsletter/` Handler (dispatches by provider), Settings, Providers, Form (the one §6.3 form renderer), Provider/{Provider,Jetpack,CustomUrl,Mailto,None}; the only newsletter `wp_safe_remote_post` is in `Newsletter/Provider/CustomUrl.php`
- `Bindings/` Sources (all binding sources incl. `ttm/newsletter-copy`, `ttm/archive-kind`, `ttm/section-label`, `ttm/search-summary`, `ttm/word-count whenUnsyndicated`, `ttm/category-count journal-full`; pagination relabel + disabled spans; empty `ttm/*`-bound paragraphs/headings render nothing), Values (pure formatters)
- `Blocks/` Registrar (registers on `init`; missing build → `assets/editor-fallback.js` + admin notice), Helpers (wrapper, year grouping, whole-row links for `ttm-archive-row`/`ttm-journal-row`, tag chips on `is-style-tags`, featured-image caption, search block classes)
- `Editor/` Sidebar, Checks, Columns, SeriesPartList · `Templates/` Hierarchy · `Nav/` CurrentSection (primary category on singles; `/series/` on the hub and series archives; F18) · `Rest/` SeriesController, VerseController, LeadController · `Cli/` Loader, Command, *Command, Seeder (rule 45 images, `weekday`/`caption`/`featured` fixture fields, admin display name) · `Compat/` Theme · `Admin/` Page, General, BuildNotice · `Plugin.php` composition root
- Blocks: `plugins/ttm-core/blocks/<name>/{block.json,render.php,index.js}` (19, fixed); `ttm/series-list` layouts `list|rail|grid-2|grid-3|strip`; editor JS `plugins/ttm-core/src/editor/`.

Theme `themes/ttm-theme/`: `theme.json` (font-size slug `article-h2` → `--wp--preset--font-size--article-h-2`), `style.css`, `functions.php`, `inc/{block-styles,patterns,image-sizes,bindings-compat,starter-content}.php`, `templates/*.html`, `parts/{header-front,header-inner,rail,footer}.html`, `patterns/*.php`, `assets/{css/ttm.css,css/editor.css,js/nav.js,js/variations.js,fonts/}`. `ttm.css` is organised by `docs/01-design-language.md §4` component numbers, one comment header per component, plus `/* 0.2 page container */`.

Tests: `tests/unit` (Brain\Monkey; `BoundariesTest`, `ConfigFallbacksTest`, `ScaffoldTest` reads this file), `tests/integration` (WP suite in wp-env; `TTM_IntegrationTestCase` with `set_now()`, `seed()`, `render_template()`), `tests/e2e` (Playwright: `specs/*.spec.mjs`, `fidelity.spec.mjs`, `editors.spec.mjs`, `selectors.spec.mjs`, `selectors-allow.txt`, `lib/{urls,presets,style}.mjs`). Screenshots: `docs/feedback/phase-3/`. Archived flights: `docs/phase-1/`, `docs/phase-2/` (history, not instructions).

## Constraints
- No `register_taxonomy|register_post_type|register_post_meta|register_term_meta|add_option|update_option|get_term_meta|get_post_meta|WP_Query|get_posts|wp_remote_` under `themes/ttm-theme/`; counts, labels, kickers and the current-section mark come from plugin bindings/filters (rules 1, 40).
- No `wp_enqueue_style` on `wp_enqueue_scripts`, no `<style`, and no `style="` outside `grid-column`, `aspect-ratio`, `--ttm-*` under `plugins/ttm-core/` (rule 2); render changes add or rename `ttm-*` classes only.
- Every `TTM\Core`/`ttm_` reference under `themes/` is guarded by `function_exists`/`class_exists`/`defined`; theme PHP references only `Config` and `Compat\Theme`.
- `TTM_CORE_API === 1` in `ttm-core.php`; the theme checks it and shows a notice, never a fatal.
- Prefixes: PHP `TTM\Core\`/`TTM\Theme\`, blocks `ttm/`, CSS `ttm-`, text domains `ttm-core`/`ttm-theme`, options/meta/transients `ttm_`.
- No `fetch(`, `XMLHttpRequest`, `apiFetch`, `admin-ajax.php`, `/wp-json/` under `themes/ttm-theme/assets/js/` or any block `view.js`; the only front-end script is `assets/js/nav.js` (+ core Navigation).
- No `wp_create_nonce|wp_nonce_field|wp_nonce_url` in any `render.php`, pattern, part or template; the newsletter form uses the HMAC token or Jetpack's server-side subscribe.
- No `is_user_logged_in|wp_get_current_user|get_current_user_id|$_COOKIE|$_SESSION` in `render.php`, bindings, patterns, templates; only `src/Cache/Headers.php` may call `is_user_logged_in()`.
- No `time()`, `date(`, `wp_date(`, `current_time(`, `current_datetime(`, `new DateTime*` outside `src/Support/Clock.php`.
- No `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, `number => 0` under `plugins/ttm-core/src/` or `render.php`.
- Derived data (series index, stats, top tags, lead id, verse) is read from options/transients, recomputed only on write hooks; front-end requests never write.
- `wp_safe_remote_*` only in `Verse/Fetcher.php`, `Cache/Cloudflare.php`, `Newsletter/Provider/CustomUrl.php`; targets never come from request input.
- No `eval|unserialize|extract|create_function|assert|system|exec|shell_exec|passthru|proc_open|curl_|file_get_contents('http|fopen('http` or variable-path `include`/`require` under `plugins/` or `themes/`; structured meta is schema-validated arrays (rule 15).
- Secrets (`TTM_NEWSLETTER_API_KEY`, `TTM_CLOUDFLARE_API_TOKEN`, `TTM_CLOUDFLARE_ZONE_ID`) are constants: never in options, never `show_in_rest`, never printed.
- Every non-`GET` REST route and every `admin_post_*`/settings save checks a capability and a nonce; `admin_post_nopriv_ttm_subscribe` is protected by token + honeypot + rate limit; public `ttm/v1` routes are `GET` with `permission_callback => '__return_true'`.
- Every `ttm/*` block: `block.json` with `supports {html,align,color,typography,spacing}: false`, `render: file:./render.php`, `index.js` (ServerSideRender), `previewState`, wrapper `div.ttm-<name>[data-ttm-block]` via `Helpers::wrapper()`, empty → `''`, a test in `tests/integration/Blocks/` (rules 25, 46).
- Bare numeric literals outside `Config.php` are limited to HTTP status codes, structural arithmetic, array indices and `Config::get()` fallbacks equal to `defaults()` (rule 24); CSS/theme.json values and e2e fixture values are not tunables. New keys: `series.related_limit` (4), `seed.image_band_angle` (30 ⚠️ ASSUMPTION, Seeder only).
- `themes/ttm-theme/assets/css/ttm.css` ≤ `cssBudgetBytes` (62464 ⚠️ ASSUMPTION, `scripts/check-budget.mjs`, raised from 61440 in R1-06 to restore SPEC/PLAN-named declarations dropped to fit) — plain CSS, presets via `var(--wp--preset--…)`, no hex literals; every `--wp--preset--font-size--*`/`--color--*` reference is generated by a slug (rule 44, `scripts/check-theme-json.mjs`).
- Every `ttm-*` class emitted by templates, parts, patterns, `inc/`, `render.php` or `src/` has a selector in `ttm.css`/`style.css` and vice versa; `scripts/css-coverage-allow.txt` holds only `<class> # P<n>-<nn> pending` lines and is empty at flight end (rule 34).
- Every `ttm-`/`is-style-` selector in `ttm.css` matches ≥ 1 element across the seeded screen set (`tests/e2e/selectors.spec.mjs`); `tests/e2e/selectors-allow.txt` lines carry a reason; no `pending` line remains (rule 41, since P5-02).
- `.wp-site-blocks { max-width: 1280px; margin-inline: auto; padding-inline: var(--wp--custom--gutter--desktop) }`; full-bleed only by negative gutter margins (rule 42). Inner nav `overlayMenu: "mobile"`, breakpoint 720 in CSS; no open/close button visible at ≥ 721 (rule 43).
- `.is-style-rule-1`/`.is-style-rule-2` are full width (rule 35); no `core/group` with `is-style-grid-*` uses `constrained`/`flow`, and no `layout: constrained` group sits inside one (rule 36 extended). Grep `is-style-grid` in `themes/ttm-theme/**`.
- `grep -n '210, 48, 19' plugins/ttm-core/src/Cli/Seeder.php` returns nothing; `grep -ri lorem docs/fixtures/seed/` returns nothing (rules 45, 39).
- `tests/e2e/fidelity.spec.mjs` and `tests/e2e/editors.spec.mjs` have no `test.fixme(` at all (`scripts/check-fixme.mjs`, since P5-02).
- `package.json` `dependencies` stays `{}`; `composer.lock`/`package-lock.json` committed; `npm audit --audit-level=high` and `composer audit` pass.
- No `prefers-color-scheme`, dark palette or theme toggle; every colour is a `theme.json` preset. Text under 18.66px is never `accent`, `neutral-500` or `neutral-600` (use `accent-700` / `neutral-700`); the poster ghost button is the one 01 §2.1 exception.
- Landmarks per 04 §8; every whole-row link is one `<a>` with the headline first in its text; axe serious/critical = 0 on every seeded screen at 1280 and 390.
- Never stage, move, delete or gitignore-edit `FOUNDRY_FEEDBACK.md`; never re-enable commit signing; never edit `docs/phase-1/` or `docs/phase-2/`.

## Commit template
```
<ID>: <title>

Goal: <one sentence>
Tests: <test files/names added or changed; fidelity rows un-fixme'd; pending lines removed>
Interpretation: <any SPEC reading you had to choose, or "none">
Measurement: <before/after for tuning tasks; omit otherwise>
Manual check: <what a human must open, or "none">
```

Precedence: `docs/SPEC.md` wins over `docs/PLAN.md`, which wins over code comments. Design docs (`docs/01`, `02 §B–§H`, `06`, the mock cards) win on visuals; SPEC wins on engineering. `docs/phase-1/` and `docs/phase-2/` are history, not instructions.
