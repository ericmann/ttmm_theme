# These Things Matter — design & build handoff

A newspaper-style redesign of **eric.mann.blog** (currently the Powder block theme). One author, seven sections, cross-category series, serialized fiction, a daily verse pulled from an external API. This package is written for a build team (human or agent) to plan and implement the whole thing without coming back for design decisions.

## Read in this order

| File | What it covers |
|---|---|
| `README.md` | This index, goals, non-goals, architecture decision (theme vs plugin), glossary |
| `01-design-language.md` | The visual system: principles, tokens, type scale, spacing, grids, every recurring component, states, imagery, iconography, motion |
| `02-screens.md` | Per-screen anatomy — every zone, its grid, its content source (query), its block tree, and its responsive behaviour |
| `03-content-model.md` | Sections, taxonomies, meta, editorial rules (what goes where, how the lead is chosen, journal vs article, series and serials) |
| `04-theme-spec.md` | The block theme: `theme.json`, templates, template parts, patterns, block styles, block variations, editor experience |
| `05-plugin-spec.md` | The companion plugin: data model, custom blocks, block-binding sources, cron/API integration, REST, admin UI, migration |
| `06-fallbacks.md` | Content-fallback matrix — how every block degrades when content is thin/missing (mirrors screen `3c`) |
| `07-open-questions.md` | Decisions deliberately left to the build team, with the design's default |
| `Eric Mann Newspaper.dc.html` | The interactive prototype — all screens on one canvas |
| `_ds/…/styles.css` | Token sheet (source of truth for hex/px values) |
| `../.github/` | The generated, committed WordPress Playground demo: `demo-content.xml`/`demo-options.json`/`blueprint.json` (`npm run demo:build`), `screenshots/` (the eight public README images, `npm run screenshots -- --readme`) |
| `fixtures/demo/` | The demo's real, licence-checked assets: `images/` (13 CC0/Public Domain photographs), `CREDITS.json` (creator/licence per photo), `LICENSE.md` |
| `feedback/phase-5/` | This flight's owner screenshots (`npm run screenshots`), retaken at the end of every phase |

## The prototype
Open `Eric Mann Newspaper.dc.html` in a browser. It is a pan/zoom canvas; each screen has a badge (`2a`, `3c`…). **Turns 2 and 3 are current.** Turn 1 is the first round — `1e` (Section archive) and `1f` (Series hub) are still current and were not re-drawn; everything else in turn 1 is superseded (`1b` is a rejected compact alternative, kept for reference).

Current screens:
- `2a` Front page (1280) · `3a` Front page (390)
- `2b` Article (1280) · `3b` Article (390)
- `2c` Journal post
- `2d` Writing (fiction) landing
- `1e` Section archive (Security)
- `1f` Series hub
- `3c` Missing-content states

The prototype is HTML for **reference only** — it is not theme code. Recreate it as a block theme + plugin. Fidelity target is **high**: colours, sizes, weights, spacing and rules are final; headlines and body copy are sample text; photographs are drop zones.

## Goals
1. Read like a newspaper: sections weighted by how much gets written, structure drawn with rules, no cards.
2. Surface the newest thing in every section on the front page; strict chronology on archives.
3. Make long-form technical writing the visual centre of gravity; keep the journal present but quiet.
4. Tie multi-part work together across categories (Series) and make serialized fiction first-class (Writing).
5. Stay 100% inside the block editor for authoring. No shortcodes, no page builders, no classic widgets.
6. Degrade gracefully: the site must look finished on a quiet month.

## Non-goals
- Comments UI (keep core, style minimally; may be disabled).
- Multi-author UX. There is one author; “by Eric Mann” is static in the masthead and dynamic only in the byline.
- Dark mode (not designed; do not add).
- E-commerce. Book purchase links are plain outbound links.
- Search UI beyond a core Search block in the footer (not drawn — use core, styled per tokens).

## Architecture decision: theme + companion plugin
**Separate them.** The user’s convention (and WordPress best practice) is that a theme owns presentation and a plugin owns data that must survive a theme switch.

**Theme `ttm-theme` owns:** `theme.json`, templates, template parts, patterns, block styles/variations, front-end CSS beyond theme.json, fonts, editor styles.

**Plugin `ttm-core` owns:** the `series` taxonomy and its meta; the `serial` data (fiction), `syndication` meta; the Verse-of-the-day fetcher, storage and block; custom dynamic blocks (`ttm/*`); block-binding sources; REST extensions; WP-CLI commands; migration tooling; admin UI for series/serials.

**Contract between them** (§ in `04` and `05`): the theme only references plugin blocks inside patterns and templates. If the plugin is inactive, those blocks render nothing (server-side blocks with no render callback → empty string) and the layouts close around them per `06-fallbacks.md`. The theme must never `register_taxonomy` or `register_post_meta`; the plugin must never output styling beyond structural inline styles that the theme’s CSS classes target.

Namespaces: PHP `TTM\Theme\…` / `TTM\Core\…`; block namespace `ttm/`; CSS class prefix `ttm-`; text domains `ttm-theme`, `ttm-core`.

## Glossary
- **Section** — one of the seven top-level categories: Technology, Business, Faith, Journal, Writing, Security, Opinion.
- **Cell** — a front-page grid unit showing one section’s latest content.
- **Lead** — the single top story on the front page (8-col, image).
- **Rail** — the right column (verse + journal) on the front page.
- **Series** — an ordered group of posts, any categories, with a part number and total. Non-fiction (e.g. “Hardening WordPress”).
- **Serial** — a Series whose `form` is fiction (novel, novella, story cycle). Its parts are **chapters**. Shown on the Writing page.
- **Story** — a standalone fiction post (short story) in Writing, not in a serial.
- **Kicker** — the small uppercase red label above a headline (section · series · part).
- **Dek** — the standfirst/summary under a headline (WordPress excerpt).
- **Rule** — a divider line. 2px = zone boundary; 1px = item boundary.
- **Zone** — a horizontal band of the page between 2px rules.
- **Poster** — the one red field per page (the newsletter block).

## Existing site facts (from the owner)
- Categories and counts: Technology 431, Business 231, Faith 100, Journal 87, Writing 70, Security 36, Politics 24. Politics folds into Opinion.
- Journal entries are short (< 300 words), titled, mirrored to X and Mastodon.
- Fiction is published as chapters, roughly monthly; some serials have covers, most stories don’t.
- Verse source: `https://dailymedtoday.com/api/v1/meditations/` — returns recent meditations indexed by day; attribution link back is required.
