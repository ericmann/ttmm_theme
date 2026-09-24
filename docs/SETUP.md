# Setup and local development

This repository is a monorepo holding the block theme (`themes/ttm-theme`) and its companion plugin (`plugins/ttm-core`). Local development runs in [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) (wp-env), which boots WordPress in Docker with both mapped in.

## Prerequisites

| Tool | Version | Notes |
|---|---|---|
| Node.js | ≥ 22 (24 in CI) | `nvm use 24` |
| npm | ≥ 10 | ships with Node |
| PHP | ≥ 8.1 (8.3 in CI and wp-env) | needed on the host for `composer lint` / `composer test:unit`; not needed for wp-env itself |
| Composer | 2.x | |
| Docker | any current Docker Engine or Docker Desktop | wp-env needs the daemon running |
| gh CLI | optional | Foundry uses it to open the draft PR |

## First run

```bash
git clone git@github.com:ericmann/ttmm_theme.git && cd ttmm_theme
git checkout poc
npm ci
composer install
npm run build              # builds plugins/ttm-core/build (blocks + editor bundle)
npx wp-env start           # first run pulls images; a few minutes
npm run env:seed           # wp ttm seed — demo content
```

Then:

| URL | What |
|---|---|
| http://localhost:8888 | the site |
| http://localhost:8888/wp-admin | `admin` / `password` |
| http://localhost:8889 | the tests instance (used by integration tests; do not edit content here) |

