# 01 · Design language

The site is set in the **Modernist** design system: flat, architectural, Archivo-only, near-mono red on off-white, zero corner radius, structure carried by 2px rules. This document is the complete visual contract. When in doubt, the token sheet `_ds/…/styles.css` wins over any number quoted here.

## 1. Principles

1. **Structure is drawn, not boxed.** No cards, no shadows on content, no rounded corners, no background tints behind text columns. Zones are separated by 2px rules; items inside a zone by 1px rules; columns inside a zone by 1px vertical rules. Whitespace alone is never the separator between two content blocks.
2. **Ink on ground; red is a mark.** The page is `#201e1d` on `#f3f2f2`. Red appears as *marks* — kickers, the active nav item, the 10px series square, progress segments, hover on links/headlines, one primary button per view. It runs as a *field* exactly once per page: the newsletter poster.
3. **Weight by volume.** Technology writes 2× everything else and gets 2× the space. Cells are otherwise equal. Weight is expressed by *width and headline size*, never by colour or decoration.
4. **Flush left, always.** Headings, body, meta, button labels, form labels. Nothing is centred, including on the phone.
5. **Photographs are documents.** Grayscale on the front page and archives (`filter: grayscale(1) contrast(1.08)`), full colour on article pages. Never tinted, never cropped to circles, never given borders. Covers of books are treated as *objects*: colour everywhere, and the one place a shadow is allowed.
6. **Finished on a quiet month.** Every block has a designed step-down (see `06-fallbacks.md`). Nothing renders empty and nothing shows placeholder copy.

## 2. Tokens

All from `_ds/…/styles.css` (`:root`). The theme must expose the same names as `theme.json` presets (slugs given).

### 2.1 Colour
| Token | Slug | Value | Use |
|---|---|---|---|
| `--color-bg` | `bg` | `#f3f2f2` | Page ground; text on accent field |
| `--color-surface` | `surface` | `#eae9e9` | Verse box, newsletter box, story tiles, image placeholders, inline code bg, tag fill |
| `--color-text` | `text` | `#201e1d` | Ink |
| `--color-accent` | `accent` | `#ec3013` | Marks (squares, active nav, current progress segment), primary button fill, poster field, hover colour on headlines/links |
| `--color-accent-600` | `accent-600` | ramp | Primary button hover |
| `--color-accent-700` | `accent-700` | `#ae1800` | **All accent text at ≤ 17px** (kickers, “Continue →”, inline links, status “In progress”), primary button pressed |
| `--color-divider` | `divider` | `rgba(32,30,29,.40)` | Every rule, 1px and 2px; secondary button border |
| `--color-neutral-100` | `neutral-100` | ramp | Code block text |
| `--color-neutral-300` | `neutral-300` | ramp | Unpublished progress segments |
| `--color-neutral-400` | `neutral-400` | ramp | Hiatus series square; dashed frames in docs only |
| `--color-neutral-500` | `neutral-500` | ramp | “Complete” status text, chapter/part numbers, disabled pagination |
| `--color-neutral-600` | `neutral-600` | ramp | Faintest meta (cell counts, word counts, “by”) |
| `--color-neutral-700` | `neutral-700` | ramp | Meta: dates, bylines, captions, footer, nav “Series” item |
| `--color-neutral-800` | `neutral-800` | ramp | Deks, excerpts, secondary body |
| `--color-neutral-900` | `neutral-900` | ramp | Published progress segments, completed-series square, code block background |
| `--shadow-lg` | — | `0 12px 32px rgba(45,43,43,.22)` | Book covers on the Writing hero only |

Ramp values: read them from `styles.css`; do not re-derive.

Contrast rules: accent-on-ground is ~3.3:1 — fine for the 10px square, bars, and display type; **not** for text under 18px. Hence `accent-700` for kickers and small links. Text on the red poster is the ground colour at ≥ 28px only, or 14px/800 inside a ghost button with a 1px ground border.

### 2.2 Type
Family: **Archivo** (`--font-heading` = `--font-body` = Archivo). Weights used: **400, 600, 800**. Self-host woff2 (latin + latin-ext), `font-display: swap`. Fallback stack: `Archivo, "Helvetica Neue", Arial, sans-serif`.

Global: `-webkit-font-smoothing: antialiased`; `text-wrap: pretty` on paragraphs, `text-wrap: balance` on headings ≥ 30px; `font-feature-settings: "tnum"` on every date, count, number column, and the journal date block.

