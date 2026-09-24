/**
 * Pure builder of `docs/fixtures/live/screens.json`'s `screens` array from already-fetched
 * plain data (SPEC §6.10, PLAN Decision "screens.json shape"). No I/O, no wp-cli, no network --
 * the CLI wrapper (`scripts/live/screens.mjs`) fetches everything and calls this.
 *
 * `screens.json` shape (PLAN Decision, `date` added P4-01 for `live.spec.mjs`'s byline check;
 * `primary` added R1-02 for the single-screen kicker/masthead checks, SPEC §6.10):
 *   { generated: "<ISO>", host: "<LIVE_HOST>",
 *     screens: [{ id, path, kind: "front|single|archive|page|search|404",
 *                 expectStatus: 200|404, section: "<slug>"|null,
 *                 classic: boolean, freeform: boolean, converted: boolean,
 *                 date: string|null, primary: "<display name>"|null }] }
 *
 * `converted` (R3-01) is distinct from `classic`: `classic` means "still has no `<!-- wp:`
 * block markup" (the shortcode-residue gate's signal); `converted` means "this post carries
 * `ttm_converted_at` post meta", i.e. it went through `ConvertCommand::import_one()` at some
 * point and is exactly the population the merged-paragraph (R2-01) check must run against --
 * including the `ref-*`/`cc-*`/`mfn-*` screens, which are converted but no longer `classic`
 * once conversion has replaced their content with block markup.
 */

/**
 * `wp post list` args for a category section's newest-post/count queries (P4-04, SPEC §6.10
 * archive finding). WP-CLI forwards `--category` straight through to `WP_Query`, whose
 * `category` arg is a **category ID**, not a slug; a non-numeric slug casts to `0` and
 * `WP_Query` silently drops the filter, so `--category=<slug>` (the bug this replaces) returned
 * the *site-wide* newest post / published count instead of the section's own -- and made every
 * section compute the same wrong "last archive page" (`ceil(<site-wide count>/perPage)`), which
 * 404s. `--category_name` is WP_Query's slug-based equivalent.
 *
 * @param {string} section Category slug.
 * @return {string[]} Extra `wp post list` flags, appended after the shared `--post_type`/
 *                     `--post_status` flags.
 */
export function sectionCategoryArgs( section ) {
	return [ `--category_name=${ section }` ];
}

const KIND = {
	FRONT: 'front',
	SINGLE: 'single',
	ARCHIVE: 'archive',
	PAGE: 'page',
	SEARCH: 'search',
	NOT_FOUND: '404',
};

/**
 * @typedef {Object} ScreenPost
 * @property {number}      id          Post ID (informational only; not written to the manifest).
 * @property {string}      slug        `post_name`.
 * @property {boolean}     classic     Still has no `<!-- wp:` block markup.
 * @property {boolean}     freeform    Contains a `core/freeform`/`core/html` fallback block.
 * @property {boolean}     [converted] Carries `ttm_converted_at` post meta (R3-01: gates the
 *                                     merged-paragraph check in `live.spec.mjs` onto every
 *                                     classic-converted screen, not just still-classic ones).
 * @property {string|null} [date]      `post_date` (P4-01: `live.spec.mjs`'s byline-date check).
 * @property {string|null} [primary]   The primary category's display name (R1-02: `live.spec.mjs`'s
 *                                     single-screen kicker/masthead checks, SPEC §6.10), read host
 *                                     side via `wp post meta get ttm_primary_category` + `wp term get`.
 */

/**
 * @param {Object}                                                           inputs
 * @param {string}                                                           inputs.host                      `LIVE_HOST`.
 * @param {number}                                                           inputs.archivePerPage            `archive.per_page`, for each section's last page (P4-04: overridden per section by
 *                                                                                                            `inputs.archivePerPageBySection`, e.g. Journal's own `journal.archive_per_page`).
 * @param {Record<string, number>}                                           [inputs.archivePerPageBySection]
 *                                                                                                            Per-section overrides of `archivePerPage`, keyed by section slug.
 * @param {Array<{section: string, newest: ScreenPost|null, count: number}>} inputs.sectionPosts
 *                                                                                                            One entry per section, in nav order.
 * @param {ScreenPost|null}                                                  [inputs.oldest]                  Oldest published post overall.
 * @param {ScreenPost|null}                                                  [inputs.journalNewest]           Newest Journal post.
 * @param {ScreenPost[]}                                                     [inputs.refPosts]                Posts whose classic backup had `[ref]` (≤ 2 used).
 * @param {ScreenPost[]}                                                     [inputs.ccPosts]                 Posts whose classic backup had `[cci]`/`[cc]` (≤ 2 used).
 * @param {ScreenPost[]}                                                     [inputs.mfnPosts]                Posts whose classic backup had `[mfn]` (≤ 1 used).
 * @param {ScreenPost|null}                                                  [inputs.asidePost]               A `post-format-aside` post.
 * @param {ScreenPost|null}                                                  [inputs.featuredPost]            A post with a featured image.
 * @param {ScreenPost|null}                                                  [inputs.unfeaturedPost]          A post without one.
 * @param {string[]}                                                         [inputs.seriesSlugs]             `docs/migration/series.json` slugs.
 * @return {{generated: string, host: string, screens: Array<object>}} The `screens.json` manifest.
 */
