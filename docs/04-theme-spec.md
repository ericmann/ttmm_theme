# 04 · Theme spec — `ttm-theme`

A block theme (FSE). Greenfield is preferred over a Powder child: Powder’s presets, spacing and typography would fight the Modernist tokens at every turn and the templates are all new anyway. Requires WP 6.5+ (block bindings), PHP 8.1+.

## 1. Files
```
ttm-theme/
  style.css              (header only + minimal resets that theme.json can't express)
  theme.json
  functions.php          (enqueue, block styles, pattern categories, image sizes, template hierarchy hooks)
  templates/
    front-page.html  single.html  single-journal.html  page.html  page-series.html  page-writing.html
    category.html  category-journal.html  taxonomy-series.html  archive.html  search.html  404.html  index.html
  parts/
    header-front.html  header-inner.html  rail.html  footer.html
  patterns/            (PHP files with pattern headers; all inserter-visible where marked)
    masthead-front.php  masthead-inner.php  lead-story.php  journal-rail.php  section-cell.php
    section-cell-large.php  section-row-1.php  section-row-2.php  series-strip.php  newsletter-poster.php
    newsletter-box.php  article-header.php  more-in-section.php  archive-header.php  filter-row.php
    journal-stream.php  pull-quote.php  code-figure.php  stat-row.php
  assets/
    fonts/archivo-{400,600,800,400i}.woff2
    css/ttm.css        (component classes from 01 §4; loaded front + editor)
    css/editor.css     (editor-only chrome)
    js/nav.js          (phone overlay toggle only; core Navigation handles most)
  inc/
    block-styles.php  bindings-compat.php  template-hierarchy.php  image-sizes.php  patterns.php
```

