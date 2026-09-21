# Migrating eric.mann.blog to These Things Matter

This is the runbook for moving the existing site (WordPress 7.1.1, Powder theme, Jetpack, 888 posts) onto `ttm-core` + `ttm-theme`. It is written to be rehearsed in wp-env against a real export first, then repeated on production. Every plugin command supports `--dry-run`; nothing here deletes content.

The CLI commands are specified in `docs/SPEC.md §6.7` and built in Phases 1 and 8. Flags in this document are updated by the build if they change.

## What changes, in one table

| Today | After | Mechanism |
|---|---|---|
| 7 top-level categories + `politics` (24) + `uncategorized` (0) | 7 sections; `opinion` new; `politics` becomes `opinion/politics` | `wp ttm migrate:politics` |
| Posts in several categories, no notion of "primary" | `ttm_primary_category` on every post (first section in nav order) | `wp ttm primary:assign` |
| Series expressed as tags (`boundless-summer-challenge`, `cryptopals`, …) | `series` taxonomy terms with part numbers and status | `wp ttm series:assign` |
| No word counts | `ttm_word_count` on every post | `wp ttm recount --all` |
| Pre-2016 posts are classic HTML | Block content, original kept in `ttm_classic_backup` | `convert:export` → `scripts/convert-classic.mjs` → `convert:import` |
| `modern-footnotes` plugin markup | Core footnotes | part of conversion |
| Journal entries mirrored via Jetpack Social | `ttm_syndication` URLs (best effort) | Phase 8 spike |
| Comments open on old posts | Closed everywhere | `wp ttm migrate:close-comments` |
| `/writing/` page exists; no `/series/` | Both pages with templates | theme starter content |

## 0. Before you start

1. **Decide the series list.** Write down each multi-part work: its name, slug, the tag (or title pattern) that identifies its posts, whether it is fiction, status, and planned part count. `wp ttm audit --only=series-tag-candidate` lists tags with ≥ 3 posts that look like series. Known candidates on the live site: `boundless-summer-challenge` (21), `boundless` (23), `cryptopals` (9).
2. **Decide the fiction.** The live `writing` category currently holds posts *about* writing, not chapters. Until chapters are filed in Writing with a fiction `series` term, the front-page Writing cell renders fallback F2 (an ordinary cell). That is by design; there is nothing to migrate for fiction today.
3. **Export.** In production: Tools → Export → All content, or `wp export --dir=/tmp --skip_comments`. Copy the WXR file to `docs/fixtures/live-export.xml` locally (gitignored).
4. **Export media list** (optional, for the rehearsal): `wp media list --format=csv > media.csv` so image counts can be checked after import.

## 1. Rehearse in wp-env

```bash
npx wp-env start
npx wp-env run cli wp plugin install wordpress-importer --activate
npx wp-env run cli wp plugin install jetpack --activate       # so the newsletter block registers; no connection needed
npx wp-env run cli wp import /var/www/html/wp-content/ttm-tests/../../docs/fixtures/live-export.xml --authors=create --skip=attachment
```

