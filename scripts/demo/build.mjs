#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run demo:build [-- --out <dir>] [--release <tag>] [--check-determinism]` (SPEC §6.3
 * steps 1-5, §6.4, P2-04). No network of its own: only `npx wp-env run cli` and local files.
 * Generates `<out>/demo-content.xml`, `<out>/demo-options.json` and `<out>/blueprint.json`.
 */
import { execFileSync } from 'node:child_process';
import {
	existsSync,
	mkdirSync,
	mkdtempSync,
	readdirSync,
	readFileSync,
	rmSync,
	writeFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { normalizeWxr, countItems } from './lib/wxr.mjs';
import {
	renderBlueprint,
	releaseFromPluginHeader,
	verifyArgs,
} from './lib/blueprint.mjs';
import { checkDemoOutputs } from '../lib/demo-checks.mjs';

const SITE_ORIGIN = 'http://localhost:8888';
const IMAGE_BASE =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/docs/fixtures/demo/images/';
const IMAGES_DIR = join( 'docs', 'fixtures', 'demo', 'images' );
const CREDITS_PATH = join( 'docs', 'fixtures', 'demo', 'CREDITS.json' );
const SEED_POSTS_PATH = join( 'docs', 'fixtures', 'seed', 'posts.json' );
const SEED_PAGES_PATH = join( 'docs', 'fixtures', 'seed', 'pages.json' );
const PLUGIN_MAIN_FILE = join( 'plugins', 'ttm-core', 'ttm-core.php' );
const BLUEPRINT_TEMPLATE_PATH = join(
	'scripts',
	'demo',
	'blueprint.template.json'
);
const FIXTURE_BUILD_DIR = join( 'docs', 'fixtures', '.demo-build' );
const CONTAINER_BUILD_DIR = 'wp-content/ttm-fixtures/.demo-build';

// `wp eval` code dumping every `series` term's ttm_status/ttm_form as one JSON line, keyed by
// slug -- the side channel normalizeWxr()'s `seriesTermMeta` option needs (see the call site).
const SERIES_TERM_META_PHP =
	'$out = []; ' +
	'foreach ( get_terms( array( "taxonomy" => "series", "hide_empty" => false ) ) as $t ) { ' +
	'$out[ $t->slug ] = array( ' +
	'"ttm_status" => (string) get_term_meta( $t->term_id, "ttm_status", true ), ' +
	'"ttm_form" => (string) get_term_meta( $t->term_id, "ttm_form", true ) ' +
	'); } ' +
	'echo wp_json_encode( $out );';

/**
 * @param {string[]} args  Raw CLI args.
 * @param {string}   name  Flag name (without `--`).
 * @param {string}   [def] Default.
 * @return {string|undefined} Value.
 */
function argValue( args, name, def ) {
	const index = args.indexOf( `--${ name }` );
	if ( -1 === index ) {
		return def;
	}
	return args[ index + 1 ];
}

/**
 * Run a child process, exiting with its output on failure.
 *
 * @param {string}   cmd  Command.
 * @param {string[]} args Args.
 * @return {string} stdout.
 */
function run( cmd, args ) {
	try {
		return execFileSync( cmd, args, { encoding: 'utf8' } );
	} catch ( error ) {
		if ( error.stdout ) {
			console.error( error.stdout );
		}
		if ( error.stderr ) {
			console.error( error.stderr );
		}
		console.error(
			`demo:build: command failed: ${ cmd } ${ args.join( ' ' ) }`
		);
		process.exit( 1 );
		return '';
	}
}

/**
 * Today's date, UTC, `YYYY-MM-DD` -- pinned once per `demo:build` invocation so a
 * `--check-determinism` run (which seeds twice) uses the same day for both.
 *
 * @return {string} Date.
 */
function utcToday() {
	return new Date().toISOString().slice( 0, 10 );
}

/**
 * Run SPEC §6.3 steps 1-5 once: reseed, export, normalise, read `demo:options`, render the
 * blueprint. Does not write anything to disk itself.
 *
 * @param {Object} opts         Options.
 * @param {string} opts.nowDate Pinned `YYYY-MM-DD` seed date.
 * @param {string} opts.release Release tag, e.g. `v0.2.0`.
 * @return {{wxr: string, options: Object, blueprint: Object}} The three outputs' data.
 */
function buildOnce( { nowDate, release } ) {
	run( 'npx', [
		'wp-env',
		'run',
		'cli',
		'wp',
		'ttm',
		'seed',
		'--reset',
		`--now=${ nowDate } 12:00:00`,
	] );

	if ( existsSync( FIXTURE_BUILD_DIR ) ) {
		rmSync( FIXTURE_BUILD_DIR, { recursive: true, force: true } );
	}
	mkdirSync( FIXTURE_BUILD_DIR, { recursive: true } );

	run( 'npx', [
		'wp-env',
		'run',
		'cli',
		'--',
		'wp',
		'export',
		`--dir=${ CONTAINER_BUILD_DIR }`,
		'--filename_format=export.xml',
		'--skip_comments',
	] );

	const exportXml = readFileSync(
		join( FIXTURE_BUILD_DIR, 'export.xml' ),
		'utf8'
	);
	const demoFiles = existsSync( IMAGES_DIR ) ? readdirSync( IMAGES_DIR ) : [];

	// `wp export` never emits `<wp:termmeta>` in any invocation (docs/spikes/P2-01.md) -- read
	// every `series` term's ttm_status/ttm_form directly and hand them to normalizeWxr to inject.
	const seriesMetaOutput = run( 'npx', [
		'wp-env',
		'run',
		'cli',
		'--',
		'wp',
		'eval',
		SERIES_TERM_META_PHP,
	] );
	const seriesTermMeta = JSON.parse(
		seriesMetaOutput.trim().split( '\n' ).pop()
	);

	const wxr = normalizeWxr( exportXml, {
		siteOrigin: SITE_ORIGIN,
		imageBase: IMAGE_BASE,
		demoFiles,
		seriesTermMeta,
	} );

	const optionsOutput = run( 'npx', [
		'wp-env',
		'run',
		'cli',
		'--',
		'wp',
		'ttm',
		'demo:options',
	] );
	const optionsLine = optionsOutput.trim().split( '\n' ).pop();
	const options = JSON.parse( optionsLine );

	const seriesOutput = run( 'npx', [
		'wp-env',
		'run',
		'cli',
		'--',
		'wp',
		'option',
		'get',
		'ttm_series_index',
		'--format=json',
	] );
	const seriesCount = JSON.parse( seriesOutput.trim() ).length;

	const template = JSON.parse(
		readFileSync( BLUEPRINT_TEMPLATE_PATH, 'utf8' )
	);
	const blueprint = renderBlueprint( template, {
		release,
		options,
		verifyArgs: verifyArgs( countItems( wxr ), seriesCount ),
	} );

	rmSync( FIXTURE_BUILD_DIR, { recursive: true, force: true } );

	return { wxr, options, blueprint };
}

/**
 * Write the three outputs to a directory.
 *
 * @param {string} dir               Target directory.
 * @param {Object} outputs           `{wxr, options, blueprint}`.
 * @param {string} outputs.wxr       Normalised WXR.
 * @param {Object} outputs.options   `demo:options` payload.
 * @param {Object} outputs.blueprint Rendered blueprint.
 */
function writeOutputs( dir, { wxr, options, blueprint } ) {
	mkdirSync( dir, { recursive: true } );
	writeFileSync( join( dir, 'demo-content.xml' ), wxr );
	writeFileSync(
		join( dir, 'demo-options.json' ),
		JSON.stringify( options, null, 2 ) + '\n'
	);
	writeFileSync(
		join( dir, 'blueprint.json' ),
		JSON.stringify( blueprint, null, 2 ) + '\n'
	);
}

/**
 * Run `checkDemoOutputs()` against a written directory's three files.
 *
 * @param {string} dir Directory containing the three files.
 * @return {string[]} Failures.
 */
function checkOutputsIn( dir ) {
	const pluginSource = readFileSync( PLUGIN_MAIN_FILE, 'utf8' );
	const versionMatch = pluginSource.match( /^\s*\*\s*Version:\s*(\S+)/m );

	const imageFiles = existsSync( IMAGES_DIR )
		? readdirSync( IMAGES_DIR )
		: [];
	const credits = existsSync( CREDITS_PATH )
		? JSON.parse( readFileSync( CREDITS_PATH, 'utf8' ) )
		: [];
	const posts = existsSync( SEED_POSTS_PATH )
		? JSON.parse( readFileSync( SEED_POSTS_PATH, 'utf8' ) )
		: [];
	const pages = existsSync( SEED_PAGES_PATH )
		? JSON.parse( readFileSync( SEED_PAGES_PATH, 'utf8' ) )
		: [];

	return checkDemoOutputs( {
		wxr: readFileSync( join( dir, 'demo-content.xml' ), 'utf8' ),
		options: JSON.parse(
			readFileSync( join( dir, 'demo-options.json' ), 'utf8' )
		),
		blueprint: JSON.parse(
			readFileSync( join( dir, 'blueprint.json' ), 'utf8' )
		),
		imageFiles,
		credits,
		fixtures: { posts, pages },
		pluginVersion: versionMatch ? versionMatch[ 1 ] : '0.0.0',
	} );
}

function main() {
	const args = process.argv.slice( 2 );
	const outDir = argValue( args, 'out', '.github' );
	const pluginSource = readFileSync( PLUGIN_MAIN_FILE, 'utf8' );
	const release = argValue(
		args,
		'release',
		releaseFromPluginHeader( pluginSource )
	);
	const checkDeterminism = args.includes( '--check-determinism' );

	const nowDate = utcToday();

	console.log(
		`demo:build: seeding and exporting (release ${ release }, date ${ nowDate })`
	);
	const outputs = buildOnce( { nowDate, release } );
	writeOutputs( outDir, outputs );

	const failures = checkOutputsIn( outDir );
	if ( failures.length > 0 ) {
		for ( const failure of failures ) {
			console.error( failure );
		}
		console.error( `demo:build: ${ failures.length } check failure(s)` );
		process.exit( 1 );
	}

	console.log(
		`demo:build: wrote ${ outDir }/demo-content.xml, demo-options.json, blueprint.json`
	);

	if ( checkDeterminism ) {
		console.log(
			'demo:build: --check-determinism: re-running into a temp directory'
		);
		const tempDir = mkdtempSync( join( tmpdir(), 'ttm-demo-build-' ) );
		try {
			const second = buildOnce( { nowDate, release } );
			writeOutputs( tempDir, second );

			for ( const file of [
				'demo-content.xml',
				'demo-options.json',
				'blueprint.json',
			] ) {
				const a = readFileSync( join( outDir, file ), 'utf8' );
				const b = readFileSync( join( tempDir, file ), 'utf8' );
				if ( a !== b ) {
					console.error(
						`demo:build: --check-determinism: ${ file } differs between runs`
					);
					process.exit( 1 );
				}
			}
			console.log(
				'demo:build: --check-determinism: ok, both runs byte-identical'
			);
		} finally {
			rmSync( tempDir, { recursive: true, force: true } );
		}
	}
}

main();
