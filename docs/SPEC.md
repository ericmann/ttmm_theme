# These Things Matter — Specification, phase 3 (inner-template fidelity)

Version: 3.0
Status: ready for flight (refinement pass on branch `refine/*`, base `main`)

The Foundry planner reads this file in full and derives the build plan from it. Phase 1 (`docs/phase-1/`) built the plugin and theme; phase 2 (`docs/phase-2/`) made the front page match mock `2a`/`3a` and merged as PR #11. The owner then previewed the article, the journal post and the Writing page and found them nowhere near the mocks (`docs/feedback/design_article.png` vs `preview_article.png`, `design_journal.png` vs `preview_journal.png`, `design_serial.png` vs `preview_serial.png`), and the section archives no better. **This flight makes every remaining template match its mock exactly**, the same way phase 2 did for the front page: an element-by-element contract per screen, a computed-style fidelity table asserted by Playwright, screenshots committed per phase, and a CSS-coverage lint with **no** allow-list left.

Precedence: this file wins on engineering; `docs/01-design-language.md`, `docs/02-screens.md §B–§H`, `docs/06-fallbacks.md` and the mock cards in `docs/Eric Mann Newspaper.dc.html` (`id="2b"` lines 329–414, `id="2c"` 415–461, `id="2d"` 462–544, `id="1e"` 917–975, `id="1f"` 976–1030, `id="3b"` 118–163) win on visuals. `docs/phase-1/` and `docs/phase-2/` are history. Numbers marked `⚠️ ASSUMPTION` carry a config key and must never be hard-coded anywhere else.

---

## 1. Overview

A refinement flight on a working codebase. Phase 2's approach is repeated for the inner screens: article (`2b`/`3b`), journal post (`2c`), Writing (`2d`), section archive (`1e`), journal archive, series hub and single series (`1f`), and the "not drawn" screens built from the system (`02 §H`: tag/date archive, search, 404, static page). The diagnosis, verified against the rendered HTML on 2026-09-21:

- **No page container.** `theme.json` `contentSize` is 1280px but only constrained groups honour it; every inner template's top-level grid group uses `layout: default` (correctly, per phase 2 rule 36), so at 1870px the page spans the whole viewport. `.wp-site-blocks` needs a max-width.
- **Inner masthead nav is broken at desktop.** `core/navigation` with `overlayMenu: "always"` renders the overlay container inline: items with no gap, and the overlay's "Close" button visible next to "Series".
- **Inner components have no working CSS.** Thirty-two `ttm-*` classes render with no rule (series bar, series TOC, series featured, progress, stats, hub head, serial hero, book grid, category stats, filter row, archive year rows, journal stream, syndication), hidden from the coverage lint by four glob lines in `scripts/css-coverage-allow.txt` marked "later flight". Where a section header exists in `ttm.css` (4.22 article head, 4.25 archive head, 4.38 series featured…), its selectors do not match the classes the markup emits.
- **Constrained wrappers inside grids.** Article header, journal columns, archive header columns and the hub header are `layout: constrained` groups inside `layout: default` grids, so core's `max-width`/margin rules fight the grid.
- **Seed images are solid red** (`Seeder` fills 210,48,19), so every colour hero is a red slab instead of a photograph placeholder.
- `theme.json` slug `h2` yields `--wp--preset--font-size--h-2`; two rules reference `--font-size--h2` and silently fail (phase 2 spec issue 8).

"Done": the seeded pages `/signing-your-options-table/`, `/journal-post-1/`, `/writing/`, `/category/security/`, `/category/journal/`, `/series/`, `/series/hardening-wordpress/`, `/tag/wordpress/`, `/?s=ledger`, `/about/` and a 404 pass every row of §6.9 at 1280, 1920 and 390 in Playwright; `docs/feedback/phase-3/*.png` are committed; `scripts/css-coverage-allow.txt` is empty; phase 1 and 2 suites still pass.

## 2. Goals and non-goals

Goals:

- Goal: Each screen in §6.1–§6.8 matches its mock at 1280 and its phone rule at 390, and is bounded to the 1280 container at any wider viewport.
- Goal: The inner masthead renders the nav inline with the mock's gap at ≥ 721px and as the overlay at ≤ 720px, with no overlay chrome visible when closed.
- Goal: Every `ttm-*` class any markup emits has a rule, every `ttm-*` selector in `ttm.css` matches at least one element on the seeded screen set, and the allow-list is empty.
- Goal: Seed images look like photographs at a glance (neutral, not red), covers look like covers, and the seed carries the mocks' copy for the article, journal post, Writing page, Security archive and series hub.
- Goal: Every phase 2 spec issue (`docs/phase-2/SUMMARY.md §Spec issues`) is folded in here so the planner and reviewer read one consistent document; §6.3 of this file is the corrected newsletter contract.
- Goal: Screenshots per phase under `docs/feedback/phase-3/`.

Non-goals (become `**Out of scope:**` lines on tasks):

- Non-goal: Changes to the content model, taxonomies, meta, REST, CLI, migration, cache or verse fetching, except the seed and the block `render.php` markup/classes named in §6.
- Non-goal: Front-page changes beyond what shared chrome forces (the container and nav fixes must not move a single front-page fidelity row; the phase 2 spec at `docs/phase-2/SPEC.md §6.2` stays green and its rows stay in `tests/e2e/fidelity.spec.mjs`).
- Non-goal: New blocks, dark mode, comments, analytics, search ranking, newsletter archive, production deploy, Jetpack connection in wp-env.
- Non-goal: Dependabot PRs #5 and #10 (parked by the owner).

## 3. Engineering principles

Phase 1 rules 1–33 (`docs/phase-1/SPEC.md §3`) and phase 2 rules 34–40 (`docs/phase-2/SPEC.md §3.2`) remain in force with the amendments below; the planner copies the whole amended set into `CLAUDE.md` `## Constraints`.

### 3.1 Amendments

- Rule 15: serialization is schema-validated arrays (phase 2 SI-1).
- Rule 24: tolerated literals are HTTP status codes, structural arithmetic, array indices and `Config::get()` fallbacks equal to `defaults()` (tested); CSS/theme.json values and e2e fixture values are not tunables.
- Rule 30: `cssBudgetBytes` = `61440` ⚠️ ASSUMPTION (phase 2 ended at 42 980 of 43 008; this flight styles nine more screens). The number lives only in `scripts/check-budget.mjs` and `CLAUDE.md`; the planner schedules one tuning task in the last phase that records the final size.
- Rule 34 (CSS coverage): allow-list lines may be globs, **and the allow-list must be empty when the flight ends**. Phase 0 removes the four phase-2 glob lines so the lint fails loudly until each phase lands its component; a task may add a single-class line with `# P<n>-<nn> pending` only for a class its own phase will style, and the phase's push task removes it.
- Rule 36 (grids not constrained): extended — **no `layout: constrained` group anywhere inside a `layout: default` grid group**; column children are `layout: default` (or `flow`) and `ttm.css` owns their width. Grep `is-style-grid` in `themes/ttm-theme/**` and check every descendant block comment.
- Rule 38 (fidelity is a test): rows for all screens live in `tests/e2e/fidelity.spec.mjs` grouped by screen; viewports 1280, 390 and, for the container rows, 1920.
- §4 import table: as shipped, with `BoundariesTest::KNOWN_EXCEPTIONS` (`Cache`→`Meta`,`Query`; `Verse`/`Newsletter`/`Fiction`→`Admin\Page`) accepted (phase 2 SI-5).
- §6.7 (phase 2): the missing-build fallback registers each block with `ServerSideRender`; not a no-op (SI-6).

### 3.2 New rules