`.wp-env.json` pins WordPress `7.1.1` (the live site's version) and PHP `8.3`, sets pretty permalinks, the site title and tagline, the `America/Los_Angeles` timezone, and closes comments by default. Adjust locally with a gitignored `.wp-env.override.json`.

## Everyday commands

```bash
npm run start              # wp-scripts watch mode for the plugin's JS
npm run env:cli -- post list --post_type=post    # any WP-CLI command in the container
npm run env:cli -- ttm seed --reset              # reseed
npm run env:logs           # PHP + Apache logs from the containers
npm run env:stop           # stop containers (data persists)
npm run env:destroy        # wipe containers and database
```

`WP_DEBUG_LOG` is on, so PHP notices land in `wp-content/debug.log` inside the container: `npx wp-env run cli tail -f /var/www/html/wp-content/debug.log`.

`npm run build` must run before opening any `ttm/*` block in an editor (post editor, Site
Editor, or Customizer) in wp-env — `npm run env:seed` runs it for you (SPEC §6.7). Without a
build, `Blocks\Registrar` swaps each block's `editorScript` for a committed fallback
(`plugins/ttm-core/assets/editor-fallback.js`, plain, no build step) instead of dropping it, so
the block still shows up (server-side rendered) rather than "doesn't include support for the
… block" — and an admin notice on every `wp-admin` screen tells you to build.

## Tests and checks

These are the commands the Foundry pipeline runs after every task (`docs/foundry.json`) and CI runs on every push. All of them exit non-zero on failure.

| Command | What it runs | Needs Docker |
|---|---|---|
| `composer lint` | `php -l` on every PHP file, then PHPCS with `WordPress-Extra`, `WordPress-Docs`, `WordPress-VIP-Go`, `PHPCompatibilityWP` (security sniffs are errors) | no |
| `composer test:unit` | PHPUnit + Brain\Monkey, `tests/unit`; no WordPress loaded | no |
| `npm run lint` | ESLint (`@wordpress/scripts`), stylelint, `theme.json` check, `block.json` contract check, CSS budget, CSS coverage (`scripts/css-coverage-allow.txt` must stay empty), fixme guard (no `test.fixme(` at all) | no |
| `npm run test:unit` | Jest via `wp-scripts` | no |
| `npm run build` | `wp-scripts build` for the plugin | no |
| `npm run test:integration` | starts wp-env if needed, then PHPUnit with the WordPress test suite inside the `tests-cli` container (`tests/integration`) | yes |
| `npm run test:e2e` | starts wp-env, reseeds it (`wp ttm seed --reset`), then Playwright + axe against the thirteen seeded screens at 1280×900 and 390×844, plus the `fidelity`/`editors`/`phone`/`selectors` projects (`tests/e2e`) | yes |
| `npm run screenshots` | against a running, seeded wp-env, writes the fourteen `docs/feedback/phase-3/*.png` zone crops plus, when a live import is present, the phase-4 `live-*.png` set (`scripts/screenshots.mjs`); does not reseed | yes |
| `bash scripts/forbidden-patterns.sh` | greps for the mechanical rules in `SPEC.md §3` | no |
| `npm run env:live` | imports a WXR export (`LIVE_WXR=<path>` or the newest `docs/*.xml`) and runs the whole migration plan against it (`MIGRATION.md §1.0`); skips cleanly (exit 0) with no export present | yes |
| `npm run test:live` | Playwright's `live` project against every URL `env:live` discovered (`docs/fixtures/live/screens.json`, SPEC §6.10); skips cleanly with no live import | yes |
| `npm run env:backup [-- <dir>]` | `scripts/live/backup.sh`: db export + uploads tar + `manifest.json` into `docs/fixtures/live/backups/<UTC stamp>/` | yes |
| `npm run env:restore -- <dir> [--host=<url>]` | `scripts/live/restore.sh`: the inverse of `env:backup` | yes |
| `npm run env:drill` | `scripts/live/drill.sh`: seeds, backs up, wipes, restores, and compares post counts + five page hashes — proves the backup/restore round-trip; runs in CI's `integration` job after `test:integration` | yes |

Fix formatting automatically with `composer lint:fix` (phpcbf) and `npx wp-scripts format`.

### How the e2e suite works

`npm run test:e2e` runs against the wp-env **dev** site (`http://localhost:8888`), not the tests instance on `8889` - it reseeds the dev site itself (`wp-env run cli wp ttm seed --reset`) first, and `tests/e2e/playwright.config.mjs` sets `WP_BASE_URL` explicitly for the same reason: `wp-scripts test-playwright` otherwise defaults it to the *tests* environment's port when `@wordpress/env` is installed, which would run the suite against an empty, unseeded site. `tests/e2e/lib/urls.mjs` hard-codes the thirteen screens' paths from the seed fixtures (`docs/fixtures/seed/{posts,series,pages}.json`); `screens.spec.mjs` checks one `<main>` landmark, zero `serious`/`critical` axe violations, a single `img[fetchpriority="high"]` hero image on the front page and one article, and (P5-01) that every screen is a centred 1280px column at a 1920px viewport; `network.spec.mjs` asserts every request is same-origin, `data:`/`blob:`, or (only when Jetpack happens to be active) one of its own stats/subscribe hosts, and never hits `/wp-json/` or `admin-ajax.php` — Jetpack itself is not installed in wp-env, so this allowance is currently unexercised; `focus.spec.mjs` tabs through the front page and checks the skip link, first nav link, first `.ttm-item`, and first `.btn` all keep a visible focus outline. The HTML report lands at `playwright-report/` (`--open=never`); open it with `npx playwright show-report`.

The `fidelity` Playwright project (also driven by `npm run test:e2e`, `--config tests/e2e/playwright.config.mjs`) runs `tests/e2e/fidelity.spec.mjs` and `tests/e2e/editors.spec.mjs` — one `test()` per `docs/SPEC.md §6.2`/`§6.9` table row, transcribed verbatim, each checking a single computed-style/text/count assertion against a seeded screen at the mock's viewport (`tests/e2e/lib/presets.mjs` reads colours/sizes from `theme.json` rather than hard-coding hex; `tests/e2e/lib/style.mjs` wraps `getComputedStyle()`), plus the per-screen `a11y`/`network` rows and the two editor-registration checks. `scripts/check-fixme.mjs`, wired into `npm run lint`, fails the build on any `test.fixme(` in either file at all (`ALLOW_TAGGED = false`, set once every phase had landed in P5-02) — during earlier phases of this repeat's build, a row not yet built could be `test.fixme(` tagged with the task that would un-fixme it (`// P<n>-<nn>`), but no such tag is ever accepted again. `desktop`/`phone` (the phase 1 projects) ignore these files; `fidelity` ignores everything else. The `phone` project additionally runs `tests/e2e/specs/phone.spec.mjs`, 390px-only layout facts (no horizontal overflow on every seeded screen, the section nav and archive filter row actually scroll, the Writing cell/poster/Writing-page sections stack and reorder) that don't fit the fidelity table's one-row-per-property shape; `desktop` ignores that file.

`tests/e2e/selectors.spec.mjs` (also in the `fidelity` project, SPEC §3.2 rule 41, P0-03) is the runtime counterpart to `npm run lint`'s static `check:css-coverage`: it parses `ttm.css`/`style.css` with the pure `scripts/lib/css-selectors.mjs`, then visits every seeded screen (`tests/e2e/lib/urls.mjs`'s `SCREEN_URLS` plus the tag-filtered security archive) and asserts every `.ttm-`/`.is-style-` selector matches at least one element somewhere across the set. A selector that's exempt (state pseudo-classes, `@media`-only rules) or listed in `tests/e2e/selectors-allow.txt` is skipped; everything else with zero matches fails the test, naming the selector. Allow-list lines carry a reason — `# editor block style (04 §3)` for editor-only block styles no seeded template uses, or `# state: ...` for a real selector whose triggering state the current seed never reaches — both may remain indefinitely (rule 41 permits reasoned entries); a `# P<n>-<nn> pending` line (CSS a later phase task would make reachable) was allowed only while the build was in progress and none remain.

### How the integration harness works

`.wp-env.json` maps `./tests` to `wp-content/ttm-tests` and `./vendor` to `wp-content/ttm-vendor` in both containers. `npm run test:integration` runs `phpunit` from inside the tests container with `tests/integration/bootstrap.php`, which loads the WordPress test suite wp-env ships at `/wordpress-phpunit`, loads `ttm-core` as an mu-plugin and switches to `ttm-theme`. Tests extend `WP_UnitTestCase`; every test runs in a transaction that is rolled back.

