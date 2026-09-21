/* eslint-disable no-console */
// CSS coverage lint (SPEC §3.2 rules 34/37, P0-01). Every ttm-* class emitted
// by markup has a selector in ttm.css/style.css, and every ttm-* selector in
// those files matches emitted markup. Unresolved gaps must be listed, one
// line per group of classes, in scripts/css-coverage-allow.txt (< 10 lines).
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';
import {
	collectMarkupClasses,
	collectCssClasses,
	parseAllowList,
	report,
} from './lib/css-coverage.mjs';

const MARKUP_ROOTS = [
	'themes/ttm-theme/templates',
	'themes/ttm-theme/parts',
	'themes/ttm-theme/patterns',
	'themes/ttm-theme/inc',
];

const CSS_FILES = [
	'themes/ttm-theme/assets/css/ttm.css',
	'themes/ttm-theme/style.css',
];

const ALLOW_PATH = 'scripts/css-coverage-allow.txt';

// Files under plugins/ttm-core/src that are known not to contain markup
// (e.g. pure data/config classes) never emit a ttm-* class literal, so
// walking them costs nothing; nothing is skipped today.
const SRC_SKIP = [];

/**
 * Recursively list files under `dir`.
 *
 * @param {string} dir Directory to walk.
 * @return {string[]} Absolute-ish file paths (relative to CWD).
 */
function walk( dir ) {
	let out = [];
	let entries;
	try {
		entries = readdirSync( dir, { withFileTypes: true } );
	} catch {
		return out;
	}
	for ( const entry of entries ) {
		const full = join( dir, entry.name );
		if ( entry.isDirectory() ) {
			out = out.concat( walk( full ) );
		} else {
			out.push( full );
		}
	}
	return out;
}

function readAll( paths ) {
	return paths.map( ( p ) => readFileSync( p, 'utf8' ) );
}

const markupFiles = [];
for ( const root of MARKUP_ROOTS ) {
	markupFiles.push( ...walk( root ) );
}

const renderFiles = readdirSync( 'plugins/ttm-core/blocks' )
	.map( ( name ) => join( 'plugins/ttm-core/blocks', name, 'render.php' ) )
	.filter( ( p ) => {
		try {
			return statSync( p ).isFile();
		} catch {
			return false;
		}
	} );

const srcFiles = walk( 'plugins/ttm-core/src' ).filter(
	( p ) => p.endsWith( '.php' ) && ! SRC_SKIP.includes( p )
);

const allMarkupFiles = [ ...markupFiles, ...renderFiles, ...srcFiles ];

const markup = collectMarkupClasses( readAll( allMarkupFiles ) );
const css = collectCssClasses( readAll( CSS_FILES ).join( '\n' ) );
const allow = parseAllowList( readFileSync( ALLOW_PATH, 'utf8' ) );

const { missing, dead, allowCount } = report( { markup, css, allow } );

if ( missing.length > 0 ) {
	console.error(
		'css-coverage: markup classes with no ttm.css/style.css selector:'
	);
	missing.forEach( ( cls ) => console.error( `  ${ cls }` ) );
}

if ( dead.length > 0 ) {
	console.error(
		'css-coverage: ttm.css/style.css selectors with no matching markup:'
	);
	dead.forEach( ( cls ) => console.error( `  ${ cls }` ) );
}

if ( allowCount >= 10 ) {
	console.error(
		`css-coverage: ${ ALLOW_PATH } has ${ allowCount } entries; keep it under 10 (rule 34/37).`
	);
}

if ( missing.length > 0 || dead.length > 0 || allowCount >= 10 ) {
	process.exit( 1 );
}

console.log(
	`css-coverage: ${ markup.size } markup classes, ${ css.size } css classes, ${ allowCount } allow-listed`
);