Optical alignment: 800-weight display headings (≥ 40px) get `margin-left: -0.058em` so the stem of the first cap sits on the column edge. Pull quotes hang the opening quote mark: `text-indent: -0.496em`.

### 2.3 Type scale (desktop → phone)
| Slug | Role | Desktop | Phone (≤ 720) | Weight / LH / tracking | Colour |
|---|---|---|---|---|---|
| `display-xl` | Section archive H1, Series hub H1 | 80 | 44 | 800 / 0.95 / −0.03em | text |
| `display-l` | Front masthead title | 76 | 44 | 800 / 0.95 / −0.03em | text |
| `display-m` | Fiction title (Writing hero) | 64 | 40 | 800 / 0.98 / −0.03em | text |
| `h1` | Article H1 | 56 | 34 | 800 / 1.02 / −0.025em | text · max 18ch |
| `journal-date` | Big date on journal post | 48 | 36 | 800 / 1.0 / −0.025em · tnum | text |
| `lead` | Front lead headline | 44 | 30 | 800 / 1.05 / −0.02em | text · max 22ch |
| `poster` | Newsletter poster H3 | 40 | 28 | 800 / 1.05 / −0.02em | bg (on accent) |
| `featured-series` | Series hub featured title | 40 | 30 | 800 / 1.02 / −0.02em | text |
| `h2` | Article H2 | 30 | 24 | 800 / 1.1 / −0.02em | text |
| `journal-title` | Journal post H1 | 30 | 26 | 800 / 1.12 / −0.015em | text |
| `pull` | Pull quote | 28 | 22 | 800 / 1.25 / −0.015em | text · max 26ch |
| `cell-lead-l` | Tech cell featured headline | 24–26 | 21 | 800 / 1.1–1.15 | text |
| `cell-lead` | Other cells featured headline; archive row title; series list title | 20–21 | 21 | 800 / 1.15–1.2 | text |
| `dek-l` | Article dek | 21 | 17 | 400 / 1.4 | neutral-800 · max 32em |
| `journal-stream-date` | Date in journal stream rows | 20 | 18 | 800 / 1.0 · tnum | text |
| `synopsis` | Writing hero synopsis; verse text | 19 | 17 | 400 / 1.45 (verse 600 / 1.35) | neutral-800 (verse: text) |
| `body` | Article & journal body | 18 | 17 | 400 / 1.65 (phone 1.6) | text · measure 38em |
| `secondary` | Prev/next title; series TOC current; chapter title | 17–18 | 16 | 800 / 1.2–1.25 | text |
| `dek` | Front lead dek | 17 | 15 | 400 / 1.5 | neutral-800 · max 60ch |
| `headline-s` | Secondary headlines in cells | 15–17 | 15 | 800 (or 600 at 14–15) / 1.25–1.3 | text |
| `body-s` | Cell deks, excerpts, list deks | 13–14 | 14 | 400 / 1.45–1.5 | neutral-800 |
| `ui` | Nav, buttons, TOC items, tags on filter row | 13–14 | 13 | 600 / 1.35 | text |
| `caption` | Figure captions, footer, meta rows, dates | 12 | 12 | 400 / 1.5 · tnum | neutral-700 |
| `kicker` | Kickers, cell headings (h6) | 12 (rail 11) | 11 | 600 (h6: 800) / 1.2 / +0.08em / uppercase | accent-700 (h6: text) |
| `micro` | Cell counts, word counts, tag chips | 11 | 11 | 400 (tags 600) | neutral-600 |

Never set readable text below 12px; 11px is reserved for kickers, tags and counts.

### 2.4 Spacing
Tokens: `--space-1…10` = 4, 8, 12, 16, 24, 32, 40, 48, 64, 96. Slugs `10…100`.

| Use | Value |
|---|---|
| Page gutter | 48 (desktop) · 20 (≤ 720) |
| Content max width | 1280 (`contentSize` = `wideSize` = 1280) |
| Column gap: lead/rail | 40 |
| Column gap: cells in a section row | 32 (cells get `padding-right: 32` + 1px right rule) |
| Column gap: article/aside, archive/aside | 64 |
| Column gap: journal 3/7/2 | 48 |
| Zone padding (above/below a 2px rule) | 20 top · 24 bottom for cells; 28 top · 32 bottom for lead row; 40 top for page headers |
| Cell heading → first item | 16 |
| List item padding | 10–16 vertical (denser lists 9–10; front cells 12–14; archive rows 14; series lists 16–18) |
| Paragraph gap (body) | 22 (phone 18) |
| H2 in body | 40 above · 14 below (phone 28 / 10) |
| Pull quote | 36 above/below |
| Figure caption | 8 below image |
| Byline row | 14 vertical, 2px rule above, 1px below |
| Verse/newsletter box padding | 18 20 (rail 14 16; phone 16 18) |
| Button padding | 8 14 (from `.btn`) |

