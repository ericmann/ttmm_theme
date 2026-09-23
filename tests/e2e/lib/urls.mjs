/**
 * The seeded screens (SPEC §1 "Done", §3.4 rule 33, Phase 8), as literal paths.
 *
 * Slugs are hard-coded from `docs/fixtures/seed/posts.json` and `docs/fixtures/seed/series.json`
 * (the fixtures `wp ttm seed --reset` reads), not derived at runtime, per the task text. The
 * permalink structure is `/%postname%/` (set by `.wp-env.json`'s `afterStart` lifecycle script),
 * so every post/page URL below is just `/<slug>/`.
 *
 * - `front`: the front page (`front-page.html`, the lead story + section cells).
 * - `article`: the seeded Technology post `signing-your-options-table` (P0-03, Decision
 *   "e2e screen set" — one of the few seeded posts with a real featured image, needed for the
 *   `img[fetchpriority="high"]` assertion below, since WordPress core only adds that attribute
 *   to a real attached image and most seeded posts have none).
 * - `journalPost`: a seeded Journal entry (`single-journal.html`).
 * - `writing`: the Writing hub page (`page-writing.html`).
 * - `securityArchive`: the Security category archive (`category.html`).
 * - `journalArchive`: the Journal category archive (`category-journal.html`).
 * - `seriesHub`: the Series index page (`page-series.html`).
 * - `seriesHardening`: the seeded "Hardening WordPress" series (`taxonomy-series.html`).
 * - `seriesEntry`: the seeded fiction serial "The Quiet Ledger" (`taxonomy-series.html`).
 * - `tagArchive`: the "wordpress" tag archive (`tag.html`).
 * - `search`: a search results page (`search.html`).
 * - `about`: the seeded "About" page (`page.html`), the eighth screen (P4-03, SPEC §8 Phase 4).
 * - `notFound`: the 404 template (`404.html`).
 */
export const SCREENS = {
	front: '/',
	article: '/signing-your-options-table/',
	journalPost: '/journal-post-1/',
	writing: '/writing/',
	securityArchive: '/category/security/',
	journalArchive: '/category/journal/',
	seriesHub: '/series/',
	seriesHardening: '/series/hardening-wordpress/',
	seriesEntry: '/series/the-quiet-ledger/',
	tagArchive: '/tag/wordpress/',
	search: '/?s=ledger',
	about: '/about/',
	notFound: '/this-page-does-not-exist/',
};

export const SCREEN_URLS = Object.values( SCREENS );

/**
 * The security archive filtered by the "wordpress" tag (`?tag=wordpress`), used only by
 * `fidelity.spec.mjs` and `selectors.spec.mjs` (rule 41: `.tag-accent` needs a filtered
 * archive to appear on).
 */
export const securityFiltered = '/category/security/?tag=wordpress';

/**
 * wp-env's fixed admin credentials (`.wp-env.json` default), used by `editors.spec.mjs` to log
 * into `/wp-login.php` before opening the Site Editor / Customizer.
 */
export const ADMIN = { user: 'admin', pass: 'password' };
