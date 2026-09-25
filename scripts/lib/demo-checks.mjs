/**
 * Pure demo-content checks (P0-05, SPEC §3.2 rules 53/56, §5, §6.1, P2-04 §6.4). No I/O: every
 * function takes already-read data and returns an array of failure strings (empty = pass).
 */

const IMAGE_RAW_BASE =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/docs/fixtures/demo/images';
const WXR_RAW_URL =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/.github/demo-content.xml';

const OPTIONS_KEYS = [
	'blogname',
	'blogdescription',
	'timezone_string',
	'permalink_structure',
	'ttm_books',
	'ttm_verse',
	'ttm_verse_history',
	'ttm_settings',
];

// The SPEC §6.4 step sequence: each entry is either a bare step name, or `wp-cli:<command>`
// for a `wp-cli` step (the final `demo:verify` command's dynamic args are matched by prefix).
const STEP_SEQUENCE = [
	'setSiteOptions',
	'installPlugin',
	'installTheme',
	'wp-cli:wp site empty --yes',
	'importWxr',
	'wp-cli:wp rewrite structure /%postname%/ --hard',
	'wp-cli:wp ttm primary:assign',
	'wp-cli:wp ttm recount --all',
	'wp-cli:wp ttm series:rebuild',
	'wp-cli:wp ttm stats:flush',
	'wp-cli:wp ttm demo:verify',
];

/**
 * A dependency-free well-formedness check: every non-self-closing opening tag has a matching
 * closing tag, correctly nested. Not a real XML parser (no entity/namespace validation), but
 * catches the actual failure mode this check exists for -- a normalisation bug that leaves a
 * tag unclosed or mismatched. `jsdom`'s `DOMParser` would be more thorough, but this module is
 * dynamically `import()`-ed by both a plain-Node CLI script and Jest tests, and requiring
 * `jsdom` from inside a dynamically-imported `.mjs` breaks under this project's Jest setup the
 * same way `scripts/convert-classic.mjs`'s own `createRequire( import.meta.url )` does (spike
 * Outcome B, `docs/spikes/P8-01.md`) -- confirmed directly: `await import()`-ing any `.mjs` file
 * that itself has a top-level `import { createRequire } from 'node:module'` fails Jest with
 * "Must use import to load ES Module", even with no jsdom/`.mjs` import involved at all.
 *
 * @param {string} xml XML string.
 * @return {boolean} True when tags are balanced and correctly nested.
 */
function parsesAsXml( xml ) {
	const text = xml
		.replace( /<\?[\s\S]*?\?>/g, '' )
		.replace( /<!--[\s\S]*?-->/g, '' )
		.replace( /<!\[CDATA\[[\s\S]*?\]\]>/g, '' )
		.replace( /<!DOCTYPE[\s\S]*?>/gi, '' );

	const tagRe = /<(\/?)([a-zA-Z_][\w:.-]*)\b[^>]*?(\/?)>/g;
	const stack = [];
	let match;
	while ( ( match = tagRe.exec( text ) ) ) {
		const [ , closing, name, selfClosing ] = match;
		if ( closing ) {
			if ( stack.pop() !== name ) {
				return false;
			}
		} else if ( ! selfClosing ) {
			stack.push( name );
		}
	}
	return 0 === stack.length;
}

/**
 * A `<tag>`'s content (CDATA-unwrapped, or empty for `<tag/>`), or null if absent. A small
 * duplicate of `demo/lib/wxr.mjs`'s own helper -- a static ESM import of one local `.mjs` from
 * another breaks under this project's Jest setup (`await import()` of the outer module then
 * fails with "Must use import to load ES Module" on the inner one), so `countItems()`/
 * `attachmentBasenames()`'s small regexes are reimplemented here rather than shared.
 *
 * @param {string} str String to search.
 * @param {string} tag Tag name.
 * @return {string|null} Value, or null.
 */
function getTag( str, tag ) {
	const match = str.match(
		new RegExp( `<${ tag }\\s*/>|<${ tag }>([\\s\\S]*?)<\\/${ tag }>` )
	);
	if ( ! match ) {
		return null;
	}
	if ( undefined === match[ 1 ] ) {
		return '';
	}
	const cdata = match[ 1 ].match( /^<!\[CDATA\[([\s\S]*)\]\]>$/ );
	return cdata ? cdata[ 1 ] : match[ 1 ];
}

/**
 * Count `<item>` rows by `wp:post_type` (a local copy of `wxr.mjs`'s `countItems()` -- see the
 * note on `getTag()`).
 *
 * @param {string} xml WXR.
 * @return {{post: number, page: number, attachment: number}} Counts.
 */