### 2.5 Radius & elevation
`--radius-*` = 0. Elevation only via `--shadow-lg` on book covers. Nothing else casts a shadow; nothing has a border-radius.

### 2.6 Rules
- `.ttm-rule-2` — `height: 2px; background: var(--color-divider)` — zone boundary. Also the top edge of cell headings’ underline on rails/asides.
- `.ttm-rule-1` — `1px` — item boundary and column separators.
- Never lighten, never dash (dashed is used only in the fallback documentation frame, not the site), never gradient.

## 3. Grids

12-column mental model at 1280 with 32px gutters; implemented with CSS grid on Group blocks (theme adds `is-style-…` classes for these tracks) or core Columns where the ratio is simple.

| Screen · zone | Tracks | Notes |
|---|---|---|
| Front · lead row | `minmax(0,8fr) minmax(0,4fr)`, gap 40 | rail is a flex column, gap 24 |
| Front · section rows | `repeat(4, minmax(0,1fr))`, gap 0 32 | Technology `span 2`, Writing `span 2`; cells except last have `border-right: 1px` |
| Front · series strip | `repeat(3, minmax(0,1fr))`, gap 24 | |
| Front · newsletter poster | `1fr auto`, gap 32, align end | |
| Article | `minmax(0,8fr) minmax(0,4fr)`, gap 64 | aside `position: sticky; top: 24px` |
| Journal post | `minmax(0,3fr) minmax(0,7fr) minmax(0,2fr)`, gap 48 | same tracks for stream rows |
| Archive header/body, Series hub | `minmax(0,8fr) minmax(0,4fr)`, gap 64 | |
| Archive year group | `120px 1fr`, gap 24 · rows `72px 1fr`, gap 20 | |
| Series hub featured | `minmax(0,5fr) minmax(0,7fr)`, gap 64 | |
| Series hub “All series” | `1fr 1fr`, gap 0 48 | |
| Writing hero | `280px minmax(0,1fr)`, gap 64, align end | 320px if a cover is present and the viewport ≥ 1200 |
| Writing body | `minmax(0,7fr) minmax(0,5fr)`, gap 64 | |
| Inner masthead | `auto 1fr auto`, gap 32 | |

Breakpoints: **1024** (2-col cell rows, asides drop below) and **720** (single column). Details per screen in `02-screens.md §Responsive`.

## 4. Components

Each component is named as the CSS class the theme should register (and the pattern slug, where it is a pattern). Measurements are desktop unless noted.

### 4.1 Masthead — front (`ttm-masthead-front`, pattern `ttm/masthead-front`)
1. Meta row: flex, space-between, 12px neutral-700, padding 14 0, 1px rule below. Left: full date (`l, F j, Y`). Right: links Newsletter · RSS · About (gap 18).
2. Title row: grid `1fr auto`, align end, padding 28 0 20. Left: Site Title block at `display-l`, then a byline line 12px up: “by” (neutral-600) + author name (600 weight, link to About). Right: Site Tagline block, 15px/1.5 neutral-800, max 32ch.
3. 2px rule.
4. Nav row: Navigation block, flex, gap 28, 14px/600, padding 12 0. Items = the seven sections in fixed order. Current section = accent. “Series” is pushed right (`margin-left: auto`) and set 400/neutral-700 — it is a *hub link*, not a section.
5. 2px rule.

### 4.2 Masthead — inner (`ttm-masthead-inner`, pattern `ttm/masthead-inner`)
One row under a 2px rule: grid `auto 1fr auto`, align center, padding 14 0 12. Left: Site Title at 22px/800 −0.02em + “by Eric Mann” 12px neutral-700 (baseline-aligned, gap 10). Middle: nav as above at 13px, gap 22. Right: “Newsletter” 12px neutral-700.
Phone: title 18px, right item becomes “Menu” (opens core Navigation overlay); nav hidden behind it.

