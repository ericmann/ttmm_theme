/**
 * Pure CSS-selector parsing for the runtime coverage spec (SPEC §3.2 rule 41, P0-03,
 * Decision "Rule 41 selectors spec").
 *
 * `tests/e2e/selectors.spec.mjs` visits every seeded screen and asserts every `.ttm-`/
 * `.is-style-` selector `ttm.css`/`style.css` defines matches at least one element somewhere
 * across the set. This module only extracts selectors from CSS text; the spec owns browsing
 * and querying.
 */

/**
 * Strip `/* ... *\/` comments from CSS text, without being confused by `{`/`}` characters
 * that happen to appear inside a comment.
 *
 * @param {string} cssText Raw CSS.
 * @return {string} Comment-free CSS.
 */
function stripComments( cssText ) {
	return cssText.replace( /\/\*[\s\S]*?\*\//g, '' );
}

/**
 * Split a selector list on top-level commas (commas inside `(...)`, e.g.
 * `:not(a, b)`, do not split).
 *
 * @param {string} prelude Raw selector list text (before the `{`).
 * @return {string[]} Individual selectors, trimmed.
 */
function splitPrelude( prelude ) {
	const parts = [];
	let depth = 0;
	let current = '';

	for ( const char of prelude ) {
		if ( char === '(' ) {
			depth++;
		} else if ( char === ')' ) {
			depth = Math.max( 0, depth - 1 );
		}

		if ( char === ',' && depth === 0 ) {
			parts.push( current.trim() );
			current = '';
			continue;
		}

		current += char;
	}

	if ( current.trim() !== '' ) {
		parts.push( current.trim() );
	}

	return parts.filter( ( s ) => s !== '' );
}

/**
 * Walk comment-free CSS, tracking brace nesting and `@media` depth, and return every style
 * rule's selectors (at-rules with no selector prelude, like `@media`/`@font-face` bodies
 * themselves, are skipped as containers, not selectors).
 *
 * @param {string} cssText Raw CSS.
 * @return {{selector: string, inMedia: boolean}[]} Every selector, one entry each.
 */
export function selectorsOf( cssText ) {
	const text = stripComments( cssText );
	const out = [];

	let i = 0;
	let mediaDepth = 0; // brace depth at which we entered the current @media block; 0 = not in one.
	let depth = 0;
	let buffer = '';

	while ( i < text.length ) {
		const char = text[ i ];

		if ( char === '{' ) {
			const prelude = buffer.trim();
			buffer = '';
			depth++;

			if ( prelude.startsWith( '@' ) ) {
				if ( /^@media\b/.test( prelude ) ) {
					mediaDepth = mediaDepth || depth;
				}
				// Other at-rules (@font-face, @supports, …) are containers, not selectors;
				// their contents are handled by the normal loop as it continues.
			} else if ( prelude !== '' ) {
				const inMedia = mediaDepth !== 0 && depth > mediaDepth - 1;
				for ( const selector of splitPrelude( prelude ) ) {
					out.push( { selector, inMedia } );
				}
			}
		} else if ( char === '}' ) {
			if ( mediaDepth !== 0 && depth === mediaDepth ) {
				mediaDepth = 0;
			}
			depth = Math.max( 0, depth - 1 );
			buffer = '';
		} else {
			buffer += char;
		}

		i++;
	}

	return out;
}

/**
 * Strip pseudo-elements a real DOM query can't take: `::before`, `::after`, and any
 * `::-webkit-*` pseudo-element. Pseudo-*classes* (`:hover`, `:focus`, …) are left alone —
 * they're valid in `querySelectorAll` and just narrow the match.
 *
 * @param {string} selector Raw selector.
 * @return {string} A selector safe to pass to `document.querySelectorAll`.
 */
export function queryable( selector ) {
	return selector
		.replace( /::(before|after)\b/g, '' )
		.replace( /::-webkit-[a-z-]+/g, '' )
		.trim();
}

const AUTO_EXEMPT_SUBSTRINGS = [
	':hover',
	':focus',
	':focus-visible',
	':active',
	'[data-state',
	'.is-empty',
	'.is-nocover',
	'.is-textonly',
	'::-webkit-scrollbar',
	'.ttm-nav-open',
];

/**
 * Whether a selector entry is auto-exempt from the zero-match check: state/interaction
 * selectors that only apply conditionally, and anything found inside `@media` (a selector
 * may only match at a viewport the coverage run doesn't visit).
 *
 * @param {{selector: string, inMedia: boolean}} entry Parsed selector entry.
 * @return {boolean} True if the entry should never be reported as unmatched.
 */
export function isExempt( entry ) {
	if ( entry.inMedia ) {
		return true;
	}
	return AUTO_EXEMPT_SUBSTRINGS.some( ( needle ) =>
		entry.selector.includes( needle )
	);
}

/**
 * Parse `tests/e2e/selectors-allow.txt`: `<selector> # <reason>` lines, blank/comment-only
 * lines ignored. Mirrors `scripts/lib/css-coverage.mjs`'s `parseAllowList` shape (a literal
 * selector match, not a glob — rule 41 allow-list entries are exact selectors).
 *
 * @param {string} text Raw allow-list file contents.
 * @return {{selector: string, reason: string}[]} Parsed entries.
 */
export function parseSelectorAllow( text ) {
	const entries = [];

	for ( const rawLine of text.split( '\n' ) ) {
		const line = rawLine.trim();
		if ( line === '' || line.startsWith( '#' ) ) {
			continue;
		}

		const hashIndex = line.indexOf( '#' );
		const selector = (
			hashIndex === -1 ? line : line.slice( 0, hashIndex )
		).trim();
		const reason =
			hashIndex === -1 ? '' : line.slice( hashIndex + 1 ).trim();

		if ( selector === '' ) {
			continue;
		}

		entries.push( { selector, reason } );
	}

	return entries;
}
