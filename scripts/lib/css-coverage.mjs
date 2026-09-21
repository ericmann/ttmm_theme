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

/**
 * Parse the allow-list file. Blank lines and comment-only lines (starting
 * with `#` once trimmed) are ignored. Each remaining line is
 * `<glob pattern> # <reason>`.
 *
 * @param {string} text Raw allow-list file contents.
 * @return {{pattern: string, regex: RegExp, reason: string}[]} Parsed entries.
 */
export function parseAllowList( text ) {
	const entries = [];

	for ( const rawLine of text.split( '\n' ) ) {
		const line = rawLine.trim();
		if ( line === '' || line.startsWith( '#' ) ) {
			continue;
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

		entries.push( { pattern, regex: globToRegExp( pattern ), reason } );
	}

	return entries;
}

/**
 * Compare markup classes against CSS classes, filtering both directions
 * through the allow-list.
 *
 * @param {{markup: Set<string>, css: Set<string>, allow: {pattern: string, regex: RegExp, reason: string}[]}} args Comparison inputs.
 * @return {{missing: string[], dead: string[], allowCount: number}} Gaps found.
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

	return { missing, dead, allowCount: allow.length };
}
