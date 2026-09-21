# 02 · Screens

Every screen: purpose → zones (top to bottom) with grid, content source, block tree → responsive behaviour → fallbacks reference. Component numbers (§4.x) refer to `01-design-language.md`. Block names are core (`core/…`) or plugin (`ttm/…`). Pattern slugs `ttm/…` are defined in `04-theme-spec.md`.

Templates in the theme: `front-page.html`, `single.html`, `single-journal.html` (via template hierarchy hook), `category.html`, `taxonomy-series.html`, `page-series.html`, `page-writing.html`, `archive.html` (tags, dates — same as category minus header extras), `search.html`, `404.html`, `page.html`.

---

## A. Front page — `2a` (1280) / `3a` (390)
Template `front-page.html`. Purpose: surface the newest item per section, weighted; hold the verse and journal quietly on the side; point to series and fiction; end on the newsletter.

### Zones
1. **Masthead (front)** §4.1 — template part `header-front`.
2. **Lead row** — Group, grid 8/4 gap 40, padding 28 0 32.
   - Left: pattern `ttm/lead-story` §4.7. Query: the **lead post** (see `03-content-model.md §Lead selection`). Blocks: `core/query` (1 post) → `core/post-template` → `core/post-featured-image` (`is-style-grayscale`, 16:9) · `core/paragraph` (kicker, bound to `ttm/kicker`) · `core/post-title` (h2, `lead`) · `core/post-excerpt` (`dek`) · `core/paragraph` meta bound to `ttm/meta-line` (date · read time · “Part 2: …” previous-part link when in a series).
   - Right: template part `rail` §4.8 → `ttm/verse-of-the-day` §4.9 · pattern `ttm/journal-rail` §4.10 (Group: cell heading “Journal” / “All 87 entries” bound to category count; `core/query` category=journal, 3 posts, exclude sticky; per post: date line bound to `ttm/relative-date`, `core/post-title` h4, `core/post-excerpt` (40 words, no “more” link), `core/read-more` text “Continue →”).
3. **2px rule** (`core/separator is-style-rule-2`).
4. **Section row 1** — Group grid 4-col gap 32, padding 0 0 8.
   - Technology (span 2): pattern `ttm/section-cell-large` §4.14 + §4.6. Query: category=technology, 3 posts, exclude lead post ID (plugin binding `ttm/exclude-lead` sets `exclude`). Item 1 featured with 3:2 grayscale image, `cell-lead-l` headline, dek, meta; items 2–3 `ttm-item` 17px.
   - Business: pattern `ttm/section-cell`. 3 posts; item 1 `cell-lead` 21px + dek 13px + meta; items 2–3 15px.
   - Security: same, last in row (no right rule).
5. **2px rule.**
6. **Section row 2** — same grid.
   - Faith: `ttm/section-cell`, 2 posts (1 + 1).
   - Opinion: `ttm/section-cell`, 2 posts; meta line appends “· Politics” when the post is also in that (child) category or tag.
   - Writing (span 2, last): `ttm/writing-cell` block §4.15. Data: the active serial (status in-progress, most recently updated) and its latest chapter; “Also running” = up to 3 other serials/stories by last activity.
7. **2px rule.**
8. **Series strip** — Group, padding 20 0 28. Cell heading “Series in progress” / “All series” (link to `/series/`). `ttm/series-list` block, `status=in-progress`, `limit=3`, layout `grid-3`: rows §4.17 with title 16px + meta “Technology · Security · 3 of 6”.
9. **Newsletter poster** — pattern `ttm/newsletter-poster` §4.33. Form posts to the newsletter provider (see `07-open-questions.md`).
10. **Footer** §4.34 — template part `footer`, without the 2px rule (poster above), 16px padding.

