/**
 * Pure demo-content checks (P0-05, SPEC §3.2 rules 53/56, §5, §6.1). No I/O: every function
 * takes already-read data and returns an array of failure strings (empty = pass).
 */

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