function countItems( xml ) {
	const items = [ ...xml.matchAll( /<item>[\s\S]*?<\/item>/g ) ].map(
		( m ) => m[ 0 ]
	);
	const counts = { post: 0, page: 0, attachment: 0 };
	for ( const item of items ) {
		const type = getTag( item, 'wp:post_type' );
		if ( type && Object.prototype.hasOwnProperty.call( counts, type ) ) {
			counts[ type ]++;
		}
	}
	return counts;
}

/**
 * Every attachment item's file basename (a local copy of `wxr.mjs`'s `attachmentBasenames()` --
 * see the note on `getTag()`).
 *
 * @param {string} xml WXR.
 * @return {string[]} Basenames.
 */
function attachmentBasenames( xml ) {
	const items = [ ...xml.matchAll( /<item>[\s\S]*?<\/item>/g ) ].map(
		( m ) => m[ 0 ]
	);
	const names = [];
	for ( const item of items ) {
		if ( 'attachment' === getTag( item, 'wp:post_type' ) ) {
			const url = getTag( item, 'wp:attachment_url' ) || '';
			names.push( url.substring( url.lastIndexOf( '/' ) + 1 ) );
		}
	}
	return names;
}

/**
 * The distinct demo image basenames a set of seed fixture rows actually reference.
 *
 * @param {Array<{featured_image?: unknown}>} rows Parsed `posts.json`/`pages.json` rows.
 * @return {Set<string>} Distinct `featured_image` basenames.
 */
function distinctFixtureImages( rows ) {
	const names = new Set();
	for ( const row of rows ) {
		if ( 'string' === typeof row.featured_image && row.featured_image ) {
			names.add( row.featured_image );
		}
	}
	return names;
}

/**
 * One `wp-cli`/other step's comparable signature, matching `STEP_SEQUENCE`'s shape.
 *
 * @param {Object} step Blueprint step.
 * @return {string} `<step>` or `wp-cli:<command>`.
 */
function stepSignature( step ) {
	return 'wp-cli' === step.step ? `wp-cli:${ step.command }` : step.step;
}

/**
 * SPEC §6.4/§6.3: validate `demo:build`'s three generated outputs together (each one alone
 * can be internally consistent while still disagreeing with the others or the source fixtures).
 *
 * @param {Object}        data               Already-read data.
 * @param {string}        data.wxr           `demo-content.xml` contents.
 * @param {Object}        data.options       Parsed `demo-options.json`.
 * @param {Object}        data.blueprint     Parsed `blueprint.json`.
 * @param {string[]}      data.imageFiles    `docs/fixtures/demo/images/` file names.
 * @param {Array<Object>} data.credits       Parsed `CREDITS.json`.
 * @param {Object}        data.fixtures      `{posts: Array, pages: Array}` (parsed seed fixtures).
 * @param {string}        data.pluginVersion The plugin's `Version:` header value, e.g. `0.2.0`.
 * @return {string[]} Failures.
 */