## 2. `theme.json` (v3) — required settings
```jsonc
{
  "settings": {
    "appearanceTools": true,
    "useRootPaddingAwareAlignments": true,
    "layout": { "contentSize": "1280px", "wideSize": "1280px" },
    "color": {
      "defaultPalette": false, "defaultGradients": false, "gradients": [], "duotone": [],
      "palette": [ /* bg, surface, text, accent, accent-600, accent-700, divider, neutral-100…900 — values from styles.css */ ]
    },
    "typography": {
      "fluid": false, "defaultFontSizes": false, "dropCap": false,
      "fontFamilies": [ { "slug": "archivo", "name": "Archivo", "fontFamily": "Archivo, 'Helvetica Neue', Arial, sans-serif", "fontFace": [ /* 400, 600, 800, 400 italic */ ] } ],
      "fontSizes": [ /* slugs: micro 11, caption 12, ui 13, body-s 14, dek 15, headline-s 17, body 18, synopsis 19, cell-lead 21, h2 30, pull 28, featured 40, lead 44, h1 56, display-m 64, display-l 76, display-xl 80 */ ]
    },
    "spacing": { "defaultSpacingSizes": false, "spacingSizes": [ /* 10:4 20:8 30:12 40:16 50:24 60:32 70:40 80:48 90:64 100:96 */ ], "units": ["px","em","rem","ch","%"] },
    "border": { "radius": false, "color": true, "style": false, "width": true },
    "shadow": { "defaultPresets": false, "presets": [ { "slug": "cover", "shadow": "0 12px 32px rgba(45,43,43,.22)" } ] },
    "custom": {
      "rule": { "thin": "1px solid var(--wp--preset--color--divider)", "thick": "2px solid var(--wp--preset--color--divider)" },
      "measure": { "body": "38em", "dek": "32em" },
      "gutter": { "desktop": "48px", "phone": "20px" }
    }
  },
  "styles": {
    "color": { "background": "var(--wp--preset--color--bg)", "text": "var(--wp--preset--color--text)" },
    "typography": { "fontFamily": "var(--wp--preset--font-family--archivo)", "fontSize": "var(--wp--preset--font-size--body)", "lineHeight": "1.65" },
    "spacing": { "padding": { "left": "var(--wp--custom--gutter--desktop)", "right": "var(--wp--custom--gutter--desktop)" }, "blockGap": "0" },
    "elements": {
      "link": { "color": { "text": "var(--wp--preset--color--text)" }, "typography": { "textDecoration": "none" }, ":hover": { "color": { "text": "var(--wp--preset--color--accent)" } }, ":focus": { "outline": "2px solid var(--wp--preset--color--accent)" } },
      "heading": { "typography": { "fontWeight": "800", "letterSpacing": "-0.02em", "lineHeight": "1.1" } },
      "h1": { "typography": { "fontSize": "var(--wp--preset--font-size--h1)", "lineHeight": "1.02", "letterSpacing": "-0.025em" } },
      "h2": { "typography": { "fontSize": "var(--wp--preset--font-size--h2)" } },
      "h6": { "typography": { "fontSize": "var(--wp--preset--font-size--caption)", "textTransform": "uppercase", "letterSpacing": "0.08em" } },
      "button": { "border": { "radius": "0" }, "typography": { "fontWeight": "800", "fontSize": "var(--wp--preset--font-size--body-s)" }, "color": { "background": "var(--wp--preset--color--accent)", "text": "var(--wp--preset--color--bg)" }, ":hover": { "color": { "background": "var(--wp--preset--color--accent-600)" } } },
      "caption": { "typography": { "fontSize": "var(--wp--preset--font-size--caption)" }, "color": { "text": "var(--wp--preset--color--neutral-700)" } }
    },
    "blocks": {
      "core/separator": { "color": { "background": "var(--wp--preset--color--divider)", "text": "var(--wp--preset--color--divider)" }, "border": { "width": "0" }, "css": "height:1px;opacity:1" },
      "core/code": { "color": { "background": "var(--wp--preset--color--neutral-900)", "text": "var(--wp--preset--color--neutral-100)" }, "typography": { "fontSize": "14px", "lineHeight": "1.55", "fontFamily": "ui-monospace, Menlo, monospace" }, "spacing": { "padding": { "top": "18px", "right": "20px", "bottom": "18px", "left": "20px" } }, "border": { "left": { "width": "4px", "color": "var(--wp--preset--color--accent)" } } },
      "core/quote": { "border": { "left": { "width": "2px", "color": "var(--wp--preset--color--text)" } }, "spacing": { "padding": { "left": "20px" } } },
      "core/pullquote": { "css": "text-align:left;border:0;padding:0" },
      "core/post-featured-image": { "border": { "radius": "0" } },
      "core/image": { "border": { "radius": "0" } },
      "core/navigation": { "typography": { "fontWeight": "600", "fontSize": "var(--wp--preset--font-size--body-s)" } },
      "core/search": { "css": "& .wp-block-search__input{border:1px solid var(--wp--preset--color--divider);border-radius:0;background:var(--wp--preset--color--bg)} & .wp-block-search__button{border-radius:0}" },
      "core/post-comments-form": { "css": "& input,& textarea{border-radius:0;border:1px solid var(--wp--preset--color--divider)}" }
    }
  }
}
```
Body resets in `style.css`: `*{box-sizing:border-box}`, `img{display:block;max-width:100%;height:auto}`, `::selection`, `:focus-visible`, `text-wrap` rules, `font-feature-settings` utility classes. Phone gutter via `@media (max-width:720px){ body{ --wp--custom--gutter--desktop: var(--wp--custom--gutter--phone) } }`.

