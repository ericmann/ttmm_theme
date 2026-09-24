# These Things Matter — repository guide (phase 5: demo content, Playground, open source)

Block theme `themes/ttm-theme` (presentation only) + companion plugin `plugins/ttm-core` (all data, blocks, bindings, cron, CLI, migration) for eric.mann.blog, now built in public. The site is served from cache: every page must be a correct static document. This flight ships Openverse CC0/PDM photographs in the seed (`docs/SPEC.md §6.1–§6.2`), a committed demo WXR + options + WordPress Playground blueprint with a headless check (§6.3–§6.4), README screenshots (§6.5), a release workflow (§6.6) and GPL licence/identity hygiene (§6.7), plus the §3.1 fold-ins (SI-13, SI-16, SI-17). `§6.9` is the new fidelity table; `docs/PLAN.md` holds every decision.

## Principles
- [ ] Theme never owns data; plugin never owns presentation. Every `ttm-*` class the markup emits has a rule in `ttm.css`, every `ttm-*`/`is-style-` selector matches a seeded screen, and the coverage allow-list is empty (rules 34, 37, 41). No visual change this flight.
- [ ] Every page renders correctly as a cached static document: no per-visitor markup, no nonces on cacheable output, no front-end network requests. Photographs are same-origin.
- [ ] Demo assets are licence-clean and provable (rule 53): every file in `docs/fixtures/demo/images/` has a `CREDITS.json` row (cc0/pdm), sizes within the script constants; no image reaches the repo except through `npm run demo:fetch-images`.
- [ ] The demo is offline and deterministic (rule 54): `demo:build` makes no network request and two same-day builds are identical; the Openverse fetch is human-run and never in CI; `demo:check` fetches only from the tree, GitHub and the Playground CDN.
- [ ] The repository is publishable as it stands (rule 55): GPL-2.0-or-later everywhere, `LICENSE`, both `readme.txt`, versions agree (`0.2.0`), the owner's name only behind `site.author_name`. Screenshots are generated, never hand-edited (rule 56). The Playground path is a test in CI (rule 57).
- [ ] Private data never enters the repository (rule 47): the WXR export, dumps, uploads, backups and audit output stay gitignored; `docs/fixtures/demo/` is public; demo prose, alt text and captions are synthetic CC0.
- [ ] A block's data comes from its context (rule 50); related lists are same-form, same-section first (rule 51). Live content is a state (rule 48); migration commands are idempotent and dry-run first (rule 49).
- [ ] Fidelity is a test: every §6.9 row is a Playwright test in `tests/e2e/fidelity.spec.mjs`, asserting a count or position where presence is the point.
- [ ] Every tunable is a `Config` key read as `Config::get('key', <default equal to defaults()>)` (author keys via `Config::author_name()`/`author_url()`); script-side ⚠️ ASSUMPTIONs live only in `scripts/demo/lib/constants.mjs` (`IMAGE_MAX_BYTES` 350000, `IMAGE_BUDGET_BYTES` 8000000), `scripts/screenshots.mjs` (`SCREENSHOT_MAX_BYTES` 1500000), `scripts/check-budget.mjs` (`cssBudgetBytes` 63488).
- [ ] Every fallback in `docs/06-fallbacks.md` is a server-side branch with a test; seed placeholders stay neutral, never red (rule 45), and remain the fallback when a demo file is absent or `--no-demo-images` is passed.
- [ ] Tests are the acceptance criteria; unit tests never load WordPress; every binding source has normal and empty tests; CLI cores are tested directly, never through `WP_CLI`; pure script libs are Jest-tested without network; every user-facing string is translatable.
- [ ] Non-goals: beta deployment, k3s, Cloudflare, Jetpack, Batcache; live-content work beyond SI-13/16/17 (`env:live`/`test:live` frozen); visual changes; the owner's live posts in the demo; dark mode, comments, analytics, new blocks, a marketing site, WordPress.org submission; committing `plugins/ttm-core/build/` or `dist/`.

