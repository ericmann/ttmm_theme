# Deployment and caching configuration

The site is meant to behave like a static site: Cloudflare caches whole pages at the edge, Batcache caches whole pages at the origin, and nothing on a page depends on who is looking at it or on the moment it was rendered, except the date, which the cache lifetime is computed around. This document is the production configuration that makes that true. The plugin behaviour it relies on is specified in `docs/SPEC.md §3.2` and `§6.10`.

## 1. What the plugin does on its own

- Sends `Cache-Control: public, max-age=N, s-maxage=N` on anonymous front-end responses, where `N` is the number of seconds until the next local **midnight** or the next local **06:00** (`cache.verse_boundary_hour`), whichever comes first, clamped to `[60, 86400]`. Feeds get one hour. Logged-in and admin requests get `no-store`.
- Sets `$batcache['max_age']` to the same `N` when Batcache is present.
- Fires `ttm_purge_urls` with the affected URLs when a post is published, updated, unpublished or scheduled, when the verse is fetched, and when series, books or settings change. The affected set is: the front page, the post URL, every section archive the post is in, its series archive, `/series/`, `/writing/` when relevant, and the relevant feeds.
- Purges those URLs through the Cloudflare API when `TTM_CLOUDFLARE_ZONE_ID` and `TTM_CLOUDFLARE_API_TOKEN` are defined.
- Renders no nonces, no user-specific markup, and makes no front-end network requests. The newsletter form is Jetpack's (posts to WordPress.com) or a stateless HMAC-tokened form.

Nothing below is required for the site to work; it is required for the site to be fast and correct at scale.

## 2. Constants (`wp-config.php`)

```php
define( 'WP_ENVIRONMENT_TYPE', 'production' );   // wp ttm seed refuses to run
define( 'DISABLE_WP_CRON', true );               // run cron from the system, see §6
define( 'TTM_CLOUDFLARE_ZONE_ID', '…' );
define( 'TTM_CLOUDFLARE_API_TOKEN', '…' );       // token scoped to Zone → Cache Purge only
// define( 'TTM_NEWSLETTER_API_KEY', '…' );      // only for the custom-url provider
```

Never put these in the options table or a committed file. Use the host's secrets mechanism or a file outside the web root that `wp-config.php` includes.

## 3. Cloudflare

The live site already uses Cloudflare with the Cloudflare WordPress plugin and **APO** (Automatic Platform Optimization); the `cf-edge-cache: cache,platform=wordpress` response header shows it. Keep it. APO caches HTML at the edge, bypasses for logged-in users by cookie, and purges on post publish through the plugin. The `ttm-core` purge adapter is additive: it purges the archive and hub URLs APO does not know about (series pages, the Writing page, section feeds) and the front page after the verse refresh.

Settings to verify in the dashboard:

| Setting | Value | Why |
|---|---|---|
| Caching → Configuration → Browser Cache TTL | **Respect Existing Headers** | The origin computes the midnight boundary; a fixed TTL would show yesterday's masthead date and "Today" labels. |
| Cache Rules (if not using APO) | Cache everything for `eric.mann.blog/*`, Edge TTL "Use cache-control header from origin", bypass when cookie contains `wordpress_logged_in`, `wp-postpass`, `wordpress_sec` or when path starts with `/wp-admin`, `/wp-login.php`, `/wp-json/wp/`, `/wp-cron.php` | Same behaviour as APO, explicit. |
| Cache Rules | Also cache `/wp-json/ttm/v1/*` with origin TTL | These endpoints are public and carry the same computed headers. |
| Tiered Cache | On | Fewer origin hits after a purge. |
| Rules → Redirect Rules | `/category/politics/*` → `/category/opinion/politics/$1` (301) plus anything `wp ttm migrate:redirects` printed | Politics fold, `MIGRATION.md §2.2`. |
| Rules → Transform → Response headers | `Strict-Transport-Security`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`, and a `Content-Security-Policy` allowing `'self'`, Jetpack's stats/subscribe endpoints, and `data:` fonts | The plugin sets no security headers by design; the edge does. |
| Speed → Optimization | Rocket Loader **off**, Auto Minify off | The theme ships one tiny script and one small stylesheet; rewriting them adds risk and nothing else. |
| Purge | Custom purge by URL is what the adapter uses; "Purge everything" once at cut-over | |

API token: create one with the single permission **Zone → Cache Purge → Purge** on this zone only.

## 4. Batcache at the origin

[Batcache](https://github.com/Automattic/batcache) stores whole rendered pages in the object cache so the origin survives a Cloudflare miss storm (after a purge, or for long-tail URLs).

1. Install a persistent object cache. Memcached (`wp-content/object-cache.php` from [Automattic/wp-memcached](https://github.com/Automattic/wp-memcached)) is the reference pairing; Redis with an `object-cache.php` drop-in that supports `wp_cache_add` semantics also works.
2. Copy Batcache's `advanced-cache.php` to `wp-content/advanced-cache.php` and `batcache.php` to `wp-content/plugins/batcache.php` (or as an mu-plugin, for the automatic post-save invalidation).
3. `define( 'WP_CACHE', true );` in `wp-config.php`.
4. Configure at the top of `advanced-cache.php` or in `wp-config.php` before it loads:

```php
$batcache = array(
    'max_age'  => 300,      // overridden per request by ttm-core to the midnight/06:00 boundary
    'times'    => 2,        // cache after 2 hits in 'seconds' window; keeps rare URLs out of memcached
    'seconds'  => 120,
    'group'    => 'ttm',
    'unique'   => array(),  // nothing varies by device; the theme is responsive
    'headers'  => array(),  // Cache-Control is added by ttm-core, not Batcache
    'cache_redirects' => true,
    'ignored_query_args' => array( 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid' ),
);
```

Batcache skips requests with WordPress auth or comment cookies and skips `POST`, so admin, previews and the newsletter handler are unaffected. `ttm-core` sets `$batcache['max_age']` on `init` to match the edge TTL, so the two layers expire together.

Verify: `curl -sI https://eric.mann.blog/ | grep -i -E 'cache-control|cf-cache-status'` twice; the second response should be a Cloudflare `HIT`; behind it, Batcache adds no header by default, so check memcached stats or temporarily enable `$batcache['debug'] = true` (adds an HTML comment).

## 5. Web server

- PHP 8.3, OPcache on, `opcache.validate_timestamps=0` on production deploys that restart PHP-FPM.
- `plugins/ttm-core/build/` must exist on the server (built by CI or on deploy). The theme needs no build step.
- Redirect `/category/writing/` → `/writing/` (301) if you want a single canonical URL for the Writing page (both render the same template).
- Serve `themes/ttm-theme/assets/fonts/*.woff2` with a long `Cache-Control: max-age=31536000, immutable`; Cloudflare's default for fonts is fine.

## 6. Cron

With `DISABLE_WP_CRON` true, run WordPress cron from the system so the 05:00 verse fetch does not depend on a visitor arriving:

```cron
*/5 * * * * cd /var/www/eric.mann.blog && wp cron event run --due-now >/dev/null 2>&1
```

The verse job (`ttm_verse_fetch`) runs at 05:00 site time and retries once at 07:00 on failure; the front page is purged after a successful fetch. `wp ttm verse log` shows the last 20 attempts; Settings → These Things Matter → Verse shows the same plus a "Fetch now" button.

## 7. Feeds and REST under caching

- Feeds carry `max-age=3600`; Jetpack and RSS readers tolerate that.
- `/wp-json/ttm/v1/*` is read-only and carries the computed page TTL; it is safe to cache at the edge.
- Core REST (`/wp-json/wp/v2/*`) is used by the editor and Jetpack and must **not** be edge-cached; the cache rule above excludes it.

## 8. Things that would break the static model

Avoid adding any of these without a plan for cache keys:

- A plugin that prints a nonce or the current user on the front end (most "related posts" and social-share plugins do).
- Cookie banners or A/B testing that vary the HTML.
- Comments (a comment form carries a nonce and a per-post state). If comments come back after the content cleanup, they need a JavaScript-loaded form or a Cloudflare bypass on single posts.
- Jetpack modules that inject per-visitor markup (Likes, Sharing with counts). Jetpack Stats' beacon is fine: it is a fixed script and an image request that vary nothing in the HTML.

## 9. Checklist for a release

1. CI green on the tag (lint, unit, integration, e2e, security).
2. `npm run build` artifact deployed with the code.
3. `wp ttm series:rebuild && wp ttm verse fetch` after deploy.
4. Purge Cloudflare everything once; then load the front page twice and confirm `cf-cache-status: HIT` and a `Cache-Control` that ends at the next local midnight or 06:00.
5. `curl -s https://eric.mann.blog/ | grep -c 'wp-json\|admin-ajax'` returns `0`.

## 10. Hosting on k3s (target for the migration)

The live site is a Docker Compose stack on the `hive` NUC behind a Cloudflare Tunnel; the migration (`MIGRATION.md §3`) stands the new site up on the k3s cluster privately and moves the tunnel hostname at cut-over. The shape, without prescribing a chart:

| Component | Choice | Notes |
|---|---|---|
| WordPress | `wordpress:php8.3-apache` (or fpm + nginx) Deployment, 1 replica to start | Mount the repo's `plugins/ttm-core` and `themes/ttm-theme` from an image built in CI (copy `build/` in), not from a hostPath. |
| Database | MariaDB 11 StatefulSet with a PVC (or the Bitnami/`mariadb-operator` chart) | `utf8mb4`; `innodb_buffer_pool_size` sized to the NUC. |
| Uploads | PVC (RWO is fine at one replica; RWX or S3-offload via a media plugin if replicas grow) | The theme applies grayscale in CSS; nothing rewrites images. |
| Object cache | Memcached Deployment (`memcached:alpine`, 256 MB) + `wp-memcached` drop-in | Required for Batcache (§4). |
| Cron | Kubernetes CronJob every 5 minutes running `wp cron event run --due-now` in a `wp-cli` container against the same volumes and DB | With `DISABLE_WP_CRON` true. |
| Ingress | Cloudflare Tunnel (`cloudflared` Deployment, token in a Secret) → Service | No public IP, no cert-manager needed. Beta hostname (`beta.mann.blog`) behind Cloudflare Access. Restore the real client IP from `CF-Connecting-IP` (see note below) at this hop -- WordPress otherwise sees every visitor as the tunnel/ingress's own address. |
| Secrets | `wp-config.php` constants from a Secret mounted as a PHP file included by `wp-config.php` (`TTM_CLOUDFLARE_*`, DB credentials, salts) | Never in the image or the repo. |
| DB + media backups | CronJob nightly: `scripts/live/backup.sh` inside a `wp-cli` container against the same volumes (`wp db export --single-transaction` + `tar` of the uploads PVC, gzipped, plus `manifest.json` with SHA-256s), synced to a versioned S3 bucket with lifecycle (30 daily, 12 monthly) | Test a restore quarterly with `scripts/live/restore.sh` — the exact procedure `npm run env:drill` (`MIGRATION.md §1.0`, P5-01) already rehearses against the seed in CI on every `integration` job run, so the restore path is proven continuously, not just quarterly. |
| Monitoring | Uptime check on `/` expecting `cf-cache-status`, and on `/wp-json/ttm/v1/verse` expecting today's date after 06:00 | The verse endpoint is the cheapest "cron is alive" probe. |

Restore drill: `MIGRATION.md §1.2` is literally the restore procedure; if it works into wp-env it works into a fresh k3s namespace.

**Beta hostname.** The k3s deployment is exposed as `beta.mann.blog` (working name, `MIGRATION.md §3.1`), not the production hostname — content import and cleanup happen there, reviewed, before cut-over. Recommended: a Cloudflare Tunnel hostname behind **Cloudflare Access** (email one-time-code policy naming the owner and reviewers), `blog_public 0`. Documented alternatives: fully public (simpler, crawlable — only once cleanup is mostly done), or cluster-only (no public ingress; a hosts-file entry or `kubectl port-forward`, no Cloudflare configuration). This flight documents the choice (SPEC §9 Q7); the next flight stands it up.

**Real client IP for the newsletter rate limit.** `Newsletter\Handler::client_ip()` reads `$_SERVER['REMOTE_ADDR']` directly (SPEC §6.7) to bucket the custom-url provider's per-IP rate limit; it never trusts a client-supplied header. Behind a Cloudflare Tunnel, `REMOTE_ADDR` at the WordPress container is the `cloudflared` connection, not the visitor -- every visitor would share one rate-limit bucket, and one abusive visitor would lock out everyone else. Restore the real address from Cloudflare's `CF-Connecting-IP` header (which Cloudflare sets and strips any client-supplied copy of, so it can't be spoofed past the edge) *before* it reaches PHP, at the ingress: `cloudflared`'s `originRequest.httpHostHeader`/proxy config or an nginx sidecar in front of PHP with `set_real_ip_from` scoped to the tunnel's own address and `real_ip_header CF-Connecting-IP;`. Do this at the network layer, not in `Handler::client_ip()` itself -- trusting an arbitrary request header inside the plugin would let anyone spoof their rate-limit bucket the moment the tunnel changes or is bypassed.