### 4.3 Nav (`core/navigation` styled)
Items 600 weight, ink; hover accent; current-menu-item accent. No underline, no background, no pill. Overlay (phone): full-screen, ground background, items 24px/800 stacked with 1px rules, close = “×” 24px top-right.

### 4.4 Cell heading (`ttm-cell-heading`)
Flex, space-between, baseline. Left: `h6`-style label — 12px/800 uppercase +0.08em ink (11px in rail/phone). Right: micro link 11px neutral-600 — either “N articles →” / “N →” (count from category) or a verb (“All serials →”, “All 87”). 16px below (10 in compact contexts). In rails/asides the heading has an 8px padding-bottom and a **2px** rule under it; in front-page cells it has no rule (the zone rule above suffices).

### 4.5 Headline item (`ttm-item`)
Block-level link: 1px top rule, padding 12–14 0. Headline (`headline-s` or `cell-lead`) then meta line 12px neutral-700 (date · optional read time · optional “Series: X, N of M”). Optional dek 13–14px neutral-800 between headline and meta, only for the cell’s first (featured) item. Hover: headline turns accent.

### 4.6 Featured item with image (`ttm-item-featured`)
Grid `200px 1fr`, gap 20, align start. Image 3:2 grayscale. Text: headline `cell-lead-l`, dek, meta. Used once, in the Technology cell.

### 4.7 Lead story (`ttm-lead`, pattern `ttm/lead-story`)
Image 16:9 grayscale (4:3 on phone), 20px gap, kicker (“Technology · Series: Hardening WordPress, part 3 of 6”), headline `lead`, dek `dek`, meta 12px with a link to the previous part when in a series. No featured image → see fallbacks.

### 4.8 Rail (`ttm-rail`, template part `rail`)
Flex column, gap 24. Contents in order: Verse of the day box (if available), Journal block. On phone the rail is a full-width zone between the lead and the cells.

### 4.9 Verse of the day (`ttm/verse-of-the-day` block; class `ttm-verse`)
Surface box, padding 18 20. Kicker “Verse of the day” (11px). Verse text 19px/600/1.35 −0.01em ink in curly quotes. Reference 13px neutral-800 (“Psalm 46:10”). Attribution 12px neutral-700: “Meditation from **dailymedtoday.com**” — the domain is a link (`accent-700`, underline, `text-underline-offset: 3px`) to the meditation’s own URL if the API provides one, else the site root. This is the only underlined link on the site.

### 4.10 Journal excerpt (`ttm-journal-excerpt`, in pattern `ttm/journal-rail`)
Per entry: date line 11px neutral-700 (“Today · Sept 20”, “Thursday · Sept 18” — relative day name for the last 6 days, else full date), title 16px/800/1.25, excerpt 14px/1.5 neutral-800 (~40 words, sentence-trimmed, no ellipsis if the sentence ends), “Continue →” 12px/600 accent-700 with 8px top margin. 16px vertical padding, 1px rules between. **No social links here.**

### 4.11 Journal stream row (`ttm-journal-row`)
Grid `3fr 7fr 2fr` gap 48, baseline. Col 1: date 20px/800 tnum. Col 2: title 18px/800 + excerpt 15px/1.5 neutral-800 (max 40em). Col 3: word count 12px neutral-600, right-aligned. 18px padding, 1px rule above. Whole row is a link.

### 4.12 Journal post header (`ttm-journal-head`)
Col 1: kicker “Journal”, big date `journal-date`, sub-line 14px neutral-700 (“Sunday · Portland” — weekday and a `location` meta if set). Col 2: title `journal-title`, body. Col 3: standing explainer 12px neutral-700 (editable in the template part) + “Journal RSS” link.

### 4.13 Syndication line (`ttm/syndicated-to` block; class `ttm-syndication`)
Flex, 1px rule above, 14px top padding, 28 margin-top; 13px neutral-700: “Syndicated to X and Mastodon” where each network name is a link (accent-700) to the mirrored post; right-aligned word count. Rendered only on single journal posts and only when at least one URL exists.

### 4.14 Section cell (`ttm-cell`, pattern `ttm/section-cell`)
Padding 20 32 24 0, 1px right rule (except the last cell in a row). Cell heading, then a Query Loop: first item featured (`cell-lead` headline + dek + meta), following items `ttm-item`. Counts: Technology 1 featured (with image) + 2; Business/Security 1 + 2; Faith/Opinion 1 + 1; Writing is a distinct pattern (4.15).

