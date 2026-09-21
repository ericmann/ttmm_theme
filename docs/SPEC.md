# These Things Matter — Specification, phase 2 (front-page fidelity)

Version: 2.0
Status: ready for flight (refinement pass on branch `refine/*`, base `main`)

The Foundry planner reads this file in full and derives the build plan from it. Phase 1 (archived under `docs/phase-1/`) built the plugin `ttm-core` and the block theme `ttm-theme` and merged them to `main`. The owner previewed the seeded site against the Claude Design mock and found the front page does not match it (`docs/feedback/`). **This flight makes the front page — and the chrome it shares with every other template — match mock `2a` (1280) and `3a` (390) exactly.** The mock is the contract; the current preview is wrong wherever they differ.

Precedence: this file wins on engineering; `docs/01-design-language.md`, `02-screens.md §A`, `06-fallbacks.md` and the `2a`/`3a` markup inside `docs/Eric Mann Newspaper.dc.html` (lines 193–352, `id="2a"`; and `id="3a"`) win on visuals. `docs/phase-1/*` is history: read `docs/phase-1/SUMMARY.md` for what exists and why, but do not treat its spec, plan or review as instructions. Numbers marked `⚠️ ASSUMPTION` carry a config key and must never be hard-coded anywhere else.

---

## 1. Overview

A refinement flight on an existing, working codebase: the seeded front page at `http://localhost:8888/` must reproduce mock `2a` at 1280px and `3a` at 390px, element by element, and the fixes must be mechanically verified so they do not regress. The gaps are almost all presentation (missing CSS for classes the blocks already emit, a core separator style that shrinks every rule to 100px, constrained layouts wrapping grid rows, a class-name mismatch on the "Series" nav item) plus three functional gaps: the newsletter poster shows a `mailto:` button instead of an email form, the verse box prints the full copyright notice, and the seed content is lorem ipsum that cannot be judged against the mock.

"Done": `npx wp-env start && npm run env:seed` renders `/` such that every row of the fidelity table (§6.2) passes in Playwright at 1280 and 390, `docs/feedback/phase-2/*.png` screenshots are committed for the owner to compare with `docs/feedback/design_*.png`, and the phase 1 test suites still pass.

## 2. Goals and non-goals

Goals:

- Goal: `/` matches `2a` at 1280 and `3a` at 390 for every zone: masthead, lead row (lead story + rail), section row 1, section row 2, series strip, newsletter poster, footer. §6.1 is the element-by-element contract; §6.2 is how it is checked.
- Goal: The shared chrome the front page exposes (front masthead, inner masthead's nav rules, footer, newsletter poster, cell heading, headline item, rules) is fixed once in `ttm.css` so inner templates inherit the fix.
- Goal: The newsletter poster renders a real email form (input + button) for every provider except `none`, including on the seeded dev site, and posts somewhere that works in production (Jetpack, per the owner's decision Q5 in phase 1).
- Goal: Every `ttm-*` class any template, part, pattern or block emits has a rule in `ttm.css`, enforced by a lint script, so "block emits class, theme forgot it" cannot recur.
- Goal: Seed content reads like the mock (real sentences, realistic titles and deks, the mock's tagline and journal entries) so a human can compare screenshots.
- Goal: Screenshots of the result are produced by the flight itself (Playwright) and committed under `docs/feedback/phase-2/`.

Non-goals (become `**Out of scope:**` lines on tasks):

- Non-goal: Any change to the content model, taxonomies, meta, REST, CLI, migration tooling, cache or verse fetching. Phase 1 owns those; only what §6 names may change in `plugins/ttm-core/src/`.
- Non-goal: Redesigning inner templates (article, journal, archive, series hub, Writing). They receive shared-chrome fixes only; their own zones are a later flight.
- Non-goal: Dark mode, comments, analytics, search, a newsletter archive, production deploy.
- Non-goal: New blocks. The block set is fixed; fixes happen in existing `render.php` files, patterns, parts, templates and CSS. (Exception: none.)
- Non-goal: Jetpack connection in wp-env. The dev site stays unconnected; the seed uses the `custom-url` provider in accept-locally mode (§6.3) so the form is visible and submittable.

## 3. Engineering principles

Every phase 1 rule in `docs/phase-1/SPEC.md §3` (rules 1–33) remains in force with the amendments below, and the reviewer checks them the same way. The planner copies the amended rule set into `CLAUDE.md` `## Constraints`. Amendments and additions:

### 3.1 Amendments to phase 1 rules

- Rule 2 (plugin never owns presentation) — unchanged. The fixes to lead, writing cell, series list and newsletter form markup add or rename `ttm-*` classes; they add no `style="` beyond the phase 1 allow-list.
- Rule 15 — serialization is "schema-validated arrays" (SI-1); JSON is not required.
- Rule 24 (every tunable is a config key) — tolerated literal classes are: HTTP status codes, structural arithmetic (`+ 1`, `% 2`), array indices, and the second argument of `Config::get()` (the fallback must equal `Config::defaults()`; a unit test asserts every fallback literal in `src/` and `blocks/` matches the defaults table) (SI-12). CSS values in `ttm.css`/`theme.json` and pixel values in `tests/e2e/` fixtures are not tunables.
- Rule 30 (CSS budget) — `cssBudgetBytes` = `40960` ⚠️ ASSUMPTION (phase 1 measured 33 070 bytes; this flight adds the missing component rules). The number lives only in `scripts/check-budget.mjs` and `CLAUDE.md`.
- Rule 12 — "every query is bounded" applies to request-time code; CLI term enumeration may page with `Config::get('cli.batch')` (SI-5).
- §4.2 boundaries — the "May import" column is the one in §4 below (SI-2, SI-9, SI-11), which the existing `tests/unit/BoundariesTest.php` must be updated to match.

### 3.2 New rules

34. **CSS coverage.** `scripts/check-css-coverage.mjs` collects every `ttm-[a-z0-9_-]+` class literal from `themes/ttm-theme/{templates,parts,patterns,inc}/**`, `plugins/ttm-core/blocks/*/render.php` and `plugins/ttm-core/src/**/*.php` (string literals only; `Helpers::wrapper('x')` counts as `ttm-x`, and `is-*` state classes are ignored) and fails when any class has no selector in `themes/ttm-theme/assets/css/ttm.css`. An allow-list file `scripts/css-coverage-allow.txt` may name classes that are intentionally unstyled hooks, one per line with a reason after `#`. The script runs inside `npm run lint`. Grep: the script exists, `package.json` `lint` invokes it, and the allow-list has fewer than 10 entries.
35. **Rules are full width.** `.is-style-rule-1` and `.is-style-rule-2` in `ttm.css` set `width: 100%; max-width: none; margin-left: 0; margin-right: 0;` so core's `.wp-block-separator:not(.is-style-wide)…{width:100px}` never applies. Fidelity table rows `rule-*` assert the computed width equals the container width.
36. **Grid rows are not constrained.** No `core/group` that carries an `is-style-grid-*` class uses `"layout":{"type":"constrained"}` or `"layout":{"type":"flow"}`; they use `"layout":{"type":"default"}` (or omit `layout`) so core adds no `max-width`/`margin` to grid children. Grep `is-style-grid` in `themes/ttm-theme/**` and check each block comment.
37. **One class per component, spelled once.** A class the CSS styles must be the class the markup emits: `scripts/check-css-coverage.mjs` also reports selectors in `ttm.css` whose `ttm-*` class appears nowhere in markup (dead rules), and the reviewer treats a dead `ttm-*` rule as a finding.
38. **Fidelity is a test.** Every row of §6.2 is a Playwright assertion in `tests/e2e/fidelity.spec.js`, run at both viewports against the seeded site. A task that changes a front-page component adds or updates its rows; the reviewer rejects a front-page CSS change without a row.
39. **No lorem.** `grep -ri 'lorem' docs/fixtures/seed/` returns nothing after Phase 0.
40. **The theme keeps rule 1.** Nothing in this flight adds a data call to `themes/`; the copyright line, the "All N entries" count and the current-section mark come from plugin bindings/filters, not theme PHP.

## 4. Architecture

The repository layout is phase 1's (`docs/phase-1/SPEC.md §4.1`) and does not change. The plugin module map, with the import column corrected to what shipped and what this flight needs:

| Directory | Responsibility | May import |
|---|---|---|
| `Config.php` | Defaults + `get()` + `ttm_config` | nothing |
| `Support/` | `Clock`, `Dates`, `Text`, `Html` | `Config` |
| `Taxonomy/` | `series` taxonomy, `SeriesAdmin` | `Support`, `Config` |
| `Meta/` | post meta, primary category, word count, form, series position | `Support`, `Config`, `Taxonomy` |
| `Query/` | `Lead`, `Cells`, `Archive` (pure lookups), `SeriesIndex`, `Stats`, `JournalExcerpt` | `Support`, `Config`, `Meta`, `Taxonomy` |
| `Fiction/` | `Serials`, `Books` | `Query`, `Taxonomy`, `Support`, `Config` |
| `Cache/` | headers, purge, adapters | `Config`, `Support` |
| `Verse/` | fetch, cron, admin | `Support`, `Config`, `Cache` |
| `Newsletter/` | handler, providers, settings | `Config`, `Support` |
| `Bindings/` | `Sources` (all binding sources, pagination label filters), `Values` (pure formatters) | `Query`, `Support`, `Meta`, `Config`, `Verse` (read-only, for `ttm/verse-copyright`) |
| `Blocks/` | `Registrar`, `Helpers` (shared render helpers, year grouping, archive scope) | everything above |
| `Editor/`, `Templates/`, `Nav/`, `Rest/`, `Cli/`, `Compat/`, `Admin/` | as phase 1 | everything above except `Cli` may import anything |

Dependency arrow points down. `tests/unit/BoundariesTest.php` encodes this table and is updated in Phase 0 (SI-2/SI-9/SI-11).

Theme layout is phase 1's (`docs/04-theme-spec.md §1`) minus `inc/template-hierarchy.php` (never built, SI-4). `ttm.css` stays organised by `01 §4` component numbers with a comment header per component; **every component in §6.1 gets its own header block in the file** so the reviewer can find it.

Request flow is unchanged (`docs/phase-1/SPEC.md §4.4`): zero database writes on a front-end request; the new copyright binding reads option `ttm_verse` only.

## 5. Data and configuration

Phase 1's `Config::defaults()` is unchanged except:

```text
cells.thin_days             — remove (no code reads it; SI-8)
cells.stale_count           = 2      (exists; document in this table, SI-10)
writing.tile_columns        = 2      (exists; SI-13)
sections.technology_slug    = 'technology' (exists; SI-3)

journal.excerpt_words       = 40     (exists) — target length, sentence-trimmed
journal.excerpt_max_words   = 55     ⚠️ ASSUMPTION — hard cap when no sentence ends between 40 and 55 words; the excerpt is cut at 55 words and gets "…"
nav.front_current           = 'lead' — 'lead' | 'none': which section the front-page nav marks current (§6.1.1 step 4)
verse.copyright_placement   = 'footer' — 'footer' | 'box' | 'none' (§6.4)
newsletter.dev_accept       = true   — `custom-url` with an empty endpoint accepts locally when WP_ENVIRONMENT_TYPE ≠ production (§6.3)
```

`ttm_settings` overlay keys are unchanged. No new options, meta or transients. Seed data changes are in §6.5 (fixtures only).

## 6. Interfaces

### 6.1 Front-page contract (mock `2a` at 1280; `3a` at 390 in §6.1.9)

Measurements are the mock's inline values (`docs/Eric Mann Newspaper.dc.html` lines 193–352). Colour names are `theme.json` presets. "Rule" means a `core/separator` with `is-style-rule-N` or a border using `--ttm-rule-N`. The page gutter is 48px at 1280 (`--wp--custom--gutter--desktop`), 20px at 390.

#### 6.1.1 Masthead — front (`.ttm-masthead-front`, pattern `ttm/masthead-front`)

1. Meta row: flex space-between, align center, `caption` 12px neutral-700, `font-feature-settings: 'tnum'`, padding 14 0, 1px rule below. Left: `l, F j, Y` via `ttm/today`. Right: Newsletter · RSS · About, gap 18, each a plain link (no `wp-block-navigation` chrome: no gap-less items, no hover background).
2. Title row: grid `1fr auto`, align end, gap 32, padding 28 0 20. Left: Site Title at `display-l` (76px/0.95/−0.03em, margin-left −0.058em), then the byline **12px below** (`margin-top: 12px`, not negative): "by" neutral-600 + "Eric Mann" 600 weight linked to `/about/`, 15px. Right: Site Tagline 15px/1.5 neutral-800, `max-width: 32ch`, bottom-aligned with the byline.
3. 2px rule, full width (rule 35).
4. Nav row: `core/navigation` with the seven sections in `sections.order` then the hub link. Flex, gap 28, 14px/600, padding 12 0. **Current section = accent**: when `nav.front_current = 'lead'`, `Nav\CurrentSection` marks the lead post's primary section current on the front page (in `2a` the lead is Technology and Technology is red); `'none'` marks nothing. "Series" carries the class the CSS styles — pick one name, `ttm-nav__hub`, use it in the pattern, the JS variation and the CSS — and is `margin-left: auto`, 400 weight, neutral-700.
5. 2px rule, full width.

The pattern currently emits `ttm-nav__hub` while `ttm.css` styles `.ttm-nav-series`; that mismatch is the bug.

#### 6.1.2 Lead row (`.ttm-lead-row`)

Group, grid `minmax(0,8fr) minmax(0,4fr)`, gap 0 40, padding 28 0 32, `layout: default` (rule 36). Left: `ttm/lead-story`. Right: template part `rail` (`.ttm-rail`, flex column, gap 24).

#### 6.1.3 Lead story (`ttm/lead-story` → `.ttm-lead`)

Block markup is `div.ttm-lead > article.ttm-lead__inner > figure.ttm-lead__media + p.ttm-lead__kicker + h2.ttm-lead__title > a + p.ttm-lead__dek + p.ttm-lead__meta`. Every one of those classes gets CSS (today only `.ttm-lead.is-textonly .ttm-lead__title` exists):

- `__media`: width 100%, `aspect-ratio: 16/9`, background surface, grayscale (`filter: grayscale(1) contrast(1.08)`), `img { object-fit: cover; width:100%; height:100% }`.
- `__kicker`: margin 20 0 10, 12px, letter-spacing .08em, uppercase, 600, **accent-700**. Text = `Values::kicker()` → "Technology · Series: Hardening WordPress, part 3 of 6" (section · "Series: {name}, part N of M"; no series → section only).
- `__title`: margin 0 0 12 −0.02em, `lead` preset 44px/1.05/−0.02em, 800, `max-width: 22ch`; link ink, hover accent.
- `__dek`: margin 0 0 12, 17px/1.5 neutral-800, `max-width: 60ch`; inline `code` 15px surface bg padding 1 5.
- `__meta`: 12px neutral-700, tnum: "Sept 19 · 14 min read · Part 2: {title}" where the previous-part link is accent-700.
- F8 (no image): title steps to `display-m` 56px, `max-width: 18ch`, dek 19px; no placeholder box.

#### 6.1.4 Verse of the day (`ttm/verse-of-the-day` → `.ttm-verse`)

Surface box, padding 18 20. Kicker "Verse of the day" 11px/600/uppercase/.08em **accent-700**, margin 0 0 12. Text 19px/600/1.35/−0.01em ink, margin 0 0 10, curly quotes. Reference 13px neutral-800, margin 0 0 14. Attribution 12px neutral-700: "Meditation for Sept 20 from dailymedtoday.com" — the domain is accent-700, `text-decoration: underline; text-underline-offset: 3px`. **No copyright text in the box** (§6.4). Nothing else.

#### 6.1.5 Journal rail (pattern `ttm/journal-rail` → `.ttm-journal-rail`)

Heading: `.ttm-cell-heading.is-rail` — flex space-between baseline, padding-bottom 8, **2px rule below**, label `h6`-style 12px/800/uppercase ink ("Journal", rendered as a heading level the a11y tree accepts — h2 is fine, styled as the label), right link 12px neutral-700 reading **"All 87 entries"** (`ttm/category-count` gains `format: 'entries'` → "All {N} entries"; 0 → "All →" per F25).

Entries (3 = `journal.rail_count`): each `article`/`.wp-block-post` is padding 16 0 with a **1px rule below** (last one included, as in the mock). Date line 11px neutral-700 tnum, margin 0 0 6 ("Today · Sept 20", "Thursday · Sept 18" via `ttm/relative-date`). Title h4 16px/800/1.25, margin 0 0 6, link ink hover accent. Excerpt 14px/1.5 neutral-800, margin 0, sentence-trimmed at `journal.excerpt_words`, hard-capped at `journal.excerpt_max_words` (§5). "Continue →" 12px/600 **accent-700**, `display:inline-block; margin-top: 8px`. No "more" text inside the excerpt, no social links.

#### 6.1.6 Section rows (`.ttm-section-row`)

Two groups, each `grid-template-columns: repeat(4, minmax(0,1fr))`, gap 0 32, `layout: default`; row 1 padding 0 0 8. A full-width 2px rule sits above row 1, between the rows, and below row 2 (the template already has the separators; rule 35 makes them visible).

Cells (`.ttm-cell`): padding 20 32 24 0, `border-right: 1px` divider except the **last cell in the row** (`:last-child`), which has padding-right 0. Cell heading (`.ttm-cell-heading`, not `.is-rail`): flex space-between baseline, margin-bottom 16, **no rule**; label 12px/800/uppercase/.08em ink; right link 11px neutral-600 tnum ("431 articles →" / "231 →" / "All serials & stories →").

Items:

- Featured item (first post): title `cell-lead` 21px/800/1.15, margin 0 0 8; dek 13px/1.45 neutral-800, margin 0; meta 12px neutral-700, margin 6 0 0 ("Sept 15"; no read time in cells — `ttm/meta-line` `parts: ['date']`; Opinion appends " · Politics" when applicable). No rule above the featured item.
- Following items (`.ttm-item`): `border-top: 1px` divider, padding-top 12, margin-bottom 12 (0 on the last), title `headline-s` 15px/800/1.3, margin 0 0 4, **no dek** (the existing `:not(:first-child)` suppression stays), meta 12px neutral-700.
- Counts per `cells.counts` (Technology 3, Business 3, Security 3, Faith 2, Opinion 2).

Technology cell (`ttm/section-cell-large`, `is-style-span-2`): inner grid `1fr 1fr`, gap 20 28. The featured item spans both columns and is itself a grid `200px 1fr`, gap 20, align start: left a 3:2 grayscale image (`ttm-thumb` size, `.ttm-item-featured__media`), right title `cell-lead-l` 24px/800/1.15 margin 0 0 8, dek 14px/1.45 neutral-800, meta 12px margin 8 0 0 ("Sept 16 · 9 min" — here the read time **is** shown, `parts: ['date','reading']`, format "9 min"). Items 2 and 3 sit side by side in the two columns, each with a 1px top rule, padding-top 14, title 17px/800/1.25. No image → the featured item collapses to a single column (F8-style, `is-textonly`).

Writing cell (`ttm/writing-cell`, `is-style-span-2`, last in row 2, no right rule): uses the **same cell heading component** (`.ttm-cell-heading` with label "Writing" and link "All serials & stories →" to `/writing/`). Body grid `1fr 1fr`, gap 0 28. Left (`__featured`): kicker 12px/600/uppercase/.08em accent-700 "Serial · new chapter monthly" (cadence from term meta; "Serial" alone when empty), margin 0 0 8; headline (`__headline`) 26px/800/1.1 margin 0 0 8 "{Serial} — Ch. {n}: {part title}" (no part title → "{Serial} — Ch. {n}"); dek (`__dek`) 14px/1.5 neutral-800 `max-width: 40ch` margin 0 0 12 (chapter excerpt; none → omitted); buttons row flex gap 10: `.btn.btn-primary` "Read chapter {n}", `.btn.btn-secondary` "From chapter 1". Right (`__also`): `border-left: 1px` divider, padding-left 28; label 11px/600/uppercase/.08em neutral-700 "Also running" margin 0 0 6; up to 3 rows (`writing.also_running_limit`) each a block link padding 8 0 with 1px rule below except the last: title 15px/800/1.3, meta 12px neutral-700 ("Novella · complete · 9 chapters" / "Short story · 3,100 words"). F1/F2 states as phase 1.

#### 6.1.7 Series strip (pattern `ttm/series-strip` + `ttm/series-list`)

Section padding 20 0 28. Heading: `.ttm-cell-heading` (no rule) "Series in progress" / right "All series" 12px neutral-700 → `/series/`. List grid `repeat(3, minmax(0,1fr))`, gap 24 (`is-style-grid-3`), **always three columns at ≥ 1024 even with fewer rows**. Row (`.ttm-series-row`): block link, grid `10px 1fr`, gap 12, align start, padding-top 12, `border-top: 1px` divider; square `.ttm-series-mark` 10×10 accent (in progress) / neutral-900 (complete) / neutral-400 (hiatus), margin-top 5; title 16px/800/1.25; **one meta line** 12px neutral-700 tnum margin-top 4: "{Category} · {Category} · {n} of {M}" plus " · {cadence}" when set (mock: "Faith · 9 of 12 · Sundays"). Drop the phase 1 two-column count/status layout on the front page (it belongs to the hub, 01 §4.17 "optional right column"); the block gets a `layout` attribute `strip|list` (strip = single meta line) and the pattern passes `strip`. Categories joined by " · ", not ", ". F4 heading fallback unchanged.

#### 6.1.8 Newsletter poster + footer

Poster (`.ttm-poster`, pattern `ttm/newsletter-poster`): accent field **full-bleed to the page gutter** (padding 36 48 32), grid `1fr auto`, gap 32, align end. H3 `poster` 40px/1.05/−0.02em 800 bg colour, margin-left −0.058em, `max-width: 20ch`, **left-aligned**. Right: the newsletter form (§6.3) as flex gap 8 align center: `.input` 260px wide, bg colour background and border, placeholder "you@example.com"; `.btn.btn-ghost` "Subscribe" bg-colour text with 1px bg-colour border. No "Subscribe by email" mailto button when a form provider is configured.

Footer (`.ttm-footer`, part `footer`): flex space-between, padding 16 48, 12px neutral-700, no rule when it follows the poster (`is-after-poster`). Left: **one line** "These Things Matter · © 2026 Eric Mann · Built on WordPress" — a single paragraph bound to `ttm/today format=footer` that returns the whole line (the three-paragraph flex group is replaced). Then, when `verse.copyright_placement = 'footer'` and a verse is stored, a second line in the same style bound to `ttm/verse-copyright` (§6.4). Right: "Technology · Business · Faith · Journal · Writing · Security · Opinion · Series · RSS" — a `core/navigation` whose items are separated by " · " via CSS (`.ttm-footer .wp-block-navigation-item + .wp-block-navigation-item::before { content: "·"; margin: 0 .4em }`, gap 0), each a link, neutral-700, hover accent. The items come from the same `sections.order` list the masthead uses (the plugin's navigation-link filter fills labels from term names, F18 hides Series).

#### 6.1.9 Phone (`3a`, 390px, ≤ 720 breakpoint)

Exactly `02-screens.md §A Responsive`. In addition: gutter 20; every zone rule stays full width; the masthead meta row shows the date left and only "Newsletter" right; nav scrolls horizontally with no visible scrollbar and the "Series" item keeps its subdued style at the end; the lead image is 4:3; the rail becomes a full-width zone (verse box then journal with 2 entries) under a 2px rule; each cell is its own zone under a 2px rule with 18px padding and no right rules; the Writing cell stacks (featured, then "Also running" without the left rule, with a 1px rule above); series strip rows stack; poster padding 24 20, H3 28px, input and button stacked full width; footer stacked 11px/1.6 with the nav items wrapping. ≤ 1024: cells `repeat(2, 1fr)` per `02 §A`.

### 6.2 Fidelity table (asserted by `tests/e2e/fidelity.spec.js`)

Each row is one `expect(await el.evaluate(getComputedStyle...))` at the named viewport on the seeded `/`. Selectors are the ones markup emits after this flight. Pixel values may differ by ≤ 0.5px; colours compare as `rgb()` of the preset hex.

| id | selector | viewport | property | expected |
|---|---|---|---|---|
| rule-2 | `main > hr.is-style-rule-2` (first) | 1280 | width | container width (1280 − 96) |
| rule-2 | same | 1280 | height | 2px |
| rule-2-phone | same | 390 | width | 350px |
| mast-meta | `.ttm-masthead-front__meta` | 1280 | font-size / color | 12px / neutral-700 |
| mast-title | `.ttm-masthead-front .wp-block-site-title` | 1280 | font-size | 76px |
| mast-byline | `.ttm-masthead-front__byline` | 1280 | margin-top | 12px |
| mast-byline-phone | same | 390 | font-size | 12px |
| mast-nav | `.ttm-masthead-front__nav a` (first) | 1280 | font-size / font-weight | 14px / 600 |
| mast-nav-gap | `.ttm-masthead-front__nav ul` | 1280 | column-gap | 28px |
| mast-current | `.ttm-masthead-front__nav .current-menu-item > a` | 1280 | color | accent (exists when `nav.front_current='lead'`) |
| mast-hub | `.ttm-masthead-front__nav .ttm-nav__hub > a` | 1280 | font-weight / color / margin-left | 400 / neutral-700 / auto (x ≥ 1000) |
| lead-row | `.ttm-lead-row` | 1280 | grid-template-columns | two tracks, ratio 2:1 ± 2px |
| lead-row | same | 1280 | column-gap | 40px |
| lead-media | `.ttm-lead__media` | 1280 | aspect-ratio / filter | 16 / 9 / grayscale(1) contrast(1.08) |
| lead-media-phone | same | 390 | aspect-ratio | 4 / 3 |
| lead-kicker | `.ttm-lead__kicker` | 1280 | font-size / text-transform / color / letter-spacing | 12px / uppercase / accent-700 / 0.96px |
| lead-title | `.ttm-lead__title` | 1280 | font-size / line-height / font-weight | 44px / 46.2px / 800 |
| lead-title-phone | same | 390 | font-size | 30px |
| lead-dek | `.ttm-lead__dek` | 1280 | font-size / color | 17px / neutral-800 |
| lead-meta | `.ttm-lead__meta` | 1280 | font-size / color | 12px / neutral-700 |
| verse-box | `.ttm-verse` | 1280 | background-color / padding | surface / 18px 20px |
| verse-kicker | `.ttm-verse .is-style-kicker` | 1280 | font-size / color / text-transform | 11px / accent-700 / uppercase |
| verse-text | `.ttm-verse__text` | 1280 | font-size / font-weight | 19px / 600 |
| verse-ref | `.ttm-verse__reference` | 1280 | font-size / color / font-weight | 13px / neutral-800 / 400 |
| verse-attr | `.ttm-verse__attribution a` | 1280 | color / text-decoration-line | accent-700 / underline |
| verse-nocopy | `.ttm-verse__copyright` | 1280 | count | 0 |
| rail-head | `.ttm-journal-rail .ttm-cell-heading.is-rail` | 1280 | border-bottom-width | 2px |
| rail-head-link | `.ttm-journal-rail .ttm-cell-heading__link` | 1280 | text | matches `/^All \d+ entries$/` |
| rail-entry | `.ttm-journal-rail .wp-block-post` (first) | 1280 | padding-top / border-bottom-width | 16px / 1px |
| rail-date | `.ttm-journal-rail .ttm-journal-excerpt__date` | 1280 | font-size / color | 11px / neutral-700 |
| rail-title | `.ttm-journal-rail .wp-block-post-title` | 1280 | font-size / font-weight | 16px / 800 |
| rail-excerpt | `.ttm-journal-rail .wp-block-post-excerpt__excerpt` | 1280 | font-size / color / word count | 14px / neutral-800 / ≤ 55 |
| rail-more | `.ttm-journal-rail .wp-block-read-more` | 1280 | font-size / font-weight / color / margin-top | 12px / 600 / accent-700 / 8px |
| rail-count-phone | `.ttm-journal-rail .wp-block-post` | 390 | count | 2 |
| row-grid | `.ttm-section-row` (each) | 1280 | grid-template-columns | four equal tracks |
| row-grid-tablet | same | 1000 | grid-template-columns | two equal tracks |
| row-gap | same | 1280 | column-gap | 32px |
| row-layout | same | 1280 | class list | not `is-layout-constrained` |
| cell-pad | `.ttm-cell:not(:last-child)` (first) | 1280 | padding / border-right-width | 20px 32px 24px 0px / 1px |
| cell-last | `.ttm-section-row .ttm-cell:last-child` | 1280 | border-right-width | 0px |
| cell-head | `.ttm-cell .ttm-cell-heading__label` | 1280 | font-size / text-transform / border-bottom-width | 12px / uppercase / 0px |
| cell-head-link | `.ttm-cell .ttm-cell-heading__link` | 1280 | font-size / color | 11px / neutral-600 |
| cell-lead | `.ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .wp-block-post-title` | 1280 | font-size | 21px |
| cell-lead-dek | `.ttm-cell:not(.is-style-span-2) .wp-block-post:first-child .ttm-item__dek` | 1280 | font-size / display | 13px / block |
| cell-item | `.ttm-cell:not(.is-style-span-2) .wp-block-post:nth-child(2) .wp-block-post-title` | 1280 | font-size / border-top (parent) | 15px / 1px |
| cell-item-nodek | `.ttm-cell .wp-block-post:nth-child(2) .ttm-item__dek` | 1280 | display | none |
| cell-meta | `.ttm-cell .ttm-item__meta` (first) | 1280 | font-size / color | 12px / neutral-700 |
| tech-grid | `.ttm-cell.is-style-span-2 .wp-block-post-template` | 1280 | grid-template-columns | two equal tracks |
| tech-featured | `.ttm-cell.is-style-span-2 .wp-block-post:first-child` | 1280 | grid-column / grid-template-columns | span 2 / 200px + rest |
| tech-img | `.ttm-cell.is-style-span-2 .ttm-item-featured__media` | 1280 | aspect-ratio / filter | 3 / 2 / grayscale(1) contrast(1.08) |
| tech-title | `.ttm-cell.is-style-span-2 .wp-block-post:first-child .wp-block-post-title` | 1280 | font-size | 24px |
| tech-item | `.ttm-cell.is-style-span-2 .wp-block-post:nth-child(2) .wp-block-post-title` | 1280 | font-size | 17px |
| writing-head | `.ttm-writing-cell .ttm-cell-heading__label` | 1280 | text-transform / font-size | uppercase / 12px |
| writing-grid | `.ttm-writing-cell__body` | 1280 | grid-template-columns / column-gap | two equal tracks / 28px |
| writing-kicker | `.ttm-writing-cell__kicker` | 1280 | color / text-transform | accent-700 / uppercase |
| writing-headline | `.ttm-writing-cell__headline` | 1280 | font-size / line-height | 26px / 28.6px |
| writing-also | `.ttm-writing-cell__also` | 1280 | border-left-width / padding-left | 1px / 28px |
| writing-btn | `.ttm-writing-cell .btn-primary` | 1280 | background-color / color | accent / bg |
| strip-grid | `.ttm-series-list.is-strip` (or `.ttm-series-strip .is-style-grid-3`) | 1280 | grid-template-columns / column-gap | three equal tracks / 24px |
| strip-row | `.ttm-series-row` (first) | 1280 | grid-template-columns / border-top-width | 10px + rest / 1px |
| strip-mark | `.ttm-series-row .ttm-series-mark` | 1280 | width / height / background-color | 10px / 10px / accent |
| strip-meta | `.ttm-series-row__meta` | 1280 | font-size / color / text | 12px / neutral-700 / contains " · " and matches `/\d+ of \d+/` |
| poster | `.ttm-poster` | 1280 | background-color / padding / grid-template-columns | accent / 36px 48px 32px / 1fr auto |
| poster-h3 | `.ttm-poster h3` | 1280 | font-size / color / text-align | 40px / bg / left |
| poster-input | `.ttm-poster input[type="email"]` | 1280 | width / background-color | 260px / bg |
| poster-btn | `.ttm-poster button, .ttm-poster .btn-ghost` | 1280 | color / border-width / background-color | bg / 1px / transparent |
| poster-nomailto | `.ttm-poster a[href^="mailto:"]` | 1280 | count | 0 |
| poster-phone | `.ttm-poster h3` | 390 | font-size | 28px |
| footer | `.ttm-footer` | 1280 | padding / font-size / color / border-top-width | 16px 48px / 12px / neutral-700 / 0px |
| footer-left | `.ttm-footer__meta` | 1280 | text | `/^These Things Matter · © \d{4} Eric Mann · Built on WordPress$/` |
| footer-copy | `.ttm-footer__copyright` | 1280 | count / font-size | 1 (verse seeded) / 12px |
| footer-nav | `.ttm-footer .wp-block-navigation-item` | 1280 | count | 9 |
| footer-nav-sep | `.ttm-footer .wp-block-navigation-item:nth-child(2)::before` | 1280 | content | "·" |
| footer-phone | `.ttm-footer` | 390 | flex-direction / font-size | column / 11px |
| a11y | whole page | both | axe | 0 serious/critical |
| network | whole page | both | requests | same-origin only; no `wp-json`, `admin-ajax`, `fonts.googleapis.com` |

The planner turns this table into the spec file verbatim (one `test()` per row, grouped by zone) in Phase 0 with every row marked `test.fixme` and each later task un-fixmes the rows it makes pass; the flight ends with zero `fixme` rows.

### 6.3 Newsletter form contract

`ttm/newsletter-form` renders **one markup shape** for every provider except `none`:

```html
<div class="ttm-newsletter-form is-poster" data-provider="jetpack">
  <form class="ttm-newsletter-form__form" method="post" action="…" novalidate>
    <label class="screen-reader-text" for="ttm-nl-email-{n}">Email address</label>
    <input class="input" type="email" name="email" id="ttm-nl-email-{n}" placeholder="you@example.com" autocomplete="email" required>
    <!-- provider hidden fields -->
    <button class="btn btn-ghost" type="submit">Subscribe</button>
  </form>
  <p class="ttm-newsletter-form__done">Check your inbox.</p>
</div>
```

`{n}` is a per-request counter (deterministic, rule 29). Placement `box` uses `.btn.btn-primary`. The `data-state="subscribed"` swap (`?subscribed=1`) is unchanged.

Providers:

- `jetpack` (default when Jetpack is active and connected — `Jetpack::is_connection_ready()` or the `jetpack/subscriptions` block is registered): action = the current page URL (`get_permalink()` or `home_url('/')` on the front page), hidden fields exactly as Jetpack's widget handler expects (`Jetpack_Subscriptions_Widget::process_subscription`, Jetpack 16.2, `modules/subscriptions/views.php` lines 522–575): `action=subscribe`, `source={current URL}`, `sub-type=widget`, `redirect_fragment=ttm-newsletter-{n}`, and the submit button carries `name="jetpack_subscriptions_widget"`. No nonce (rule 7; the widget path has none). Jetpack handles the POST on `init`, subscribes via WordPress.com, and redirects back with `?subscribe=success#…`; the block also treats `?subscribe=success` like `?subscribed=1` for the done state. **Spike (bounded, one task, Phase 1):** confirm the field list against the installed Jetpack source in wp-env, record it in `docs/spikes/P1-jetpack-form.md`, and capture Jetpack's own rendered widget form into `docs/fixtures/jetpack-subscriptions.html` for the integration test. If Jetpack is active but unconnected, this provider is *unavailable* and the switch falls through.
- `custom-url`: action = `admin-post.php`, `action=ttm_subscribe`, honeypot, HMAC token, `redirect_to`; the handler forwards to `newsletter.endpoint`. **New:** when `newsletter.endpoint` is empty, `newsletter.dev_accept` is true and `wp_get_environment_type() !== 'production'`, the handler accepts (logs nothing, forwards nothing) and redirects to `redirect_to` with `?subscribed=1`. This is what the seed configures, so the dev poster shows and submits a real form.
- `mailto`: renders the same form with `action="mailto:{email}"`, `method="get"`? **No** — `mailto:` forms are unreliable; `mailto` keeps its phase 1 button but only when it is the *configured* provider or when `newsletter.fallback_email` is set and no form provider is available; it is never the dev default.
- `none`: statement only (F26).

Provider resolution: configured provider if available → `custom-url` if `newsletter.dev_accept` applies → `mailto` if fallback email → `none`. `wp ttm seed` sets `ttm_settings.newsletter.provider = 'custom-url'` with an empty endpoint.

### 6.4 Verse copyright placement

The NIV notice must appear on the page (Zondervan's terms), but not in the box. Binding source `ttm/verse-copyright` (in `Bindings\Sources`, reads option `ttm_verse['copyright']`, returns `''` when empty or when `verse.copyright_placement !== 'footer'`) feeds a `core/paragraph.ttm-footer__copyright` in the footer part; the block renders the `<small>` only when `verse.copyright_placement === 'box'`. Default `'footer'`. REST `/verse` still returns `copyright`.

### 6.5 Seed content (fixtures only)

`docs/fixtures/seed/posts.json` and `pages.json` are rewritten so the seeded front page carries the mock's copy where the mock shows copy, and plausible copy elsewhere:

- Site tagline: "Technology, business, faith and the occasional story. One writer, several desks." (`wp option update blogdescription` in the seeder).
- Lead: the `2a` lead ("Stop trusting the database: signing your options table", Technology, series "Hardening WordPress" part 3 of 6, dek with inline `<code>wp_options</code>`, 14 min ≈ 3 200 words of prose, featured image).
- Technology cell: the three `2a` headlines with the mock's dek and dates; the featured one has an image.
- Business, Security, Faith, Opinion: the `2a` headlines, deks, dates ("Aug 22 · Politics" for the Opinion second item = a post also in `politics`).
- Journal: at least 6 entries with the three `2a` entries newest (titles, excerpts of 38–48 words in two or three sentences, dates today/−2/−3 relative to `seed` run time via `Clock`).
- Writing: serial "The Quiet Ledger" (12 chapters, cadence "new chapter monthly", chapter 12 "Reconciliation" with the mock dek), plus "Failover" (novella, complete, 9 chapters), "Salt Water Wires" (novel, complete, 24), "The Last Cron Job" (short story, 3 100 words).
- Series: "Hardening WordPress" (Technology · Security, 3 of 6), "The Consultant's Ledger" (Business, 5 of 8), "Ordinary Time" (Faith, 9 of 12, cadence "Sundays").
- Verse: `docs/fixtures/verse-sample.json` unchanged (Psalm 33:20–22); the seeder stores it with today's date so the attribution reads "Meditation for {today}".
- Body prose comes from `docs/fixtures/seed/prose.json` (an array of ≥ 40 original paragraphs of two to five sentences, written for this repo, no lorem, no real third-party text); posts draw paragraphs deterministically by index.
- Word counts, read times and category counts in the mock ("431 articles") are **not** reproduced; the counts are whatever the seed produces.

### 6.6 Screenshots

`npm run screenshots` (Playwright, added in Phase 0) writes `docs/feedback/phase-2/front-1280.png` and `front-390.png` (full page) plus one per zone at 1280 (`masthead`, `lead-row`, `section-rows`, `series-strip`, `poster-footer`) from the seeded site. Each phase's push task re-runs it and commits the PNGs so the owner compares against `docs/feedback/design_*.png`.

## 7. Commands

Unchanged from phase 1 except `npm run lint` now also runs `scripts/check-css-coverage.mjs`, and the e2e set gains `fidelity.spec.js` and the screenshot script.

```text
verify:
  composer lint
  composer test:unit
  npm run lint                  # eslint + stylelint + theme.json/block.json checks + CSS budget + CSS coverage
  npm run test:unit
  npm run build
  bash scripts/forbidden-patterns.sh

extraVerify:
  plugins/ttm-core/, themes/ttm-theme/, tests/integration/:
      npm run test:integration
  themes/ttm-theme/theme.json:
      npm run check:theme-json
  themes/ttm-theme/, plugins/ttm-core/blocks/, tests/e2e/:
      npm run test:e2e          # includes fidelity.spec.js; wp-env must be running and seeded

build:
  npm run build

foundry.json:
  baseBranch       = "main"
  branchPrefix     = "refine/"
  maxRounds        = 4
  commandTimeoutMs = 900000
```

`npm run test:e2e` must (re)seed before running (`npm run env:seed` is idempotent) so the fidelity rows see the §6.5 content. If wp-env's bind mounts go stale (the theme "disappears" after a branch switch that recreates directories), `npx wp-env stop && npx wp-env start` fixes it; document this in `docs/SETUP.md` in Phase 0.

## 8. Phases

Each phase ends with a task that runs `npm run screenshots`, commits the PNGs, pushes, and records `Manual check: NOT VERIFIED (human)` naming the screenshot pair to compare. Later tasks never depend on a manual check.

### Phase 0 — Harness

- `scripts/check-css-coverage.mjs` + allow-list, wired into `npm run lint`; fix or allow-list every class it reports today (the eleven unstyled classes are fixed by later phases; allow-listing them now with `# phase 2 pending` and removing the entries as each phase lands is acceptable, but Phase 3's push task must leave the allow-list under 10 entries).
- `cssBudgetBytes` → 40960; `CLAUDE.md` and `scripts/check-budget.mjs` agree.
- `BoundariesTest` updated to §4; `Config` fallback-literal test (rule 24 amendment); remove `cells.thin_days`; add the §5 keys.
- `tests/e2e/fidelity.spec.js` with every §6.2 row as `test.fixme`, plus the helper that resolves preset colours from `theme.json` to `rgb()`.
- `npm run screenshots` script and `docs/feedback/phase-2/` (with a `README.md` naming the pairs).
- Seed rewrite per §6.5 (`prose.json`, posts, pages, series, tagline); `grep -ri lorem docs/fixtures/seed` empty; `wp ttm seed --reset` runs clean.
- `docs/SETUP.md`: stale-mount note; `docs/phase-1/README.md` exists (it does).
- **Visible result:** seeded site with real copy; baseline screenshots committed. Manual check: `docs/feedback/phase-2/front-1280.png` shows the mock's headlines.

### Phase 1 — Chrome: masthead, rules, footer, poster

- Rules 35/36: `.is-style-rule-*` full width; every `is-style-grid-*` group in templates/patterns switched to `layout: default`; verify no core `max-width` on grid children.
- Masthead front per §6.1.1: byline spacing, tagline alignment, nav gap/size, `ttm-nav__hub` unified, `Nav\CurrentSection` front-page current mark (`nav.front_current`), meta-row links without navigation chrome. Inner masthead: only what it shares (rules, nav hub class).
- Footer per §6.1.8: single meta line via `ttm/today format=footer`, `ttm/verse-copyright` binding + `verse.copyright_placement`, nav separators, phone stacking. Verse block drops the `<small>` unless placement is `box`.
- Poster per §6.1.8 and the newsletter form per §6.3: shared form markup, provider switch, `custom-url` dev-accept, Jetpack spike (bounded, one task) with fixture, seed sets `custom-url`. Integration tests: each provider's markup; handler dev-accept only outside production; Jetpack fields match the fixture.
- Un-fixme rows: `rule-*`, `mast-*`, `verse-nocopy`, `poster-*`, `footer-*`.
- **Visible result:** masthead and footer match; poster has an email field. Manual check: compare `masthead.png` and `poster-footer.png` with `design_top.png` / `design_footer.png`.

### Phase 2 — Lead row

- `ttm/lead-story` CSS per §6.1.3 (all five classes), kicker text and colour, F8 sizes; `.ttm-lead-row` grid.
- Verse box per §6.1.4 (kicker colour, sizes, spacing).
- Journal rail per §6.1.5: heading rule + "All N entries" (`ttm/category-count` `format: 'entries'`), entry rules and spacing, typography, `journal.excerpt_max_words` cap in `JournalExcerpt`/`Text::sentence_excerpt`, "Continue →" style. Phone: 2 entries.
- Un-fixme rows: `lead-*`, `verse-*`, `rail-*`.
- **Visible result:** lead row matches `2a`. Manual check: `lead-row.png` vs the top of `design_top.png`.

### Phase 3 — Section rows, Writing cell, series strip

- `.ttm-section-row`/`.ttm-cell`/`.ttm-cell-heading`/`.ttm-item` per §6.1.6; Technology inner grid and featured-with-image item (`ttm-item-featured__media`, `ttm-thumb`); meta-line parts per cell; Opinion " · Politics".
- Writing cell per §6.1.6: shared heading component, `__body`/`__featured`/`__kicker`/`__headline`/`__dek`/`__also` classes styled, buttons, phone stacking.
- Series strip per §6.1.7: `layout=strip` attribute, single meta line with " · ", 3-column grid, mark colours.
- ≤ 1024 and ≤ 720 behaviour for all of the above (`02 §A Responsive`).
- Un-fixme rows: `row-*`, `cell-*`, `tech-*`, `writing-*`, `strip-*`; allow-list < 10.
- **Visible result:** section rows match `design_blocks.png`. Manual check: `section-rows.png` and `series-strip.png`.

### Phase 4 — Phone pass, a11y, close-out

- Full `3a` pass at 390 per §6.1.9; ≤ 1024 pass; `a11y` and `network` rows; every `fixme` removed; `npm run test:e2e` green in CI (the CI job already exists — make sure it seeds).
- Inner templates smoke: `/about/`, a post, `/category/security/`, `/series/`, `/writing/` still render with the shared-chrome changes (existing e2e screens + axe).
- `docs/HANDOFF.md` with every manual check; final screenshots.
- **Visible result:** CI green; `front-390.png` matches `3a`. Manual check: open `/` at 390 in a real phone browser.

## 9. Open questions

| # | Question | Decision for this flight |
|---|---|---|
| R1 | Which nav item is current on the front page? The mock shows Technology red. | `nav.front_current = 'lead'`: the lead post's primary section. `'none'` available. |
| R2 | Where does the NIV copyright go? | Footer (§6.4), 12px neutral-700, under the meta line. Box and none are config options. |
| R3 | Jetpack form without a connection | Widget-POST contract (§6.3); dev uses `custom-url` accept-locally. Spike bounded to one task; if the field list differs from §6.3, the spike's record wins and the task updates this section in its commit. |
| R4 | "All 87 entries" vs "11 →" | `format: 'entries'` on the rail; cells keep "N articles →"/"N →". |
| R5 | Series strip meta layout | Single line on the front (`layout=strip`); hub keeps the two-column form. |
| R6 | CSS budget | 40 KB (rule 30 amendment). If Phase 3 exceeds it, the planner's tuning task measures and raises once more with the number recorded. |
| R7 | Tagline copy | The mock's, set by the seed; the owner changes it in Settings → General on the live site. |
| R8 | Read time in cells | Only on the Technology featured item ("9 min"); other cells date only, as the mock. |
| R9 | Journal entries in the mock have no read time and the rail count is 3 | Unchanged from phase 1. |

## Appendix A — Files in `docs/`

| File | What it is |
|---|---|
| `SPEC.md` | This file. |
| `feedback/design_top.png`, `design_blocks.png`, `design_footer.png` | The mock (`2a`) as rendered by Claude Design, in three crops: masthead + lead row, section rows + series strip, poster + footer. **Target.** |
| `feedback/preview_top.png`, `preview_blocks.png`, `preview_footer.png` | The phase 1 result on `localhost:8888`, same crops. **Wrong wherever it differs.** |
| `feedback/phase-2/` | Screenshots this flight produces (§6.6). |
| `Eric Mann Newspaper.dc.html` (+ `image-slot.js`, `support.js`) | Interactive prototype; the `id="2a"` and `id="3a"` cards are normative for values not in `01`/`02`. |
| `README.md`, `01`–`07` | Design handoff; normative for visuals and fallbacks. |
| `_ds/…/styles.css` | Token sheet (hex/px). |
| `phase-1/` | Archived phase 1 spec, plan, progress, reviews, handoff, summary, CLAUDE.md, foundry.json. History only. |
| `SETUP.md`, `MIGRATION.md`, `DEPLOYMENT.md` | Operational docs; `SETUP.md` gains the stale-mount note. |
| `fixtures/` | `verse-sample.json`, `classic-sample.html`, `seed/` (rewritten in Phase 0), `jetpack-subscriptions.html` (captured in Phase 1). |
| `spikes/` | Phase 1 spikes plus `P1-jetpack-form.md` from this flight. |
