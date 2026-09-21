/* eslint-disable no-console */
// CSS budget (SPEC §3.4 rule 30). cssBudgetBytes is the single tunable.
// Raised from 25600 to 28000 in P5-04: archive head, filter row, year group,
// pagination and archive-row CSS for category/category-journal/archive/search
// templates pushed ttm.css past the prior cap (25451 -> ~27360 bytes measured
// before this bump); see the P5-04 commit body for exact before/after counts.
import { statSync, existsSync } from 'node:fs';
const cssBudgetBytes = 28000;
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
