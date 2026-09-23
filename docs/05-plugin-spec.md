# 05 · Plugin spec — `ttm-core`

Owns every piece of data and behaviour that must outlive the theme. PHP 8.1+, WP 6.5+. Namespace `TTM\Core`. Blocks built with `@wordpress/scripts` (`block.json` per block, `render.php` for dynamic output). No front-end React; all `ttm/*` blocks are **server-rendered**; editor-side JS only for controls and previews.

## 1. Modules
```
ttm-core/
  ttm-core.php
  src/
    Taxonomy/Series.php          register taxonomy + term meta + REST + admin columns
    Meta/PostMeta.php            series_part, part_title, primary_category, form, word_count, syndication, location
    Meta/PrimaryCategory.php     sidebar panel + default resolver
    Fiction/Serials.php          queries: active serial, serial stats, chapters
    Fiction/Books.php            ttm_books option + settings screen
    Verse/Fetcher.php            HTTP client, parser, storage, cron, CLI
    Verse/Block.php
    Query/Lead.php               lead selection + caching
    Query/Cells.php              section queries, primary-only, exclude-lead (query loop filters)
    Query/Archive.php            year grouping, tag filter, pagination labels
    Bindings/Sources.php         block-binding sources (§4)
    Blocks/…                     one folder per block (§3)
    Editor/Checks.php            pre-publish checks (dek, alt, series part)
    Editor/Sidebar.js            series picker (single), part number, form, syndication, location
    Templates/Hierarchy.php      single-journal template routing; writing category → page template
    Nav/CurrentSection.php       adds current-section class; hides “Series” when zero terms
    CLI/Commands.php             wp ttm verse fetch | recount | migrate-politics | series:assign
    Compat/Theme.php             API version constant + notice
```

## 2. Data model (see `03-content-model.md` for semantics)
- Taxonomy `series` on `post`: `hierarchical=false`, `show_in_rest=true`, `rewrite=['slug'=>'series']`, `show_admin_column=true`. Term meta registered via `register_term_meta` with sanitizers and `show_in_rest` (`ttm_status`, `ttm_total_parts`, `ttm_form`, `ttm_genre`, `ttm_cadence`, `ttm_next_date`, `ttm_cover_id`, `ttm_featured`, `ttm_purchase_links`).
- Post meta via `register_post_meta('post', …, ['single'=>true,'show_in_rest'=>true])`: `ttm_series_part` (int), `ttm_part_title`, `ttm_primary_category` (int), `ttm_form` (enum: article|story|chapter — derived, editable), `ttm_word_count` (int, computed on `save_post`), `ttm_syndication` (object), `ttm_location` (string).
- Options: `ttm_verse`, `ttm_verse_history` (array, max 30), `ttm_books` (array), `ttm_settings` (newsletter provider config, journal-in-main-feed flag, lead rules overrides).
- Transients: `ttm_lead_id` (5 min), `ttm_series_index` (1 h, flushed on any post/term save), `ttm_category_stats_{id}` (1 h), `ttm_top_tags_{cat}` (12 h).

Validation: `ttm_series_part` required when a series term is set; unique per series (soft — warn in editor via pre-publish check, never block save). A post may have only one `series` term (editor UI single-select; server trims to the first on save).

## 3. Blocks (`ttm/*`) — all dynamic, `render.php`
Every block: `supports: { html:false, align:false, color:false, typography:false, spacing:false }` — styling is the theme’s. Each has an editor preview using `ServerSideRender`. Attributes listed are editor-facing.