export function buildScreens( inputs ) {
	const {
		host,
		archivePerPage,
		archivePerPageBySection = {},
		sectionPosts = [],
		oldest = null,
		journalNewest = null,
		refPosts = [],
		ccPosts = [],
		mfnPosts = [],
		asidePost = null,
		featuredPost = null,
		unfeaturedPost = null,
		seriesSlugs = [],
	} = inputs;

	const seen = new Set();
	const screens = [];

	const add = ( id, path, kind, options = {} ) => {
		if ( seen.has( path ) ) {
			return;
		}
		seen.add( path );
		screens.push( {
			id,
			path,
			kind,
			expectStatus: options.expectStatus ?? 200,
			section: options.section ?? null,
			classic: options.classic ?? false,
			freeform: options.freeform ?? false,
			converted: options.converted ?? false,
			date: options.date ?? null,
			primary: options.primary ?? null,
		} );
	};

	const addSingle = ( id, post, section = null ) => {
		if ( ! post ) {
			return;
		}
		add( id, `/${ post.slug }/`, KIND.SINGLE, {
			section,
			classic: Boolean( post.classic ),
			freeform: Boolean( post.freeform ),
			converted: Boolean( post.converted ),
			date: post.date ?? null,
			primary: post.primary ?? null,
		} );
	};

	add( 'front', '/', KIND.FRONT );

	sectionPosts.forEach( ( { section, newest } ) => {
		addSingle( `single-${ section }`, newest, section );
	} );

	addSingle( 'oldest', oldest );
	addSingle( 'journal-newest', journalNewest, 'journal' );

	refPosts
		.slice( 0, 2 )
		.forEach( ( post, index ) => addSingle( `ref-${ index + 1 }`, post ) );
	ccPosts
		.slice( 0, 2 )
		.forEach( ( post, index ) => addSingle( `cc-${ index + 1 }`, post ) );
	mfnPosts
		.slice( 0, 1 )
		.forEach( ( post, index ) => addSingle( `mfn-${ index + 1 }`, post ) );

	addSingle( 'aside', asidePost );
	addSingle( 'featured', featuredPost );
	addSingle( 'unfeatured', unfeaturedPost );

	sectionPosts.forEach( ( { section, count } ) => {
		add(
			`archive-${ section }-1`,
			`/category/${ section }/`,
			KIND.ARCHIVE,
			{
				section,
			}
		);

		const perPage = archivePerPageBySection[ section ] ?? archivePerPage;
		const lastPage = Math.max( 1, Math.ceil( count / perPage ) );
		if ( lastPage > 1 ) {
			add(
				`archive-${ section }-last`,
				`/category/${ section }/page/${ lastPage }/`,
				KIND.ARCHIVE,
				{ section }
			);
		}
	} );

	add( 'category-writing', '/category/writing/', KIND.ARCHIVE, {
		section: 'writing',
	} );
	add( 'writing-page', '/writing/', KIND.PAGE, { section: 'writing' } );

	add( 'series-hub', '/series/', KIND.ARCHIVE );
	seriesSlugs.forEach( ( slug ) =>
		add( `series-${ slug }`, `/series/${ slug }/`, KIND.SINGLE )
	);

	add( 'tag-wordpress', '/tag/wordpress/', KIND.ARCHIVE );
	add( 'date-2014-03', '/2014/03/', KIND.ARCHIVE );
	add( 'search-wordpress', '/?s=wordpress', KIND.SEARCH );
	add( 'speaking', '/speaking/', KIND.PAGE );
	add( 'blog', '/blog/', KIND.PAGE );
	add( 'not-found', '/this-path-does-not-exist-404-check/', KIND.NOT_FOUND, {
		expectStatus: 404,
	} );

	return {
		generated: new Date().toISOString(),
		host,
		screens,
	};
}

/**
 * Whether `live.spec.mjs`'s merged-paragraph (R2-01, SPEC §6.8) check should run against a
 * screen: any screen that went through conversion, not just screens still `classic` today --
 * a converted `ref-*`/`cc-*`/`mfn-*` screen's content is now block markup (`classic: false`)
 * but its paragraphs are exactly what the wpautop() merge regression would have broken.
 *
 * @param {{converted?: boolean}} screen A `screens.json` entry.
 * @return {boolean} True when the merged-paragraph check applies.
 */
export function checksMergedParagraphs( screen ) {
	return Boolean( screen && screen.converted );
}
