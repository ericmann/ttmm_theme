#!/usr/bin/env node
/* eslint-disable no-console */
// Demo-content lint (P0-05, SPEC §3.2 rules 53/56): wires the pure checks in
// scripts/lib/demo-checks.mjs to the real docs/fixtures/demo/ tree, the seed fixtures,
// README.md and .github/screenshots/.
import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';
import {
	checkCredits,
	checkFixtureImages,
	checkReadmeScreenshots,
} from './lib/demo-checks.mjs';
import { IMAGE_MAX_BYTES, IMAGE_BUDGET_BYTES } from './demo/lib/constants.mjs';

const IMAGES_DIR = join( 'docs', 'fixtures', 'demo', 'images' );
const CREDITS_PATH = join( 'docs', 'fixtures', 'demo', 'CREDITS.json' );
const SCREENSHOTS_DIR = join( '.github', 'screenshots' );

/**
 * @param {string} dir Directory path.
 * @return {string[]} File names (not full paths), or [] if the directory doesn't exist.
 */
function listDir( dir ) {
	if ( ! existsSync( dir ) ) {
		return [];
	}
	return readdirSync( dir ).filter( ( name ) =>
		statSync( join( dir, name ) ).isFile()
	);
}

const imageFiles = listDir( IMAGES_DIR ).map( ( name ) => {
	const buffer = readFileSync( join( IMAGES_DIR, name ) );
	return {
		name,
		bytes: buffer.length,
		sha256: createHash( 'sha256' ).update( buffer ).digest( 'hex' ),
	};
} );

const credits = existsSync( CREDITS_PATH )
	? JSON.parse( readFileSync( CREDITS_PATH, 'utf8' ) )
	: null;

const failures = [
	...checkCredits(
		{ files: imageFiles, credits },
		{ maxBytes: IMAGE_MAX_BYTES, budgetBytes: IMAGE_BUDGET_BYTES }
	),
];

const seedRows = [];
for ( const fixture of [ 'posts.json', 'pages.json' ] ) {
	const path = join( 'docs', 'fixtures', 'seed', fixture );
	if ( existsSync( path ) ) {
		seedRows.push( ...JSON.parse( readFileSync( path, 'utf8' ) ) );
	}
}
failures.push(
	...checkFixtureImages(
		seedRows,
		imageFiles.map( ( f ) => f.name )
	)
);

const readme = existsSync( 'README.md' )
	? readFileSync( 'README.md', 'utf8' )
	: '';
failures.push(
	...checkReadmeScreenshots( readme, listDir( SCREENSHOTS_DIR ) )
);

if ( failures.length > 0 ) {
	for ( const failure of failures ) {
		console.error( failure );
	}
	console.error( `check-demo: ${ failures.length } failure(s)` );
	process.exit( 1 );
}

console.log( 'check-demo: clean' );
