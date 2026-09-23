# These Things Matter — repository guide (phase 4: real content)

Block theme `themes/ttm-theme` (presentation only) + companion plugin `plugins/ttm-core` (all data, blocks, bindings, cron, CLI, migration) for eric.mann.blog. The site is served from cache (Cloudflare + Batcache): every page must be a correct static document. This flight fixes the four owner-reported defects (`docs/SPEC.md §1.1`: section filter row, footer, "In this series" on non-series posts, "Other series" ignoring form/section), imports the live WXR export into wp-env through the plugin's own migration plan (§6.6–§6.8), proves every template on the real posts (§6.10), and adds a backup/restore drill (§6.9). `§6.11` is the fidelity assertion table; `docs/PLAN.md` holds every decision.

## Principles
- [ ] Theme never owns data; plugin never owns presentation (SPEC §3.1). Every `ttm-*` class the markup emits has a rule in `ttm.css`, every `ttm-*`/`is-style-` selector matches a seeded screen, and the coverage allow-list is empty (rules 34, 37, 41).
- [ ] Every page renders correctly as a cached static document: no per-visitor markup, no nonces on cacheable output, no front-end network requests (§3.2). Markup never varies per viewport; CSS does.
- [ ] Private data never enters the repository (rule 47): the export, dumps, uploads, backups and audit output live under gitignored `docs/*.xml` / `docs/fixtures/live/`; test fixtures imitating live content are synthetic shapes, never a live post's text.
- [ ] Live content is a state, not a fixture (rule 48): `npm run env:live` and `npm run env:seed -- --reset` are each idempotent from any state; `test:e2e` always starts from the seed; `test:live` skips cleanly (exit 0) without `docs/fixtures/live/screens.json`.
- [ ] Migration commands are idempotent, dry-run first and non-destructive (rule 49): `--dry-run` everywhere, counts reported, `post_content` rewritten only by `convert:import` and `migrate:images`, each writing `ttm_classic_backup` once; nothing deletes posts, terms, attachments or comments (the guarded `seed --reset` wipe is the one local exception).
- [ ] A block's data comes from its context, never from a site-wide default (rule 50); the sanctioned defaults are the Writing-page blocks reading `Serials::active()`. Related lists are same-form, same-section first (rule 51, F27). Every live finding is fixed as a class with a synthetic test and logged in `LIVE-TRIAGE.md` (rule 52).
- [ ] Fidelity is a test: every §6.11 row is a Playwright test in `tests/e2e/fidelity.spec.mjs`, asserting a position or count where the defect was placement or presence (SI-27). Rows below WCAG AA are asserted as `accent-700`/`neutral-700`.
- [ ] Every tunable is a `Config` key read as `Config::get('key', <default equal to defaults()>)`; ⚠️ ASSUMPTION values (`excerpt_length` 55, `migration.image_timeout` 20, `seed.image_band_angle` 30, `cssBudgetBytes` 63488) live only in `Config.php` / `scripts/check-budget.mjs`.
- [ ] Every fallback row in `docs/06-fallbacks.md` (now incl. F27, F28) is a server-side branch with a test; blocks never render empty wrappers or placeholder copy; seed images are neutral placeholders, never red (rule 45).
- [ ] Tests are the acceptance criteria (rule 27); unit tests never load WordPress; every binding source has normal and empty tests (rule 26); CLI cores are tested directly, never through `WP_CLI`; every user-facing string is translatable.
- [ ] Non-goals this flight: standing up `beta.mann.blog`, k3s, Cloudflare Access, S3 backups, Batcache in wp-env, connecting Jetpack; visual changes beyond §6.1–§6.5; front-page changes beyond the shared footer; new screens; dark mode; comments; analytics; hand-cleaning content; importing Jetpack `feedback`/`custom_css`/foreign templates as anything but inert rows; content model, REST, cache headers, verse fetching (except the `Stats` invalidation).

