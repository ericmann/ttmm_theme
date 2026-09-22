/* eslint-disable no-console */
// Zero-fixme guard (SPEC §6.2, P4-02). By the end of the flight every row in the fidelity and
// editor-registration suites must be a real, passing test -- `test.fixme(` left in either file
// means a row was never finished. Exits 1 and prints every hit (file:line) when any remain.
import { readFileSync } from 'node:fs';

const FILES = [ 'tests/e2e/fidelity.spec.mjs', 'tests/e2e/editors.spec.mjs' ];

let hits = 0;

for ( const file of FILES ) {
	const lines = readFileSync( file, 'utf8' ).split( '\n' );
	lines.forEach( ( line, index ) => {
		if ( line.includes( 'test.fixme(' ) ) {
			console.error( `${ file }:${ index + 1 }: ${ line.trim() }` );
			hits += 1;
		}
	} );
}

if ( hits > 0 ) {
	console.error( `check-fixme: ${ hits } test.fixme( occurrence(s) remain` );
	process.exit( 1 );
}

console.log( 'check-fixme: clean' );
