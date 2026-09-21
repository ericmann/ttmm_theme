# 03 · Content model & editorial rules

## 1. Sections (categories)
Seven top-level `category` terms. Slugs must be stable; the theme’s nav and cells key on them.

| Section | Slug | Front-page cell | Weight | Notes |
|---|---|---|---|---|
| Technology | `technology` | 1 featured w/ image + 2 | span 2 | Default lead source |
| Business | `business` | 1 + 2 | 1 | |
| Faith | `faith` | 1 + 1 | 1 | Verse box is *not* tied to this category |
| Journal | `journal` | Rail (3 excerpts) | rail | Own single template; excluded from lead |
| Writing | `writing` | Writing cell (serial + list) | span 2 | Fiction chapters and stories live here as posts |
| Security | `security` | 1 + 2 | 1 | |
| Opinion | `opinion` | 1 + 1 | 1 | **Politics** (24 posts) becomes a child category `opinion/politics` (or tag `politics`); cells show “· Politics” in meta |

Nav order is fixed: Technology, Business, Faith, Journal, Writing, Security, Opinion, then “Series” (hub). Order is by post volume at design time; it is a menu, not computed.

**Primary category.** A post may have several categories. The plugin stores `ttm_primary_category` (term ID) — defaults to the first assigned section in nav order; editable in the post sidebar. Kickers, single-template selection, breadcrumb-like context (“More in Technology”), and cell exclusion use the primary category.

## 2. Post types
Everything readable is `post`. No CPT for fiction chapters — they are posts in Writing so they appear in feeds, search and archives, and so the block editor experience is identical. Structured data lives in taxonomies and meta owned by the plugin.

## 3. Series (taxonomy `series`)
Non-hierarchical taxonomy on `post`, `show_in_rest`, public, rewrite `series/{slug}`.

Term meta (all registered with `show_in_rest`):
| Key | Type | Meaning |
|---|---|---|
| `ttm_status` | enum `in-progress` \| `complete` \| `hiatus` | Drives square colour, status word, featured eligibility |
| `ttm_total_parts` | int | Planned parts (M in “N of M”). 0/empty = open-ended → UI shows “N parts” |
| `ttm_form` | enum `nonfiction` \| `novel` \| `novella` \| `story-cycle` | `nonfiction` = a Series; anything else = a **Serial** (fiction) |
| `ttm_genre` | string | Free text shown in the form line (“literary thriller”) |
| `ttm_cadence` | string | “Monthly”, “Sundays” — display only |
| `ttm_next_date` | date | Next scheduled part; shown as “next part Sept 26” / “next: Oct 17” |
| `ttm_cover_id` | attachment ID | Optional cover image (serials) |
| `ttm_featured` | bool | Pin as the hub’s featured series (overrides auto-pick) |
| `ttm_purchase_links` | JSON `[{label,url}]` | “Buy the paperback”, “Ebook” |

Post meta:
| Key | Type | Meaning |
|---|---|---|
| `ttm_series_part` | int | Position within the series (1-based). Required when a `series` term is set; plugin validates uniqueness per series and warns in the editor |
| `ttm_part_title` | string | Optional short title for TOC/prev-next (“Reconciliation” instead of the full post title) |

Rules:
- A post belongs to **at most one** series (enforced in the editor UI: single-select).
- A series can span any categories. Its “categories line” on the hub = distinct primary categories of its parts, in nav order.
- “Published” count = published posts in the series. “N of M” uses `ttm_total_parts`; if a scheduled (future) post exists for a part, the TOC shows it unlinked with its scheduled date.
- Series last-update = most recent part’s publish date; hub sort key.
- Featured series (hub, front) = `ttm_featured` if set, else the in-progress series with the newest part; else most recently completed (fallback F5).

## 4. Fiction
- **Serial** = `series` term with `ttm_form ≠ nonfiction`. Chapters = its posts (category Writing), `ttm_series_part` = chapter number.
- **Story** = a Writing post with post meta `ttm_form = story` (set automatically when the post is in Writing and has no series; editable). Meta `ttm_word_count` computed on save.
- **Book** = an entry in the plugin’s `ttm_books` option (simple repeater: title, form, year, cover ID, formats, purchase links, optional linked serial). Rendered by `ttm/book-grid`. This avoids a CPT for a handful of items; if the list grows past ~10, promote to a CPT (`07`).
- Covers are optional everywhere. Absence collapses the layout, never shows a placeholder.
- Cadence is descriptive (“Monthly”); nothing is scheduled automatically.

