/**
 * Pure builder of `docs/fixtures/live/screens.json`'s `screens` array from already-fetched
 * plain data (SPEC §6.10, PLAN Decision "screens.json shape"). No I/O, no wp-cli, no network --
 * the CLI wrapper (`scripts/live/screens.mjs`) fetches everything and calls this.
 *
 * `screens.json` shape (PLAN Decision):
 *   { generated: "<ISO>", host: "<LIVE_HOST>",
 *     screens: [{ id, path, kind: "front|single|archive|page|search|404",
 *                 expectStatus: 200|404, section: "<slug>"|null,
 *                 classic: boolean, freeform: boolean }] }
 */

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
 * @property {number}  id       Post ID (informational only; not written to the manifest).
 * @property {string}  slug     `post_name`.
 * @property {boolean} classic  Still has no `<!-- wp:` block markup.
 * @property {boolean} freeform Contains a `core/freeform`/`core/html` fallback block.
 */

/**
 * @param {Object}                                                           inputs
 * @param {string}                                                           inputs.host             `LIVE_HOST`.
 * @param {number}                                                           inputs.archivePerPage   `archive.per_page`, for each section's last page.
 * @param {Array<{section: string, newest: ScreenPost|null, count: number}>} inputs.sectionPosts
 *                                                                                                   One entry per section, in nav order.
 * @param {ScreenPost|null}                                                  [inputs.oldest]         Oldest published post overall.
 * @param {ScreenPost|null}                                                  [inputs.journalNewest]  Newest Journal post.
 * @param {ScreenPost[]}                                                     [inputs.refPosts]       Posts whose classic backup had `[ref]` (≤ 2 used).
 * @param {ScreenPost[]}                                                     [inputs.ccPosts]        Posts whose classic backup had `[cci]`/`[cc]` (≤ 2 used).
 * @param {ScreenPost[]}                                                     [inputs.mfnPosts]       Posts whose classic backup had `[mfn]` (≤ 1 used).
 * @param {ScreenPost|null}                                                  [inputs.asidePost]      A `post-format-aside` post.
 * @param {ScreenPost|null}                                                  [inputs.featuredPost]   A post with a featured image.
 * @param {ScreenPost|null}                                                  [inputs.unfeaturedPost] A post without one.
 * @param {string[]}                                                         [inputs.seriesSlugs]    `docs/migration/series.json` slugs.
 * @return {{generated: string, host: string, screens: Array<object>}} The `screens.json` manifest.
 */
export function buildScreens( inputs ) {
	const {
		host,
		archivePerPage,
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

		const lastPage = Math.max( 1, Math.ceil( count / archivePerPage ) );
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
