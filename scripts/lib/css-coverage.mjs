/**
 * Pure functions for the CSS coverage lint (SPEC §3.2 rules 34/37, P0-01).
 *
 * Every `ttm-*` class emitted by markup must have a selector in ttm.css /
 * style.css, and every `ttm-*` selector in those files must match emitted
 * markup. This module only extracts class names from text; the CLI
 * (`scripts/check-css-coverage.mjs`) owns file discovery and reporting.
 */

const TTM_CLASS = /\bttm-[a-zA-Z0-9_-]+\b/g;

/**
 * Block wrapper classes with no dedicated rule by design (R1-10, shared with
 * `tests/e2e/lib/live.mjs`'s DOM coverage check, P4-04): `archive-by-year` and `most-read` apply
 * no layout of their own beyond their children's own selectors (`.ttm-archive-year`,
 * `.ttm-numbered__row`, …), so `ttm-archive`/`ttm-most-read` never need a CSS rule. A prior
 * `.ttm-archive, .ttm-most-read { display: block }` rule existed only to satisfy the coverage
 * scanner and was removed as a self-admitted no-op; this exemption is in its place.
 */
export const UNSTYLED_WRAPPERS = new Set( [ 'ttm-archive', 'ttm-most-read' ] );

/**
 * Dynamic `body_class()` identifier classes (`Templates/Hierarchy.php::body_classes()`, P4-04):
 * `ttm-section-{slug}`, `ttm-form-{form}` and `ttm-in-series` are content-driven hooks (an
 * unbounded set of category slugs / post-format values), never styled -- the same "identifier,
 * not visual" exemption `UNSTYLED_WRAPPERS` already carries for block wrappers, just for classes
 * PHP builds by string concatenation rather than a literal `class="…"`/`Helpers::wrapper()` the
 * static markup scanner above can even see. `tests/e2e/lib/live.mjs`'s runtime DOM scan can see
 * them (they're real classes in the live HTML), so it needs this list too.
 *
 * @param {string} cls A `ttm-*` class name.
 * @return {boolean} Whether `cls` is a known dynamic identifier class.
 */
export function isIdentifierClass( cls ) {
	return (
		/^ttm-section-[a-z0-9-]+$/.test( cls ) ||
		/^ttm-form-[a-z0-9-]+$/.test( cls ) ||
		'ttm-in-series' === cls
	);
}

/**
 * Collect every `ttm-*` class referenced by markup sources: class="…" /
 * class='…' attributes, `"className":"…"` JSON (block comments and
 * block.json), and `Helpers::wrapper( 'name', … )` calls, which emit
 * `ttm-<name>`.
 *
 * @param {string[]} files Raw file contents to scan.
 * @return {Set<string>} Class names referenced by markup.
 */
export function collectMarkupClasses( files ) {
	const found = new Set();

	const addAll = ( text ) => {
		const matches = text.match( TTM_CLASS );
		if ( matches ) {
			matches.forEach( ( cls ) => found.add( cls ) );
		}
	};

	for ( const content of files ) {
		// class="…" / class='…' attributes.
		for ( const m of content.matchAll(
			/\bclass\s*=\s*["']([^"']*)["']/g
		) ) {
			addAll( m[ 1 ] );
		}

		// "className":"…" JSON (block comments, block.json attributes).
		for ( const m of content.matchAll( /"className"\s*:\s*"([^"]*)"/g ) ) {
			addAll( m[ 1 ] );
		}

		// Helpers::wrapper( 'name', … ) -> ttm-<name>.
		for ( const m of content.matchAll(
			/Helpers::wrapper\(\s*'([a-z0-9_-]+)'/g
		) ) {
			found.add( `ttm-${ m[ 1 ] }` );
		}
	}

	return found;
}

/**
 * Collect every `ttm-*` class named by a CSS selector.
 *
 * @param {string} cssText Raw CSS.
 * @return {Set<string>} Class names selected by the stylesheet.
 */
export function collectCssClasses( cssText ) {
	const found = new Set();
	const matches = cssText.match( /\.ttm-[a-zA-Z0-9_-]+/g );
	if ( matches ) {
		matches.forEach( ( m ) => found.add( m.slice( 1 ) ) );
	}
	return found;
}

/**
 * Turn one allow-list glob into an anchored RegExp. `*` matches
 * `[a-z0-9_-]*`; `{a,b,c}` matches any one alternative; everything else is
 * escaped and matched literally.
 *
 * @param {string} pattern Glob pattern.
 * @return {RegExp} Anchored regular expression.
 */
