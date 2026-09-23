/* eslint-disable no-console */
// Zero-fixme guard (SPEC §6.2, §6.9, P4-02, P0-01). By the end of the flight every row in the
// fidelity and editor-registration suites must be a real, passing test -- `test.fixme(` left in
// either file means a row was never finished. During the flight, a `test.fixme(` line tagged
// `// P<n>-<nn>` is tolerated (the task that will un-fixme it); an untagged one always fails.
// Once every phase has landed (P5-02), flip ALLOW_TAGGED to false so no fixme survives at all.
import { readFileSync } from 'node:fs';
import { taggedFixmeHits } from './lib/fixme.mjs';

const FILES = [ 'tests/e2e/fidelity.spec.mjs', 'tests/e2e/editors.spec.mjs' ];

// P0-01: phase 4 in flight; P5-03 flips this back.
export const ALLOW_TAGGED = true;

let hits = 0;
let tagged = 0;

for ( const file of FILES ) {
	const lines = readFileSync( file, 'utf8' ).split( '\n' );
	for ( const hit of taggedFixmeHits( lines, ALLOW_TAGGED ) ) {
		console.error( `${ file }:${ hit.index + 1 }: ${ hit.line.trim() }` );
		hits += 1;
	}
	lines.forEach( ( line ) => {
		if (
			/test\.fixme\(/.test( line ) &&
			/\/\/\s*P\d-\d\d\b/.test( line )
		) {
			tagged += 1;
		}
	} );
}

console.log( `check-fixme: ${ tagged } tagged fixme row(s)` );

if ( hits > 0 ) {
	console.error( `check-fixme: ${ hits } fixme occurrence(s) remain` );
	process.exit( 1 );
}

console.log( 'check-fixme: clean' );