## 3. Block styles (register in `inc/block-styles.php`)
| Block | Style slug | Effect |
|---|---|---|
| `core/separator` | `rule-2` | 2px height |
| `core/separator` | `rule-1` | 1px height (default) |
| `core/image`, `core/post-featured-image` | `grayscale` | `filter: grayscale(1) contrast(1.08)` on the img; caption unaffected |
| `core/image`, `core/post-featured-image` | `cover` | `aspect-ratio: 2/3; object-fit: cover` |
| `core/image`, `core/post-featured-image` | `cover-shadow` | as `cover` + `--shadow-lg` |
| `core/group` | `poster` | accent bg, bg-colour text, padding 36 48 32 (24 20 phone); headings bg colour |
| `core/group` | `surface-box` | surface bg, padding 18 20 |
| `core/group` | `tile` | surface bg, aspect 4/3, padding 16, flex column space-between |
| `core/group` | `zone` | padding 20 0 24, `border-top: 2px` |
| `core/group` | `cell` | padding 20 32 24 0, `border-right: 1px`; `:last-child` no border |
| `core/group` | `grid-4` | `display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap: 0 32px` + responsive collapse |
| `core/group` | `grid-8-4`, `grid-3-7-2`, `grid-5-7`, `grid-7-5`, `grid-3`, `grid-2` | the tracks in `01 §3` with their gaps and breakpoints |
| `core/group` | `span-2` | `grid-column: span 2` |
| `core/group` | `sticky-aside` | `position: sticky; top: 24px` ≥ 1024 |
| `core/heading` | `cell-heading` | h6 look + flex space-between when it has a trailing link (implemented as Group `cell-heading` containing Heading + Paragraph) |
| `core/paragraph` | `kicker` | 12/600 uppercase +0.08em accent-700 |
| `core/paragraph` | `dek` | 17/1.5 neutral-800 max 60ch (`dek-l` 21/1.4 max 32em) |
| `core/paragraph` | `meta` | 12 neutral-700 tnum |
| `core/paragraph` | `micro` | 11 neutral-600 |
| `core/paragraph` | `display-xl` / `display-l` / `display-m` / `lead` / `poster` | the display sizes with optical margin |
| `core/post-title`, `core/heading` | `lead`, `cell-lead`, `cell-lead-l`, `headline-s`, `journal-title`, `journal-date` | per type scale |
| `core/post-date` | `journal-date`, `short` | 48px big date / “Sept 19” |
| `core/quote` | `pull` | pull-quote treatment (28/800, hanging quote, no rule) |
| `core/buttons` / `core/button` | `primary` (default), `secondary`, `ghost`, `ghost-on-poster`, `block` | per `01 §4.30` |
| `core/post-terms` | `kicker`, `tags` | kicker line / `.tag.tag-neutral` chips |
| `core/list` | `numbered-rows` | ordered rows with neutral-500 numbers and 1px rules |

Prefer Group styles + inner core blocks over custom blocks wherever the content is static or a plain Query Loop.

## 4. Block variations (theme-side JS, `assets/js/variations.js`)
- `core/query` → `ttm/section-query` (preset: 3 posts, category from a `sectionSlug` attribute, `ttm_exclude_lead` flag, `ttm_primary_only` flag — the flags are read by the plugin’s `pre_render_block`/`query_loop_block_query_vars` filter).
- `core/query` → `ttm/journal-query` (category journal, 3, excerpt 40).
- `core/navigation` → `ttm/sections-nav` (locked item order = section slugs + Series; theme provides a `wp_navigation` post seed on activation).

## 5. Templates — block trees (abridged; full trees follow the zone lists in `02-screens.md`)

### `front-page.html`
```
<!-- wp:template-part {"slug":"header-front"} /-->
<!-- wp:group {"className":"is-style-grid-8-4 ttm-lead-row"} -->
  <!-- wp:pattern {"slug":"ttm/lead-story"} /-->
  <!-- wp:template-part {"slug":"rail"} /-->
<!-- /wp:group -->
<!-- wp:separator {"className":"is-style-rule-2"} /-->
<!-- wp:pattern {"slug":"ttm/section-row-1"} /-->   (grid-4: section-cell-large technology span-2 · section-cell business · section-cell security)
<!-- wp:separator {"className":"is-style-rule-2"} /-->
<!-- wp:pattern {"slug":"ttm/section-row-2"} /-->   (grid-4: section-cell faith · section-cell opinion · ttm/writing-cell span-2)
<!-- wp:separator {"className":"is-style-rule-2"} /-->
<!-- wp:pattern {"slug":"ttm/series-strip"} /-->
<!-- wp:pattern {"slug":"ttm/newsletter-poster"} /-->
<!-- wp:template-part {"slug":"footer","className":"is-after-poster"} /-->
```
Patterns are **unsynced** copies at insert time so the owner can rearrange cells in the Site Editor; the row Groups carry `"lock":{"move":false,"remove":false}` on the 2px separators only.

