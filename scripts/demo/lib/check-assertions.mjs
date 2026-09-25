/**
 * Pure §6.4 page assertions for `demo:check` (P2-06). No I/O, no DOM library: a small
 * dependency-free regex scan over each fetched page's raw HTML. `jsdom` was the PLAN's original
 * plan, but a dynamically `import()`-ed `.mjs` that pulls in `jsdom` (even lazily, even the
 * package's own top-level `import`) fails Jest with "Must use import to load ES Module" under
 * this project's Jest setup -- confirmed directly: `jsdom`'s own dependency chain
 * (`html-encoding-sniffer` -> `@exodus/bytes`) hits the exact ESM-interop wall `docs/spikes/
 * P8-01.md` (spike Outcome B) already found for `@wordpress/block-library`. `check.mjs` runs
 * this module both for real (plain Node, no jsdom global) and under Jest (`demo-check.test.js`),
 * so it has to work without a DOM implementation at all.
 */

const REQUIRED_SECTION_LABELS = [
	'Technology',
	'Business',
	'Faith',
	'Journal',
	'Writing',
	'Security',
	'Opinion',
];

const ERROR_MARKERS = [ 'Warning:', 'Notice:', 'Deprecated:', 'Fatal error' ];

/**
 * Every value captured by a class-scoped regex, e.g. every `<span class="foo">…</span>` text.
 *
 * @param {string} html       Page HTML.
 * @param {string} tagPattern Tag name alternation, e.g. `h[1-6]`.
 * @param {string} className  Exact class token to require.
 * @return {string[]} Captured inner text, in document order.
 */
function textsOf( html, tagPattern, className ) {
	const re = new RegExp(
		`<(?:${ tagPattern })\\b[^>]*class="[^"]*\\b${ className }\\b[^"]*"[^>]*>([^<]*)<\\/(?:${ tagPattern })>`,
		'g'
	);
	const out = [];
	let match;
	while ( ( match = re.exec( html ) ) ) {
		out.push( match[ 1 ].trim() );
	}
	return out;
}

/**
 * Whether an `<img ...>` whose nearest ancestor tag carries `className` has a `src` containing
 * `demo-` (the WXR normalisation's own marker for a real demo photograph).
 *
 * @param {string} html      Page HTML.
 * @param {string} className Ancestor class token, e.g. `ttm-lead__media`.
 * @return {boolean} True when such an image exists.
 */
function hasDemoImageIn( html, className ) {
	const wrapperRe = new RegExp(
		`class="[^"]*\\b${ className }\\b[^"]*"[^>]*>([\\s\\S]{0,2000}?)<\\/(?:figure|div|a)>`
	);
	const match = html.match( wrapperRe );
	if ( ! match ) {
		return false;
	}
	return /<img\b[^>]*src="[^"]*demo-[^"]*"/.test( match[ 1 ] );
}

/**
 * One fetched page's failures against the §6.4 checks common to every page.
 *
 * @param {string}                         name Page name, for the failure messages (`front`, `article`, …).
 * @param {{status: number, html: string}} page Fetched page.
 * @return {string[]} Failures.
 */
function checkCommon( name, page ) {
	const failures = [];

	if ( 200 !== page.status ) {
		failures.push( `${ name }: status ${ page.status }, expected 200` );
	}

	for ( const marker of ERROR_MARKERS ) {
		if ( page.html.includes( marker ) ) {
			failures.push( `${ name }: page contains "${ marker }"` );
		}
	}

	if ( page.html.includes( 'Uncategorized' ) ) {
		failures.push( `${ name }: page contains "Uncategorized"` );
	}

	return failures;
}

/**
 * SPEC §6.4's four page checks: `front`, `article`, `series`, `writing`, each
 * `{status, html}`. Every assertion, including the two "has a real demo photograph" checks,
 * always runs: R1-01 gives the local Playground variant a loopback-allow mu-plugin so its
 * `importWxr` step actually fetches attachment binaries, so there is no longer a variant where
 * a demo photograph is expected to be absent.
 *
 * @param {Object}                         pages         Fetched pages.
 * @param {{status: number, html: string}} pages.front   Front page.
 * @param {{status: number, html: string}} pages.article Article page.
 * @param {{status: number, html: string}} pages.series  Series index page.
 * @param {{status: number, html: string}} pages.writing Writing page.
 * @return {string[]} Every failure across all four pages (empty = pass).
 */
export function checkPages( { front, article, series, writing } ) {
	const failures = [
		...checkCommon( 'front', front ),
		...checkCommon( 'article', article ),
		...checkCommon( 'series', series ),
		...checkCommon( 'writing', writing ),
	];

	if ( ! hasDemoImageIn( front.html, 'ttm-lead__media' ) ) {
		failures.push( 'front: no demo photograph in .ttm-lead__media img' );
	}

	const labels = textsOf( front.html, 'h[1-6]', 'ttm-cell-heading__label' );
	for ( const required of REQUIRED_SECTION_LABELS ) {
		if ( ! labels.includes( required ) ) {
			failures.push(
				`front: .ttm-cell-heading__label is missing "${ required }"`
			);
		}
	}

	const seriesRowCount = (
		front.html.match( /class="ttm-series-row"/g ) || []
	).length;
	if ( seriesRowCount < 3 ) {
		failures.push(
			`front: .ttm-series-strip .ttm-series-row count is ${ seriesRowCount }, expected >= 3`
		);
	}

	if ( ! hasDemoImageIn( article.html, 'wp-block-post-featured-image' ) ) {
		failures.push(
			'article: no demo photograph in .wp-block-post-featured-image img'
		);
	}

	const seriesTitles = new Set(
		textsOf( series.html, 'span', 'ttm-series-row__title' )
	);
	if ( 7 !== seriesTitles.size ) {
		failures.push(
			`series: ${ seriesTitles.size } distinct .ttm-series-row__title text(s), expected 7`
		);
	}

	if ( ! writing.html.includes( 'ttm-serial-hero' ) ) {
		failures.push( 'writing: .ttm-serial-hero not present' );
	}

	return failures;
}
