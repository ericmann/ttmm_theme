#!/usr/bin/env node
/* eslint-disable no-console */
// Demo-content lint (P0-05, SPEC §3.2 rules 53/56; P2-04 SPEC §6.4): wires the pure checks in
// scripts/lib/demo-checks.mjs to the real docs/fixtures/demo/ tree, the seed fixtures,
// README.md, .github/screenshots/ and (once demo:build has run) the three .github/ demo outputs.
import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { join } from 'node:path';
import {
	checkCredits,
	checkFixtureImages,
	checkReadmeScreenshots,
	checkDemoOutputs,
} from './lib/demo-checks.mjs';
import { IMAGE_MAX_BYTES, IMAGE_BUDGET_BYTES } from './demo/lib/constants.mjs';

const IMAGES_DIR = join( 'docs', 'fixtures', 'demo', 'images' );
const CREDITS_PATH = join( 'docs', 'fixtures', 'demo', 'CREDITS.json' );
const SCREENSHOTS_DIR = join( '.github', 'screenshots' );
const OUT_DIR = '.github';
const PLUGIN_MAIN_FILE = join( 'plugins', 'ttm-core', 'ttm-core.php' );

// P2-04: once demo:build has landed the three .github/ outputs, check-demo requires them --
// a missing file is a real regression (someone deleted a generated, committed output), not an
// "hasn't been built yet" state to skip quietly.
const REQUIRE_OUTPUTS = true;

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

const wxrPath = join( OUT_DIR, 'demo-content.xml' );
const optionsPath = join( OUT_DIR, 'demo-options.json' );
const blueprintPath = join( OUT_DIR, 'blueprint.json' );
const outputsExist =
	existsSync( wxrPath ) &&
	existsSync( optionsPath ) &&
	existsSync( blueprintPath );

if ( ! outputsExist && REQUIRE_OUTPUTS ) {
	failures.push(
		`${ OUT_DIR }/demo-content.xml, demo-options.json and blueprint.json are required (run npm run demo:build)`
	);
} else if ( outputsExist ) {
	const pluginSource = existsSync( PLUGIN_MAIN_FILE )
		? readFileSync( PLUGIN_MAIN_FILE, 'utf8' )
		: '';
	const versionMatch = pluginSource.match( /^\s*\*\s*Version:\s*(\S+)/m );

	const postsPath = join( 'docs', 'fixtures', 'seed', 'posts.json' );
	const pagesPath = join( 'docs', 'fixtures', 'seed', 'pages.json' );

	failures.push(
		...checkDemoOutputs( {
			wxr: readFileSync( wxrPath, 'utf8' ),
			options: JSON.parse( readFileSync( optionsPath, 'utf8' ) ),
			blueprint: JSON.parse( readFileSync( blueprintPath, 'utf8' ) ),
			imageFiles: imageFiles.map( ( f ) => f.name ),
			credits: credits || [],
			fixtures: {
				posts: existsSync( postsPath )
					? JSON.parse( readFileSync( postsPath, 'utf8' ) )
					: [],
				pages: existsSync( pagesPath )
					? JSON.parse( readFileSync( pagesPath, 'utf8' ) )
					: [],
			},
			pluginVersion: versionMatch ? versionMatch[ 1 ] : '0.0.0',
		} )
	);
}

if ( failures.length > 0 ) {
	for ( const failure of failures ) {
		console.error( failure );
	}
	console.error( `check-demo: ${ failures.length } failure(s)` );
	process.exit( 1 );
}

console.log( 'check-demo: clean' );
