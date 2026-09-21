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

## Strategy: blue/green, never in place

The live site is a Docker Compose stack on the `hive` NUC behind a Cloudflare Tunnel. The migration does **not** touch it. Instead:

1. **Rehearse locally.** Pull a full archive (database + uploads) from `hive` into wp-env and run the whole plan against real content until it is boring.
2. **Build the green deployment on k3s**, private (tunnel hostname not published, Cloudflare Access in front, search engines told to go away), restore the same archive there, run the plan for real, and do the content cleanup at leisure.
3. **Cut over** by pointing the public Cloudflare Tunnel hostname at the k3s ingress, then purge Cloudflare. The `hive` stack stays running as the rollback for a week.

`DEPLOYMENT.md §10` covers the k3s side (backups to S3, secrets, cron). A later move to an Automattic-hosted environment follows the same archive → restore → cut-over shape.

## 0. Before you start

1. **Decide the series list.** Write down each multi-part work: its name, slug, the tag (or title pattern) that identifies its posts, whether it is fiction, status, and planned part count. `wp ttm audit --only=series-tag-candidate` lists tags with ≥ 3 posts that look like series. Known candidates on the live site: `boundless-summer-challenge` (21), `boundless` (23), `cryptopals` (9).
2. **Decide the fiction.** The live `writing` category currently holds posts *about* writing, not chapters. Until chapters are filed in Writing with a fiction `series` term, the front-page Writing cell renders fallback F2 (an ordinary cell). That is by design; there is nothing to migrate for fiction today.
3. **Take the archive** (§1.1). A WXR export is the fallback, but the archive is what both the rehearsal and the k3s deployment restore from, so the two are identical.

## 1. Rehearse in wp-env with the real archive

### 1.1 Pull the archive from `hive`

On the NUC, from the Compose project directory (adjust service names to the stack):

```bash
STAMP=$(date +%F)
docker compose exec -T db sh -c 'exec mysqldump --single-transaction --quick --default-character-set=utf8mb4 -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' | gzip > eric-mann-blog-$STAMP.sql.gz
docker compose exec -T wordpress tar -C /var/www/html/wp-content -czf - uploads > uploads-$STAMP.tar.gz
docker compose exec -T wordpress wp option get siteurl --allow-root     # note it; the restore rewrites it
```

Copy both files to the workstation into `docs/fixtures/live/` (gitignored: add `docs/fixtures/live/` to `.gitignore` if it is not there yet). Keep a copy in S3 as well; this is also the first backup of the new setup.

If the Compose stack has no `wp` binary in the container, the WXR route still works: Tools → Export → All content, then `wp import` as in §1.3.

### 1.2 Restore into wp-env

```bash
npx wp-env start
# Find the wp-env WordPress container (name contains "wordpress-1")
WP=$(docker ps --format '{{.Names}}' | grep -E 'ttmm[-_]theme.*wordpress-1$' | head -1)
# Database: copy the dump in, import, rewrite URLs (search-replace handles serialized data)
gunzip -c docs/fixtures/live/eric-mann-blog-*.sql.gz > /tmp/live.sql && docker cp /tmp/live.sql "$WP":/tmp/live.sql
npx wp-env run cli wp db import /tmp/live.sql
npx wp-env run cli wp search-replace 'https://eric.mann.blog' 'http://localhost:8888' --all-tables --precise
npx wp-env run cli wp cache flush
# Uploads
docker cp docs/fixtures/live/uploads-*.tar.gz "$WP":/tmp/uploads.tar.gz
npx wp-env run cli sh -c 'tar -C /var/www/html/wp-content -xzf /tmp/uploads.tar.gz && chown -R www-data:www-data /var/www/html/wp-content/uploads'
# Plugins the archive references but wp-env does not have
npx wp-env run cli wp plugin install jetpack modern-footnotes --activate
npx wp-env run cli wp plugin activate ttm-core && npx wp-env run cli wp theme activate ttm-theme
npx wp-env run cli wp user update admin --user_pass=password   # the archive's users replace wp-env's
```

Jetpack will run disconnected; that is fine for the rehearsal (the newsletter block still registers). Deactivate anything from the archive that phones home (Jetpack Boost, stats) if it gets noisy.

### 1.3 WXR fallback

```bash
npx wp-env run cli wp plugin install wordpress-importer --activate
npx wp-env run cli wp import /path/inside/container/export.xml --authors=create --skip=attachment
```

`--skip=attachment` gives a fast rehearsal without images; drop it to fetch every image from the live site (slow).

### 1.4 Run the plan

Run §2 below with `--dry-run` first, read the output, then for real. Open `http://localhost:8888` after each step; the front page, `/category/technology/`, and a journal post are the fastest smoke tests. Repeat from §1.2 (`npm run env:destroy`, start again) until the plan runs clean without manual intervention. Write down every hand-correction you had to make; those become the cleanup worklist in §5.

## 2. The migration plan (same order on production)

### 2.1 Audit

```bash
wp ttm audit --format=csv > audit.csv
```