## 5. Journal
- Category `journal`. Titled. Body typically < 300 words.
- Post meta: `ttm_syndication` JSON `{ x: url, mastodon: url, bluesky?: url }`; `ttm_location` string (“Portland”).
- Excerpt: if the manual excerpt is empty, the plugin derives ~40 words ending at a sentence boundary (no “…” when the sentence ends). This derived excerpt is what the rail shows.
- Journal posts are **excluded** from: lead selection, section cells, “Latest” lists, and the site’s main RSS *only if the owner chooses* (`07`); they have their own feed at `/category/journal/feed/`.

## 6. Verse of the day
- Source: `GET https://dailymedtoday.com/api/v1/meditations/` (most recent meditations indexed by day). The plugin must inspect the real payload shape during build; assume each item has at least a date, a verse text, a scripture reference, and a URL/slug.
- Stored as option `ttm_verse` `{ date, text, reference, url, fetched_at }` plus `ttm_verse_history` (last 30, for the fallback).
- Fetch daily via WP-Cron at 05:00 site time with a retry at 07:00 on failure; also refresh on demand via WP-CLI `wp ttm verse fetch`.
- Attribution is mandatory: “Meditation for {date} from dailymedtoday.com” linking to the item URL (or site root).
- Never rendered blank; see F6.

## 7. Lead selection (front page)
1. If a post is **sticky** and published within 30 days → lead.
2. Else newest published post whose primary category is Technology.
3. If that post is ≥ 30 days old → newest published post site-wide excluding Journal.
Lead ID is excluded from all cells. Kicker shows the lead’s primary category and, if in a series, “Series: {name}, part N of M”. Meta line links to the previous part if one exists.

## 8. Cell queries
`category = {section} AND post_status = publish AND ID != lead AND primary_category = {section}` ordered by date desc, `posts_per_page` as in §1. Posts whose primary category is elsewhere do not appear in a secondary category’s cell (prevents duplicates across the grid). Counts (“431 articles →”) = published posts with that category (any position).

## 9. Excerpts / deks
- Article dek = manual excerpt. Editors should write one; the plugin adds a pre-publish check (“No dek”) for non-Journal posts.
- Cell deks use the same excerpt, CSS-clamped to 3 lines.
- Archive rows show the excerpt in full (max 56ch wide, no clamp).

## 10. Reading time & word count
`ttm_word_count` on save (strip blocks/shortcodes/code blocks). Reading time = ceil(words / 230) min, shown as “14 min read” (article), “14 min” (lists). Not shown for Journal; Journal shows word count.

## 11. Tags
Free tags as today. Only surfaced in: article byline row (all tags), archive filter row (top 5 within the category), archive row meta (up to 2). Never on the front page.

## 12. Newsletter
Weekly. Provider TBD (`07`). Two placements: poster (front page, newsletter page) and box (article aside, series pages). Copy is fixed in the patterns: poster “Everything above, once a week, in your inbox.”; box “Series land in the newsletter the week they publish.” (series contexts) / “The weekly issue, every Sunday.” (non-series contexts — verify day with owner).

## 13. Dates
- Lists: `M j` (“Sept 19” — note the 4-letter “Sept”; WordPress `M` gives “Sep”. Plugin provides `ttm_short_month()` mapping Sep→Sept, and binding `ttm/short-date`).
- Posts: `F j, Y`.
- Compact mastheads: `D, M j, Y` with the same Sept fix.
- Relative day names in the rail for the last 6 days (“Today”, “Yesterday”, “Thursday”), then `M j`; beyond the current year add `, Y`.

## 14. Migration notes (from Powder / current content)
- Map `Politics` → child of Opinion (or tag); keep the old URLs via redirect.
- Create `series` terms from any existing “series” tag/category convention (owner to list them); assign `ttm_series_part` by publish order as a first pass, then hand-correct.
- Set `ttm_primary_category` for all posts (first section in nav order).
- Compute `ttm_word_count` for all posts (WP-CLI `wp ttm recount`).
- Existing featured images stay; the theme applies grayscale via CSS, not by editing images.