### Responsive
- ≤ 1024: lead row stays 8/4 down to 900, then stacks (lead, then rail as a full-width zone: verse box and journal as two columns 1fr 1fr with a 1px vertical rule). Section rows → `repeat(2, 1fr)`: Technology full width (its inner grid stays 2-col), Business | Security, Faith | Opinion, Writing full width. Right rules only on the left cell of each pair.
- ≤ 720 (`3a`): gutter 20. Masthead: meta row 11px with date left and “Newsletter” right; title 44px + byline; 2px rule; nav horizontal scroll (`overflow-x: auto; white-space: nowrap; scrollbar-width: none`), 13px, gap 20; 2px rule. Lead: image 4:3, kicker 11, headline 30, dek 15, meta 12. 2px rule. Verse box (surface, 16 18; verse 18px). Journal: heading with 2px underline, 2 entries. Then each cell as its own zone: 2px rule + 18px padding: heading, featured 21px + date, 1–2 items at 15px/600 with 1px rules. Order: Technology, Business, Faith, Security, Opinion, Writing (Writing: kicker, 22px headline, dek, one `.btn-primary.btn-block`). Series strip: 3 rows §4.17 at 15px. Poster: 24 20 padding, 28px H3, stacked input + block ghost button. Footer stacked 11px.

### Fallbacks
Lead without image; Journal quiet week; verse unavailable; thin cells; Writing with no active serial / no fiction; no series in progress; empty category. See `06-fallbacks.md` rows F1–F9, F17.

---

## B. Article — `2b` (1280) / `3b` (390)
Template `single.html` (all posts except Journal). Purpose: long-form reading; keep the series context one glance away; colour imagery.

### Zones
1. **Masthead (inner)** §4.2 — template part `header-inner`. Current section highlighted from the post’s primary category.
2. **Series bar** — `ttm/series-bar` block §4.18. Renders only if the post has a `series` term.
3. **Body row** — grid 8/4 gap 64, padding 40 0 48.
   - Article column: pattern `ttm/article-header` §4.22 (`core/post-terms` category → kicker style; `core/post-title` h1; `core/post-excerpt` as dek; byline Group: `core/post-author-name` prefixed “By”, `core/post-date` (F j, Y), `core/paragraph` bound to `ttm/reading-time`, `core/post-terms` tags right). Then `core/post-featured-image` (colour, 16:9, caption from attachment caption) with 28px top margin. Then `core/post-content` (§4.24 typography, `max-width: 38em`). Then `ttm/series-prev-next` §4.21 (falls back to chronological within primary category).
   - Aside (sticky ≥ 1024): `ttm/series-toc` §4.20 (only in a series) · pattern `ttm/more-in-section` (cell heading “More in {Category}”, `core/query` 3 posts same primary category excluding current, titles 14px/600 with 1px rules) · pattern `ttm/newsletter-box` §4.32.
4. **Footer** with 2px rule.

### Responsive
- ≤ 1024: grid → single column; aside sections become full-width zones after prev/next, in order: series TOC (heading gains “Hub →” link right), More in section, Newsletter box. No sticky.
- ≤ 720 (`3b`): masthead → title 18px + “Menu”. Series bar compact (no “Series” word, no “View series”; segments 12×4). Kicker 11, H1 34, dek 17, byline row wraps (flex-wrap, gap 6 14). Hero full-bleed 4:3 (`margin: 20px -20px 0; width: calc(100% + 40px)`). Body 17/1.6; H2 24; code blocks full-bleed with 20px inner padding; pull quote 22px. Prev/next stacked with 1px rule between, 16px titles. Aside zones as above.

### Fallbacks
Not in a series (F11); no featured image (F12); small category (F13). Also: no excerpt → no dek, byline row sits 18px under the H1; no tags → byline row right side empty.

---

## C. Journal post — `2c`
Template `single-journal.html` — selected when the post’s primary category is Journal (plugin filter `single_template` / `template_include`, or a `journal` post format — see `07-open-questions.md`). Purpose: a short, dated, titled entry; the date is the hero.

### Zones
1. Masthead (inner), Journal active.
2. **Header + body** — grid 3/7/2 gap 48, padding 40 0 48.
   - Col 1: kicker “Journal”; `core/post-date` styled `journal-date` (“Sept 20”); sub-line bound to `ttm/journal-subline` (“Sunday · Portland” — weekday · `ttm_location` meta if present).
   - Col 2: `core/post-title` h1 `journal-title`; `core/post-content` 18/1.65 max 36em; `ttm/syndicated-to` §4.13.
   - Col 3: `core/paragraph` standing note 12px neutral-700 (editable in the template) + “Journal RSS” link (`/category/journal/feed/`).