export function checkDemoOutputs( {
	wxr,
	options,
	blueprint,
	imageFiles,
	credits,
	fixtures,
	pluginVersion,
} ) {
	const failures = [];

	if ( ! parsesAsXml( wxr ) ) {
		failures.push( 'demo-content.xml does not parse as XML' );
		return failures;
	}

	const counts = countItems( wxr );
	const expectedPosts = fixtures.posts.length;
	const expectedPages = fixtures.pages.length;
	const expectedAttachments = distinctFixtureImages( [
		...fixtures.posts,
		...fixtures.pages,
	] ).size;

	if ( counts.post !== expectedPosts ) {
		failures.push(
			`demo-content.xml has ${ counts.post } post item(s), expected ${ expectedPosts } (posts.json rows)`
		);
	}
	if ( counts.page !== expectedPages ) {
		failures.push(
			`demo-content.xml has ${ counts.page } page item(s), expected ${ expectedPages } (pages.json rows)`
		);
	}
	if ( counts.attachment !== expectedAttachments ) {
		failures.push(
			`demo-content.xml has ${ counts.attachment } attachment item(s), expected ${ expectedAttachments } (distinct fixture featured_image files)`
		);
	}

	const imageFileSet = new Set( imageFiles );
	const creditFiles = new Set( ( credits || [] ).map( ( row ) => row.file ) );
	for ( const name of attachmentBasenames( wxr ) ) {
		if ( ! imageFileSet.has( name ) ) {
			failures.push(
				`${ name }: attachment has no matching file in docs/fixtures/demo/images/`
			);
		}
		if ( ! creditFiles.has( name ) ) {
			failures.push( `${ name }: attachment has no CREDITS.json row` );
		}
	}

	const attachmentUrls = [
		...wxr.matchAll( /<wp:attachment_url>([^<]*)<\/wp:attachment_url>/g ),
	].map( ( m ) => m[ 1 ] );
	for ( const url of attachmentUrls ) {
		if ( ! url.startsWith( IMAGE_RAW_BASE ) ) {
			failures.push(
				`${ url }: attachment URL doesn't start with the raw GitHub image base`
			);
		}
	}

	const haystacks = [
		wxr,
		JSON.stringify( options ),
		JSON.stringify( blueprint ),
	];
	for ( const haystack of haystacks ) {
		if ( /localhost|127\.0\.0\.1|:8888/.test( haystack ) ) {
			failures.push(
				'a generated output still contains a local host reference'
			);
		}
		if ( /[\w.+-]+@[\w-]+\.[\w.-]+/.test( haystack ) ) {
			failures.push(
				'a generated output still contains an e-mail address'
			);
		}
	}

	const optionsKeys = Object.keys( options );
	if (
		optionsKeys.length !== OPTIONS_KEYS.length ||
		! OPTIONS_KEYS.every( ( key, index ) => optionsKeys[ index ] === key )
	) {
		failures.push(
			`demo-options.json keys are [${ optionsKeys.join(
				', '
			) }], expected exactly [${ OPTIONS_KEYS.join( ', ' ) }]`
		);
	}

	const steps = blueprint.steps || [];
	const signatures = steps.map( stepSignature );
	const orderMatches =
		signatures.length === STEP_SEQUENCE.length &&
		STEP_SEQUENCE.every( ( expected, index ) =>
			expected.endsWith( 'demo:verify' )
				? ( signatures[ index ] || '' ).startsWith( expected )
				: signatures[ index ] === expected
		);
	if ( ! orderMatches ) {
		failures.push(
			`blueprint.json steps are [${ signatures.join(
				', '
			) }], expected the SPEC §6.4 order [${ STEP_SEQUENCE.join( ', ' ) }]`
		);
	}

	const installPlugin = steps.find(
		( step ) => 'installPlugin' === step.step
	);
	const installTheme = steps.find( ( step ) => 'installTheme' === step.step );
	const releaseTag = `release=v${ pluginVersion }`;
	if (
		! installPlugin ||
		! installPlugin.pluginData?.url?.includes( releaseTag )
	) {
		failures.push( `installPlugin's URL doesn't contain ${ releaseTag }` );
	}
	if (
		! installTheme ||
		! installTheme.themeData?.url?.includes( releaseTag )
	) {
		failures.push( `installTheme's URL doesn't contain ${ releaseTag }` );
	}

	const importWxr = steps.find( ( step ) => 'importWxr' === step.step );
	if ( ! importWxr || importWxr.file?.url !== WXR_RAW_URL ) {
		failures.push( `importWxr's URL isn't ${ WXR_RAW_URL }` );
	}

	const setSiteOptions = steps.find(
		( step ) => 'setSiteOptions' === step.step
	);
	if (
		! setSiteOptions ||
		JSON.stringify( setSiteOptions.options ) !== JSON.stringify( options )
	) {
		failures.push(
			"setSiteOptions.options doesn't deep-equal demo-options.json"
		);
	}

	return failures;
}

const FILE_NAME_RE = /^demo-[a-z0-9-]+\.jpg$/;

const CREDITS_FIELDS = [
	'file',
	'openverse_id',
	'title',
	'creator',
	'creator_url',
	'source',
	'foreign_landing_url',
	'license',
	'license_url',
	'width',
	'height',
	'bytes',
	'sha256',
	'attribution',
];

/**
 * Rule 53: every `docs/fixtures/demo/images/` file has a matching, complete, correctly-sized
 * `CREDITS.json` row under a CC0/PDM licence, and the total stays under budget.
 *
 * @param {{files: {name: string, bytes: number, sha256: string}[], credits: Array|null}} data   Image files and parsed CREDITS.json (null when absent).
 * @param {{maxBytes: number, budgetBytes: number}}                                       limits `IMAGE_MAX_BYTES`/`IMAGE_BUDGET_BYTES`.
 * @return {string[]} Failures.
 */