### 4.15 Writing cell (`ttm/writing-cell` block or pattern; class `ttm-writing-cell`)
Span 2. Grid `1fr 1fr` gap 28. Left: kicker “Serial · new chapter monthly”, headline 26px/800/1.1 (“{Serial} — Ch. {n}: {title}”), dek 14px, buttons: primary “Read chapter {n}”, secondary “From chapter 1”. Right (1px left rule, padding-left 28): kicker-style label “Also running” (11px uppercase neutral-700), then up to 3 other serials/stories as 8px-padded rows (title 15px/800, meta 12px neutral-700: “Novella · complete · 9 chapters”).

### 4.16 Series square (`ttm-series-mark`)
`10px × 10px` square, no radius, `margin-top` to sit on the cap height (5px at 15–16px text, 6px at 20px). Colour by status: accent = in progress; neutral-900 = complete; neutral-400 = on hiatus. Used in a `10px 1fr` (or `10px 1fr auto`) grid with 12–14px gap.

### 4.17 Series list row (`ttm-series-row`)
Square · title (16–20px/800) + meta or dek + categories line · optional right column (count over status, 12px, right-aligned; status coloured accent-700 / neutral-500 / neutral-600). 1px rule between rows.

### 4.18 Series bar (`ttm/series-bar` block; class `ttm-series-bar`)
Directly under the inner masthead on any post that has a series term. Grid `10px 1fr auto`, gap 14, align center, padding 12 0, 1px rule below, 13px. Square · “Series” (neutral-700) · “ · ” · series name (600, link to hub) · “ · Part N of M” (tnum) · right: segmented progress (each segment 22×4, gap 4: neutral-900 = published before this, accent = this part, neutral-300 = unpublished) + “View series” 12px accent-700 with 14px left margin. Phone: drop the word “Series” and the link; segments 12×4, gap 3.

### 4.19 Series progress (`ttm-series-progress`)
Flex row of equal `flex:1; height: 6px` segments, gap 4; same colours as 4.18 minus the accent “current” (used on hub and Writing where there is no current part). Followed by a meta line “3 of 6 published · next part Sept 26” (12px neutral-700).

### 4.20 Series TOC (`ttm/series-toc` block; class `ttm-series-toc`)
Aside section: cell heading “In this series” with the series name right (11px neutral-600). Items: grid `28px 1fr`, gap 10, padding 10 0, 1px rules, 14px/1.35. Number 800 neutral-600 tnum (“01”). Title: current part 800 accent-700; published parts 400 ink; unpublished 400 neutral-700, not linked, with the scheduled date as `title` attribute.

### 4.21 Prev / next (`ttm/series-prev-next` block; class `ttm-prevnext`)
Grid `1fr 1fr`, 2px rule above, 40px top margin, 1px vertical rule between. Each: padding 20 (24 toward the middle), label 11px uppercase +0.08em neutral-700 (“← Part 2” / “Part 4 →”), 8px, title 18px/800/1.25. Not in a series → chronological within the primary category: “← Previously in Technology” / “Next →”. Missing side → cell renders empty but keeps the rule structure. Phone: stack, 1px rule between.

### 4.22 Article header (`ttm-article-head`)
Kicker (categories joined by “ · ”), H1, dek (excerpt), byline row: flex gap 18, padding 14 0, 2px rule above, 1px below, 13px neutral-700 tnum: “By **Eric Mann**” (600, ink), full date, read time; right: tags as `.tag.tag-neutral`.

### 4.23 Figure (`core/image`, `core/post-featured-image` styled)
Full column width, 16:9 default (respect source ratio inside body), no border. Caption 12px neutral-700, 8px above. Front/archive: `is-style-grayscale`. Article: colour. Phone: hero and code blocks run full-bleed (negative 20px margins).