Run a single file: `npx wp-env run tests-cli --env-cwd=wp-content/ttm-tests php ../ttm-vendor/bin/phpunit -c integration/phpunit.xml.dist --filter BootTest`.

`.wp-env.json` also maps `./docs/fixtures` to `wp-content/ttm-fixtures`, so integration tests can read fixed sample payloads (a Verse API response, a classic-editor HTML sample) with plain `file_exists()`/`file_get_contents()` calls against `WP_CONTENT_DIR . '/ttm-fixtures/...'` instead of embedding them inline. If a mapping does not appear after editing `.wp-env.json`, run `npx wp-env destroy && npx wp-env start` to force a rebuild.

## Seed states

`npm run env:seed` runs `wp ttm seed` (state `normal` by default), which fully populates the eight categories, four pages, ~100 posts, the six seed series (three nonfiction, three fiction, one with a cover), three books and the current verse from `docs/fixtures/verse-sample.json`. `npx wp-env run cli wp ttm seed --state=quiet` shifts every post 120 days into the past (no cell has anything within 90 days, journal nothing within 30) without changing any series status. `npx wp-env run cli wp ttm seed --state=empty` seeds everything except Security/Opinion posts, series (and their chapters), stories and books, and deletes the verse options — useful for exercising every documented fallback (`06-fallbacks.md`). Add `--reset` to any of these to delete every previously seeded object (identified by `_ttm_seed` post/term meta) first; seeding itself is idempotent by slug, so re-running `wp ttm seed` without `--reset` never duplicates content. Seeding refuses to run when `wp_get_environment_type()` returns `production`.

## Repository layout

See `docs/SPEC.md §4.1`. In short: `plugins/ttm-core` (data, blocks, CLI), `themes/ttm-theme` (presentation), `tests/{unit,integration,e2e}`, `scripts/` (checks), `docs/` (design handoff + this documentation), `.github/workflows/ci.yml`.

## Configuration

Runtime tunables live in one place, `plugins/ttm-core/src/Config.php`, and can be overridden with the `ttm_config` filter from an mu-plugin. Secrets are PHP constants, never options:

| Constant | Purpose |
|---|---|
| `TTM_CLOUDFLARE_ZONE_ID`, `TTM_CLOUDFLARE_API_TOKEN` | Enables the Cloudflare purge adapter (see `DEPLOYMENT.md`). Empty in wp-env. |
| `TTM_NEWSLETTER_API_KEY` | Only for the `custom-url` newsletter provider. |
| `TTM_REMOVE_DATA` | `true` makes `uninstall.php` delete options. Terms and meta are never deleted. |
| `WP_ENVIRONMENT_TYPE` | `wp ttm seed` refuses to run when this is `production`. |

Site-editable settings (Settings → These Things Matter): newsletter provider, "include Journal in main feed", lead-story day windows, the Books list, and the Verse status page with a "Fetch now" button.

## Git workflow for the proof of concept

- Earlier flights landed on `poc`, with Foundry creating `poc/<date>` branches from `poc` and opening draft PRs into `poc`. This flight (phase 3, "inner-template fidelity") instead runs on `refine/<date>` from `main` (see `docs/PLAN.md`'s Decisions).
- Commits are unsigned on `refine/2026-09-22` by design (`commit.gpgsign=false`); the owner rebases and signs before merging to `main`.
- CI runs on pushes to `main`, `poc`, `poc/**`, `build/**`, `refine/**` and on PRs into `main`, `poc` or `refine/**`.

## Running the Foundry flight

```bash
claude --plugin-dir /media/ericmann/Data/Projects/agentic-foundry --permission-mode acceptEdits
> /foundry:go-flight
```

The planner reads `docs/SPEC.md`, writes `docs/PLAN.md`, `docs/PROGRESS.md`, `docs/foundry.json` and `CLAUDE.md`, then the implementer works the task list. The first `npm run test:integration` in a fresh checkout pulls Docker images, so `commandTimeoutMs` in `foundry.json` should be at least `900000`. Resume an interrupted flight by running the same command again.

## Troubleshooting

| Symptom | Fix |
|---|---|
| `wp-env start` hangs on "Pulling images" | Check `docker ps` works without sudo; on Linux add your user to the `docker` group. |
| Port 8888 in use | Set `"port": 8890` in `.wp-env.override.json`. |
| `WordPress test suite not found at /wordpress-phpunit` | You ran phpunit on the host. Use `npm run test:integration`. |
| PHPCS says a prefix is too short | The `ttm` prefix is intentional; the sniff is excluded in `phpcs.xml.dist`. Any other prefix is a real error. |
| Fonts render as Helvetica | Confirm the Archivo woff2 files are present in `themes/ttm-theme/assets/fonts/` (shipped since Phase 0) and that the browser cache isn't serving a stale `style.css`. |
| Front page shows the default index | Run `npm run env:seed` — `front-page.html` and the rest of `themes/ttm-theme/templates/` render only once there is content to query. |
| Theme "disappears" (blank admin, `ttm-theme` not listed) after a branch switch | The switch recreated `themes/`/`plugins/` directories under wp-env's stale bind mount. Run `npx wp-env stop && npx wp-env start` (§7). |