41. **Runtime selector coverage.** `tests/e2e/selectors.spec.mjs` parses every selector in `ttm.css` containing a `ttm-` or `is-style-` class, visits the seeded screen set (§1 "Done"), and fails if any selector matches zero elements across the set, unless the selector is listed in `tests/e2e/selectors-allow.txt` with a reason (`:hover`, `:focus-visible`, `[data-state]`, `.is-empty`/`.is-nocover`/`.is-textonly` fallback states and `@media` variants are exempt automatically). A CSS rule that matches nothing on the seeded site is a defect, because it means the markup and the stylesheet disagree.
42. **The page is a 1280px column.** `ttm.css` sets `.wp-site-blocks { max-width: 1280px; margin-inline: auto; }` with the gutter as inline padding (`--wp--custom--gutter--desktop`, 20px at ≤ 720). Fidelity rows `container-*` assert it at 1920 and 390. Full-bleed elements (the poster, phone hero and code blocks) break out with negative margins equal to the gutter, never by escaping the container.
43. **Navigation overlay is phone-only.** The inner masthead's `core/navigation` uses `overlayMenu: "mobile"`; `ttm.css` sets the breakpoint to 720px (core's is 600px: override `.wp-block-navigation__responsive-container` visibility between 600 and 720). At ≥ 721px the open/close buttons are `display: none` and the list is inline; at ≤ 720 only "Menu" (text, no icon) is visible until opened. Rows `nav-*`.
44. **Slugs are the variables.** Every `theme.json` preset slug is used via its generated custom property; `scripts/check-theme-json.mjs` additionally greps `ttm.css` for `--wp--preset--font-size--` and `--wp--preset--color--` references and fails on any that no slug generates (catches the `h2`/`h-2` bug). Rename the `h2` slug to `h-2`? No — rename it to `article-h2` and reference that.
45. **Seed images are photograph placeholders.** `Seeder` generates PNGs as a neutral field (`#bab6b6` neutral-400) with a darker diagonal band and a 2px lighter border, deterministic per label; covers use a 2:3 field in `#605d5d` with the title drawn in the centre. No red. `grep -n '210, 48, 19' plugins/ttm-core/src/Cli/Seeder.php` returns nothing.
46. **Block wrappers keep phase 1's shape** (`div.ttm-<name>[data-ttm-block]` via `Helpers::wrapper()`); this flight may add element classes inside a block and may rename an inner class, but every rename updates the markup, `ttm.css`, the coverage lint and the fidelity table in the same commit.

## 4. Architecture

Unchanged from `docs/phase-2/SPEC.md §4` with the KNOWN_EXCEPTIONS accepted (§3.1). Theme layout unchanged. `ttm.css` keeps one comment header per `01 §4` component; this flight fills in 4.2, 4.3, 4.11–4.13, 4.18–4.29 and 4.35–4.38 and adds `/* 0.2 page container */`.

## 5. Data and configuration

No new options, meta or transients. Config keys (all exist unless marked new):

```text
article.more_in_section     = 3
archive.per_page            = 12
archive.tag_filter_limit    = 5
archive.row_tags            = 2
archive.most_read_limit     = 3
journal.stream_count        = 4
journal.archive_per_page    = 20
series.hub_featured_parts   = 12
series.related_limit        = 4      (new; "Other series" on a single series page)
writing.chapters_recent     = 4
writing.story_tiles         = 4
writing.tile_columns        = 2
reading.words_per_minute    = 230
seed.image_band_angle       = 30     ⚠️ ASSUMPTION (new; degrees, only the seeder reads it)
```

`cssBudgetBytes` per rule 30. Seed content changes are §6.10.

## 6. Interfaces

Measurements are the mocks' inline values. Colour names are `theme.json` presets. "Rule" = `core/separator.is-style-rule-N` or a border using `--ttm-rule-N`. Gutter 48 at ≥ 721, 20 at ≤ 720. Unless a section says otherwise, ≤ 1024 and ≤ 720 behaviour is exactly `02-screens.md` §B–§F "Responsive".

### 6.1 Shared chrome (every inner screen)

#### 6.1.0 Page container (rule 42)