## Commands
- `composer lint` — php -l + PHPCS (WordPress-Extra, Docs, VIP-Go, PHPCompatibilityWP; security sniffs are errors)
- `composer test:unit` — PHPUnit + Brain\Monkey, `tests/unit` (no WordPress)
- `npm run lint` — ESLint, stylelint, theme.json check, block.json contract, CSS budget, CSS coverage (`scripts/css-coverage-allow.txt` stays empty), fixme guard (`ALLOW_TAGGED = true` during the flight: a `test.fixme(` line needs a `// P<n>-<nn>` tag; P5-03 flips it back to `false`)
- `npm run test:unit` — Jest via wp-scripts (`scripts/test`, plugin editor JS, `scripts/lib/*.mjs`, `scripts/live/lib/*.mjs`)
- `npm run build` — wp-scripts build → `plugins/ttm-core/build/`
- `bash scripts/forbidden-patterns.sh` — SPEC §3 greps incl. the rule 47 `git ls-files` check
- `npm run test:integration` — wp-env (WP 7.1.1 / PHP 8.3) + PHPUnit `tests/integration` (Docker)
- `npm run test:e2e` — starts wp-env, reseeds, runs Playwright + axe: `specs/*.spec.mjs`, the `fidelity` project (`fidelity.spec.mjs`, `editors.spec.mjs`, `selectors.spec.mjs`)
- `npm run env:live` — `scripts/live/import.sh` + `plan.sh`: import the newest `docs/*.xml` (`LIVE_WXR`, `LIVE_SKIP_ATTACHMENTS=1`, `LIVE_HOST`) and run the migration plan; prints `env:live skipped: no export` and exits 0 without an export
- `npm run test:live` — Playwright project `live` over `docs/fixtures/live/screens.json`; skips with exit 0 when absent
- `npm run env:backup` / `npm run env:restore -- <dir> [--host=<url>]` / `npm run env:drill` — `scripts/live/{backup,restore,drill}.sh`; the drill runs in the CI `integration` job
- `npm run screenshots` — writes `docs/feedback/phase-4/*.png` from the running site (live PNGs only when `screens.json` exists)
- `npm run env:seed` / `npm run env:cli -- ttm seed --state=quiet|empty --reset --starter-only` — demo content (`docs/fixtures/seed/*.json`)
- Stale wp-env mounts after a branch switch: `npx wp-env stop && npx wp-env start`

