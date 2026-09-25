/**
 * Pure Openverse search/selection/credit logic for `demo:fetch-images` (P1-01, SPEC §6.1,
 * §6.2, §6.8, rules 53/54). No network, no filesystem -- `scripts/demo/fetch-images.mjs` is the
 * only place that actually talks to Openverse or writes a file.
 */

const FILE_NAME_RE = /^demo-[a-z0-9-]+\.jpg$/;
const ORIENTATIONS = [ 'wide', 'tall', 'square' ];
const ALLOWED_TYPES = [ 'jpg', 'jpeg', 'png' ];

// The rule 53 CREDITS.json field order, `attribution` last.
const CREDITS_FIELD_ORDER = [
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
 * `images.json` must be exactly 13 rows, each `file` unique and matching the demo file-name
 * pattern, each `orientation` one of wide/tall/square.
 *
 * @param {Array<{file: string, orientation: string}>} rows Parsed `images.json`.
 * @return {string[]} Failures (empty = valid).
 */
export function validateRows( rows ) {
	const failures = [];

	if ( 13 !== rows.length ) {
		failures.push( `expected 13 rows, got ${ rows.length }` );
	}

	const seen = new Set();
	for ( const row of rows ) {
		if ( ! FILE_NAME_RE.test( row.file ) ) {
			failures.push(
				`${ row.file }: doesn't match ^demo-[a-z0-9-]+\\.jpg$`
			);
		}
		if ( seen.has( row.file ) ) {
			failures.push( `${ row.file }: duplicate file` );
		}
		seen.add( row.file );

		if ( ! ORIENTATIONS.includes( row.orientation ) ) {
			failures.push(
				`${ row.file }: orientation "${ row.orientation }" is not wide/tall/square`
			);
		}
	}

	return failures;
}

/**
 * The exact Openverse search URL for one `images.json` row.
 *
 * @param {{query: string, orientation: string}} row     One `images.json` row.
 * @param {{pageSize: number, licenses: string}} options `OPENVERSE_PAGE_SIZE`/`OPENVERSE_LICENSES`.
 * @return {string} The search URL.
 */
export function searchUrl( row, { pageSize, licenses } ) {
	return (
		`https://api.openverse.org/v1/images/?q=${ encodeURIComponent( row.query ) }` +
		`&license=${ licenses }` +
		`&aspect_ratio=${ row.orientation }` +
		`&size=large&mature=false&page_size=${ pageSize }`
	);
}

/**
 * The file extension `pickResult` falls back to when an Openverse result's own `filetype` is
 * `null` (real Openverse data sometimes omits it).
 *
 * @param {string} url Result URL.
 * @return {string} Lower-cased extension, without the leading dot; `''` if none.
 */
function extensionFromUrl( url ) {
	const match = ( url || '' ).match( /\.([a-zA-Z0-9]+)(?:\?.*)?$/ );
	return match ? match[ 1 ].toLowerCase() : '';
}

/**
 * The first Openverse result that qualifies: wide enough, an allowed licence, an allowed file
 * type, and not already chosen for another row, explicitly excluded for this row, or skipped
 * (e.g. a previous over-`IMAGE_MAX_BYTES` attempt).
 *
 * @param {Array<Object>}                                                                                        results Openverse `results` array.
 * @param {{minWidth: number, licenses: string[], chosenIds?: string[], exclude?: string[], skipIds?: string[]}} options Selection constraints.
 * @return {Object|null} The chosen result, or `null` if nothing qualifies.
 */
export function pickResult(
	results,
	{ minWidth, licenses, chosenIds = [], exclude = [], skipIds = [] }
) {
	const allowedLicenses = new Set(
		licenses.map( ( license ) => license.toLowerCase() )
	);
	const chosen = new Set( chosenIds );
	const excluded = new Set( exclude );
	const skipped = new Set( skipIds );

	for ( const result of results ) {
		if ( ! result || 'number' !== typeof result.width ) {
			continue;
		}
		if ( result.width < minWidth ) {
			continue;
		}
		if ( ! allowedLicenses.has( ( result.license || '' ).toLowerCase() ) ) {
			continue;
		}
		// Rule 53's CREDITS.json row needs a real `creator` and `title` (only `creator_url`
		// may be null) -- some real Openverse results carry a null creator and/or title, so
		// those can't be used even though every other field qualifies.
		if ( ! result.creator || ! result.title ) {
			continue;
		}
		const type = (
			result.filetype || extensionFromUrl( result.url )
		).toLowerCase();
		if ( ! ALLOWED_TYPES.includes( type ) ) {
			continue;
		}
		if (
			chosen.has( result.id ) ||
			excluded.has( result.id ) ||
			skipped.has( result.id )
		) {
			continue;
		}

		return result;
	}

	return null;
}

/**
 * Whether a downloaded-and-re-encoded image is acceptable: wide enough (its *actual* decoded
 * width, not the Openverse search result's own possibly-wrong metadata) and not over budget.
 * `pickResult()` already filters candidates on the API's reported `width`, but real Openverse
 * metadata sometimes disagrees with the file it actually serves (R1-02/review F3) -- this is the
 * second, authoritative check against the bytes actually downloaded.
 *
 * @param {{width: number, bytes: number}}       encoded Decoded width and encoded byte size.
 * @param {{minWidth: number, maxBytes: number}} limits  `OPENVERSE_MIN_WIDTH`/`IMAGE_MAX_BYTES`.
 * @return {boolean} True when the encoded image qualifies.
 */
export function acceptEncoded( { width, bytes }, { minWidth, maxBytes } ) {
	return width >= minWidth && bytes <= maxBytes;
}

/**
 * The rule 53 `CREDITS.json` row for a downloaded, re-encoded image.
 *
 * @param {Object}                                                         result  Chosen Openverse result.
 * @param {string}                                                         file    The written file's name.
 * @param {{width: number, height: number, bytes: number, sha256: string}} written Dimensions/size/hash of the file actually written (after `sharp` re-encoding).
 * @return {Object} A rule 53 CREDITS row.
 */
export function creditRow( result, file, { width, height, bytes, sha256 } ) {
	return {
		file,
		openverse_id: result.id,
		title: result.title,
		creator: result.creator,
		creator_url: result.creator_url ?? null,
		source: result.source,
		foreign_landing_url: result.foreign_landing_url,
		license: ( result.license || '' ).toLowerCase(),
		license_url: result.license_url,
		width,
		height,
		bytes,
		sha256,
		attribution: result.attribution,
	};
}

/**
 * `CREDITS.json` rows, ordered by `file`, each with its rule 53 keys in a fixed order
 * (`attribution` last).
 *
 * @param {Array<Object>} rows Credit rows.
 * @return {Array<Object>} Sorted, key-ordered rows.
 */
export function sortCredits( rows ) {
	return [ ...rows ]
		.sort( ( a, b ) => a.file.localeCompare( b.file ) )
		.map( ( row ) => {
			const ordered = {};
			for ( const key of CREDITS_FIELD_ORDER ) {
				ordered[ key ] = row[ key ];
			}
			return ordered;
		} );
}