One row per post with flags: `classic`, `no-excerpt`, `no-featured-image`, `missing-alt`, `multi-category` (lists them), `no-primary`, `uncategorized`, `politics`, `series-tag-candidate`, `legacy-footnotes`, `broken-internal-link`. This is the cleanup worklist. Nothing in later steps needs the audit to be clean; it tells you what the design's fallbacks will be doing on day one (a post without an excerpt shows no dek; a post without a featured image shows the text-only lead, and so on).

### 2.2 Categories

```bash
wp ttm migrate:politics --dry-run
wp ttm migrate:politics            # creates `opinion` if missing, moves `politics` under it, keeps every post's terms,
                                    # adds `opinion` to every Politics post and sets it as that post's primary category
                                    # (so PrimaryCategory::slug() reads "opinion" — Politics posts are Opinion posts now)
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

```bash
wp ttm migrate:syndication [--dry-run] [--post=<id>]
```

Reads Jetpack Social/Publicize's `_publicize_done_external` per-post meta (`{service: {id: url}}`) where it exists and populates `ttm_syndication` for `x`/`mastodon`/`bluesky`, `https` URLs only, never overwriting a post that already has a syndication value. See `docs/spikes/P8-05.md` for exactly which Jetpack meta keys were examined and why only this one carries usable URLs. Check a few journal posts: the syndication line appears only when at least one URL exists. Anything missing can be pasted into the post sidebar.

### 2.8 Comments

```bash
wp ttm migrate:close-comments --dry-run
wp ttm migrate:close-comments      # comment_status=closed, ping_status=closed on every post; default_comment_status=closed
```

Existing comments are kept in the database and not rendered. Revisit after the content cleanup if you want them back. The command updates `comment_status`/`ping_status` directly (not through `wp_update_post()`) and fires the Cloudflare/Batcache purge exactly once for the whole batch, not once per post.

### 2.9 Verse

```bash
wp ttm verse inspect       # shows what today's item parses to
wp ttm verse fetch         # stores it and schedules the daily cron
```

### 2.10 Pages and navigation

Activating `ttm-theme` runs its starter content once: creates any missing section categories, the `Series`, `Writing`, `Newsletter` and `About` pages with templates assigned (existing pages with those slugs are reused, not duplicated), and a "Sections" navigation with the seven sections plus Series. Check Appearance → Editor → Navigation, and set the front page to "Your latest posts" (the theme's `front-page.html` handles it).

The live `blog` and `speaking` pages keep working with `page.html`.

## 3. The real migration on k3s (private), then cut-over

### 3.1 Stand up green, privately

1. Deploy WordPress on k3s per `DEPLOYMENT.md §10` (MariaDB or MySQL with PVC, uploads PVC, S3 backup CronJobs, secrets for `wp-config.php` constants).
2. Expose it **only** through a Cloudflare Tunnel hostname that is not linked anywhere (for example `next.eric.mann.blog`) with a **Cloudflare Access** policy (email OTP or your identity provider) in front, and `wp option update blog_public 0` so it emits `noindex`. Do not add it to any sitemap or Jetpack site list.
3. Restore the archive from §1.1 exactly as in §1.2, using `next.eric.mann.blog` in the search-replace.
4. Install `ttm-core`, `ttm-theme`, the Jetpack plugin (leave it **disconnected** until cut-over; connecting a second site to the same WordPress.com account would split stats and subscribers), and the Cloudflare plugin (also unconfigured for now).

### 3.2 Migrate and clean up

Run §2 in order. Take a fresh archive from `hive` first if more than a day has passed since §1.1, because anything published on the live site after the archive will otherwise be lost. From this point on, **stop publishing on `hive`**; new posts go on green (they are invisible to the public until cut-over, which is fine for drafts and scheduled posts).

Content cleanup (§5) happens here, at leisure, with Access-protected previews. Nothing is public.

### 3.3 Cut over

1. Final check on green: front page, one post per section, a journal post, a series hub, `/writing/`, `/category/opinion/politics/`, a tag archive, search, a 404; `wp ttm audit` shows what you expect; `wp cron event list` shows `ttm_verse_fetch`.
2. `wp search-replace 'https://next.eric.mann.blog' 'https://eric.mann.blog' --all-tables --precise` on green, then `wp option update blog_public 1`.
3. In Cloudflare Zero Trust → Tunnels, move the public hostname `eric.mann.blog` (and `www`) from the `hive` tunnel to the k3s tunnel/ingress. Remove the Access policy from the public hostname (keep it on `next.` if that hostname stays for staging).
4. Connect Jetpack on green (it inherits the site by URL; use "Transfer connection" if Jetpack complains about a site already connected), configure the Cloudflare plugin, add the redirect rules from §2.2, then **Purge Everything** once.
5. `wp ttm verse fetch`, `wp ttm series:rebuild` on green.
6. Watch the tunnel logs and `cf-cache-status` for a few minutes. `hive` keeps running untouched.

### 3.4 After a week

Stop the `hive` Compose stack, keep its last archive in S3, and decommission.

## 4. Rollback

- Cut-over: move the tunnel hostname back to `hive`. Anything published on green after cut-over must be exported and re-imported on `hive` (WXR of posts newer than the cut-over date), which is why `hive` is kept for a week and not longer.
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