`.wp-site-blocks`: `max-width: 1280px; margin-inline: auto; padding-inline: var(--wp--custom--gutter--desktop)`; `box-sizing: border-box` so the content column is 1184px at 1280 (the mock's `padding: 0 48px`). Front page unchanged in appearance at 1280 (its rows already sit inside the same padding); at 1920 the front page is also centred at 1280. `theme.json` keeps `useRootPaddingAwareAlignments: true` and root padding **only if** it does not double the gutter; otherwise root padding is removed and the container owns it. One or the other, tested by `container-gutter`.

#### 6.1.1 Masthead — inner (`.ttm-masthead-inner`, pattern `ttm/masthead-inner`)

One row under a **2px rule**: grid `auto 1fr auto`, align center, gap 32, padding 14 0 12. Left: Site Title 22px/800/−0.02em + "by Eric Mann" 12px neutral-700, baseline-aligned, gap 10, in one `<a>` to `/`. Middle: nav (rule 43) flex, gap 22, 13px/600, ink; current section accent (the post's primary category on singles, the queried term on archives, "Journal" on journal screens, "Writing" on `/writing/` and `/category/writing/`, "Series" on `/series/` and series archives); "Series" hub item `ttm-nav__hub` 400/neutral-700 (accent when current). Right: "Newsletter" 12px neutral-700 → `/newsletter/`. No "Close", no "Menu", no icon at ≥ 721.

Phone (`3b`): flex space-between baseline, padding 12 0 10, 2px rule below; title 18px; right item "Menu" 11px neutral-700 opens the overlay (full-screen, ground background, items 24px/800 stacked with 1px rules, "×" 24px top-right).

#### 6.1.2 Footer — inner variant

The phase 2 footer with a **2px rule above** and padding 14 48 (`is-after-poster` absent): left "These Things Matter · © {year} Eric Mann · Built on WordPress" (the mock omits "Built on WordPress" on inner pages; keep it — one string everywhere) plus the verse copyright line when a verse is stored; right the dot-separated nav.

#### 6.1.3 Shared components

- **Cell heading, rail/aside variant** (`.ttm-cell-heading.is-rail`): as phase 2 (2px rule below, padding-bottom 8). All inner asides use it.
- **Numbered rows** (`.ttm-numbered`, 01 §4.29): grid `24px 1fr` (chapters `44px 1fr auto`), gap 8–16, padding 10–13 0, 1px rules; number 800 neutral-500 tnum.
- **Series row** (`.ttm-series-row`, 01 §4.17) gets its `list` layout (two-column right cell: count over status) styled here; `strip` layout is phase 2's.
- **Buttons/tags/inputs**: phase 1 classes `.btn.btn-primary|secondary|ghost`, `.tag.tag-neutral|accent`, `.input` (01 §4.30–4.32); verify each is styled (rule 41 will catch `tag-accent` if the seed has no filtered archive — the seed includes `?tag=` in the screen set).

### 6.2 Article (`single.html`, mock `2b`/`3b`)

Order: inner masthead · series bar · body row · footer.

- **Series bar** (`ttm/series-bar` → `.ttm-series-bar`): grid `10px 1fr auto`, gap 14, align center, padding 12 0, 1px rule below, 13px. `.ttm-series-mark` 10×10 accent; text "Series" neutral-700 · series name 600 (link to `/series/{slug}/`) · "Part 3 of 6" tnum; right: `.ttm-series-bar__segments` flex gap 4 of `__seg` 22×4 (neutral-900 before, accent current, neutral-300 after; F23 open-ended: published parts dark + one neutral-300) then "View series" 12px accent-700 margin-left 14. Phone: drop "Series" and "View series", segments 12×4 gap 3, 12px. F11: block returns `''`.
- **Body row** (`.ttm-article`): grid `minmax(0,8fr) minmax(0,4fr)`, gap 0 64, padding 40 0 48; children `layout: default`.
- **Article header** (pattern `ttm/article-header` → `.ttm-article-head`): kicker (`core/post-terms` category, `.is-style-kicker`) 12px/600/uppercase/.08em accent-700 margin 0 0 14, terms joined " · " (no separator markup from core: set `separator: " · "`); H1 `h1` preset 56px/1.02/−0.025em/800, margin 0 0 18 −0.03em, `max-width: 18ch`; dek (`core/post-excerpt`, `.is-style-dek-l`) 21px/1.4 neutral-800 `max-width: 32em` margin 0 0 22, inline `code` 18px surface padding 1 6; **byline row** (`.ttm-byline`) flex gap 18 align center, padding 14 0, **2px rule above, 1px below**, 13px neutral-700 tnum: "By **Eric Mann**" (600 ink, `core/post-author-name` with prefix "By "), `F j, Y`, "14 min read"; right (`margin-left:auto`) tags as `.tag.tag-neutral` gap 6 (`core/post-terms` tag with `.is-style-tags`). No excerpt → no dek, byline 18px under H1; no tags → right side empty.
- **Hero** (`core/post-featured-image`): margin-top 28, 16:9, **colour** (no grayscale filter on singles), caption 12px neutral-700 8px below. F12: omitted, byline → body gap 28.
- **Body** (`core/post-content.ttm-entry` + `.entry-content` rules, 01 §4.24): 18/1.65, `max-width: 38em`, padding-top 28; paragraphs margin 0 0 22; H2 30px/1.1/−0.02em margin 40 0 14 (preset `article-h2`, rule 44); inline code 16px surface padding 1 6; `pre` neutral-900 bg neutral-100 text 14/1.55 mono padding 18 20 4px accent left rule; pull quote (`is-style-pull`) 28/800/1.25/−0.015em, `text-indent: -0.496em`, margins 36, `max-width: 26ch`; links underline divider-coloured offset 3. Classic HTML posts get the same rules.
- **Prev/next** (`ttm/series-prev-next` → `.ttm-prevnext`): grid `1fr 1fr`, 2px rule above, margin-top 40; left cell padding 20 24 20 0 with 1px right rule, right cell padding 20 0 20 24; label 11px/uppercase/.08em neutral-700 margin-bottom 8 ("← Part 2" / "Part 4 →"; F11 "← Previously in Technology" / "Next →"); title 18px/800/1.25. Missing side keeps the cell and rules. Phone: stacked, 1px rule between, 16px titles.
- **Aside** (`.is-style-sticky-aside`, sticky top 24 at ≥ 1024, flex column gap 28): **In this series** (`ttm/series-toc` → `.ttm-series-toc`): heading `.is-rail` with the series name right (11px neutral-600); items (`__item`) grid `28px 1fr`, gap 10, padding 10 0, 1px rules, 14px/1.35; number 800 neutral-600 tnum "01"; current part 800 accent-700; published 400 ink linked; unpublished 400 neutral-700 unlinked with `title="Scheduled Sept 26"` (F24). Phone: heading gains "Hub →" 11px accent-700 right. **More in {Category}** (pattern `ttm/more-in-section` → `.ttm-more-in`): heading `.is-rail` "More in Technology" as **one label** (the category name inline, not a second label on the right); rows 14px/600/1.35 padding 10 0 with 1px rules, 3 posts, same primary category, excluding current; F13 empty → section omitted. **Newsletter box** (pattern `ttm/newsletter-box` → `.ttm-newsletter-box`): surface, padding 16 18; title 15px/800 "Get the next part" in series contexts / "The weekly issue." otherwise (the pattern reads `ttm-in-series` body class via CSS-only swap of two paragraphs, or the block binding `ttm/newsletter-copy` — planner's call, recorded); copy 13px neutral-800; form flex gap 6 with `.input` + `.btn.btn-primary` "Subscribe" (§6.3). Phone: stacked, `.btn-block`.
- ≤ 1024: single column; aside sections become full-width zones after prev/next in the order TOC, More in, Newsletter; no sticky. ≤ 720 per `3b`: kicker 11, H1 34, dek 17, byline wraps (gap 6 14, 12px), hero full-bleed 4:3 (`margin: 20px -20px 0; width: calc(100% + 40px)`), body 17/1.6, H2 24, `pre` full-bleed with 20px inner padding, pull 22, TOC zone under a 2px rule, newsletter box margin-bottom 24, footer 11px.

### 6.3 Newsletter form (corrected contract, replaces phase 2 §6.3)

Markup as phase 2 (one `form.ttm-newsletter-form__form` with `input.input[type=email]` + `button.btn`). All providers except `none` post to `admin-post.php?action=ttm_subscribe` with the token, honeypot and `redirect_to`. `Handler` dispatches by provider: `custom-url` forwards to `newsletter.endpoint` (empty endpoint + `newsletter.dev_accept` outside production = accept); `jetpack` calls `Jetpack_Subscriptions::init()->subscribe( $email, 0, false )` when the class exists, else falls through; `mailto` renders the phase 1 link only when it is the configured provider; `none` = statement. Success redirects to `redirect_to` with `?subscribed=1`. Box placement uses `.btn-primary`; poster placement `.btn-ghost`. Nothing else changes.

### 6.4 Journal post (`single-journal.html`, mock `2c`)

- **Header + body** (`.ttm-journal-head`): grid `minmax(0,3fr) minmax(0,7fr) minmax(0,2fr)`, gap 0 48, padding 40 0 48; children `layout: default`.
- Col 1: kicker "Journal" 12px accent-700 margin 0 0 6; date `journal-date` 48px/1/−0.025em/800 tnum margin 0 0 0 −0.03em via `ttm/short-date` ("Sept 20"); sub-line 14px neutral-700 margin 6 0 0 via `ttm/journal-subline` ("Sunday · Portland"; no location → weekday only).
- Col 2 (`<main>`, `max-width: 36em`): H1 `journal-title` 30px/1.12/−0.015em/800 margin 0 0 18; body 18/1.65 paragraphs margin 0 0 18 (last 0); **syndication line** (`ttm/syndicated-to` → `.ttm-syndication`): flex gap 20 align center, margin-top 28, padding-top 14, 1px rule above, 13px neutral-700: "Syndicated to X and Mastodon" (network names accent-700 links) + `__words` "248 words" `margin-left:auto`. F14: no line; word count moves to col 3.
- Col 3: standing note 12px/1.5 neutral-700 ("Journal entries are short and unpolished — things I saw and what they made me think. Longer arguments land in a section.", editable in the template) margin 0 0 8; "Journal RSS" 12px accent-700 → `/category/journal/feed/`; word count (`.ttm-journal-head__count`) only when F14 applies. **The current template's stray paragraphs (word count always shown, note text differing from the mock) are replaced.**
- **2px rule**, then **stream** (pattern `ttm/journal-stream` → `.ttm-journal-stream`): padding 24 0 40; heading (no rule) "Earlier" / right "Full journal · 87 entries" 12px neutral-700 (`ttm/category-count format=journal-full` → "Full journal · {N} entries"); rows (`.ttm-journal-row`, whole row a link): grid `3fr 7fr 2fr` gap 0 48, padding 18 0, 1px rule above, baseline; date 20px/800/−0.015em tnum (`ttm/short-date`); title 18px/800/1.25 + excerpt 15px/1.5 neutral-800 margin-top 4 `max-width: 40em`; word count 12px neutral-600 `justify-self: end`. 4 rows, exclude current; 0 → zone omitted.
- ≤ 1024: header stacks (date block, body, note as a 1px-ruled footnote); stream rows `auto 1fr`, date 16px/800, no word count. ≤ 720: date 36px, title 26px, body 17/1.6.

### 6.5 Writing (`page-writing.html`, mock `2d`)

- **Hero** (`ttm/serial-hero` → `.ttm-serial-hero`): grid `280px minmax(0,1fr)`, gap 0 64, padding 40 0, align end (320px at ≥ 1200 when a cover exists). Left `.ttm-cover` 2:3 colour with `--shadow-lg` (`box-shadow: 0 12px 32px rgba(45,43,43,.22)`), the only shadow on the site. Right (`__body`): kicker (`__kicker`) 12px accent-700 "Writing · Serial in progress" margin 0 0 12; title (`__title`) `display-m` 64px/.98/−0.03em margin 0 0 16 −0.04em `max-width: 14ch`; synopsis (`__synopsis`) 19px/1.45 neutral-800 `max-width: 48ch` margin 0 0 20 (**term description; the current render omits it**); buttons (`__buttons`) flex gap 10 margin-bottom 22: `.btn-primary` "Read chapter 1", `.btn-secondary` "Latest: chapter 12", `.btn-ghost` "Follow by email" → `#newsletter`; stat row (`.ttm-stats`, 01 §4.37): grid `repeat(3,auto)`, gap 0 40, justify start, 2px rule above, padding-top 14, 13px neutral-700 tnum; values (`__value`) 20px/800/−0.01em ink on their own line: "12 / 31" + "chapters published", "Monthly" (cadence, capitalised) + "next: Oct 17", "~14 min" + "per chapter" (average reading time of published chapters). F3/F21 as phase 1.
- **2px rule.** **Body** (`.ttm-writing-body`): grid `minmax(0,7fr) minmax(0,5fr)`, gap 0 64, padding 28 0 48. Left column flex gap 36: **All serials** heading `.is-rail` / "Newest activity first" 12px neutral-700; `ttm/series-list form=fiction layout=list`: rows grid `10px 1fr auto`, gap 0 14, padding 16 0, 1px rule below, align start; mark; title 20px/800/1.15/−0.01em; dek 14px/1.45 neutral-800 `max-width: 46ch` margin-top 4; form line 12px neutral-700 margin-top 6 ("Novel · literary thriller · monthly" = form · genre · cadence, omitting empties); right cell 12px neutral-700 tnum right-aligned: "12 of 31" / "9 chapters" over status 600 (accent-700 in progress / neutral-500 complete / neutral-600 hiatus). **Recent chapters** heading `.is-rail` "The Quiet Ledger — recent chapters" / "All 12" 12px neutral-700 → the series page; `ttm/series-toc variant=chapters` rows `.ttm-numbered` `44px 1fr auto` gap 0 16 padding 12 0 1px rules baseline: number 15px/800 neutral-500; title 17px/800/1.2 + dek 13px neutral-800 margin-top 3; date 12px neutral-700. Right column flex gap 32: **Short fiction** heading `.is-rail`; `ttm/story-tiles` grid `1fr 1fr` gap 16 padding-top 16; tile (`.ttm-tile`) surface 4:3 padding 16 flex column space-between, title 19px/800/1.1/−0.015em, meta 12px neutral-700 tnum "3,100 words · 2026"; explainer 12px neutral-700 margin-top 10 ("Stories without artwork get a typographic tile; a cover, when one exists, replaces it in the same box."). **In print** heading `.is-rail`; `ttm/book-grid` grid `repeat(2,1fr)` gap 20 padding-top 16; `.ttm-cover` 2:3 no shadow; title 14px/800/1.25 margin-top 10; meta 12px neutral-700 margin-top 2 ("Novel · 2022 · paperback, ebook"); purchase links (`__links`) as `.btn-ghost` under the meta only when present. F20 as phase 1.
- ≤ 1024: hero cover 200px; body single column in order All serials, Short fiction, Recent chapters, In print. ≤ 720: hero stacks (cover 160px), title 40px, buttons stack `.btn-block`, stats 3-across at 16px values; tiles 2-col min 148px.

### 6.6 Section archive (`category.html`, mock `1e`) and siblings

- **Archive header** (pattern `ttm/archive-header` → `.ttm-archive-head`): grid `8fr 4fr` gap 0 64, padding 40 0 28, align end; children `layout: default`. Left: kicker "Section" 12px accent-700 margin 0 0 10; H1 (`core/query-title`) `display-xl` 80px/.95/−0.03em margin 0 0 14 −0.058em; description (`core/term-description`) 17px/1.5 neutral-800 `max-width: 52ch`. Right (`ttm/category-stats` → `.ttm-category-stats`): 13px/1.6 neutral-700 tnum: "36 articles · 2014–2026" / "4 series touch this section" / "Security RSS" accent-700 link. Phone: stacks, H1 44px.
- **Filter row** (`ttm/tag-filter` → `.ttm-filter-row`; the theme's `.ttm-filter-row-wrap` group is removed — the block is placed directly): flex gap 6 align center, padding 12 0, **2px rule above, 1px below**, 12px; "Filter" neutral-700 margin-right 8; `.tag.tag-accent` "All" (active when no `?tag=`), top 5 tags `.tag.tag-neutral` linking `?tag=`; right (`__sort`) "Newest first" neutral-700 `margin-left:auto`. F16: block returns `''` and the 2px rule above the list stays (the template adds a separator that is hidden by `:has()` when the filter row renders — or the list group carries the rule; planner's call).
- **Body**: grid `8fr 4fr` gap 0 64, padding 8 0 48. Left (`ttm/archive-by-year` → `.ttm-archive`): year group (`.ttm-archive-year`) grid `120px 1fr` gap 0 24, padding 24 0 8, **2px rule above**; year label 32px/800/−0.02em tnum line-height 1; rows (`.ttm-archive-row`, whole row a link) grid `72px 1fr` gap 0 20, padding 14 0, 1px rule below; date 12px neutral-700 tnum padding-top 4 ("Sept 10"); title 20px/800/1.2; dek 14px/1.45 neutral-800 `max-width: 56ch` margin-top 5; meta 12px neutral-700 margin-top 6 ("11 min · wordpress · php" = read time + up to `archive.row_tags` tags, or "9 min · Series: Reading CVEs, 4 of 4"). F15 as is. **Pagination** (`core/query-pagination`): flex space-between, padding 20 0, 13px/600; "← Newer" / "Older (2014–2022) →" via `ttm/pagination-label`, disabled side neutral-500 not a link, **no page numbers** (remove `query-pagination-numbers`). Right aside (padding-top 24, flex gap 28): **Series in {Section}** heading `.is-rail` as one label ("Series in Security"); `ttm/series-list inCategory layout=rail`: rows grid `10px 1fr` gap 12 padding 12 0 1px rules; mark; title 15px/800/1.25; meta 12px neutral-700 margin-top 3. **Most read** (`ttm/most-read` → `.ttm-most-read`): heading `.is-rail`; rows `.ttm-numbered` `24px 1fr` gap 8 padding 10 0 1px rules 14px/600/1.35, number 800 neutral-500.
- ≤ 1024: aside below the list as `1fr 1fr`, then single at 720; header stacks. ≤ 720: H1 44, year label 24px as a 2px-ruled heading above rows, rows `auto 1fr` date 12px, filter row horizontal scroll.
- **Journal archive** (`category-journal.html`): archive header with kicker "Section", H1 "Journal", description, stats; then the stream rows of §6.4 full width (`journal.archive_per_page` = 20) with pagination as above. No filter row, no aside.
- **Tag / date archive** (`archive.html`): the `1e` layout with kicker "Tag" / "Month" (`core/query-title` prefix-less; the kicker text from `ttm/archive-kind` binding or a static pattern per template — planner's call), no description unless the term has one, no filter row, aside = Most read only. Rows as §6.6. Pagination as above.
- **Search** (`search.html`): `1e` header with H1 "Search" and the query as the description line ("Results for “ledger”" 17px neutral-800); `core/search` under the header styled `.input` + `.btn-secondary` (flex gap 6, input 320px); rows as archive rows plus the matched section as a kicker line (12px accent-700) above the title; no aside; pagination as above. Zero results: description "Nothing matched “{q}”." and the search form only.
- **404**: H1 "Not here." `display-xl`, paragraph 17px neutral-800, `core/search` as above, then the front page's series strip (phase 2 markup) and a "Latest" list of 4 (`.ttm-numbered`-style rows 17px/800 with date 12px) under a `.is-rail` heading.
- **Static page** (`page.html`): grid `8fr 4fr` gap 64 padding 40 0 48; H1 `h1` 56px margin 0 0 18; body `.entry-content` 18/1.65 `max-width: 38em`; empty aside. `/about/` may carry a colour 3:2 image above the H1.

### 6.7 Series hub (`page-series.html`, mock `1f`) and single series (`taxonomy-series.html`)

- **Header** (`.ttm-hub-head`): grid `8fr 4fr` gap 0 64, padding 40 0 28, align end; children `layout: default`. Left: H1 "Series" `display-xl` 80px margin 0 0 14 −0.058em; description 17px/1.5 neutral-800 `max-width: 52ch` ("Longer arguments, split across posts and sometimes across sections. Each series has an order, a status, and a single page that holds it together." — static, editable). Right (`ttm/series-stats` → `.ttm-series-stats`): 13px/1.6 neutral-700 tnum "11 series · 3 in progress" / "Spanning Technology, Security, Faith, Business, Writing" (categories in nav order).
- **2px rule.** **Featured** (`ttm/series-featured` → `.ttm-series-featured`): grid `minmax(0,5fr) minmax(0,7fr)`, gap 0 64, padding 28 0 36. Left (`__main`): kicker (`__kicker`) 12px accent-700 "In progress · Technology · Security" margin 0 0 10; title (`__title`) `featured` 40px/1.02/−0.02em margin 0 0 12; dek (`__dek`) 16px/1.5 neutral-800 margin 0 0 16; progress (`ttm/series-progress` → `.ttm-series-progress`): flex gap 4 of `flex:1; height:6px` segments (neutral-900 published, neutral-300 not) margin-bottom 8; meta (`__meta`) 12px neutral-700 tnum "3 of 6 published · next part Sept 26" margin 0 0 18; buttons (`__buttons`) flex gap 10 `.btn-primary` "Start at part 1", `.btn-secondary` "Follow this series" → `#newsletter`. Right (`__parts`, an `<ol>`): 2px rule above; rows (`__part`) grid `40px 1fr auto` gap 16 padding 12 0 1px rules baseline 16px/1.3: number (`__num`) 800 neutral-500 tnum; title 800 ink for published (link), 400 neutral-700 unlinked for scheduled; date (`__date`) 12px neutral-700 ("Sept 19" or "Sept 26" scheduled). Up to `series.hub_featured_parts` then "All N →" (`__all`) 13px accent-700. F5 complete-series variant per phase 1.
- **2px rule.** **All series** (`.ttm-hub-all`): heading (no rule) "All series" / "Sorted by last update" 12px neutral-700 margin-bottom 12; `ttm/series-list layout=grid-2`: grid `1fr 1fr` gap 0 48; rows grid `10px 1fr auto` gap 0 14 padding 18 0 **1px rule above** align start; mark margin-top 6; title 20px/800/1.15/−0.01em; dek 14px/1.45 neutral-800 `max-width: 44ch` margin-top 5; categories line 12px neutral-700 margin-top 8 ("Technology · Security"); right cell count over status as §6.5. No newsletter box on the hub (the current template has one; remove it).
- **Single series** (`taxonomy-series.html` → `.ttm-series-single`): header = the featured block's left column full width (kicker "{status} · {categories}", H1 series name `display-xl` `max-width: 16ch`, dek, progress + meta, buttons) ; 2px rule; full-width ordered part list rows `40px 1fr auto` at 18px with a 14px dek line per part (chapter/part excerpt); 2px rule; "Other series" heading `.is-rail` + `ttm/series-list layout=list limit=series.related_limit excludeCurrent`; footer. Current section in the nav = "Series".
- ≤ 1024: featured stacks; all-series single column. ≤ 720: H1 44; part rows `28px 1fr` with the date on a second line.

### 6.8 Phone and wide

Every screen above at 390 per its `02` "Responsive" bullet and `3b`; every screen at 1920 centred in the 1280 container (rows `container-*`).

### 6.9 Fidelity table (asserted by `tests/e2e/fidelity.spec.mjs`, grouped by screen)

Selectors are the ones markup emits after this flight (rule 46). Pixel tolerance ±0.5; colours as `rgb()`. "1280" rows also run at 1920 for anything in the `container` group. Seeded URLs as §1.

| id | screen | selector | vp | property | expected |
|---|---|---|---|---|---|
| container-width | any | `.wp-site-blocks` | 1920 | width / margin-left | 1280 / (1920−1280)/2 |
| container-gutter | any | `.wp-site-blocks` | 1280 | padding-left / content box | 48px / 1184px |
| container-phone | any | `.wp-site-blocks` | 390 | padding-left | 20px |
| container-front | `/` | `.ttm-lead-row` | 1920 | width | 1184px |
| nav-inline | article | `.ttm-masthead-inner__nav ul` | 1280 | display / column-gap | flex / 22px |
| nav-item | article | `.ttm-masthead-inner__nav a` (first) | 1280 | font-size / font-weight | 13px / 600 |
| nav-nochrome | article | `.wp-block-navigation__responsive-container-open, .wp-block-navigation__responsive-container-close` | 1280 | visible count | 0 |
| nav-current | article | `.ttm-masthead-inner__nav .current-menu-item > a` | 1280 | color / text | accent / "Technology" |
| nav-hub | article | `.ttm-masthead-inner__nav .ttm-nav__hub > a` | 1280 | font-weight / color | 400 / neutral-700 |
| nav-hub-current | `/series/` | `.ttm-masthead-inner__nav .ttm-nav__hub > a` | 1280 | color | accent |
| nav-phone-menu | article | `.wp-block-navigation__responsive-container-open` | 390 | visible / text | true / "Menu" |
| nav-phone-hidden | article | `.ttm-masthead-inner__nav ul` | 390 | visible (closed) | false |
| mast-inner | article | `.ttm-masthead-inner` | 1280 | grid-template-columns / border-bottom-width / padding | auto 1fr auto (3 tracks) / 2px / 14px 0 12px |
| mast-inner-title | article | `.ttm-masthead-inner .wp-block-site-title` | 1280 | font-size / font-weight | 22px / 800 |
| mast-inner-by | article | `.ttm-masthead-inner__by` | 1280 | font-size / color | 12px / neutral-700 |
| mast-inner-phone | article | `.ttm-masthead-inner .wp-block-site-title` | 390 | font-size | 18px |
| footer-inner-rule | article | `.ttm-footer` | 1280 | border-top-width / padding | 2px / 14px 48px |
| bar | article | `.ttm-series-bar` | 1280 | grid-template-columns / border-bottom-width / padding | 10px + 2 / 1px / 12px 0 |
| bar-mark | article | `.ttm-series-bar .ttm-series-mark` | 1280 | width / background-color | 10px / accent |
| bar-name | article | `.ttm-series-bar__name` | 1280 | font-weight / font-size | 600 / 13px |
| bar-seg | article | `.ttm-series-bar__seg` (3rd) | 1280 | width / height / background-color | 22px / 4px / accent |
| bar-seg-1 | article | `.ttm-series-bar__seg` (1st) | 1280 | background-color | neutral-900 |
| bar-view | article | `.ttm-series-bar__view` | 1280 | font-size / color / margin-left | 12px / accent-700 / 14px |
| bar-phone | article | `.ttm-series-bar__seg` (1st) / `.ttm-series-bar__view` | 390 | width / count | 12px / 0 |
| art-row | article | `.ttm-article` | 1280 | grid-template-columns / column-gap / padding | 2:1 tracks / 64px / 40px 0 48px |
| art-row-layout | article | `.ttm-article > *` | 1280 | class list | none is `is-layout-constrained` |
| art-kicker | article | `.ttm-article-head .is-style-kicker` | 1280 | font-size / color / text-transform / text | 12px / accent-700 / uppercase / "Technology · Security" |
| art-h1 | article | `.ttm-article-head h1` | 1280 | font-size / line-height / max-width | 56px / 57.12px / 18ch |
| art-h1-phone | article | `.ttm-article-head h1` | 390 | font-size | 34px |
| art-dek | article | `.ttm-article-head .is-style-dek-l` | 1280 | font-size / color / max-width | 21px / neutral-800 / 32em |
| art-dek-code | article | `.ttm-article-head .is-style-dek-l code` | 1280 | font-size / background-color | 18px / surface |
| art-byline | article | `.ttm-byline` | 1280 | border-top-width / border-bottom-width / padding / font-size / color | 2px / 1px / 14px 0 / 13px / neutral-700 |
| art-byline-author | article | `.ttm-byline .wp-block-post-author-name a` | 1280 | font-weight / color | 600 / text |
| art-byline-tags | article | `.ttm-byline .tag` (first) | 1280 | font-size / background-color / bounding box | 11px / surface / right of the read-time span |
| art-hero | article | `.ttm-article .wp-block-post-featured-image` | 1280 | margin-top / aspect-ratio / filter | 28px / 16 / 9 / none |
| art-hero-phone | article | same | 390 | margin-left / width | −20px / 390px |
| art-body | article | `.ttm-entry p` (first) | 1280 | font-size / line-height / max-width (parent) | 18px / 29.7px / 38em |
| art-h2 | article | `.ttm-entry h2` (first) | 1280 | font-size / margin-top | 30px / 40px |
| art-pre | article | `.ttm-entry pre` (first) | 1280 | background-color / border-left-width / border-left-color / padding | neutral-900 / 4px / accent / 18px 20px |
| art-pull | article | `.ttm-entry .is-style-pull` | 1280 | font-size / font-weight / text-indent | 28px / 800 / ≈ −13.9px |
| art-link | article | `.ttm-entry p a` (first) | 1280 | text-decoration-line / text-underline-offset | underline / 3px |
| prevnext | article | `.ttm-prevnext` | 1280 | grid-template-columns / border-top-width / margin-top | 2 equal / 2px / 40px |
| prevnext-prev | article | `.ttm-prevnext__prev` | 1280 | border-right-width / padding | 1px / 20px 24px 20px 0px |
| prevnext-label | article | `.ttm-prevnext__label` (first) | 1280 | font-size / text-transform / text | 11px / uppercase / "← Part 2" |
| prevnext-title | article | `.ttm-prevnext__title` (first) | 1280 | font-size / font-weight | 18px / 800 |
| prevnext-phone | article | `.ttm-prevnext` | 390 | grid-template-columns | 1 track |
| aside-sticky | article | `.ttm-article aside` | 1280 | position / top / row-gap | sticky / 24px / 28px |
| toc-head | article | `.ttm-series-toc .ttm-cell-heading` | 1280 | border-bottom-width / padding-bottom | 2px / 8px |
| toc-item | article | `.ttm-series-toc__item` (first) | 1280 | grid-template-columns / padding / font-size / border-bottom-width | 28px + 1 / 10px 0 / 14px / 1px |
| toc-current | article | `.ttm-series-toc__item.is-current .ttm-series-toc__title` | 1280 | font-weight / color | 800 / accent-700 |
| toc-scheduled | article | `.ttm-series-toc__item.is-scheduled` | 1280 | color / has link / title attr | neutral-700 / no / starts "Scheduled" |
| toc-phone-hub | article | `.ttm-series-toc__hub` | 390 | visible / text | true / "Hub →" |
| more-head | article | `.ttm-more-in .ttm-cell-heading__label` | 1280 | text / count | "More in Technology" / 1 |
| more-row | article | `.ttm-more-in .ttm-item a` (first) | 1280 | font-size / font-weight / padding (parent) | 14px / 600 / 10px 0 |
| more-count | article | `.ttm-more-in .wp-block-post` | 1280 | count | 3 |
| box | article | `.ttm-newsletter-box` | 1280 | background-color / padding | surface / 16px 18px |
| box-title | article | `.ttm-newsletter-box__title` | 1280 | font-size / font-weight / text | 15px / 800 / "Get the next part" |
| box-form | article | `.ttm-newsletter-box form` | 1280 | display / column-gap | flex / 6px |
| box-btn | article | `.ttm-newsletter-box button` | 1280 | background-color / color | accent / bg |
| box-phone | article | `.ttm-newsletter-box form` | 390 | flex-direction | column |
| aside-phone-order | article | `.ttm-series-toc`, `.ttm-more-in`, `.ttm-newsletter-box` | 390 | bounding boxes | all below `.ttm-prevnext`, in that order |
| jr-grid | journal | `.ttm-journal-head` | 1280 | grid-template-columns / column-gap / padding | 3:7:2 tracks / 48px / 40px 0 48px |
| jr-kicker | journal | `.ttm-journal-head .is-style-kicker` | 1280 | font-size / color | 12px / accent-700 |
| jr-date | journal | `.is-style-journal-date` | 1280 | font-size / font-weight / line-height | 48px / 800 / 48px |
| jr-date-phone | journal | same | 390 | font-size | 36px |
| jr-sub | journal | `.ttm-journal-head__subline` | 1280 | font-size / color / text | 14px / neutral-700 / matches `/^[A-Z][a-z]+day( · .+)?$/` |
| jr-h1 | journal | `.ttm-journal-head h1` | 1280 | font-size / line-height | 30px / 33.6px |
| jr-body | journal | `.ttm-journal-head main` | 1280 | max-width | 36em |
| jr-body-p | journal | `.ttm-journal-head main .entry-content p` (first) | 1280 | font-size / margin-bottom | 18px / 18px |
| jr-synd | journal | `.ttm-syndication` | 1280 | border-top-width / padding-top / margin-top / font-size | 1px / 14px / 28px / 13px |
| jr-synd-link | journal | `.ttm-syndication a` (first) | 1280 | color | accent-700 |
| jr-synd-words | journal | `.ttm-syndication__words` | 1280 | margin-left / text | auto (right-aligned) / matches `/^\d+ words$/` |
| jr-note | journal | `.ttm-journal-head__note` | 1280 | font-size / color / line-height | 12px / neutral-700 / 18px |
| jr-rss | journal | `.ttm-journal-head__rss a` | 1280 | color / text | accent-700 / "Journal RSS" |
| jr-count-hidden | journal | `.ttm-journal-head__count` | 1280 | count (post has syndication) | 0 |
| jr-rule | journal | `.ttm-journal-head + hr.is-style-rule-2` | 1280 | height / width | 2px / 1184px |
| js-head | journal | `.ttm-journal-stream .ttm-cell-heading__label` | 1280 | text / border-bottom-width | "Earlier" / 0px |
| js-link | journal | `.ttm-journal-stream .ttm-cell-heading__link` | 1280 | text | matches `/^Full journal · \d+ entries$/` |
| js-row | journal | `.ttm-journal-row` (first) | 1280 | grid-template-columns / padding / border-top-width | 3:7:2 / 18px 0 / 1px |
| js-date | journal | `.ttm-journal-row .is-style-journal-stream-date` | 1280 | font-size / font-weight | 20px / 800 |
| js-title | journal | `.ttm-journal-row .wp-block-post-title` | 1280 | font-size / font-weight | 18px / 800 |
| js-excerpt | journal | `.ttm-journal-row .wp-block-post-excerpt__excerpt` | 1280 | font-size / color / max-width | 15px / neutral-800 / 40em |
| js-words | journal | `.ttm-journal-row .ttm-journal-row__words` | 1280 | font-size / color / justify-self | 12px / neutral-600 / end |
| js-count | journal | `.ttm-journal-row` | 1280 | count | 4 |
| js-phone | journal | `.ttm-journal-row` (first) | 390 | grid-template-columns / words visible | 2 tracks / false |
| wr-hero | writing | `.ttm-serial-hero` | 1280 | grid-template-columns / column-gap / align-items / padding | 320px + 1 (cover) / 64px / end / 40px 0 |
| wr-cover | writing | `.ttm-serial-hero .ttm-cover` | 1280 | aspect-ratio / box-shadow / filter | 2 / 3 / not none / none |
| wr-kicker | writing | `.ttm-serial-hero__kicker` | 1280 | text / color | "Writing · Serial in progress" / accent-700 |
| wr-title | writing | `.ttm-serial-hero__title` | 1280 | font-size / line-height / max-width | 64px / 62.72px / 14ch |
| wr-synopsis | writing | `.ttm-serial-hero__synopsis` | 1280 | font-size / color / max-width / count | 19px / neutral-800 / 48ch / 1 |
| wr-buttons | writing | `.ttm-serial-hero__buttons .btn` | 1280 | count / classes | 3 / primary, secondary, ghost in order |
| wr-stats | writing | `.ttm-serial-hero .ttm-stats` | 1280 | grid-template-columns / border-top-width / padding-top | 3 tracks / 2px / 14px |
| wr-stat-value | writing | `.ttm-stats__value` (first) | 1280 | font-size / font-weight / text | 20px / 800 / matches `/^\d+ \/ \d+$/` |
| wr-stat-cadence | writing | `.ttm-stats__value` (2nd) | 1280 | text | "Monthly" |
| wr-body | writing | `.ttm-writing-body` | 1280 | grid-template-columns / column-gap / padding | 7:5 / 64px / 28px 0 48px |
| wr-serials-head | writing | `.ttm-writing-body main .ttm-cell-heading` (first) | 1280 | border-bottom-width / text of link | 2px / "Newest activity first" |
| wr-serial-row | writing | `.ttm-series-list.is-list .ttm-series-row` (first) | 1280 | grid-template-columns / padding / border-bottom-width | 10px 1fr auto / 16px 0 / 1px |
| wr-serial-title | writing | `.ttm-series-list.is-list .ttm-series-row__title` (first) | 1280 | font-size / font-weight | 20px / 800 |
| wr-serial-dek | writing | `.ttm-series-list.is-list .ttm-series-row__dek` (first) | 1280 | font-size / max-width | 14px / 46ch |
| wr-serial-form | writing | `.ttm-series-list.is-list .ttm-series-row__meta` (first) | 1280 | text | matches `/^Novel · .+ · monthly$/i` |
| wr-serial-count | writing | `.ttm-series-list.is-list .ttm-series-row__count` (first) | 1280 | text-align / text | right / matches `/^\d+ of \d+/` |
| wr-serial-status | writing | `.ttm-series-list.is-list .ttm-series-row__status` (first) | 1280 | color / font-weight / text | accent-700 / 600 / "In progress" |
| wr-chapters-head | writing | `.ttm-series-toc.is-chapters .ttm-cell-heading__label` | 1280 | text | "The Quiet Ledger — recent chapters" |
| wr-chapter-row | writing | `.ttm-series-toc.is-chapters .ttm-numbered__row` (first) | 1280 | grid-template-columns / padding | 44px 1fr auto / 12px 0 |
| wr-chapter-num | writing | `.ttm-series-toc.is-chapters .ttm-numbered__num` (first) | 1280 | font-size / color / font-weight | 15px / neutral-500 / 800 |
| wr-chapter-title | writing | `.ttm-series-toc.is-chapters .ttm-numbered__title` (first) | 1280 | font-size / font-weight | 17px / 800 |
| wr-tiles | writing | `.ttm-story-tiles` | 1280 | grid-template-columns / gap | 2 equal / 16px |
| wr-tile | writing | `.ttm-tile` (first) | 1280 | aspect-ratio / background-color / padding / justify-content | 4 / 3 / surface / 16px / space-between |
| wr-tile-title | writing | `.ttm-tile__title` (first) | 1280 | font-size / font-weight | 19px / 800 |
| wr-tile-meta | writing | `.ttm-tile__meta` (first) | 1280 | text | matches `/^[\d,]+ words · \d{4}$/` |
| wr-tiles-note | writing | `.ttm-story-tiles__note` | 1280 | font-size / color | 12px / neutral-700 |
| wr-books | writing | `.ttm-book-grid` | 1280 | grid-template-columns / gap | 2 equal / 20px |
| wr-book-cover | writing | `.ttm-book .ttm-cover` (first) | 1280 | aspect-ratio / box-shadow | 2 / 3 / none |
| wr-book-title | writing | `.ttm-book__title` (first) | 1280 | font-size / margin-top | 14px / 10px |
| wr-phone | writing | `.ttm-serial-hero` / `.ttm-serial-hero__title` | 390 | grid-template-columns / font-size | 1 track / 40px |
| ar-head | archive | `.ttm-archive-head` | 1280 | grid-template-columns / padding / align-items | 2:1 / 40px 0 28px / end |
| ar-kicker | archive | `.ttm-archive-head .is-style-kicker` | 1280 | text / color | "Section" / accent-700 |
| ar-h1 | archive | `.ttm-archive-head h1` | 1280 | font-size / line-height / margin-left | 80px / 76px / ≈ −4.64px |
| ar-h1-phone | archive | same | 390 | font-size | 44px |
| ar-desc | archive | `.ttm-archive-head .wp-block-term-description p` | 1280 | font-size / color / max-width | 17px / neutral-800 / 52ch |
| ar-stats | archive | `.ttm-category-stats` | 1280 | font-size / color / line-height | 13px / neutral-700 / 20.8px |
| ar-stats-text | archive | `.ttm-category-stats` | 1280 | text | matches `/^\d+ articles · \d{4}(–\d{4})?/` and contains "Security RSS" |
| ar-stats-rss | archive | `.ttm-category-stats a` | 1280 | color | accent-700 |
| ar-filter | archive | `.ttm-filter-row` | 1280 | border-top-width / border-bottom-width / padding / font-size | 2px / 1px / 12px 0 / 12px |
| ar-filter-all | archive | `.ttm-filter-row .tag` (first) | 1280 | class / text | `tag-accent` / "All" |
| ar-filter-active | `/category/security/?tag=wordpress` | `.ttm-filter-row .tag-accent` | 1280 | text | "wordpress" |
| ar-filter-sort | archive | `.ttm-filter-row__sort` | 1280 | text / margin-left | "Newest first" / auto |
| ar-body | archive | `.ttm-archive-body` | 1280 | grid-template-columns / column-gap / padding | 2:1 / 64px / 8px 0 48px |
| ar-year | archive | `.ttm-archive-year` (first) | 1280 | grid-template-columns / border-top-width / padding | 120px + 1 / 2px / 24px 0 8px |
| ar-year-label | archive | `.ttm-archive-year__label` (first) | 1280 | font-size / font-weight / line-height | 32px / 800 / 32px |
| ar-row | archive | `.ttm-archive-row` (first) | 1280 | grid-template-columns / padding / border-bottom-width / tag | 72px + 1 / 14px 0 / 1px / a |
| ar-row-date | archive | `.ttm-archive-row__date` (first) | 1280 | font-size / color / padding-top | 12px / neutral-700 / 4px |
| ar-row-title | archive | `.ttm-archive-row__title` (first) | 1280 | font-size / font-weight | 20px / 800 |
| ar-row-dek | archive | `.ttm-archive-row__dek` (first) | 1280 | font-size / max-width / margin-top | 14px / 56ch / 5px |
| ar-row-meta | archive | `.ttm-archive-row__meta` (first) | 1280 | font-size / text | 12px / matches `/^\d+ min( · .+)?$/` |
| ar-pagination | archive | `.wp-block-query-pagination` | 1280 | display / justify-content / padding / font-size | flex / space-between / 20px 0 / 13px |
| ar-pagination-numbers | archive | `.wp-block-query-pagination-numbers` | 1280 | count | 0 |
| ar-aside-series | archive | `.ttm-archive-body aside .ttm-cell-heading__label` (first) | 1280 | text | "Series in Security" |
| ar-aside-row | archive | `.ttm-series-list.is-rail .ttm-series-row` (first) | 1280 | grid-template-columns / padding / title font-size | 10px 1fr / 12px 0 / 15px |
| ar-mostread | archive | `.ttm-most-read .ttm-numbered__row` (first) | 1280 | grid-template-columns / font-size / font-weight | 24px 1fr / 14px / 600 |
| ar-mostread-num | archive | `.ttm-most-read .ttm-numbered__num` (first) | 1280 | color / font-weight | neutral-500 / 800 |
| ar-phone-year | archive | `.ttm-archive-year` (first) | 390 | grid-template-columns / label font-size | 1 track / 24px |
| ar-tablet-aside | archive | `.ttm-archive-body aside` | 1000 | grid-template-columns | 2 equal |
| aj-rows | `/category/journal/` | `.ttm-journal-row` | 1280 | count / first grid | ≥ 9 / 3:7:2 |
| aj-no-filter | `/category/journal/` | `.ttm-filter-row` | 1280 | count | 0 |
| tag-kicker | `/tag/wordpress/` | `.ttm-archive-head .is-style-kicker` | 1280 | text | "Tag" |
| tag-aside | `/tag/wordpress/` | `.ttm-archive-body aside .ttm-most-read`, `.ttm-series-list` | 1280 | count | 1 / 0 |
| search-h1 | `/?s=ledger` | `.ttm-archive-head h1` / description | 1280 | text | "Search" / "Results for “ledger”" |
| search-form | `/?s=ledger` | `.ttm-archive-head .wp-block-search__input` / `__button` | 1280 | class / width | `input` / 320px ; `btn-secondary` |
| search-row-kicker | `/?s=ledger` | `.ttm-archive-row .is-style-kicker` (first) | 1280 | font-size / color | 12px / accent-700 |
| 404-h1 | 404 | `main h1` | 1280 | font-size / text | 80px / "Not here." |
| 404-strip | 404 | `.ttm-series-strip .ttm-series-row` | 1280 | count | 3 |
| 404-latest | 404 | `.ttm-latest .ttm-numbered__row` | 1280 | count | 4 |
| page-grid | `/about/` | `main.is-style-grid-8-4` | 1280 | grid-template-columns / padding | 2:1 / 40px 0 48px |
| page-h1 | `/about/` | `main h1` | 1280 | font-size | 56px |
| hub-head | `/series/` | `.ttm-hub-head` | 1280 | grid-template-columns / padding / align-items | 2:1 / 40px 0 28px / end |
| hub-h1 | `/series/` | `.ttm-hub-head h1` | 1280 | font-size / margin-left | 80px / ≈ −4.64px |
| hub-desc | `/series/` | `.ttm-hub-head .is-style-dek` | 1280 | font-size / max-width | 17px / 52ch |
| hub-stats | `/series/` | `.ttm-series-stats` | 1280 | font-size / color / text | 13px / neutral-700 / matches `/^\d+ series · \d+ in progress/` and contains "Spanning" |
| hub-featured | `/series/` | `.ttm-series-featured` | 1280 | grid-template-columns / column-gap / padding | 5:7 / 64px / 28px 0 36px |
| hub-kicker | `/series/` | `.ttm-series-featured__kicker` | 1280 | text / color | matches `/^In progress · /` / accent-700 |
| hub-title | `/series/` | `.ttm-series-featured__title` | 1280 | font-size / line-height | 40px / 40.8px |
| hub-dek | `/series/` | `.ttm-series-featured__dek` | 1280 | font-size / color | 16px / neutral-800 |
| hub-progress | `/series/` | `.ttm-series-progress__seg` | 1280 | count / height / flex-grow | = total parts / 6px / 1 |
| hub-progress-color | `/series/` | `.ttm-series-progress__seg` (1st, last) | 1280 | background-color | neutral-900 / neutral-300 |
| hub-meta | `/series/` | `.ttm-series-progress__meta` | 1280 | font-size / text | 12px / matches `/^\d+ of \d+ published( · next part .+)?$/` |
| hub-buttons | `/series/` | `.ttm-series-featured__buttons .btn` | 1280 | count / texts | 2 / "Start at part 1", "Follow this series" |
| hub-parts | `/series/` | `.ttm-series-featured__parts` | 1280 | tag / border-top-width | OL / 2px |
| hub-part | `/series/` | `.ttm-series-featured__part` (first) | 1280 | grid-template-columns / padding / border-bottom-width / font-size | 40px 1fr auto / 12px 0 / 1px / 16px |
| hub-part-num | `/series/` | `.ttm-series-featured__num` (first) | 1280 | color / font-weight | neutral-500 / 800 |
| hub-part-scheduled | `/series/` | `.ttm-series-featured__part.is-scheduled` (first) | 1280 | title color / has link / date text | neutral-700 / no / matches `/^Sept? \d+|^[A-Z][a-z]{2,3} \d+/` |
| hub-all-head | `/series/` | `.ttm-hub-all .ttm-cell-heading__link` | 1280 | text | "Sorted by last update" |
| hub-grid | `/series/` | `.ttm-series-list.is-grid-2` | 1280 | grid-template-columns / column-gap | 2 equal / 48px |
| hub-grid-row | `/series/` | `.ttm-series-list.is-grid-2 .ttm-series-row` (first) | 1280 | grid-template-columns / padding / border-top-width | 10px 1fr auto / 18px 0 / 1px |
| hub-grid-title | `/series/` | `.ttm-series-list.is-grid-2 .ttm-series-row__title` (first) | 1280 | font-size | 20px |
| hub-grid-cats | `/series/` | `.ttm-series-list.is-grid-2 .ttm-series-row__categories` (first) | 1280 | font-size / text | 12px / contains " · " or single name |
| hub-nobox | `/series/` | `.ttm-newsletter-box` | 1280 | count | 0 |
| hub-phone | `/series/` | `.ttm-series-featured` / `.ttm-hub-head h1` | 390 | grid-template-columns / font-size | 1 track / 44px |
| single-head | `/series/hardening-wordpress/` | `.ttm-series-single h1` | 1280 | font-size / max-width | 80px / 16ch |
| single-kicker | same | `.ttm-series-single .ttm-series-featured__kicker` | 1280 | text | matches `/^In progress · Technology · Security$/` |
| single-parts | same | `.ttm-series-single .ttm-series-featured__part` | 1280 | count / first font-size / dek count | = total parts / 18px / = published parts |
| single-other | same | `.ttm-series-single__other .ttm-series-row` | 1280 | count | ≤ 4 and ≥ 1 |
| single-nav | same | `.ttm-masthead-inner__nav .current-menu-item > a` | 1280 | text | "Series" |
| seed-hero-color | article | `.wp-block-post-featured-image img` | 1280 | dominant pixel (canvas sample at centre) | not within 40 of rgb(210,48,19) |
| a11y | every screen | page | both | axe | 0 serious/critical |
| network | every screen | page | both | requests | same-origin only |
| selectors | every screen | `ttm.css` | 1280 | rule 41 | every selector matches ≥ 1 |

The planner turns the table into `test.fixme` rows in Phase 0 and each task un-fixmes the rows it makes pass; the flight ends with zero `fixme` (guarded by the existing `scripts/check-fixme.mjs`).

### 6.10 Seed content (fixtures only)

Add to `docs/fixtures/seed/`:

- Article: the `2b` post already exists ("Stop trusting the database…", Technology · Security, tags `wordpress`, `php`, `integrity`, series part 3 of 6). Give it the mock's body (two H2s "What a signature buys you" / "Where to keep the key", one `pre` block with the 12-line `add_action` snippet, one pull quote "A file-integrity scanner watches the doors…" as `core/quote` `is-style-pull`, an inline-code paragraph), a captioned featured image, and reading time 14 min (≈ 3 200 words of prose — pad with `prose.json` paragraphs after the mock's). Parts 1, 2, 4 published (4 "Keys in the environment, rotated live"), 5 "The admin with the weak password" and 6 "Incident: what to do when the alarm fires" **scheduled** (future dates) so the TOC and hub show scheduled rows.
- Journal: "A rainbow over the rail yard" gets the mock's four paragraphs, `ttm_location` "Portland", syndication URLs for X and Mastodon, and a date that is a Sunday; "Two working weeks a year" (184 words) and "Rain, Psalm 46…" (96 words) get real word counts near the mock's.
- Writing: "The Quiet Ledger" term description = the mock's synopsis; genre "literary thriller"; cadence "monthly"; next date set; a **cover** image; chapters 1–12 with titles and one-line excerpts (ch. 12 "Reconciliation"); "Failover" description "Three engineers, one data center, and the night the generators did not start."; "Salt Water Wires" description "A cable-landing station on the Oregon coast, and the family that has kept it running for sixty years."; stories "The Last Cron Job" (3 100 words, 2026), "A Field Guide to Empty Offices" (1 800, 2025), "Uptime", "What the River Audits"; books "Salt Water Wires" (Novel · 2022 · paperback, ebook) and "Eleven Small Doors" (Stories · 2019 · paperback) with covers.
- Security archive: description "Application security for people who ship. Threat models, disclosures, and the occasional post-mortem — written from the engineer's side of the incident."; enough Security posts across ≥ 2 years for two year groups on page 1 and a second page; tags `wordpress`, `threat-modeling`, `cryptography`, `disclosure`, `passwords`; three posts flagged most-read ("Nonces are not CSRF tokens", "Reading a CVE like an engineer", "Why I still recommend hardware keys to my parents").
- Series hub: description as §6.7; "Hardening WordPress" description "Six parts on defending the parts of a WordPress install that scanners ignore: configuration, keys, the database, and the people with logins."; next date set; at least 6 series total across ≥ 4 categories so the stats line reads sensibly.
- Images per rule 45; the About page gets a 3:2 image.

### 6.11 Screenshots

`npm run screenshots` extends phase 2's script with, at 1280: `article.png`, `journal.png`, `writing.png`, `archive-security.png`, `series-hub.png`, `series-single.png`, `search.png`, `404.png`; at 390: `article-390.png`, `journal-390.png`, `writing-390.png`, `archive-390.png`; at 1920: `front-1920.png`, `article-1920.png`. Written to `docs/feedback/phase-3/` with a README naming the mock for each.

## 7. Commands

Unchanged from phase 2 (`docs/phase-2/foundry.json`) plus `selectors.spec.mjs` inside `npm run test:e2e` and the theme.json variable check inside `npm run lint`.

```text
verify:
  composer lint
  composer test:unit
  npm run lint
  npm run test:unit
  npm run build
  bash scripts/forbidden-patterns.sh

extraVerify:
  plugins/ttm-core/, themes/ttm-theme/, tests/integration/:  npm run test:integration
  themes/ttm-theme/theme.json:                                npm run check:theme-json
  themes/ttm-theme/, plugins/ttm-core/blocks/, tests/e2e/:     npm run test:e2e

build: npm run build

foundry.json:
  baseBranch       = "main"
  branchPrefix     = "refine/"
  maxRounds        = 4
  commandTimeoutMs = 900000
```

## 8. Phases

Each phase ends with a task that runs `npm run screenshots`, commits the PNGs, pushes, and records `Manual check: NOT VERIFIED (human)` naming the mock to compare.

### Phase 0 — Harness and shared chrome

- Remove the four glob lines from `scripts/css-coverage-allow.txt`; the lint now fails on 32 classes; each later task removes its own.
- `selectors.spec.mjs` (rule 41) with its allow-list; theme.json variable check (rule 44) and the `article-h2` slug rename; `cssBudgetBytes` → 61440; `BoundariesTest` exceptions accepted.
- Page container (rule 42) and inner masthead/nav (rule 43, §6.1.1), inner footer variant (§6.1.2); verify the front-page fidelity rows still pass at 1280 and add `container-front`.
- Seed: images (rule 45), §6.10 content, screenshot script extension, fidelity rows as `fixme`.
- **Visible result:** every inner page is a centred 1280 column with a correct masthead; baseline PNGs. Manual check: `article.png` masthead vs `design_article.png` top.

### Phase 1 — Article

- §6.2 in full: series bar, article header, byline, hero, body typography, prev/next, aside (TOC, more-in, newsletter box copy), §6.3 verified unchanged, `3b` phone. Un-fixme `bar-*`, `art-*`, `prevnext-*`, `aside-*`, `toc-*`, `more-*`, `box-*`.
- **Visible result:** `article.png` matches `design_article.png`.

### Phase 2 — Journal post and journal archive

- §6.4 and the journal archive of §6.6. Un-fixme `jr-*`, `js-*`, `aj-*`.
- **Visible result:** `journal.png` matches `design_journal.png`.

### Phase 3 — Archives, search, 404, page

- §6.6 section archive, tag/date archive, search, 404, static page. Un-fixme `ar-*`, `tag-*`, `search-*`, `404-*`, `page-*`.
- **Visible result:** `archive-security.png` matches mock `1e`.

### Phase 4 — Series hub, single series, Writing

- §6.7 and §6.5. Un-fixme `hub-*`, `single-*`, `wr-*`.
- **Visible result:** `series-hub.png` matches `1f`; `writing.png` matches `design_serial.png`.

### Phase 5 — Phone, wide, close-out

- Every 390 and 1920 row; `a11y`, `network`, `selectors`, `seed-hero-color`; allow-list empty; budget tuning task records the final size; HANDOFF; final screenshots.
- **Visible result:** CI green; `docs/feedback/phase-3/` complete.

## 9. Open questions

| # | Question | Decision |
|---|---|---|
| S1 | Root padding vs container padding | Container owns the gutter (§6.1.0); remove root padding if it doubles. |
| S2 | Nav breakpoint 600 (core) vs 720 (design) | 720, by CSS override (rule 43). |
| S3 | Newsletter box copy by context | Series contexts "Get the next part", otherwise "The weekly issue."; mechanism is the planner's call, recorded. |
| S4 | Footer "Built on WordPress" on inner pages | Keep one string everywhere. |
| S5 | Kicker for tag/date archives | "Tag" / "Month"; mechanism planner's call. |
| S6 | Filter-row rule when F16 hides the row | Keep the 2px rule above the list (06 F16). |
| S7 | Seed image style | Rule 45; no photography, no red. |
| S8 | CSS budget | 60 KB; tuning task in Phase 5 records the final number. |

## Appendix A — Files in `docs/`

| File | What it is |
|---|---|
| `SPEC.md` | This file. |
| `feedback/design_article.png`, `preview_article.png` | Mock `2b` vs the phase 2 build at 1870px. |
| `feedback/design_journal.png`, `preview_journal.png` | Mock `2c` vs build. |
| `feedback/design_serial.png`, `preview_serial.png` | Mock `2d` vs build. |
| `feedback/design_top|blocks|footer.png`, `preview_*.png`, `phase-2/` | Front page, done in phase 2. |
| `feedback/phase-3/` | This flight's screenshots (§6.11). |
| `Eric Mann Newspaper.dc.html` | Mock cards; line ranges in the preamble. |
| `README.md`, `01`–`07` | Design handoff; normative for visuals and fallbacks. |
| `phase-1/`, `phase-2/` | Archived flights. `phase-2/SUMMARY.md §Spec issues` is folded into §3.1 and §6.3 here. |
| `SETUP.md`, `MIGRATION.md`, `DEPLOYMENT.md`, `fixtures/`, `spikes/` | As before; `fixtures/seed/` extended per §6.10. |