export function globToRegExp( pattern ) {
	const escape = ( s ) => s.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	let out = '';

	for ( let i = 0; i < pattern.length; i++ ) {
		const c = pattern[ i ];
		if ( c === '*' ) {
			out += '[a-z0-9_-]*';
		} else if ( c === '{' ) {
			const end = pattern.indexOf( '}', i );
			if ( end === -1 ) {
				out += escape( c );
				continue;
			}
			const body = pattern.slice( i + 1, end );
			const alts = body.split( ',' ).map( escape );
			out += `(?:${ alts.join( '|' ) })`;
			i = end;
		} else {
			out += escape( c );
		}
	}

	return new RegExp( `^${ out }$` );
}

// P0-01: during the flight, a strict allow-list line names exactly one class
// and is tagged with the task that will style it: `ttm-<class> # P<n>-<nn>
// pending`. A line that does not match this shape is a strict-mode error
// (rule 34 amendment): the allow-list may no longer carry the phase-2 glob
// lines that hid whole groups of classes.
const STRICT_LINE = /^ttm-[a-z0-9_-]+ # P\d-\d\d pending$/;

/**
 * Parse the allow-list file. Blank lines and comment-only lines (starting
 * with `#` once trimmed) are ignored. Each remaining line is
 * `<glob pattern> # <reason>`.
 *
 * In `strict` mode (P0-01), every remaining line must match
 * `ttm-<class> # P<n>-<nn> pending` exactly; anything else (including the
 * old glob/brace syntax) throws.
 *
 * @param {string}  text             Raw allow-list file contents.
 * @param {Object}  [options]
 * @param {boolean} [options.strict] Reject non-tagged-pending lines.
 * @return {{pattern: string, regex: RegExp, reason: string, pending: boolean}[]} Parsed entries.
 */
export function parseAllowList( text, { strict = false } = {} ) {
	const entries = [];

	for ( const rawLine of text.split( '\n' ) ) {
		const line = rawLine.trim();
		if ( line === '' || line.startsWith( '#' ) ) {
			continue;
		}

		if ( strict && ! STRICT_LINE.test( line ) ) {
			throw new Error(
				`css-coverage-allow.txt: invalid line (strict mode requires "ttm-<class> # P<n>-<nn> pending"): "${ line }"`
			);
		}

		const hashIndex = line.indexOf( '#' );
		const pattern = (
			hashIndex === -1 ? line : line.slice( 0, hashIndex )
		).trim();
		const reason =
			hashIndex === -1 ? '' : line.slice( hashIndex + 1 ).trim();

		if ( pattern === '' ) {
			continue;
		}

		entries.push( {
			pattern,
			regex: globToRegExp( pattern ),
			reason,
			pending: /^P\d-\d\d pending$/.test( reason ),
		} );
	}

	return entries;
}

/**
 * Filter a list of `plugins/ttm-core/src` file paths, dropping any listed in
 * `skip` (R1-10): files known not to contain front-end markup (e.g. a
 * wp-admin-only repeater UI) are never walked for `ttm-*` classes, so a
 * class used only in wp-admin markup does not force a coverage-driven rename.
 *
 * @param {string[]} files List of file paths.
 * @param {string[]} skip  Paths to exclude, exactly as they appear in `files`.
 * @return {string[]} `files` with every `skip` entry removed.
 */
export function filterSrcFiles( files, skip ) {
	return files.filter( ( p ) => ! skip.includes( p ) );
}

/**
 * Compare markup classes against CSS classes, filtering both directions
 * through the allow-list.
 *
 * @param {{markup: Set<string>, css: Set<string>, allow: {pattern: string, regex: RegExp, reason: string, pending: boolean}[]}} args Comparison inputs.
 * @return {{missing: string[], dead: string[], allowCount: number, pendingCount: number}} Gaps found.
 */
export function report( { markup, css, allow } ) {
	const isAllowed = ( cls ) =>
		allow.some( ( entry ) => entry.regex.test( cls ) );

	const missing = [ ...markup ]
		.filter( ( cls ) => ! css.has( cls ) && ! isAllowed( cls ) )
		.sort();
	const dead = [ ...css ]
		.filter( ( cls ) => ! markup.has( cls ) && ! isAllowed( cls ) )
		.sort();

	const pendingCount = allow.filter( ( entry ) => entry.pending ).length;

	return { missing, dead, allowCount: allow.length, pendingCount };
}