### `single.html`
```
header-inner · ttm/series-bar · group.grid-8-4 [ article: pattern article-header · post-featured-image(colour) · post-content · ttm/series-prev-next ] [ aside.sticky-aside: ttm/series-toc · pattern more-in-section · pattern newsletter-box ] · footer
```
### `single-journal.html`
```
header-inner · group.grid-3-7-2 [ kicker · post-date.journal-date · paragraph(bound ttm/journal-subline) ] [ post-title.journal-title · post-content · ttm/syndicated-to ] [ paragraph(note) · paragraph(RSS link) ] · separator.rule-2 · pattern journal-stream · footer
```
### `category.html`
```
header-inner · pattern archive-header · ttm/tag-filter · group.grid-8-4 [ ttm/archive-by-year (wraps core/query 12/page) · query-pagination ] [ aside: ttm/series-list(in-category) · ttm/most-read ] · footer
```
`category-journal.html` = header-inner · archive-header (kicker “Section”, H1 Journal) · journal-stream rows full width (20/page) · footer.
### `taxonomy-series.html`, `page-series.html`, `page-writing.html`
Per `02 §F` and `02 §D`; all `ttm/*` blocks with theme Groups for rules and grids.
### `archive.html`, `search.html`, `404.html`, `page.html`
Per `02 §H`.

## 6. Template parts
- `header-front` — pattern masthead-front (Site Title, Site Tagline, Navigation `ttm/sections-nav`, date via `ttm/today` binding, byline bound to `ttm/author-name` `format: byline-link`).
- `header-inner` — pattern masthead-inner. The theme adds `.current-section` to the nav item matching `ttm_primary_category` (plugin filter on `render_block_core/navigation-link`, or theme JS as a fallback); its own byline is bound to `ttm/author-name` `format: by`.
- `rail` — `ttm/verse-of-the-day` + pattern journal-rail.
- `footer` — static links; `is-after-poster` variant drops the top rule; its own binding source calls `Values::footer_line()`, which reads `Config::author_name()` directly (not a separate `ttm/author-name` binding) — so the owner's name is a literal string in exactly one place, `Config.php`, not in either masthead pattern or the footer part.

## 7. Editor experience
- `editor.css` loads `ttm.css` so patterns look identical in the Site Editor.
- Pattern categories: `ttm-front`, `ttm-article`, `ttm-lists`, `ttm-fiction`, `ttm-marketing`.
- Template locking: templates are unlocked; template *parts* `header-*` and `footer` are `contentOnly` locked.
- Post editor: `post-content` styles = `01 §4.24`; the editor canvas width is 8/12 of 1280 (≈ 820px) so the measure matches the front end. Provide the `pull` quote style and `code-figure` pattern (code block + caption) in the inserter.
- Disable: drop cap, gradients, duotone, custom colours (`"custom": false` on color), font size custom values (allow only presets), border radius UI.
- Starter content on activation: create the seven categories if missing, the “Series”, “Writing”, “Newsletter”, “About” pages with their templates assigned, and a `wp_navigation` post with the section items.

## 8. Performance & a11y budget
- Fonts: 3–4 woff2 files, preload 400 and 800. No Google Fonts request.
- CSS: `ttm.css` ≤ 25 KB; no framework. Critical rules inline is unnecessary at this size.
- JS: only the phone nav toggle (~1 KB) and Navigation block’s own script.
- LCP element is the lead image (front) / H1 (article); mark the lead image `fetchpriority="high"`.
- Skip link to `#main`; landmarks: `header`, `nav[aria-label="Sections"]`, `main`, `aside[aria-label]`, `footer`.
- Every whole-row link (`ttm-item`, archive rows, series rows) must be a single `<a>` with the headline as its accessible name; meta inside is `aria-hidden` where duplicated.
- Colour contrast per `01 §2.1`; run axe on all seven templates before hand-off.

## 9. Theme ⇄ plugin contract
- Theme templates and patterns may reference `ttm/*` blocks. If `ttm-core` is inactive, WordPress renders unregistered dynamic blocks as nothing; the theme must not error (no PHP calls into plugin classes without `function_exists`/`class_exists` guards).
- Theme reads plugin data **only** through blocks and block bindings — never `get_term_meta` directly in templates.
- Plugin blocks output semantic HTML with `ttm-*` classes and **no colours or sizes inline**; all styling comes from `ttm.css`. Layout-critical inline styles allowed: `grid-column: span 2`, progress segment widths, `aspect-ratio`.
- Plugin exposes block bindings (`05 §4`) used by the theme in core Paragraph/Heading blocks.
- Versioned contract: plugin declares `TTM_CORE_API = 1`; theme checks and shows an admin notice if mismatched.
