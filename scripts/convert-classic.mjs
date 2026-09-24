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
 * Usage: node scripts/convert-classic.mjs <in.ndjson> <out.ndjson> [--allow-freeform] [--allow-text-mismatch]
 *   in.ndjson lines:  {"id":6917,"slug":"...","content_raw":"<p>...</p>","footnotes_meta":null}
 *   out.ndjson lines: {"id":...,"slug":...,"blocks":"<!-- wp:paragraph -->...","footnotes":[...],
 *                      "report":{"blockCounts":{...},"freeform":0,"html":0,"textEqual":true,
 *                                "shortcodes":[{"name":"seoslides","count":1}],"footnotes":2}}
 *   report.freeform/report.html are the `core/freeform`/`core/html` counts respectively (PLAN
 *   P8-01's contract), each also present individually in report.blockCounts; kept as their own
 *   fields because they're the two block names `--allow-freeform` treats as a decision to make.
 *   report.shortcodes (P3-01, SPEC §6.8) lists any shortcode the pre-pass deliberately left in
 *   place (today: `seoslides`); report.footnotes is the merged shortcode + modern-footnotes
 *   count.
 *
 * Exit 1 if any post has report.textEqual === false, or (without --allow-freeform) any post has
 * report.freeform > 0 or report.html > 0. `--allow-text-mismatch` (R1-03, SPEC §1.3 "Done",
 * §6.6: the whole live plan must run non-interactively) still prints every text-mismatch line
 * and still writes report.textEqual: false for each such record in the output ndjson, but exits
 * 0 -- `ConvertCommand::import()` reads that flag off the record and skips converting it (SPEC
 * §6.6, rule 49: non-destructive), rather than the whole `plan.sh` run aborting on real,
 * pre-existing classic content the export can't perfectly round-trip.
 */

import { createRequire } from 'node:module';
import { readFileSync, writeFileSync } from 'node:fs';
import { transformFootnotes } from './lib/footnotes.mjs';
import { preprocessShortcodes } from './lib/shortcodes.mjs';
import { buildBlockReport } from './lib/report.mjs';
import { summarizeResults } from './lib/summarize.mjs';
import { autoParagraphPlainText } from './lib/autop.mjs';

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
 * `[caption]`, `[gallery]` and `[audio]` are deliberately left for `rawHandler` itself to
 * convert (their own native shortcode-type transforms -- see `preprocessShortcodes`'s
 * docblock; `preprocessShortcodes` does rewrite the legacy bare-URL `[audio http://…]` form to
 * the attribute syntax first, R1-09, but that's still an `[audio …]` shortcode at this point, not
 * yet a block), so their own bracket/attribute syntax (`[caption id="…" …]`/`[/caption]`,
 * `[gallery ids="…"]`, `[audio src="…"]`) survives into the "old" side of a `textEqual`
 * comparison as literal text with no `<`/`>` characters for the tag strip below to catch, even
 * though it was never meant to be visible content and `rawHandler`'s own conversion correctly
 * drops it. Stripped here (comparison only -- this never touches the HTML actually passed to
 * `rawHandler`) so a converted post doesn't read as a false `textEqual: false`. Discovered via
 * real failures on the export; see docs/feedback/phase-4/LIVE-TRIAGE.md, which also records the
 * residual failure class this doesn't fix: legacy `<code>`/`<blockquote>` markup in the classic
 * content itself containing unescaped nested HTML, a pre-existing content-quality issue
 * unrelated to shortcodes.
 *
 * @param {string} html
 * @return {string} The normalized text.
 */
function normalizedText( html ) {
	const withoutShortcodeWrappers = html
		.replace( /\[caption[^\]]*\]/g, '' )
		.replace( /\[\/caption\]/g, '' )
		.replace( /\[gallery[^\]]*\]/g, '' )
		.replace( /\[audio[^\]]*\]/g, '' );
	const withoutTags = withoutShortcodeWrappers.replace( /<[^>]+>/g, ' ' );
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
	const plainTextParagraphed = autoParagraphPlainText( post.content_raw );

	const {
		html: afterShortcodes,
		footnotes: shortcodeFootnotes,
		remaining,
	} = preprocessShortcodes( plainTextParagraphed, post.id );

	const { html: transformedHtml, footnotes: mfnFootnotes } =
		transformFootnotes(
			afterShortcodes,
			post.id,
			shortcodeFootnotes.length + 1
		);

	const footnotes = [ ...shortcodeFootnotes, ...mfnFootnotes ];

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
		report: {
			blockCounts,
			freeform,
			html,
			textEqual,
			shortcodes: remaining,
			footnotes: footnotes.length,
		},
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
		.filter(
			( a ) => a !== '--allow-freeform' && a !== '--allow-text-mismatch'
		);
	const allowFreeform = process.argv.includes( '--allow-freeform' );
	const allowTextMismatch = process.argv.includes( '--allow-text-mismatch' );
	const [ inputPath, outputPath ] = args;

	if ( ! inputPath || ! outputPath ) {
		console.error(
			'Usage: node scripts/convert-classic.mjs <in.ndjson> <out.ndjson> [--allow-freeform] [--allow-text-mismatch]'
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

	const { failed, messages } = summarizeResults( results, {
		allowFreeform,
		allowTextMismatch,
	} );
	messages.forEach( ( message ) => console.error( message ) );

	if ( failed ) {
		process.exit( 1 );
	}

	console.log( `Converted ${ results.length } post(s) -> ${ outputPath }` );
}

// Only run as a CLI when invoked directly (not when imported by the Jest test).
if ( import.meta.url === `file://${ process.argv[ 1 ] }` ) {
	main();
}
