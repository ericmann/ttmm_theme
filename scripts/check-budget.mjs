/* eslint-disable no-console */
// CSS budget (SPEC §3.4 rule 30). cssBudgetBytes is the single tunable.
import { statSync, existsSync } from 'node:fs';
const cssBudgetBytes = 25600;
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