## Commands
- `composer lint` — php -l + PHPCS (WordPress-Extra, Docs, VIP-Go, PHPCompatibilityWP). If `php` on PATH is a failing wrapper shim, run `/usr/bin/php $(command -v composer) lint`.
- `composer test:unit` — PHPUnit + Brain\Monkey, `tests/unit` (no WordPress)
- `npm run lint` — ESLint, stylelint, theme.json, block.json, CSS budget, CSS coverage, fixme guard (`ALLOW_TAGGED = true` during the flight, `false` after P4-01), `check:license` (rule 55), `check:demo` (rules 53, 56, demo outputs)
- `npm run test:unit` — Jest (`scripts/test/*.test.js`: `scripts/lib`, `scripts/demo/lib`, `scripts/release/lib`, `scripts/live/lib`, plugin editor JS)
- `npm run build` — wp-scripts build → `plugins/ttm-core/build/` (gitignored)
- `bash scripts/forbidden-patterns.sh` — SPEC §3 greps incl. the rule 47 `git ls-files` check
- `npm run test:integration` — wp-env (WP 7.1.1 / PHP 8.3) + PHPUnit `tests/integration` (Docker)
- `npm run test:e2e` — starts wp-env, reseeds, runs Playwright + axe (`specs/`, `fidelity`, `editors`, `selectors`)
- `npm run demo:fetch-images [-- --only=<file> | --dry-run]` — human-run Openverse fetch → `docs/fixtures/demo/{images/,CREDITS.json}` (refuses under `CI`)
- `npm run demo:build [-- --out <dir> --release <tag> --check-determinism]` — seed (`--now` pinned) → export → `.github/{demo-content.xml,demo-options.json,blueprint.json}` (verification uses `--out dist/demo`)
- `npm run demo:check [-- --from <dir> | --url <site> | --keep]` — `release:pack`, local-variant blueprint in headless `@wp-playground/cli server`, §6.4 assertions
- `npm run release:pack` — build + `dist/ttm-core.zip` (with `build/`) and `dist/ttm-theme.zip`
- `npm run screenshots [-- --readme]` — owner set → `docs/feedback/phase-5/`; `--readme` → `.github/screenshots/` (8 PNGs)
- `npm run env:seed [-- --reset]` / `npm run env:cli -- ttm seed --state=quiet|empty --reset --starter-only --no-demo-images --now="<Y-m-d H:i:s>"`
- `npm run env:drill` (CI integration job), `env:backup`, `env:restore`, `env:live`, `test:live` — phase 4 scripts, unchanged
- Stale wp-env mounts after a branch switch: `npx wp-env stop && npx wp-env start`