### 4.24 Body typography (`.entry-content`)
Body 18/1.65, measure 38em (`max-width: 38em`, left-aligned in an 8-col column). Paragraph gap 22. Links: ink, `text-decoration: underline; text-decoration-color: var(--color-divider); text-underline-offset: 3px`, hover accent + accent underline. Strong 800. Em italic (Archivo has true italics — load 400i if used; otherwise synthesize is acceptable). Lists: 22px left indent, markers ink. H2 30, H3 22/800/1.2 (28 above, 10 below), H4 18/800. Inline code: body − 2px, surface bg, padding 1 6, no radius. Code block: neutral-900 bg, neutral-100 text, 14/1.55 `ui-monospace, Menlo, monospace`, padding 18 20, 4px accent left rule, `overflow-x: auto`. Pull quote (`core/quote` style `is-style-pull`): 28/800/1.25 hanging quote, no rule, no italic, 36 margins, cite 13px neutral-700. Standard quote (default `core/quote`): 18px body with 2px ink left rule and 20px padding. Tables: `.table` from the system (2px header rule, 1px row rules). Separator (`core/separator`): 2px full width (`is-style-rule-2`) or 1px (`is-style-rule-1`); no dots, no short centered line. Footnotes: core; number 12px accent-700.

### 4.25 Archive header (`ttm-archive-head`)
Grid 8/4 align end, padding 40 0 28. Left: kicker “Section”, H1 `display-xl` (category name), description 17/1.5 neutral-800 max 52ch (category description). Right: 13px neutral-700/1.6 tnum: “36 articles · 2014–2026” / “4 series touch this section” / “Security RSS” link (accent-700).

### 4.26 Filter row (`ttm-filter-row`)
Flex gap 6 align center, padding 12 0, 2px rule above, 1px below, 12px. “Filter” label neutral-700 (8px right margin), then tags: active `.tag.tag-accent`, others `.tag.tag-neutral` (top 5 tags by count within the category, each linking to `?tag=` within the category — see plugin §REST/queries). Right: “Newest first” (static label; sort toggle is out of scope).

### 4.27 Archive year group (`ttm-archive-year`)
2px rule, padding 24 0 8, grid `120px 1fr`. Year 32/800 −0.02em tnum. Rows: link, grid `72px 1fr`, gap 20, padding 14 0, 1px rule. Date “Sept 10” 12px neutral-700 (4px top padding to align with the title’s x-height); title 20/800/1.2; dek 14/1.45 neutral-800 max 56ch (5px top); meta 12px neutral-700 (6px top): “11 min · tag · tag” or “9 min · Series: Reading CVEs, 4 of 4”.

### 4.28 Pagination (`core/query-pagination` styled)
Flex space-between, padding 20 0, 13px/600. Prev “← Newer” / next “Older (2014–2022) →” — plugin supplies the year range label via a binding; core label fallback “Older →”. Disabled side neutral-500, not a link. No numbered pages.

### 4.29 Numbered list (“Most read”, chapters) (`ttm-numbered`)
Grid `24px 1fr` (chapters `44px 1fr auto`), gap 8–16, padding 10–13 0, 1px rules. Number 800 neutral-500 tnum. Title 14–18/800 or 600. Right column date 12px neutral-700 when present.

### 4.30 Buttons (`.btn` from the system)
`.btn-primary` accent fill, ground text; hover accent-600; active accent-700. `.btn-secondary` transparent, 1px divider border, ink; hover 7% ink tint. `.btn-ghost` transparent, accent-700 text; on the poster: ground text + 1px ground border. All: 14/800, padding 8 14, radius 0, label flush left (`justify-content: flex-start`), `.btn-block` = full width on phone. Focus: `outline: 2px solid var(--color-accent); outline-offset: 2px`.

### 4.31 Tags (`.tag`)
11px/600, padding 2 8, radius 0. `.tag-neutral` surface bg ink; `.tag-accent` accent-100 bg accent-700 text; `.tag-outline` 1px divider. Never on the front page; only on article byline rows and archive filter rows.

### 4.32 Newsletter box (`ttm-newsletter-box`)
Surface bg, padding 16 18. Title 15/800 (“Get the next part” / “The weekly issue”), copy 13px neutral-800 (“Series land in the newsletter the week they publish.”), form: flex gap 6, `.input` (1px divider border, ground bg, 14px, padding 8 10, radius 0) + `.btn-primary` “Subscribe”. Phone: stacked, `.btn-block`.

### 4.33 Newsletter poster (`ttm-poster`, pattern `ttm/newsletter-poster`)
Full-width accent field, padding 36 48 32. Grid `1fr auto` align end. H3 `poster` in ground colour, max 20ch (“Everything above, once a week, in your inbox.”). Right: `.input` 260px with ground bg and border + `.btn-ghost` (ground text, 1px ground border). Phone: padding 24 20, H3 28px, stacked full-width input and block ghost button. The poster is the **only** place the accent is a field.