3. **2px rule.**
4. **Stream** — Group padding 24 0 40. Cell heading “Earlier” / “Full journal · 87 entries”. `core/query` category=journal, 4 posts, exclude current, rows §4.11 (date bound to `ttm/short-date`, title, excerpt, word count bound to `ttm/word-count`).
5. Footer.

### Responsive
- ≤ 1024: 3/7/2 → `1fr` stacked: date block (kicker, 40px date, subline) then body then note (as a 1px-ruled footnote under the syndication line). Stream rows → `auto 1fr` with date 16px/800 left and title+excerpt right; word count dropped.
- ≤ 720: date 36px, title 26px, body 17/1.6.

### Fallbacks
No syndication URLs → no line (F14). Fewer than 4 earlier entries → show what exists; zero → omit the stream zone.

---

## D. Writing (fiction landing) — `2d`
Template `page-writing.html` assigned to the Writing page (category archive `category-writing.html` **redirects/uses the same template** — see `07`). Purpose: showcase the active serial, index every serial and story, list print books.

### Zones
1. Masthead (inner), Writing active.
2. **Hero** — grid `280px 1fr` gap 64 align end, padding 40 0. Left: cover §4.36 with `--shadow-lg` (from the serial’s `cover` image). Right: kicker “Writing · Serial in progress”; title `display-m` (serial title); synopsis 19/1.45 neutral-800 max 48ch (serial description); buttons: primary “Read chapter 1”, secondary “Latest: chapter {n}”, ghost “Follow by email” (→ newsletter anchor); stat row §4.37 (chapters published “12 / 31”, cadence “Monthly” + “next: {date}” from `next_date` meta, “~{avg} min” per chapter computed). Block: `ttm/serial-hero`.
3. **2px rule.**
4. **Body** — grid 7/5 gap 64, padding 28 0 48.
   - Left (flex column gap 36): **All serials** — cell heading + “Newest activity first”; `ttm/series-list` `form=fiction`, rows §4.17 with title 20, dek 14 (46ch), form line (“Novel · literary thriller · monthly”), right col count + status. **Recent chapters** — cell heading “{Serial} — recent chapters” / “All 12”; `ttm/series-toc` variant `chapters` (numbered rows §4.29 `44px 1fr auto`, newest first, 4 items, dek line from chapter excerpt).
   - Right (flex column gap 32): **Short fiction** — cell heading; `ttm/story-tiles` (grid 2-col gap 16, tiles §4.35, 4 items, stories = Writing posts with `ttm_form=story`, newest first). **In print** — cell heading; `ttm/book-grid` (2-col gap 20, covers §4.36 without shadow, caption title/meta; data from `book` entries — see `03`).
5. Footer.

### Responsive
- ≤ 1024: hero → cover 200px | text; body 7/5 → single column in order: All serials, Short fiction (tiles 2-col), Recent chapters, In print.
- ≤ 720: hero stacks (cover 160px wide, left-aligned, then text); title 40px; buttons stack `.btn-block`; stats row stays 3-across at 16px values. Tiles 2-col at 148px min.

### Fallbacks
No serial in progress (F3); no cover (hero grid → `1fr`, text only, title may widen to 20ch); no stories (Short fiction section omitted); no books (In print omitted). Story with cover (F16).

---

## E. Section archive — `1e`
Template `category.html` (Technology, Business, Faith, Security, Opinion; Journal uses `category-journal.html` = the journal stream layout §4.11 full-page; Writing uses D). Purpose: strict reverse-chronology grouped by year with a tag filter.

### Zones
1. Masthead (inner), current section active.
2. **Archive header** §4.25 — grid 8/4: kicker “Section”, `core/query-title` as `display-xl`, `core/term-description` 17px. Right: meta bound to `ttm/category-stats` (“36 articles · 2014–2026”, “4 series touch this section”), “{Section} RSS” link.
3. **Filter row** §4.26 — `ttm/tag-filter` block: top 5 tags used within the category; active state from `?tag=`. Right: “Newest first”.
4. **Body** — grid 8/4 gap 64, padding 8 0 48.
   - Left: `ttm/archive-by-year` — a Query Loop wrapper that inserts a year group header §4.27 whenever the year changes (plugin renders the year label via `ttm/year-divider` inner block or a render filter on `core/post-template`). Rows: date, title, excerpt (dek), meta (read time · tags or series position). 12 posts/page. Pagination §4.28 with year-range labels.
   - Right aside (padding-top 24, flex gap 28): **Series in {Section}** — `ttm/series-list` filtered to series that contain posts in this category, rows §4.17 (15px). **Most read** — `ttm/most-read` numbered §4.29 (3, source: plugin view counts or a manual `featured_in_section` flag — `07`).
