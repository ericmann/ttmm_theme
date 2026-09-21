/* eslint-disable no-console */
// Every ttm/* block must lock its supports and render server-side (SPEC §3.4 rule 25).
import { readFileSync, readdirSync, existsSync, statSync } from 'node:fs';
import { join } from 'node:path';
const root = 'plugins/ttm-core/blocks';
if ( ! existsSync( root ) ) {
	console.log( 'no blocks yet' );
	process.exit( 0 );
}
let bad = 0;
for ( const dir of readdirSync( root ) ) {
	const d = join( root, dir );
	if ( ! statSync( d ).isDirectory() ) {
		continue;
	}
	const f = join( d, 'block.json' );
	if ( ! existsSync( f ) ) {
		console.error( `${ d }: missing block.json` );
		bad++;
		continue;
	}
	const b = JSON.parse( readFileSync( f, 'utf8' ) );
	const err = ( m ) => {
		console.error( `${ f }: ${ m }` );
		bad++;
	};
	if ( ! /^ttm\//.test( b.name ) ) {
		err( 'name must start with ttm/' );
	}
	if ( b.render !== 'file:./render.php' ) {
		err( 'render must be file:./render.php' );
	}
	if ( ! existsSync( join( d, 'render.php' ) ) ) {
		err( 'missing render.php' );
	}
	for ( const k of [ 'html', 'align', 'color', 'typography', 'spacing' ] ) {
		if ( b.supports?.[ k ] !== false ) {
			err( `supports.${ k } must be false` );
		}
	}
	if ( b.viewScript || b.viewScriptModule ) {
		err( 'no front-end scripts on ttm/* blocks' );
	}
	if ( b.style || b.viewStyle ) {
		err( 'no plugin styles; the theme styles ttm-* classes' );
	}
}
process.exit( bad ? 1 : 0 );