## Module map
Plugin `plugins/ttm-core/src/` (PSR-4 `TTM\Core\`), SPEC §4 table with `BoundariesTest::KNOWN_EXCEPTIONS` accepted; the arrow points down; `Cache`, `Verse`, `Newsletter` never import `Blocks`; `Cli` imports `Meta`, `Taxonomy`, `Query` only:
- `Config.php` (imports nothing; keys incl. `excerpt_length`, `migration.image_hosts`, `migration.image_timeout`, `migration.photon_origin`) · `Support/` Clock, Dates, Text (`truncate_sentences`), Html (`image_srcs`, `photon_origin_url`, `replace_url`)
- `Taxonomy/` Series, SeriesAdmin · `Meta/` PostMeta (incl. `ttm_images_rewritten`), PrimaryCategory (orders category terms primary-first on `get_the_terms`), WordCount, Form (`is_editor_save()`: a derived `story` is written only on editor saves), SeriesPosition
- `Query/` Lead, Cells, Archive, SeriesIndex, Stats (top tags invalidated on `set_object_terms`, tiebreak count desc/slug asc, `flush_all()`, `story_count()`), JournalExcerpt · `Fiction/` Serials, Books
- `Cache/` Headers, Purge, Cloudflare, Batcache · `Verse/` Fetcher, Cron, Admin · `Newsletter/` Handler, Settings, Providers, Form, Provider/{Provider,Jetpack,CustomUrl,Mailto,None}; the only newsletter `wp_safe_remote_post` is in `Newsletter/Provider/CustomUrl.php`
- `Bindings/` Sources (all binding sources; `ttm/verse-copyright` removed this flight; empty `ttm/*`-bound paragraphs/headings render nothing), Values (pure formatters)
- `Blocks/` Registrar, Helpers (wrapper, year grouping, whole-row links, tag chips, featured caption, search classes)
- `Editor/` Sidebar, Checks, Columns, SeriesPartList · `Templates/` Hierarchy (single-journal; page-writing unless F28, in which case `/writing/` and `/category/writing/` become the Writing category archive) · `Nav/` CurrentSection · `Rest/` SeriesController, VerseController, LeadController · `Cli/` Loader, Command, *Command (Seed incl. `--starter-only`, Verse, Recount, Primary `--from-yoast`, Series `--from-tags/--form/--status/--total/--name`, Convert, Audit `--summary`, Migrate `politics|redirects|close-comments|excerpts|images`, Syndication, Stats `stats:flush`), Seeder (rule 45 images; `run_starter()`; `reset()` wipes foreign content when `may_wipe()`) · `Compat/` Theme · `Admin/` Page, General, BuildNotice · `Plugin.php` composition root
- Blocks: `plugins/ttm-core/blocks/<name>/{block.json,render.php,index.js}` (19, fixed); `ttm/series-list` layouts `list|rail|grid-2|grid-3|strip`, attributes `relatedTo` (`""|"current"`) and `heading`; `ttm/series-toc` series variant has no active-serial fallback.

Theme `themes/ttm-theme/`: `theme.json`, `style.css`, `functions.php`, `inc/{block-styles,patterns,image-sizes,bindings-compat,starter-content}.php`, `templates/*.html`, `parts/{header-front,header-inner,rail,footer}.html`, `patterns/*.php`, `assets/{css/ttm.css,css/editor.css,js/nav.js,js/variations.js,fonts/}`. `ttm.css` is organised by `docs/01-design-language.md §4` component numbers.

Scripts: `scripts/live/{import,plan,backup,restore,drill,test-live}.sh`, `scripts/live/{screens,series-args,hash-body}.mjs`, `scripts/live/lib/screens.mjs` (pure), `scripts/convert-classic.mjs` + `scripts/lib/{shortcodes,footnotes,report}.mjs` (pure pre-pass, Jest-tested). Docs: `docs/migration/series.json` (committed owner series map), `docs/feedback/phase-4/{README.md,LIVE-TRIAGE.md,*.png}`. Tests: `tests/unit`, `tests/integration` (`TTM_IntegrationTestCase` with `set_now()`, `seed()`, `render_template()`), `tests/e2e` (`specs/`, `fidelity.spec.mjs`, `editors.spec.mjs`, `selectors.spec.mjs`, `live.spec.mjs`, `lib/{urls,presets,style,live}.mjs`). Archived flights: `docs/phase-1/`, `docs/phase-2/`, `docs/phase-3/` (history, not instructions).

## Constraints
Each line that can be a single-line pattern is also a `constraints` entry in `docs/foundry.json` (id in brackets); the others say "reviewer checks by reading".
- No `register_taxonomy|register_post_type|register_post_meta|register_term_meta|add_option|update_option|get_term_meta|get_post_meta|WP_Query|get_posts|get_terms|wp_remote_` under `themes/ttm-theme/` [theme-owns-no-data]; theme PHP references only `TTM\Core\Config` and `TTM\Core\Compat\Theme` [theme-core-refs], every reference guarded by `function_exists`/`class_exists`/`defined` (forbidden-patterns.sh rule 3 check).
- No `style="` outside `grid-column`, `aspect-ratio`, `--ttm-*` and no `<style` under `plugins/ttm-core/` [plugin-inline-style, plugin-style-tag]; no `wp_enqueue_style` on `wp_enqueue_scripts` in the plugin (reviewer checks by reading `Blocks/Registrar.php`).
- No `wp_create_nonce|wp_nonce_field|wp_nonce_url` in any `render.php`, pattern, part or template [nonce-in-cacheable-output].
- No `is_user_logged_in|wp_get_current_user|get_current_user_id|$_COOKIE|$_SESSION` in `render.php`, bindings, Query, Blocks, Templates, Nav or the theme; only `src/Cache/Headers.php` may call `is_user_logged_in()` [per-visitor-markup].
- No `time()`, `date(`, `wp_date(`, `current_time(`, `current_datetime(`, `new DateTime*` outside `src/Support/Clock.php` [one-clock]; shell scripts may use `date -u` for backup stamps.
- No `posts_per_page => -1`, `nopaging => true`, `numberposts => -1`, `number => 0` under `plugins/ttm-core/src/` or `render.php` [bounded-queries].
- No `eval|unserialize|extract|create_function|assert|system|exec|shell_exec|passthru|proc_open|curl_|file_get_contents('http|fopen('http` under `plugins/` or `themes/` [dangerous-php]; variable-path `include`/`require` only behind the `forbidden-patterns:allow-variable-include` marker (forbidden-patterns.sh).
- `wp_safe_remote_*` only in `Verse/Fetcher.php`, `Cache/Cloudflare.php`, `Newsletter/Provider/CustomUrl.php` [remote-calls-confined]; never `wp_remote_get|post|request` [unsafe-remote]; `media_sideload_image()`/`download_url()` only in `Cli/MigrateCommand.php`, hosts from `--hosts` or `Config`, never request input [sideload-confined].
- No `fetch(`, `XMLHttpRequest`, `apiFetch`, `admin-ajax.php`, `/wp-json/` under `themes/ttm-theme/assets/js/` or any block JS [front-end-network]; the only front-end script is `assets/js/nav.js` (+ core Navigation).
- Bare numeric literals ≥ 2 outside `Config.php` are limited to HTTP status codes, structural arithmetic, array indices and `Config::get()` fallbacks equal to `defaults()` [hard-coded-tunable-arrow, hard-coded-tunable-assign]; CSS/theme.json values, seed fixtures and e2e expectations are not tunables.
- Secrets (`TTM_NEWSLETTER_API_KEY`, `TTM_CLOUDFLARE_API_TOKEN`, `TTM_CLOUDFLARE_ZONE_ID`) are constants: never in options, never `show_in_rest`, never printed (forbidden-patterns.sh rule 17; reviewer checks by reading).
- Derived data (series index, stats, top tags, story count, lead id, verse) is read from options/transients, recomputed only on write hooks (`transition_post_status`, `set_object_terms`, `ttm_form` meta changes) or `wp ttm stats:flush`/`series:rebuild`; front-end requests never write (reviewer checks by reading).
- Every non-`GET` REST route and every `admin_post_*`/settings save checks a capability and a nonce; public `ttm/v1` routes are `GET` with `permission_callback => '__return_true'` (reviewer checks by reading).
- Every `ttm/*` block: `block.json` with `supports {html,align,color,typography,spacing}: false`, `render: file:./render.php`, `index.js`, `previewState`, wrapper `div.ttm-<name>[data-ttm-block]` via `Helpers::wrapper()`, empty → `''`, a `test_rule_50_no_context_with_other_content` case (`scripts/check-block-json.mjs`; reviewer checks the test by reading).
- `Serials::active()` is read only by `Fiction/Serials.php`, `serial-hero`, `writing-cell`, `story-tiles`, `book-grid` and the **chapters** variant of `series-toc` [serials-active-confined; the variant split is checked by reading `series-toc/render.php`].
- Related series lists rank same-form candidates by shared sections then last update and return `''` with no candidate (F27); `Form::on_save()` writes a derived `story` only when `is_editor_save()` (rule 51, §6.5; reviewer checks by reading and by `SeriesListTest`/`SaveHooksTest`).
- `git ls-files` lists no `*.xml`, `*.sql`, `*.sql.gz`, `*.tar.gz`, `*.csv` under `docs/` and nothing under `docs/fixtures/live/` (forbidden-patterns.sh rule 47; cross-file, reviewer runs the script). No test fixture quotes a live post's body text (reviewer checks `docs/fixtures/*.html` and `tests/` by reading).
- `tests/e2e/fidelity.spec.mjs`/`editors.spec.mjs`: every `test.fixme(` carries a `// P<n>-<nn>` tag [untagged-fixme]; zero remain after P5-03 (`scripts/check-fixme.mjs`).
- `scripts/css-coverage-allow.txt` is empty [pending-allow-list]; `tests/e2e/selectors-allow.txt` has no `pending` line [selectors-no-pending]; every `ttm-*` class in markup has a selector and vice versa (`scripts/check-css-coverage.mjs`, `selectors.spec.mjs`).
- `ttm.css` ≤ 63488 bytes (`scripts/check-budget.mjs`), plain CSS, presets via `var(--wp--preset--…)`, no hex literals [css-hex-literal]; every `--wp--preset--font-size--*`/`--color--*` reference is generated by a slug (`scripts/check-theme-json.mjs`).
- `.wp-site-blocks { max-width: 1280px; margin-inline: auto; padding-inline: var(--wp--custom--gutter--desktop) }`; full-bleed only by negative gutter margins (rule 42); inner nav overlay phone-only, breakpoint 720 (rule 43); `.is-style-rule-1/2` full width (rule 35) (reviewer checks by reading `ttm.css`).
- No `core/group` with `is-style-grid-*` uses `constrained`/`flow`/`flex` [grid-group-not-constrained] and no `layout: constrained` group sits inside one (rule 36 extended; nesting checked by reading; `grep -n 'is-style-grid' themes/ttm-theme/**`).
- No `prefers-color-scheme`, dark palette or theme toggle [no-dark-mode]; every colour is a `theme.json` preset; text under 18.66px is never `accent`, `neutral-500` or `neutral-600` (use `accent-700`/`neutral-700`); the poster ghost button is the one 01 §2.1 exception.
- Seeder never uses the accent red [seed-no-red]; `grep -ri lorem docs/fixtures/seed/` is empty [seed-no-lorem].
- Footer nav has exactly eight items, no RSS [footer-no-rss]; `ttm/verse-copyright`, `verse.copyright_placement`, `.ttm-footer__copyright`, `.ttm-verse__copyright` are gone [verse-copyright-removed] (both fail until P1-01 lands).
- Part numbers: the series TOC pads to two digits ("01"); hub part list, chapters list, Most read and the 404 Latest list are unpadded (SI-24; reviewer checks by reading).
- Landmarks per 04 §8; every whole-row link is one `<a>` with the headline first; axe serious/critical = 0 on every seeded and live screen at 1280 and 390 (`fidelity.spec.mjs`, `live.spec.mjs`).
- `package.json` `dependencies` stays `{}`; `composer.lock`/`package-lock.json` committed; `npm audit --audit-level=high` and `composer audit` pass.
- Never stage, move, delete or gitignore-edit `FOUNDRY_FEEDBACK.md`; never re-enable commit signing; never edit `docs/phase-1/`, `docs/phase-2/`, `docs/phase-3/`; never copy, quote or commit the WXR export.

## Commit template
```
<ID>: <title>

Goal: <one sentence>
Tests: <test files/names added or changed; fidelity rows un-fixme'd>
Interpretation: <any SPEC reading you had to choose, or "none">
Measurement: <before/after for tuning tasks; omit otherwise>
Manual check: <what a human must open, or "none">
```

Precedence: `docs/SPEC.md` wins over `docs/PLAN.md`, which wins over code comments. Design docs (`docs/01`, `02`, `06`, the mock cards) win on visuals; owner feedback quoted in SPEC §1.1 wins over the design docs; SPEC wins on engineering. `docs/phase-1/`, `docs/phase-2/`, `docs/phase-3/` are history, not instructions.