### 4.34 Footer (`ttm-footer`, template part `footer`)
2px rule above (or the poster directly above on the front page, then 16px). Flex space-between, padding 14–16 48, 12px neutral-700. Left: “These Things Matter · © {year} Eric Mann · Built on WordPress”. Right: section names separated by “ · ” plus “Series” and “RSS”, each a link. Phone: stacked, 11px/1.6.

### 4.35 Typographic story tile (`ttm/story-tile` block; class `ttm-tile`)
Surface box, `aspect-ratio: 4/3`, padding 16, flex column space-between. Title 19/800/1.1 −0.015em top-left; meta 12px neutral-700 tnum bottom-left (“3,100 words · 2026”). If the story has a featured image (cover), the image fills the same box (`object-fit: cover`) and the text is omitted (title appears in the list below/beside it). Hover: title accent; image no change.

### 4.36 Book cover (`ttm-cover`)
`aspect-ratio: 2/3`, `object-fit: cover`, colour, `--shadow-lg` **only** on the Writing hero; no shadow in grids. Caption below: title 14/800/1.25 (10px above), meta 12px neutral-700 (“Novel · 2022 · paperback, ebook”).

### 4.37 Stat row (`ttm-stats`)
Grid `repeat(3, auto)`, gap 0 40, justify start, 2px rule above, 14px top padding. Each: value 20/800 −0.01em ink on its own line, label 13px neutral-700 below (“12 / 31” chapters published · “Monthly” next: Oct 17 · “~14 min” per chapter).

### 4.38 Featured series (hub) (`ttm-series-featured`)
Grid 5/7. Left: kicker (status · categories), title `featured-series`, dek 16/1.5 neutral-800, progress 4.19, meta, buttons primary “Start at part 1” + secondary “Follow this series” (links to newsletter anchor). Right: ordered part list (4.29 style, `40px 1fr auto`, 16px, 2px rule above): number neutral-500, title (current/published/unpublished styling as 4.20), date 12px.

### 4.39 Empty-state documentation frame
Not a site component. The dashed neutral-400 frame in `3c` is a documentation device.

## 5. States & interaction
- Links: ink → accent on hover; no underline except within body copy (divider-coloured underline) and the verse attribution.
- Headlines (`.ttm-item h3/h4`, lead, cell titles): accent on hover; the whole item is the link target.
- Buttons: per 4.30. Transition 120ms ease on colour/background only.
- Focus: `:focus-visible { outline: 2px solid var(--color-accent); outline-offset: 2px }` everywhere. Never remove focus; never leave the UA ring.
- Selection: `::selection { background: color-mix(in oklch, var(--color-accent) 30%, transparent) }`.
- Disabled controls 45% opacity.
- Sticky aside on article: `top: 24px`, only ≥ 1024.
- No scroll-driven or entrance animation. Images `loading="lazy"` except the lead and article hero (`fetchpriority="high"`).
- Reduced motion: nothing to reduce; keep the 120ms transitions.

## 6. Imagery
- Front page & archives: `.is-style-grayscale` → `filter: grayscale(1) contrast(1.08)`. Applied to the wrapper so captions stay ink.
- Article pages: colour, full column, caption.
- Covers: colour everywhere; shadow only on the Writing hero.
- Placeholders: never shown on the live site. A missing image collapses per `06-fallbacks.md`.
- Sizes to register: `ttm-lead` 1600×900 crop, `ttm-thumb` 800×533 crop (3:2), `ttm-cover` 600×900 crop (2:3), `ttm-tile` 800×600 crop.
- Alt text required; the theme surfaces a pre-publish check (plugin, §admin).

## 7. Iconography
Lucide, 16px in UI, 1.5 stroke, ink. The current screens use **no icons** except typed arrows (“→”, “←”, “×”). Do not add icons to nav, cells or buttons. If RSS/social icons are ever wanted in the footer, use Lucide `rss`, and plain text labels remain.

## 8. Voice in UI copy
Short, declarative, lowercase after the first word, no exclamation marks. Section links read as counts (“431 articles →”) or verbs (“All serials →”, “Continue →”). Dates are “Sept 19” in lists, “September 19, 2026” on posts, “Sun, Sept 20, 2026” in compact mastheads. Status words: “In progress”, “Complete”, “On hiatus”, “Serial”. Never “Read more”; always “Continue →” for journal, “Read chapter N” for fiction, headline itself for articles.