Use `--skip=attachment` for a fast rehearsal (featured images will be missing; the theme's fallbacks handle that). Drop the flag to pull every image from the live site (slow; respects Cloudflare rate limits poorly, so run it once and keep the container).

Then run the plan below with `--dry-run` first, read the output, and run it for real. Open `http://localhost:8888` after each step; the front page, `/category/technology/`, and a journal post are the fastest smoke tests.

## 2. The migration plan (same order on production)

### 2.1 Audit

```bash
wp ttm audit --format=csv > audit.csv
```

One row per post with flags: `classic`, `no-excerpt`, `no-featured-image`, `missing-alt`, `multi-category` (lists them), `no-primary`, `uncategorized`, `politics`, `series-tag-candidate`, `legacy-footnotes`, `broken-internal-link`. This is the cleanup worklist. Nothing in later steps needs the audit to be clean; it tells you what the design's fallbacks will be doing on day one (a post without an excerpt shows no dek; a post without a featured image shows the text-only lead, and so on).

### 2.2 Categories

```bash
wp ttm migrate:politics --dry-run
wp ttm migrate:politics            # creates `opinion` if missing, moves `politics` under it, keeps every post's terms
wp ttm migrate:redirects --format=nginx   # prints `/category/politics/…` → `/category/opinion/politics/…` rules
```

Add the printed redirects to your web server or a Cloudflare Redirect Rule (see `DEPLOYMENT.md`). WordPress itself will also 301 the old term URL because the term slug is unchanged and only its parent moved, but an explicit rule keeps Cloudflare from caching a soft redirect.

Optionally reparent or delete `uncategorized` (0 posts) in the admin.

### 2.3 Primary category

```bash
wp ttm primary:assign --dry-run    # shows post → chosen section for every post without ttm_primary_category
wp ttm primary:assign
```

The resolver picks the first assigned section in nav order (Technology, Business, Faith, Journal, Writing, Security, Opinion). Two rules of thumb from the live data:

- A post in `journal` **and** `technology` (there are several) resolves to Technology by nav order, which makes it an article, not a journal entry. If it *is* a journal entry, set Primary section = Journal in the post sidebar; `audit --only=multi-category` lists these.
- Posts in `technology` + `security` resolve to Technology. The Security cell and archive still show them (archives use any-category membership; only front-page cells are primary-only).

### 2.4 Series

For each series from step 0:

```bash
wp ttm series:assign hardening-wordpress --from-tag=hardening-wordpress --dry-run
wp ttm series:assign hardening-wordpress --from-tag=hardening-wordpress
wp ttm series:assign boundless --from-tag=boundless-summer-challenge --form=nonfiction
```

The command creates the `series` term if needed, attaches it to every post carrying the tag, numbers `ttm_series_part` by publish date, and sets `ttm_status` to `complete` when the newest post is older than `lead.stale_days`, else `in-progress`. Then hand-correct in the term edit screen: status, total parts, form, next date, cover, purchase links. A post can only be in one series; the command skips posts that already have one and reports them.

```bash
wp ttm series:rebuild              # refresh the cached index (also happens automatically on every save)
```

### 2.5 Word counts

```bash
wp ttm recount --all
```

### 2.6 Classic content → blocks

Run this after the categories are settled (the conversion does not touch terms) and on a full rehearsal first.

```bash
wp ttm convert:export --all-classic --out=/tmp/classic.ndjson
# on the host:
node scripts/convert-classic.mjs /tmp/classic.ndjson /tmp/blocks.ndjson
wp ttm convert:import /tmp/blocks.ndjson --dry-run      # per-post block summary; refuses posts that produced core/freeform unless --allow-freeform
wp ttm convert:import /tmp/blocks.ndjson
```

What the converter does (`docs/SPEC.md §6.7`): the editor's own `rawHandler` from `@wordpress/blocks` under jsdom, so the result is what "Convert to blocks" in the editor would produce, batch. Legacy `modern-footnotes` markup is transformed into core footnotes first. The import keeps the original HTML in post meta `ttm_classic_backup` (written once, never overwritten), creates a revision, sets `ttm_converted_at`, and verifies that the visible text is unchanged.

Review: `wp ttm audit --only=classic` should return zero rows. Spot-check the oldest posts in the editor; anything ugly can be reverted per post:

```bash
wp ttm convert:revert --post=1234
```

If a post produced a `core/freeform` block, that fragment is HTML `rawHandler` could not map (usually an embedded script or a table with odd markup). Either fix the source in `ttm_classic_backup` and re-run for that post (`convert:export --post=1234`), or accept it with `--allow-freeform`; a freeform block renders identically to the classic content.

Once converted, the `modern-footnotes` plugin can be deactivated.

### 2.7 Journal syndication (best effort)

The Phase 8 spike populates `ttm_syndication` from Jetpack Social's per-post share records where they exist. Check a few journal posts: the syndication line appears only when at least one URL exists. Anything missing can be pasted into the post sidebar.

### 2.8 Comments

```bash
wp ttm migrate:close-comments --dry-run
wp ttm migrate:close-comments      # comment_status=closed, ping_status=closed on every post; default_comment_status=closed
```

Existing comments are kept in the database and not rendered. Revisit after the content cleanup if you want them back.

### 2.9 Verse

```bash
wp ttm verse inspect       # shows what today's item parses to
wp ttm verse fetch         # stores it and schedules the daily cron
```

### 2.10 Pages and navigation

Activating `ttm-theme` runs its starter content once: creates any missing section categories, the `Series`, `Writing`, `Newsletter` and `About` pages with templates assigned (existing pages with those slugs are reused, not duplicated), and a "Sections" navigation with the seven sections plus Series. Check Appearance → Editor → Navigation, and set the front page to "Your latest posts" (the theme's `front-page.html` handles it).

The live `blog` and `speaking` pages keep working with `page.html`.

## 3. Production cut-over

1. Put the site in maintenance mode or accept a few minutes of mixed rendering.
2. `git pull` the release on the server; `composer install --no-dev` is **not** needed (the plugin has a classmap autoload fallback); `npm run build` output (`plugins/ttm-core/build`) must be present, so build in CI and deploy the artifact, or build on the server.
3. Activate `ttm-core` first, then run §2.1–2.5 and 2.8–2.9. Conversion (§2.6) can run before or after the theme switch; it is independent.
4. Switch the theme to `ttm-theme`. Verify the pages and navigation (§2.10).
5. Add the redirect rules, then purge Cloudflare completely once (`DEPLOYMENT.md`).
6. Walk the front page, one post per section, a journal post, a series hub, `/writing/`, `/category/opinion/politics/`, a tag archive, search, and a 404.
7. Keep Powder installed for a week in case of rollback (switching back is one click; the plugin's data is untouched by the theme).

## 4. Rollback

- Theme: switch back to Powder. All content and the new taxonomy/meta remain and are simply not displayed.
- Categories: `politics` can be reparented to top level in the admin; the redirect rules can be removed.
- Conversion: `wp ttm convert:revert --all` restores every converted post's original HTML from `ttm_classic_backup`.
- Plugin: deactivating it hides the series/verse/writing blocks; the theme's layouts close around them (`docs/06-fallbacks.md`). Uninstalling keeps terms and meta unless `TTM_REMOVE_DATA` is set.

## 5. Content cleanup worklist (ongoing, after cut-over)

From `audit.csv`, in the order that most improves the front page:

1. Excerpts (deks) on the newest ~30 posts per section — cells and the lead read them.
2. Featured images with alt text on the newest Technology posts — the lead and the Technology cell use them.
3. Primary section on the multi-category posts.
4. Series part numbers and totals; `ttm_next_date` for anything in progress.
5. `ttm_featured_in_section` on up to three posts per section ("Most read").
6. File fiction chapters in Writing with a fiction series term and a cover, and add books under Settings → These Things Matter → Books, so `/writing/` leaves its fallback state.