| Block | Attributes | Renders | Fallback (→ `06`) |
|---|---|---|---|
| `ttm/series-bar` | — (context: postId) | `01 §4.18` bar with progress segments; “Series” word and link hidden ≤ 720 via classes | No series term → empty string |
| `ttm/series-toc` | `variant: series\|chapters`, `limit`, `order: asc\|desc`, `showDek`, `heading` | `01 §4.20` list; current part highlighted (context postId); scheduled parts unlinked with `title` date | No series → empty |
| `ttm/series-prev-next` | `mode: auto\|series\|chronological` | `01 §4.21`; `auto` = series when available else chronological within `ttm_primary_category` | Missing side keeps structure with empty cell |
| `ttm/series-progress` | `seriesId` (optional; context termId) | Segment bar + meta line | Open-ended series (`total_parts`=0) → bar shows published count as equal segments, meta “N parts” |
| `ttm/series-list` | `status: any\|in-progress\|complete\|hiatus`, `form: any\|nonfiction\|fiction`, `inCategory` (bool: current category context), `limit`, `layout: rows\|grid-2\|grid-3`, `orderby: updated\|title\|started`, `showDek`, `showCategories`, `showCount` | `01 §4.17` rows | F4: if `status=in-progress` yields 0 → heading text (via `data-ttm-empty-heading`) becomes “Series” and query falls back to `complete`; zero series total → empty |
| `ttm/series-featured` | `seriesId` (optional), `partsLimit` | `01 §4.38` featured block | F5: no in-progress → most recently completed |
| `ttm/series-stats` | — | “11 series · 3 in progress” + “Spanning …” | Zero → empty |
| `ttm/writing-cell` | `alsoRunningLimit` (3) | `01 §4.15` | F1: no active serial → “From the shelf” list; F2: no serials/stories at all → renders a standard section-cell query for Writing |
| `ttm/serial-hero` | `seriesId` (optional) | `02 §D` hero: cover, kicker, title, synopsis, buttons, stat row | No in-progress → most recently completed (“Complete · 31 chapters”; buttons “Read chapter 1” + purchase links); no cover → single-column |
| `ttm/story-tiles` | `limit`, `columns` | `01 §4.35` tiles for Writing posts with `ttm_form=story` | 0 stories → empty (theme omits heading via `:has()`/wrapper class) |
| `ttm/book-grid` | `columns` | `01 §4.36` covers from `ttm_books` | 0 → empty |
| `ttm/verse-of-the-day` | `compact` (bool) | `01 §4.9` | F6: last good verse with its own date; no history → empty |
| `ttm/syndicated-to` | — | `01 §4.13` | No URLs → empty |
| `ttm/tag-filter` | `limit` (5) | `01 §4.26`; links `?tag=slug` on the current category URL; active from `get_query_var('tag')` | 0 tags → empty |
| `ttm/archive-by-year` | inner: `core/query` | Wraps a Query Loop and injects year headers when the year changes (via `render_block_core/post-template` filter scoped to this wrapper’s inner block) | — |
| `ttm/most-read` | `limit` (3), `source: views\|manual` | `01 §4.29` | Fewer → what exists; 0 → empty |
| `ttm/category-stats` | — (context: term) | “36 articles · 2014–2026” / “4 series touch this section” | — |
| `ttm/lead-story` | `imageRatio` | Optional alternative to the pattern: one block that runs lead selection and renders `01 §4.7` (so the theme pattern can be either core-query + bindings or this block — build team’s call; **default: this block** for reliability of lead rules and F8) | F8: no image → step-up headline |

Shared render helpers: `ttm_date_short()`, `ttm_reading_time()`, `ttm_series_position()`, `ttm_status_word()`, `ttm_kicker()`.

## 4. Block-binding sources (`register_block_bindings_source`)
Used by the theme in core Paragraph/Heading/Button blocks so most chrome stays core.

| Source | Args | Value |
|---|---|---|
| `ttm/kicker` | — (postId context) | “Technology · Series: Hardening WordPress, part 3 of 6” |
| `ttm/meta-line` | `parts: [date, reading, prev-part]` | “Sept 19 · 14 min read · Part 2: …” (prev-part is a link — binding supplies rich text via `core/paragraph` `content`) |
| `ttm/short-date` | — | “Sept 19” |
| `ttm/relative-date` | — | “Today · Sept 20” / “Thursday · Sept 18” / “Aug 3, 2026” |
| `ttm/reading-time` | `format: long\|short` | “14 min read” / “14 min” |
| `ttm/word-count` | — | “248 words” |
| `ttm/journal-subline` | — | “Sunday · Portland” |
| `ttm/category-count` | `category`, `format: "%d articles →"\|"%d →"` | “431 articles →” |
| `ttm/today` | `format` | Masthead date |
| `ttm/pagination-label` | `dir: older\|newer` | “Older (2014–2022) →” |
| `ttm/series-name`, `ttm/series-part` | — | For custom layouts |

## 5. Query behaviour (filters)
- `query_loop_block_query_vars`: when the Query block has `ttm_primary_only`, add a meta query `ttm_primary_category = {category}`; when `ttm_exclude_lead`, add `post__not_in = [lead]`; always exclude Journal from non-journal section queries.
- `pre_get_posts` on category archives: 12 per page; `?tag=` narrows within the category; Journal category 20 per page.
- Lead selection `Query/Lead.php` per `03 §7`, cached 5 min, flushed on publish.
- Word count on `save_post` (skip autosave/revision), strip `core/code` content and shortcodes.

