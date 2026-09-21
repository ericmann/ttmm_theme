/* eslint-disable no-console */
// CSS budget (SPEC §3.4 rule 30). cssBudgetBytes is the single tunable.
// Raised from 25600 to 28000 in P5-04 (archive/search templates), then to
// 33000 in P6-05: series progress/toc/featured, story tile, book cover/grid,
// stat row and the Writing hero + hub CSS (27358 -> ~32000 bytes measured
// before this bump); see the P6-05 commit body for exact before/after counts.
// R1-09: tightened to 32700 after adding the .ttm-hp honeypot rule and
// de-duplicating the grid/rule/button CSS and two multi-line comments
// (33121 with .ttm-hp added and nothing removed -> 32610 after cleanup;
// CLAUDE.md's constraint line was stale at 25600 since P5-04/P6-05 and is
// corrected to match here).
import { statSync, existsSync } from 'node:fs';
const cssBudgetBytes = 32700;
const file = 'themes/ttm-theme/assets/css/ttm.css';
if ( ! existsSync( file ) ) {
	console.error( `${ file } missing` );
	process.exit( 1 );
}
const size = statSync( file ).size;
if ( size > cssBudgetBytes ) {
	console.error( `${ file } is ${ size } bytes; budget ${ cssBudgetBytes }` );
	process.exit( 1 );
}
console.log( `${ file }: ${ size } / ${ cssBudgetBytes } bytes` );