5. Footer.

### Responsive
- ≤ 1024: aside below the list as two columns (1fr 1fr) then single at 720. Archive header stacks (meta under description).
- ≤ 720: H1 44px; year label 24px inline above rows (grid `120px 1fr` → single column, year as a 2px-ruled heading); rows `auto 1fr` with date 12px; filter row scrolls horizontally.

### Fallbacks
F15 (year with 1 post), F16 (no tags → no filter row), F13 (small category: everything renders — the archive is complete by definition).

---

## F. Series hub — `1f`
Two templates: `page-series.html` (index at `/series/`) and `taxonomy-series.html` (one series). The prototype `1f` shows the **index** with a featured series; a single-series page reuses the featured block full-width (title, dek, progress, buttons, part list) plus “Related series” below. Purpose: hold multi-part work together across categories.

### Zones (index)
1. Masthead (inner), Series active.
2. **Header** — grid 8/4 align end, padding 40 0 28: H1 “Series” `display-xl`, description 17px (static, editable); right: “11 series · 3 in progress” + “Spanning Technology, Security, Faith, Business, Writing” (bound `ttm/series-stats`).
3. **2px rule.**
4. **Featured** §4.38 — `ttm/series-featured`: the in-progress series with the most recent part. Grid 5/7. Right: part list with dates (published) and scheduled dates (unpublished, neutral-700, unlinked).
5. **2px rule.**
6. **All series** — cell heading / “Sorted by last update”. `ttm/series-list` all, layout `grid-2`, rows: square by status, title 20, dek 14 (44ch), categories line 12px, right col: count (“3 of 6” / “4 parts”) over status word.
7. Footer.

### Zones (single series `taxonomy-series.html`)
1. Masthead (inner), Series active (or the series’ dominant category).
2. Header: kicker “{status} · {categories}”, H1 series name `display-xl` (max 16ch), dek, progress + meta, buttons “Start at part 1” / “Follow this series”.
3. 2px rule. Full-width ordered part list (`40px 1fr auto` rows at 18px, with 14px dek line per part).
4. 2px rule. “Other series” (4 rows §4.17).
5. Footer.

### Responsive
- ≤ 1024: featured 5/7 → stacked; all-series grid → 1 col.
- ≤ 720: H1 44px; part list rows `28px 1fr` with date on a second line.

### Fallbacks
F4/F5 (nothing in progress), zero series (index shows header + “All series” only; if truly empty, the nav hides “Series” via the plugin’s `wp_nav_menu_items` filter — F18).

---

## G. Missing-content states — `3c`
Not a screen; the reference render for `06-fallbacks.md`. Each frame shows a real fallback at component scale.

---

## H. Not drawn — build from the system
- **Tag archive / date archive** (`archive.html`): the `1e` layout with kicker “Tag” / “Month”, no filter row, no aside series section (keep Most read).
- **Search results** (`search.html`): `1e` layout; header H1 “Search” with the query as the description line; rows as archive rows with the matched section as kicker; core Search block (styled `.input` + `.btn-secondary`) under the header.
- **404**: inner masthead; H1 “Not here.” `display-xl`; paragraph 17px; core Search block; then the front page’s **Series strip** and a 4-item “Latest” list. Footer.
- **Static page** (`page.html`): inner masthead; H1 56px; body §4.24 in an 8-col column with an empty aside; footer.
- **About page**: `page.html` + a colour portrait (`core/image`, 3:2, full column) above the H1 — optional, the owner supplies it.
- **Newsletter landing** (`/newsletter/`): `page.html` with the poster §4.33 immediately under the masthead, then body copy explaining cadence, then the last 6 issues if an archive exists (`07`).