## 6. Verse of the day — `Verse/Fetcher.php`
- Endpoint: `https://dailymedtoday.com/api/v1/meditations/`. On build, `wp ttm verse inspect` dumps the raw payload; map fields into `{date, text, reference, url}`. Expect a list keyed/sorted by day; pick the entry for today in site TZ, else the newest ≤ today.
- Schedule: `ttm_verse_fetch` daily at 05:00 site time; on failure schedule a single retry at +2h; a second failure logs to `ttm_verse_log` (last 20) and keeps the previous value.
- HTTP: `wp_remote_get` timeout 8s, UA “TTM-Core/1.0 (+https://eric.mann.blog)”; respect `ETag`/`Last-Modified` if present.
- Storage: `ttm_verse` current; push previous into `ttm_verse_history` (cap 30).
- Sanitize text (`wp_kses` minimal: `em`, `strong`), curly-quote it if the API returns straight quotes.
- Rendering rules in `01 §4.9`; attribution link **always** present.
- Admin: Settings → These Things Matter → Verse: shows current value, last fetch, log, “Fetch now” button.

## 7. Newsletter
Provider-agnostic form handler: settings store `provider` (buttondown | mailchimp | kit | custom-url) + endpoint/API key. `ttm/newsletter-form` block (used inside the theme’s poster/box patterns) renders the input+button and posts to `admin-post.php?action=ttm_subscribe` → provider API → redirect back with `?subscribed=1` (the theme shows “Check your inbox.” inline via a small `data-state` swap; no JS required). Honeypot + nonce. If no provider configured, the block renders the form pointing at a `mailto:` fallback — or nothing, per settings.

## 8. Editor UX (`Editor/`)
- **Post sidebar panel “These Things Matter”**: Primary section (select from the seven), Series (single-select with “create new”), Part number (int, shows “of M”), Part title, Form (auto: article/chapter/story; editable), Syndication URLs (X, Mastodon, Bluesky), Location (journal only).
- **Pre-publish checks** (non-blocking): missing dek (non-Journal), missing featured-image alt, series term without part number, duplicate part number, Writing post without form.
- **Series term edit screen**: fields for all term meta; a read-only ordered part list with publish/scheduled status and “open in editor” links.
- **Books settings screen** (`ttm_books` repeater): title, form, year, cover, formats, links, linked serial.
- Admin columns on Posts: Primary section, Series (part), Words.

## 9. REST
- Term meta and post meta are exposed via `show_in_rest`.
- `GET /ttm/v1/series` → index with computed fields (published, total, status, categories, last_update, next_date) for headless or future use.
- `GET /ttm/v1/verse` → current verse (for the newsletter generator or widgets).
- `GET /ttm/v1/lead` → current lead post ID and reason (debug).

## 10. WP-CLI
`wp ttm seed [--reset] [--starter-only] [--state=quiet|empty]` (demo/starter content, refuses in production) · `wp ttm stats:flush` (clears the cached front-page/category stats transients) · `wp ttm verse fetch|inspect|log` · `wp ttm recount [--all]` (word counts) · `wp ttm primary:assign [--dry-run] [--from-yoast]` (Yoast's own primary category first, else nav-order fallback) · `wp ttm series:assign <series-slug> --from-tags=<tag,tag,…> [--from-tag=<tag>] [--form=] [--status=] [--total=] [--name=] [--dry-run]` (create/update a series from tags, number by date) · `wp ttm series:rebuild` · `wp ttm convert:export --all-classic|--post=<id> --out=<file>` → `scripts/convert-classic.mjs` → `wp ttm convert:import <file> [--dry-run] [--allow-freeform]` · `wp ttm convert:revert --post=<id>|--all` · `wp ttm audit [--format=csv] [--only=<flag,…>] [--summary]` · `wp ttm migrate:politics [--dry-run]` · `wp ttm migrate:redirects [--format=nginx]` · `wp ttm migrate:close-comments [--dry-run]` · `wp ttm migrate:syndication [--dry-run] [--post=<id>]` · `wp ttm migrate:excerpts --from=yoast [--dry-run]` · `wp ttm migrate:images --hosts=<host,host,…> [--dry-run]`.

## 11. Templates & routing (`Templates/Hierarchy.php`)
- `single_template`: if `ttm_primary_category` is Journal → `single-journal.html` (theme provides it; plugin only adds it to the hierarchy candidates so a theme without it falls back to `single`).
- Category archive for Writing → use the theme’s `page-writing.html` layout when present (filter `category_template`), else standard archive.
- Body classes: `ttm-section-{slug}`, `ttm-in-series`, `ttm-form-{article|chapter|story}`.

## 12. Feeds
- `/category/journal/feed/` unchanged. Setting “Include Journal in main feed” default **true** (`07`).
- Series feed: `/series/{slug}/feed/` works natively via taxonomy.
- Add `<link rel="alternate">` for each section feed in `wp_head` (theme reads a filterable list).

## 13. Uninstall
Keep data by default (taxonomy terms and meta persist); `uninstall.php` removes options only when `TTM_REMOVE_DATA` is defined.

## 14. Acceptance checklist
- [ ] Theme active + plugin inactive: all templates render without PHP notices; `ttm/*` areas are absent and layouts close per `06`.
- [ ] Plugin active + default theme: taxonomy, meta and blocks exist; blocks render unstyled but semantic.
- [ ] Verse: fetch succeeds; simulated failure shows last verse with its date; empty history shows nothing.
- [ ] Series: 3-part series renders bar, TOC, prev/next, hub row, featured; post without series gets chronological prev/next.
- [ ] Writing: with/without active serial; with/without covers; zero stories; zero books.
- [ ] Front page with a 60-day-old Technology post picks a site-wide lead.
- [ ] Journal: syndication line shows/hides; rail excerpt ends on a sentence.
- [ ] All screens pass axe with no serious issues; keyboard focus visible on every interactive element.
