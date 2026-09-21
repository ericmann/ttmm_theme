/* eslint-disable no-console */
// Fails when themes/ttm-theme/theme.json drifts from the token sheet (docs/_ds/.../styles.css) or from 04 §2 / P0-06.
import { readFileSync } from 'node:fs';

const path = 'themes/ttm-theme/theme.json';
const tokenPath =
	'docs/_ds/modernist-de9cc87f-4460-409f-98c7-09e0b5cce4fb/styles.css';

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

// --- Palette vs. token sheet -------------------------------------------------
const tokenCss = readFileSync( tokenPath, 'utf8' );
const tokenColors = {};
for ( const m of tokenCss.matchAll( /--color-([\w-]+):\s*([^;]+);/g ) ) {
	tokenColors[ m[ 1 ] ] = m[ 2 ].trim();
}

const paletteExpected = {
	bg: tokenColors.bg,
	surface: tokenColors.surface,
	text: tokenColors.text,
	accent: tokenColors.accent,
	'accent-100': tokenColors[ 'accent-100' ],
	'accent-600': tokenColors[ 'accent-600' ],
	'accent-700': tokenColors[ 'accent-700' ],
	'neutral-100': tokenColors[ 'neutral-100' ],
	'neutral-200': tokenColors[ 'neutral-200' ],
	'neutral-300': tokenColors[ 'neutral-300' ],
	'neutral-400': tokenColors[ 'neutral-400' ],
	'neutral-500': tokenColors[ 'neutral-500' ],
	'neutral-600': tokenColors[ 'neutral-600' ],
	'neutral-700': tokenColors[ 'neutral-700' ],
	'neutral-800': tokenColors[ 'neutral-800' ],
	'neutral-900': tokenColors[ 'neutral-900' ],
};
const dividerOk = [ 'rgba(32,30,29,0.4)', 'rgba(32, 30, 29, 0.4)' ];

const palette = json.settings?.color?.palette ?? [];
const bySlug = Object.fromEntries(
	palette.map( ( p ) => [ p.slug, p.color ] )
);

for ( const [ slug, expected ] of Object.entries( paletteExpected ) ) {
	if ( ! ( slug in bySlug ) ) {
		errors.push( `palette missing slug "${ slug }"` );
		continue;
	}
	if ( expected && bySlug[ slug ].toLowerCase() !== expected.toLowerCase() ) {
		errors.push(
			`palette "${ slug }" is ${ bySlug[ slug ] }, token sheet says ${ expected }`
		);
	}
}
if ( ! ( 'divider' in bySlug ) ) {
	errors.push( 'palette missing slug "divider"' );
} else if (
	! dividerOk.includes( bySlug.divider.replace( /\s+/g, ' ' ).trim() ) &&
	! dividerOk.includes( bySlug.divider )
) {
	errors.push(
		`palette "divider" must be rgba(32,30,29,0.4), got ${ bySlug.divider }`
	);
}

if ( json.settings?.color?.defaultPalette !== false ) {
	errors.push( 'color.defaultPalette must be false' );
}
if ( json.settings?.color?.defaultGradients !== false ) {
	errors.push( 'color.defaultGradients must be false' );
}
if ( json.settings?.color?.custom !== false ) {
	errors.push( 'color.custom must be false' );
}
if ( json.settings?.color?.customGradient !== false ) {
	errors.push( 'color.customGradient must be false' );
}
if ( ( json.settings?.color?.gradients ?? null )?.length !== 0 ) {
	errors.push( 'color.gradients must be []' );
}
if ( ( json.settings?.color?.duotone ?? null )?.length !== 0 ) {
	errors.push( 'color.duotone must be []' );
}

// --- Typography booleans and font sizes -------------------------------------
if ( json.settings?.typography?.customFontSize !== false ) {
	errors.push( 'typography.customFontSize must be false' );
}
if ( json.settings?.typography?.fluid !== false ) {
	errors.push( 'typography.fluid must be false' );
}
if ( json.settings?.typography?.defaultFontSizes !== false ) {
	errors.push( 'typography.defaultFontSizes must be false' );
}
if ( json.settings?.typography?.dropCap !== false ) {
	errors.push( 'typography.dropCap must be false' );
}

const expectedFontSizes = {
	micro: '11px',
	caption: '12px',
	ui: '13px',
	'body-s': '14px',
	dek: '15px',
	'headline-s': '17px',
	body: '18px',
	synopsis: '19px',
	'cell-lead': '21px',
	'cell-lead-l': '24px',
	pull: '28px',
	h2: '30px',
	featured: '40px',
	lead: '44px',
	'journal-date': '48px',
	h1: '56px',
	'display-m': '64px',
	'display-l': '76px',
	'display-xl': '80px',
};
const fontSizes = json.settings?.typography?.fontSizes ?? [];
const fontSizeBySlug = Object.fromEntries(
	fontSizes.map( ( f ) => [ f.slug, f.size ] )
);
for ( const [ slug, size ] of Object.entries( expectedFontSizes ) ) {
	if ( fontSizeBySlug[ slug ] !== size ) {
		errors.push(
			`fontSizes "${ slug }" must be ${ size }, got ${ fontSizeBySlug[ slug ] ?? 'missing' }`
		);
	}
}

const fontFaces =
	json.settings?.typography?.fontFamilies?.[ 0 ]?.fontFace ?? [];
if ( fontFaces.length === 0 ) {
	errors.push( 'typography.fontFamilies[0].fontFace must not be empty' );
}
for ( const face of fontFaces ) {
	const src = Array.isArray( face.src ) ? face.src[ 0 ] : face.src;
	if ( ! src || ! src.startsWith( 'file:./assets/fonts/' ) ) {
		errors.push(
			`fontFace src must be file:./assets/fonts/..., got ${ src }`
		);
	}
}

// --- Spacing -----------------------------------------------------------------
const expectedSpacing = {
	10: '4px',
	20: '8px',
	30: '12px',
	40: '16px',
	50: '24px',
	60: '32px',
	70: '40px',
	80: '48px',
	90: '64px',
	100: '96px',
};
const spacingSizes = json.settings?.spacing?.spacingSizes ?? [];
const spacingBySlug = Object.fromEntries(
	spacingSizes.map( ( s ) => [ s.slug, s.size ] )
);
for ( const [ slug, size ] of Object.entries( expectedSpacing ) ) {
	if ( spacingBySlug[ slug ] !== size ) {
		errors.push(
			`spacingSizes "${ slug }" must be ${ size }, got ${ spacingBySlug[ slug ] ?? 'missing' }`
		);
	}
}
if ( json.settings?.spacing?.defaultSpacingSizes !== false ) {
	errors.push( 'spacing.defaultSpacingSizes must be false' );
}

if ( json.settings?.border?.radius !== false ) {
	errors.push( 'border.radius must be false' );
}

if ( errors.length ) {
	for ( const e of errors ) {
		console.error( `${ path }: ${ e }` );
	}
	process.exit( 1 );
}
console.log( `${ path }: ok` );
