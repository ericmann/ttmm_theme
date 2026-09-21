/* eslint-disable no-console */
// Fails when themes/ttm-theme/theme.json is not valid v3 JSON or lacks the required presets once they exist.
import { readFileSync } from 'node:fs';
const path = 'themes/ttm-theme/theme.json';
let json;
try {
	json = JSON.parse( readFileSync( path, 'utf8' ) );
} catch ( e ) {
	console.error( `${ path }: invalid JSON — ${ e.message }` );
	process.exit( 1 );
}
const errors = [];
if ( json.version !== 3 ) {
	errors.push( 'version must be 3' );
}
if ( json.settings?.layout?.contentSize !== '1280px' ) {
	errors.push( 'settings.layout.contentSize must be 1280px' );
}
const palette = json.settings?.color?.palette;
if ( palette ) {
	const need = [
		'bg',
		'surface',
		'text',
		'accent',
		'accent-600',
		'accent-700',
		'divider',
	];
	for ( let i = 100; i <= 900; i += 100 ) {
		need.push( `neutral-${ i }` );
	}
	const slugs = new Set( palette.map( ( p ) => p.slug ) );
	for ( const s of need ) {
		if ( ! slugs.has( s ) ) {
			errors.push( `palette missing slug "${ s }"` );
		}
	}
	if ( json.settings.color.defaultPalette !== false ) {
		errors.push( 'color.defaultPalette must be false' );
	}
}
if ( errors.length ) {
	for ( const e of errors ) {
		console.error( `${ path }: ${ e }` );
	}
	process.exit( 1 );
}
console.log( `${ path }: ok` );
