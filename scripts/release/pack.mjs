#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * `npm run release:pack` (SPEC §6.6, P2-05): builds a deterministic `dist/ttm-core.zip` (with
 * `build/`) and `dist/ttm-theme.zip`, each unpacking to one top-level directory named after its
 * slug.
 */
import { execFileSync } from 'node:child_process';
import {
	existsSync,
	mkdirSync,
	readFileSync,
	readdirSync,
	statSync,
	writeFileSync,
} from 'node:fs';
import { join, relative, sep } from 'node:path';
import { writeZip } from './lib/zip.mjs';
import { pluginFiles, themePaths } from './lib/files.mjs';

const DIST_DIR = 'dist';
const LICENSE_PATH = 'LICENSE';

/**
 * Every file (not directory) under `dir`, as repo-relative POSIX paths.
 *
 * @param {string} dir Directory to walk.
 * @return {string[]} Repo-relative paths.
 */
function walk( dir ) {
	if ( ! existsSync( dir ) ) {
		return [];
	}
	const out = [];
	for ( const name of readdirSync( dir ) ) {
		const full = join( dir, name );
		if ( statSync( full ).isDirectory() ) {
			out.push( ...walk( full ) );
		} else {
			out.push( relative( '.', full ).split( sep ).join( '/' ) );
		}
	}
	return out;
}

/**
 * Build one zip: reads every `{from, to}` pair's bytes, prefixes `to` with `slug/`, adds the
 * root `LICENSE` as `<slug>/LICENSE`, writes `dist/<slug>.zip`.
 *
 * @param {string}                       slug  `ttm-core` or `ttm-theme`.
 * @param {{from: string, to: string}[]} pairs Files to pack.
 * @return {{path: string, bytes: number}} Where it was written and its size.
 */
function buildZip( slug, pairs ) {
	const entries = pairs.map( ( { from, to } ) => ( {
		name: `${ slug }/${ to }`,
		data: readFileSync( from ),
	} ) );

	entries.push( {
		name: `${ slug }/LICENSE`,
		data: readFileSync( LICENSE_PATH ),
	} );

	const buffer = writeZip( entries );
	mkdirSync( DIST_DIR, { recursive: true } );
	const outPath = join( DIST_DIR, `${ slug }.zip` );
	writeFileSync( outPath, buffer );

	return { path: outPath, bytes: buffer.length };
}

function main() {
	console.log( 'release:pack: npm run build' );
	execFileSync( 'npm', [ 'run', 'build' ], { stdio: 'inherit' } );

	const tracked = execFileSync(
		'git',
		[ 'ls-files', 'plugins/ttm-core', 'themes/ttm-theme' ],
		{ encoding: 'utf8' }
	)
		.split( '\n' )
		.filter( Boolean );

	const buildFiles = walk( join( 'plugins', 'ttm-core', 'build' ) );

	const allPaths = [ ...new Set( [ ...tracked, ...buildFiles ] ) ];

	const pluginZip = buildZip( 'ttm-core', pluginFiles( allPaths ) );
	const themeZip = buildZip( 'ttm-theme', themePaths( allPaths ) );

	for ( const { path, bytes } of [ pluginZip, themeZip ] ) {
		console.log( `release:pack: wrote ${ path } (${ bytes } bytes)` );
	}
}

main();
