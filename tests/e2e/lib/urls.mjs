/**
 * The seven seeded screens (SPEC §3.4 rule 33, Phase 8), as literal paths.
 *
 * Slugs are hard-coded from `docs/fixtures/seed/posts.json` and `docs/fixtures/seed/series.json`
 * (the fixtures `wp ttm seed --reset` reads), not derived at runtime, per the task text. The
 * permalink structure is `/%postname%/` (set by `.wp-env.json`'s `afterStart` lifecycle script),
 * so every post/page URL below is just `/<slug>/`.
 *
 * - `front`: the front page (`front-page.html`, the lead story + section cells).
 * - `article`: an ordinary seeded Technology post, slug `technology-post-2`
 *   (`docs/fixtures/seed/posts.json`). It used to double as the Hardening WordPress series'
 *   part 2 (a seed-data mismatch: the mock's own part 2 is "Salts, keys and the rotation you
 *   skipped") -- P3-06 gave the series a dedicated post for that instead
 *   (`hardening-part-2-salts-and-keys`) and left this one an ordinary article. Chosen for this
 *   screen because it's one of the few seeded posts with a real featured image (`posts.json`'s
 *   `featured_image` field) - needed for the `img[fetchpriority="high"]` assertion below, since
 *   WordPress core only adds that attribute to a real attached image, and most seeded posts have
 *   none.
 * - `journalPost`: a seeded Journal entry (`single-journal.html`).
 * - `writing`: the Writing hub page (`page-writing.html`).
 * - `securityArchive`: the Security category archive (`category.html`).
 * - `seriesHub`: the Series index page (`page-series.html`).
 * - `seriesEntry`: the seeded fiction serial "The Quiet Ledger" (`taxonomy-series.html`).
 */
export const SCREENS = {
	front: '/',
	article: '/technology-post-2/',
	journalPost: '/journal-post-1/',
	writing: '/writing/',
	securityArchive: '/category/security/',
	seriesHub: '/series/',
	seriesEntry: '/series/the-quiet-ledger/',
};

export const SCREEN_URLS = Object.values( SCREENS );

/**
 * wp-env's fixed admin credentials (`.wp-env.json` default), used by `editors.spec.mjs` to log
 * into `/wp-login.php` before opening the Site Editor / Customizer.
 */
export const ADMIN = { user: 'admin', pass: 'password' };
