# These Things Matter

A newspaper-style WordPress block theme (`themes/ttm-theme`) and its companion plugin
(`plugins/ttm-core`), built to be served almost entirely from cache: the theme owns
presentation, the plugin owns every piece of data, and no page varies per visitor.

## Open in WordPress Playground

[Open in WordPress Playground](https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/ericmann/ttmm_theme/main/.github/blueprint.json)

The link resolves once the `v0.2.0` release exists (the blueprint installs that release's
plugin/theme zips).

## Screenshots

<table>
<tr><th>Desktop</th><th>Phone</th></tr>
<tr><td>

Front page

<img src=".github/screenshots/front-1280.png" alt="Front page at 1280px" width="480">

</td><td>

<img src=".github/screenshots/front-390.png" alt="Front page at 390px" width="180">

</td></tr>
<tr><td>

Article

<img src=".github/screenshots/article-1280.png" alt="Article at 1280px" width="480">

</td><td>

<img src=".github/screenshots/article-390.png" alt="Article at 390px" width="180">

</td></tr>
<tr><td>

Journal

<img src=".github/screenshots/journal-1280.png" alt="Journal at 1280px" width="480">

</td><td>—</td></tr>
<tr><td>

Section archive

<img src=".github/screenshots/archive-1280.png" alt="Section archive at 1280px" width="480">

</td><td>—</td></tr>
<tr><td>

Series hub

<img src=".github/screenshots/series-hub-1280.png" alt="Series hub at 1280px" width="480">

</td><td>—</td></tr>
<tr><td>

Writing

<img src=".github/screenshots/writing-1280.png" alt="Writing at 1280px" width="480">

</td><td>—</td></tr>
</table>

## Try it

- **Playground** — use the link above, or import `.github/demo-content.xml` yourself into any
  WordPress site via Tools → Import → WordPress, with "Download and import file attachments"
  checked.
- **Locally**:
  ```bash
  npm ci && composer install
  npx wp-env start && npm run env:seed     # http://localhost:8888  (admin / password)
  ```
  See `docs/SETUP.md` for everyday commands, tests and checks.

## Architecture

- The theme (`themes/ttm-theme`) owns presentation only — templates, patterns and CSS; it never
  registers a taxonomy, post type or query.
- The plugin (`plugins/ttm-core`) owns everything that must survive a theme switch: data, blocks,
  bindings, cron, CLI and migration tooling.
- Every page renders as a correct static document: no per-visitor markup, no front-end network
  requests, safe to serve from a full-page cache.
- Block bindings connect the theme's markup to the plugin's data sources — a block's content
  comes from context (the current post, category, series), never a site-wide default.
- Series and short fiction are first-class: a series index, part navigation, and a dedicated
  serial/story model live in `Fiction/`.
- Migration tooling converts a classic (Jetpack-heavy) WordPress export into this model:
  classic-to-block conversion, image rehosting, redirects and a live-content triage log.

## Status

Pre-1.0, built in public with an agentic pipeline; see `docs/` for the full specification and
build history.

## Docs

- `docs/SPEC.md` — the engineering specification
- `docs/SETUP.md` — local development with wp-env, tests, CI
- `docs/MIGRATION.md` — moving a live site's content to this model, including classic → block
  conversion
- `docs/DEPLOYMENT.md` — production configuration: Cloudflare, Batcache, constants, purge
- `docs/README.md` and `docs/01`–`07` — the design handoff

## Credits

- Typeface: Archivo, [SIL Open Font License 1.1](themes/ttm-theme/assets/fonts/OFL.txt)
- Photographs: Openverse, CC0 or Public Domain Mark, listed with their creators in
  `docs/fixtures/demo/CREDITS.json`
- Demo content licence: `docs/fixtures/demo/LICENSE.md`

## Licence

GPL-2.0-or-later — see [`LICENSE`](LICENSE).
