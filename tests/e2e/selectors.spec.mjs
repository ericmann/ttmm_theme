/**
 * Runtime selector coverage (SPEC §3.2 rule 41, §6.9 row `selectors`, P0-03).
 *
 * Every `.ttm-`/`.is-style-` selector `ttm.css`/`style.css` defines must match at least one
 * element somewhere across the seeded screen set; a selector that never matches anywhere is
 * either dead CSS or CSS for a screen/state this run doesn't cover, and must be listed in
 * `tests/e2e/selectors-allow.txt` with a reason.
 */

import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import { test, expect } from '@playwright/test';
import {
	selectorsOf,
	queryable,
	isExempt,
	parseSelectorAllow,
} from '../../scripts/lib/css-selectors.mjs';
import { SCREEN_URLS, securityFiltered } from './lib/urls.mjs';

const __dirname = dirname( fileURLToPath( import.meta.url ) );
const ROOT = join( __dirname, '..', '..' );

const CSS_FILES = [
	'themes/ttm-theme/assets/css/ttm.css',
	'themes/ttm-theme/style.css',
];

const ALLOW_PATH = join( __dirname, 'selectors-allow.txt' );

function relevantSelectors() {
	const cssText = CSS_FILES.map( ( p ) =>
		readFileSync( join( ROOT, p ), 'utf8' )
	).join( '\n' );
	const allow = parseSelectorAllow( readFileSync( ALLOW_PATH, 'utf8' ) );
	const allowed = new Set( allow.map( ( entry ) => entry.selector ) );

	const candidates = new Map();
	for ( const entry of selectorsOf( cssText ) ) {
		if ( ! /\.(ttm-|is-style-)/.test( entry.selector ) ) {
			continue;
		}
		if ( isExempt( entry ) || allowed.has( entry.selector ) ) {
			continue;
		}
		const query = queryable( entry.selector );
		if ( query === '' ) {
			continue;
		}
		candidates.set( query, entry.selector );
	}
	return candidates;
}

const SCREENS = [ ...SCREEN_URLS, securityFiltered ];

test( `selectors: every non-exempt .ttm-/.is-style- selector matches >= 1 element across ${ SCREENS.length } screens`, async ( {
	page,
} ) => {
	await page.setViewportSize( { width: 1280, height: 900 } );

	const candidates = relevantSelectors();
	const matchCounts = new Map(
		[ ...candidates.keys() ].map( ( query ) => [ query, 0 ] )
	);

	for ( const path of SCREENS ) {
		await page.goto( path );
		for ( const query of candidates.keys() ) {
			const count = await page.evaluate(
				( sel ) => document.querySelectorAll( sel ).length,
				query
			);
			matchCounts.set( query, matchCounts.get( query ) + count );
		}
	}

	const unmatched = [ ...matchCounts.entries() ]
		.filter( ( [ , count ] ) => count === 0 )
		.map( ( [ query ] ) => candidates.get( query ) );

	expect( unmatched, JSON.stringify( unmatched, null, 2 ) ).toEqual( [] );
} );