export function checkCredits( { files, credits }, { maxBytes, budgetBytes } ) {
	const failures = [];

	if ( 0 === files.length && ( null === credits || 0 === credits.length ) ) {
		return failures;
	}

	if ( null === credits ) {
		return [
			'docs/fixtures/demo/CREDITS.json is missing but image files exist',
		];
	}

	for ( const file of files ) {
		if ( ! FILE_NAME_RE.test( file.name ) ) {
			failures.push(
				`${ file.name }: file name doesn't match ^demo-[a-z0-9-]+\\.jpg$`
			);
		}
		if ( file.bytes > maxBytes ) {
			failures.push(
				`${ file.name }: ${ file.bytes } bytes exceeds IMAGE_MAX_BYTES (${ maxBytes })`
			);
		}
	}

	const fileByName = new Map( files.map( ( f ) => [ f.name, f ] ) );
	const rowFiles = new Set();

	for ( const row of credits ) {
		const name = row.file;
		if ( ! name ) {
			failures.push( 'CREDITS.json has a row with no file' );
			continue;
		}
		rowFiles.add( name );

		for ( const field of CREDITS_FIELDS ) {
			if ( 'creator_url' === field ) {
				if ( ! Object.prototype.hasOwnProperty.call( row, field ) ) {
					failures.push( `${ name }: missing field "${ field }"` );
				}
				continue;
			}
			const value = row[ field ];
			if ( undefined === value || null === value || '' === value ) {
				failures.push( `${ name }: missing field "${ field }"` );
			}
		}

		if ( row.license && ! [ 'cc0', 'pdm' ].includes( row.license ) ) {
			failures.push(
				`${ name }: license "${ row.license }" is not cc0/pdm`
			);
		}

		const file = fileByName.get( name );
		if ( ! file ) {
			failures.push( `${ name }: CREDITS row has no matching file` );
			continue;
		}
		if ( undefined !== row.bytes && row.bytes !== file.bytes ) {
			failures.push(
				`${ name }: CREDITS bytes (${ row.bytes }) doesn't match the file (${ file.bytes })`
			);
		}
		if ( undefined !== row.sha256 && row.sha256 !== file.sha256 ) {
			failures.push( `${ name }: CREDITS sha256 doesn't match the file` );
		}
	}

	for ( const file of files ) {
		if ( ! rowFiles.has( file.name ) ) {
			failures.push( `${ file.name }: no CREDITS.json row` );
		}
	}

	const total = files.reduce( ( sum, f ) => sum + f.bytes, 0 );
	if ( total > budgetBytes ) {
		failures.push(
			`total image bytes (${ total }) exceeds IMAGE_BUDGET_BYTES (${ budgetBytes })`
		);
	}

	return failures;
}

/**
 * Every string `featured_image` referenced by a seed fixture row matches the demo file name
 * pattern and exists among the actual demo image files.
 *
 * @param {Array<{featured_image?: unknown}>} rows      Parsed `posts.json`/`pages.json` rows.
 * @param {string[]}                          fileNames Actual `docs/fixtures/demo/images/` file names.
 * @return {string[]} Failures.
 */
export function checkFixtureImages( rows, fileNames ) {
	const failures = [];
	const files = new Set( fileNames );

	for ( const row of rows ) {
		const value = row.featured_image;
		if ( 'string' !== typeof value ) {
			continue;
		}
		if ( ! FILE_NAME_RE.test( value ) ) {
			failures.push(
				`${ row.slug ?? '(unknown)' }: featured_image "${ value }" doesn't match ^demo-[a-z0-9-]+\\.jpg$`
			);
			continue;
		}
		if ( ! files.has( value ) ) {
			failures.push(
				`${ row.slug ?? '(unknown)' }: featured_image "${ value }" has no matching demo file`
			);
		}
	}

	return failures;
}

const SCREENSHOT_REF_RE =
	/!\[[^\]]*\]\(\.github\/screenshots\/([^)]+)\)|<img[^>]+src=["']\.github\/screenshots\/([^"']+)["']/g;

/**
 * Rule 56: every `.github/screenshots/<name>` referenced from `README.md` exists, every file
 * in that directory is referenced and is a `.png`.
 *
 * @param {string}   readme    `README.md` contents.
 * @param {string[]} fileNames Actual `.github/screenshots/` file names (empty array = no directory).
 * @return {string[]} Failures.
 */
export function checkReadmeScreenshots( readme, fileNames ) {
	const failures = [];
	const referenced = new Set();

	let match;
	SCREENSHOT_REF_RE.lastIndex = 0;
	while ( ( match = SCREENSHOT_REF_RE.exec( readme ) ) ) {
		const name = match[ 1 ] ?? match[ 2 ];
		referenced.add( name );
		if ( ! fileNames.includes( name ) ) {
			failures.push(
				`README.md references missing screenshot ${ name }`
			);
		}
	}

	for ( const name of fileNames ) {
		if ( ! name.endsWith( '.png' ) ) {
			failures.push( `${ name }: not a .png file` );
		}
		if ( ! referenced.has( name ) ) {
			failures.push( `${ name }: not referenced from README.md` );
		}
	}

	return failures;
}
