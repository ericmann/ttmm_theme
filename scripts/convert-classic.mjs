#!/usr/bin/env node
/* eslint-disable no-console */
/**
 * Classic-to-block conversion spike (P8-01, SPEC §6.7, Q-M3).
 *
 * Verifies (and exploits, once verified) the ⚠️ ASSUMPTION that `@wordpress/blocks`'
 * `rawHandler()` + `serialize()` run under plain Node with jsdom well enough to convert
 * pre-2016 classic-editor content with zero `core/freeform`/`core/html` fallback blocks and no
 * text loss. See docs/spikes/P8-01.md for the write-up; this file is the tool that write-up is
 * based on.
 *
 * Usage: node scripts/convert-classic.mjs <in.ndjson> <out.ndjson> [--allow-freeform]
 *   in.ndjson lines:  {"id":6917,"slug":"...","content_raw":"<p>...</p>","footnotes_meta":null}
 *   out.ndjson lines: {"id":...,"slug":...,"blocks":"<!-- wp:paragraph -->...","footnotes":[...],
 *                      "report":{"blockCounts":{...},"freeform":0,"html":0,"textEqual":true}}
 *   report.freeform/report.html are the `core/freeform`/`core/html` counts respectively (PLAN
 *   P8-01's contract), each also present individually in report.blockCounts; kept as their own
 *   fields because they're the two block names `--allow-freeform` treats as a decision to make.
 *
 * Exit 1 if any post has report.textEqual === false, or (without --allow-freeform) any post has
 * report.freeform > 0 or report.html > 0.
 */

import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { transformFootnotes } from './lib/footnotes.mjs';
import { buildBlockReport } from './lib/report.mjs';

/**
 * jsdom + a real DOM global setup, then load @wordpress/blocks and @wordpress/block-library via
 * require() (not dynamic import()): their ESM (build-module) entry points import `.json` files
 * without the `with { type: "json" }` import-attribute Node's ESM loader now requires, which
 * fails outright; the CJS (build) entry points have no such restriction and work unmodified.
 * This was approach 1 (import()) failing and approach 2 (require() via createRequire) working —
 * recorded in docs/spikes/P8-01.md.
 *
 * `jsdom` itself is require()'d here too (not a top-level `import`), so that importing this
 * module -- e.g. `convertPost` for a report-shape-only test with a stub editor -- doesn't pull
 * in `jsdom` (and everything it drags in) at all; only actually calling this function does.
 *
 * @return {{ rawHandler: Function, serialize: Function }} The two functions the converter needs.
 */
function setUpBlockEditorEnvironment() {
	const require = createRequire( import.meta.url );
	const { JSDOM, VirtualConsole } = require( 'jsdom' );

	const virtualConsole = new VirtualConsole();
	// jsdom can't parse every stylesheet @wordpress/block-library's editor styles ship (SCSS
	// artifacts baked into the build); harmless for conversion, so swallow the console noise.
	const dom = new JSDOM( '<!doctype html><html><body></body></html>', {
		pretendToBeVisual: true,
		virtualConsole,
	} );
	const window = dom.window;

	const setGlobal = ( name, value ) => {
		Object.defineProperty( globalThis, name, {
			value,
			configurable: true,
			writable: true,
		} );
	};

	setGlobal( 'window', window );
	setGlobal( 'document', window.document );
	setGlobal( 'navigator', window.navigator );
	setGlobal( 'Node', window.Node );
	setGlobal( 'Element', window.Element );
	setGlobal( 'HTMLElement', window.HTMLElement );
	setGlobal( 'DOMParser', window.DOMParser );
	setGlobal( 'CSSStyleDeclaration', window.CSSStyleDeclaration );
	setGlobal( 'getComputedStyle', window.getComputedStyle.bind( window ) );
	setGlobal(
		'MutationObserver',
		window.MutationObserver ||
			function () {
				return { observe() {}, disconnect() {} };
			}
	);
	setGlobal( 'requestAnimationFrame', ( cb ) => setTimeout( cb, 0 ) );

	const { rawHandler, serialize } = require( '@wordpress/blocks' );
	const { registerCoreBlocks } = require( '@wordpress/block-library' );
	registerCoreBlocks();

	return { rawHandler, serialize };
}

/**
 * Normalized plain text for comparison: HTML entities decoded (via a real DOM parse, so it's
 * exactly what a browser would show), tags stripped, whitespace collapsed. Comparing before vs.
 * after decoding equally on both sides is what makes this a real equality check rather than a
 * false "regression" from the DOM round-trip decoding entities the *raw* string never did.
 *
 * @param {string} html
 * @return {string} The normalized text.
 */
function normalizedText( html ) {
	const withoutTags = html.replace( /<[^>]+>/g, ' ' );
	const withoutEntities = document.createElement( 'div' );
	withoutEntities.innerHTML = withoutTags;

	return ( withoutEntities.textContent || '' ).replace( /\s+/g, ' ' ).trim();
}

/**
 * Convert one post's content to block markup.
 *
 * @param {{ id: number|string, slug: string, content_raw: string }} post
 * @param {{ rawHandler: Function, serialize: Function }}            editor
 * @return {{ id, slug, blocks: string, footnotes: Array, report: object }} The converted post record.
 */
export function convertPost( post, editor ) {
	const { html: transformedHtml, footnotes } = transformFootnotes(
		post.content_raw,
		post.id
	);

	const blockList = editor.rawHandler( { HTML: transformedHtml } );
	const serialized = editor.serialize( blockList );

	const { blockCounts, freeform, html } = buildBlockReport( blockList );

	const textEqual =
		normalizedText( transformedHtml ) === normalizedText( serialized );

	return {
		id: post.id,
		slug: post.slug,
		blocks: serialized,
		footnotes,
		report: { blockCounts, freeform, html, textEqual },
	};
}

/**
 * NDJSON: one JSON object per non-blank line.
 *
 * @param {string} path
 * @return {Array<object>} The parsed lines.
 */
function readNdjson( path ) {
	return readFileSync( path, 'utf8' )
		.split( '\n' )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => JSON.parse( line ) );
}

function main() {
	const args = process.argv
		.slice( 2 )
		.filter( ( a ) => a !== '--allow-freeform' );
	const allowFreeform = process.argv.includes( '--allow-freeform' );
	const [ inputPath, outputPath ] = args;

	if ( ! inputPath || ! outputPath ) {
		console.error(
			'Usage: node scripts/convert-classic.mjs <in.ndjson> <out.ndjson> [--allow-freeform]'
		);
		process.exit( 1 );
	}

	const editor = setUpBlockEditorEnvironment();
	const posts = readNdjson( inputPath );
	const results = posts.map( ( post ) => convertPost( post, editor ) );

	writeFileSync(
		outputPath,
		results.map( ( r ) => JSON.stringify( r ) ).join( '\n' ) + '\n'
	);

	let failed = false;
	for ( const result of results ) {
		if ( ! result.report.textEqual ) {
			console.error(
				`post ${ result.id } (${ result.slug }): text content changed`
			);
			failed = true;
		}
		const fallbackCount = result.report.freeform + result.report.html;
		if ( ! allowFreeform && fallbackCount > 0 ) {
			console.error(
				`post ${ result.id } (${ result.slug }): ${ fallbackCount } freeform/html block(s)`
			);
			failed = true;
		}
	}

	if ( failed ) {
		process.exit( 1 );
	}

	console.log( `Converted ${ results.length } post(s) -> ${ outputPath }` );
}

// Only run as a CLI when invoked directly (not when imported by the Jest test).
if ( import.meta.url === `file://${ process.argv[ 1 ] }` ) {
	main();
}