## Module map
Plugin `plugins/ttm-core/src/` (PSR-4 `TTM\Core\`), `BoundariesTest::KNOWN_EXCEPTIONS` accepted; the arrow points down; `Cache`, `Verse`, `Newsletter` never import `Blocks`; `Cli` imports `Config`, `Support`, `Meta`, `Taxonomy`, `Query` only:
- `Config.php` (imports nothing; keys incl. `site.author_name`, `site.author_url` + accessors `author_name()`/`author_url()`, `excerpt_length`, `migration.*`) · `Support/` Clock (`now()` filterable via `ttm_now`, `at()`), Dates, Text, Html (`photon_origin_url( $src, $match_host, $home )`)
- `Taxonomy/` Series, SeriesAdmin · `Meta/` PostMeta, PrimaryCategory, WordCount, Form, SeriesPosition · `Query/` Lead, Cells, Archive, SeriesIndex, Stats, JournalExcerpt · `Fiction/` Serials, Books
- `Cache/` Headers, Purge, Cloudflare, Batcache (unchanged) · `Verse/` Fetcher, Cron, Admin (unchanged) · `Newsletter/` Handler, Settings, Providers, Form, Provider/{Provider,Jetpack,CustomUrl,Mailto,None} (unchanged) · `Bindings/` Sources (incl. `ttm/author-name` formats `by|name|byline-link|url`; only `ttm/meta-line` and `byline-link` may return HTML), Values (`footer_line( site, year, author )`, `author_line()`)
- `Blocks/` Registrar, Helpers · `Editor/` Sidebar, Checks, Columns, SeriesPartList · `Templates/` Hierarchy · `Nav/` CurrentSection · `Rest/` SeriesController, VerseController, LeadController · `Admin/` Page, General, BuildNotice · `Compat/` Theme · `Plugin.php`
- `Cli/` Loader, Command, *Command (Seed `--no-demo-images --now`, **Demo** `demo:options`/`demo:verify`, Verse, Recount, Primary, Series, Convert, Audit (`shortcode` flag incl. `codecolorer`), Migrate, Syndication, Stats), Seeder (`featured_image_for()`), **DemoImage** (local sideload of `demo-*.jpg` with credits)
- Blocks: `plugins/ttm-core/blocks/<name>/{block.json,render.php,index.js}` (19, fixed); `ttm/story-tiles` image tiles carry `is-cover`.

Theme `themes/ttm-theme/`: `theme.json`, `style.css`, `readme.txt`, `functions.php`, `inc/`, `templates/*.html`, `parts/`, `patterns/*.php` (mastheads bind `ttm/author-name`), `assets/{css/ttm.css,css/editor.css,js/,fonts/ + OFL.txt}`.

Scripts: `scripts/demo/{images.json,fetch-images.mjs,build.mjs,check.mjs,blueprint.template.json,lib/{constants,openverse,wxr,blueprint,check-assertions,local-variant}.mjs}`, `scripts/release/{pack.mjs,lib/{zip,files}.mjs}`, `scripts/check-demo.mjs` + `scripts/lib/demo-checks.mjs`, `scripts/check-license.mjs` + `scripts/lib/license-checks.mjs`, `scripts/screenshots.mjs`, `scripts/lib/shortcodes.mjs` (classic pre-pass incl. CodeColorer tags), `scripts/live/` (phase 4). Repo: `LICENSE`, `README.md` (public), `.github/{blueprint.json,demo-content.xml,demo-options.json,screenshots/,workflows/{ci,release}.yml}`, `docs/fixtures/demo/{images/,CREDITS.json,LICENSE.md}`, `docs/feedback/phase-5/`, `docs/spikes/P2-01.md`. Tests: `tests/unit`, `tests/integration` (`TTM_IntegrationTestCase::seed( $state, $demo_images = false )`), `tests/e2e` (`lib/presets.mjs` `authorName()`, `seedPost()`). Archived flights `docs/phase-1/` … `docs/phase-4/` are history.

## Constraints
Each line that can be a single-line pattern is also a `constraints` entry in `docs/foundry.json` (id in brackets); the others say how the reviewer checks.
- No `register_taxonomy|register_post_type|register_post_meta|register_term_meta|add_option|update_option|get_term_meta|get_post_meta|WP_Query|get_posts|get_terms|wp_remote_` under `themes/ttm-theme/` [theme-owns-no-data]; theme PHP references only `TTM\Core\Config` and `TTM\Core\Compat\Theme` [theme-core-refs], each guarded (forbidden-patterns.sh rule 3).
- No `style="` outside `grid-column`, `aspect-ratio`, `--ttm-*` and no `<style` in the plugin [plugin-inline-style, plugin-style-tag]; no `wp_enqueue_style` on `wp_enqueue_scripts` in the plugin (reviewer reads `Blocks/Registrar.php`).
- No `wp_create_nonce|wp_nonce_field|wp_nonce_url` in render.php, patterns, parts, templates [nonce-in-cacheable-output].
- No `is_user_logged_in|wp_get_current_user|get_current_user_id|$_COOKIE|$_SESSION` in render.php, Bindings, Query, Blocks, Templates, Nav or the theme; only `src/Cache/Headers.php` [per-visitor-markup].
- No `time()`, `date(`, `wp_date(`, `current_time(`, `current_datetime(`, `new DateTime*` outside `src/Support/Clock.php` [one-clock]; `seed --now` pins through the `ttm_now` filter and `Clock::at()`.
- No `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, `number => 0` in `src/` or render.php [bounded-queries].
- No `eval|unserialize|extract|create_function|assert|system|exec|shell_exec|passthru|proc_open|curl_|file_get_contents('http|fopen('http` in `plugins/`/`themes/` [dangerous-php]; variable-path includes only behind the `forbidden-patterns:allow-variable-include` marker.
- `wp_safe_remote_*` only in `Verse/Fetcher.php`, `Cache/Cloudflare.php`, `Newsletter/Provider/CustomUrl.php` [remote-calls-confined]; never `wp_remote_get|post|request|head` [unsafe-remote]; `media_sideload_image()`/`download_url()` only in `Cli/MigrateCommand.php` [sideload-confined]; `media_handle_sideload()` only in `Cli/DemoImage.php`, on a `wp_tempnam()` copy of a `^demo-[a-z0-9-]+\.jpg$` file from the fixtures dir [local-sideload-confined].
- No `fetch(`, `XMLHttpRequest`, `apiFetch`, `admin-ajax.php`, `/wp-json/` in theme JS or block JS [front-end-network].
- Bare numeric literals ≥ 2 outside `Config.php` limited to HTTP status, structure, indices and matching `Config::get()` fallbacks [hard-coded-tunable-arrow, hard-coded-tunable-assign].
- The owner's name appears in `plugins/`/`themes/` only in `Config.php`, header `Author:` lines and `readme.txt` [owner-name-confined]; `check-license.mjs` also checks LICENSE sha256, licence fields and versions (rule 55).
- `CREDITS.json` licences are `cc0` or `pdm` [demo-license-set]; a string `featured_image` in seed fixtures is `demo-<slug>.jpg` [demo-image-name]; credits ↔ files, sizes and budget are checked by `check-demo.mjs` (rule 53).
- `scripts/demo/build.mjs`, `scripts/demo/lib/`, `scripts/lib/`, `scripts/release/` make no network request [demo-build-offline]; no workflow runs the Openverse fetch [fetch-not-in-ci]; `sharp` is imported only by `scripts/demo/fetch-images.mjs` and `scripts/screenshots.mjs` [sharp-confined].
- Committed `.github/{blueprint.json,demo-content.xml,demo-options.json}` contain no `localhost`/`127.0.0.1`/`:8888` [demo-no-localhost] and no e-mail address [demo-no-email]; their structure (counts, release tag, step order) is checked by `check-demo.mjs`.
- `README.md` references exactly the eight `.github/screenshots/*.png` and the directory holds nothing else (rule 56, `check-demo.mjs`, cross-file).
- `git ls-files` lists no `*.xml`, `*.sql`, `*.sql.gz`, `*.tar.gz`, `*.csv` under `docs/`, nothing under `docs/fixtures/live/`, nothing under `plugins/ttm-core/build/` or `dist/` (forbidden-patterns.sh rule 47 + reviewer runs `git ls-files`; cross-file).
- Derived data (series index, stats, top tags, story count, lead id, verse) is recomputed only on write hooks or CLI; front-end requests never write (reviewer checks by reading).
- Every non-`GET` REST route and settings save checks a capability and a nonce; public `ttm/v1` routes are `GET` with `__return_true` (reviewer checks by reading).
- Every `ttm/*` block: `block.json` supports `{html,align,color,typography,spacing}: false`, `render: file:./render.php`, `previewState`, wrapper via `Helpers::wrapper()`, empty → `''`, a rule-50 no-context test (`check-block-json.mjs`; reviewer reads the test).
- `Serials::active()` only in `Fiction/Serials.php`, `serial-hero`, `writing-cell`, `story-tiles`, `book-grid`, `series-toc` chapters variant [serials-active-confined; variant split by reading].
- `tests/e2e/fidelity.spec.mjs`/`editors.spec.mjs`: every `test.fixme(` carries a `// P<n>-<nn>` tag [untagged-fixme]; zero remain after P4-01.
- `scripts/css-coverage-allow.txt` empty [pending-allow-list]; `tests/e2e/selectors-allow.txt` has no pending line [selectors-no-pending].
- `ttm.css` ≤ 63488 bytes, plain CSS, presets via `var(--wp--preset--…)`, no hex [css-hex-literal]; no dark mode [no-dark-mode]; text under 18.66px never `accent`/`neutral-500`/`neutral-600`.
- No `core/group` with `is-style-grid-*` uses `constrained`/`flow`/`flex` [grid-group-not-constrained].
- Seeder never uses the accent red [seed-no-red]; no lorem in seed fixtures [seed-no-lorem]; footer nav eight items, no RSS [footer-no-rss]; verse-copyright stays removed [verse-copyright-removed].
- Landmarks per 04 §8; axe serious/critical = 0 on every seeded screen at 1280 and 390; every `img[src*="demo-"]` has non-empty alt (`fidelity.spec.mjs`).
- `package.json` `dependencies` stays `{}`; `composer.lock`/`package-lock.json` committed; `npm audit --audit-level=high` and `composer audit` pass.
- Never stage, move, delete or gitignore-edit `FOUNDRY_FEEDBACK.md`; never re-enable commit signing; never edit `docs/phase-1/` … `docs/phase-4/`; never copy, quote or commit the WXR export.

## Commit template
```
<ID>: <title>

Goal: <one sentence>
Tests: <test files/names added or changed; fidelity rows un-fixme'd>
Interpretation: <any SPEC reading you had to choose, or "none">
Measurement: <before/after for tuning tasks; omit otherwise>
Manual check: <what a human must open, or "none">
```

Precedence: `docs/SPEC.md` wins over `docs/PLAN.md`, which wins over code comments. Design docs (`docs/01`, `02`, `06`, the mock cards) win on visuals; SPEC wins on engineering; phase 4's shipped behaviour is the baseline. `docs/phase-1/` … `docs/phase-4/` are history, not instructions.
