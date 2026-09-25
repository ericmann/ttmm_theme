/**
 * Pure licence/version/owner-name checks for the repository (P0-04, SPEC §3.2 rule 55, §6.6,
 * §6.7). Every function takes a `{ read(path) -> string|null, list() -> string[] }` context
 * (so the real repo and a Jest fixture tree share one code path) and returns an array of
 * failure strings; an empty array means the check passed.
 */
import { createHash } from 'node:crypto';

// sha256 of the FSF's own gpl-2.0.txt (Decision "Licence strings").
const FSF_GPL2_SHA256 =
	'8177f97513213526df2cf6184d8ff986c675afb514d4e68a404010521b880643';

const GPL_RE = /License:\s*GPL-2\.0-or-later/;
const LICENSE_URI_RE =
	/License URI:\s*https:\/\/www\.gnu\.org\/licenses\/gpl-2\.0\.html/;

const BINARY_EXTENSIONS = new Set( [
	'png',
	'jpg',
	'jpeg',
	'gif',
	'webp',
	'woff',
	'woff2',
	'zip',
] );

/**
 * Root `LICENSE` is byte-identical to the FSF's `gpl-2.0.txt`.
 *
 * @param {{read: Function}} ctx Repo context.
 * @return {string[]} Failures.
 */
export function checkLicenseFile( { read } ) {
	const contents = read( 'LICENSE' );
	if ( null === contents ) {
		return [ 'LICENSE is missing' ];
	}

	const digest = createHash( 'sha256' ).update( contents ).digest( 'hex' );
	if ( digest !== FSF_GPL2_SHA256 ) {
		return [
			`LICENSE does not match the FSF gpl-2.0.txt digest (got ${ digest })`,
		];
	}

	return [];
}

/**
 * The six `GPL-2.0-or-later` fields (`package.json`, `composer.json`, `style.css`,
 * `ttm-core.php`, both `readme.txt`) and both `readme.txt` `License URI:` lines.
 *
 * @param {{read: Function}} ctx Repo context.
 * @return {string[]} Failures.
 */
export function checkLicenseFields( { read } ) {
	const failures = [];

	for ( const path of [ 'package.json', 'composer.json' ] ) {
		const raw = read( path );
		if ( null === raw ) {
			failures.push( `${ path } is missing` );
			continue;
		}
		let json;
		try {
			json = JSON.parse( raw );
		} catch {
			failures.push( `${ path } is not valid JSON` );
			continue;
		}
		if ( 'GPL-2.0-or-later' !== json.license ) {
			failures.push(
				`${ path }'s "license" is not GPL-2.0-or-later (got ${ json.license })`
			);
		}
	}

	for ( const path of [
		'themes/ttm-theme/style.css',
		'plugins/ttm-core/ttm-core.php',
	] ) {
		const raw = read( path );
		if ( null === raw ) {
			failures.push( `${ path } is missing` );
			continue;
		}
		if ( ! GPL_RE.test( raw ) ) {
			failures.push(
				`${ path }'s License: field is not GPL-2.0-or-later`
			);
		}
	}

	for ( const path of [
		'plugins/ttm-core/readme.txt',
		'themes/ttm-theme/readme.txt',
	] ) {
		const raw = read( path );
		if ( null === raw ) {
			failures.push( `${ path } is missing` );
			continue;
		}
		if ( ! GPL_RE.test( raw ) ) {
			failures.push(
				`${ path }'s License: field is not GPL-2.0-or-later`
			);
		}
		if ( ! LICENSE_URI_RE.test( raw ) ) {
			failures.push(
				`${ path }'s License URI: field is not https://www.gnu.org/licenses/gpl-2.0.html`
			);
		}
	}

	return failures;
}

/**
 * Read the plugin header `Version:`, `TTM_CORE_VERSION`, `style.css`'s `Version:`,
 * `package.json`'s `version`, and both `readme.txt` `Stable tag:` -- all equal.
 *
 * @param {{read: Function}} ctx Repo context.
 * @return {string[]} Failures.
 */
export function checkVersions( { read } ) {
	const versions = {};

	const packageJson = read( 'package.json' );
	if ( null !== packageJson ) {
		try {
			versions[ 'package.json' ] = JSON.parse( packageJson ).version;
		} catch {
			// checkLicenseFields already reports invalid JSON.
		}
	}

	const pluginPhp = read( 'plugins/ttm-core/ttm-core.php' );
	if ( null !== pluginPhp ) {
		const header = pluginPhp.match( /\*\s*Version:\s*([\d.]+)/ );
		if ( header ) {
			versions[ 'ttm-core.php header' ] = header[ 1 ];
		}
		const define = pluginPhp.match( /TTM_CORE_VERSION',\s*'([\d.]+)'/ );
		if ( define ) {
			versions.TTM_CORE_VERSION = define[ 1 ];
		}
	}

	const styleCss = read( 'themes/ttm-theme/style.css' );
	if ( null !== styleCss ) {
		const header = styleCss.match( /Version:\s*([\d.]+)/ );
		if ( header ) {
			versions[ 'style.css' ] = header[ 1 ];
		}
	}

	for ( const [ label, path ] of [
		[ 'plugin readme.txt', 'plugins/ttm-core/readme.txt' ],
		[ 'theme readme.txt', 'themes/ttm-theme/readme.txt' ],
	] ) {
		const raw = read( path );
		if ( null !== raw ) {
			const stable = raw.match( /Stable tag:\s*([\d.]+)/ );
			if ( stable ) {
				versions[ label ] = stable[ 1 ];
			}
		}
	}

	const values = Object.values( versions );
	if ( 0 === values.length ) {
		return [ 'no version fields found' ];
	}

	const target = values[ 0 ];
	const failures = [];
	for ( const [ label, value ] of Object.entries( versions ) ) {
		if ( value !== target ) {
			failures.push(
				`${ label } is ${ value }, expected ${ target } (package.json)`
			);
		}
	}

	return failures;
}

/**
 * `themes/ttm-theme/assets/fonts/OFL.txt` and `docs/fixtures/demo/LICENSE.md` (with its four
 * required mentions) are present.
 *
 * @param {{read: Function, list: Function}} ctx Repo context.
 * @return {string[]} Failures.
 */
export function checkRequiredFiles( { read, list } ) {
	const failures = [];
	const files = new Set( list() );

	if ( ! files.has( 'themes/ttm-theme/assets/fonts/OFL.txt' ) ) {
		failures.push( 'themes/ttm-theme/assets/fonts/OFL.txt is missing' );
	}

	if ( ! files.has( 'docs/fixtures/demo/LICENSE.md' ) ) {
		failures.push( 'docs/fixtures/demo/LICENSE.md is missing' );
		return failures;
	}

	const demoLicense = read( 'docs/fixtures/demo/LICENSE.md' ) ?? '';
	for ( const required of [
		'CC0 1.0',
		'CREDITS.json',
		'OFL',
		'GPL-2.0-or-later',
	] ) {
		if ( ! demoLicense.includes( required ) ) {
			failures.push(
				`docs/fixtures/demo/LICENSE.md does not mention "${ required }"`
			);
		}
	}

	return failures;
}

/**
 * The owner's name (`Config.php`'s `site.author_name` default) appears nowhere under
 * `plugins/`/`themes/` except `Config.php` itself, `Author:` header lines in `style.css`/
 * `ttm-core.php`, and `Contributors:`/`Copyright` lines in a `readme.txt` (Decision
 * "Owner-name scope").
 *
 * @param {{read: Function, list: Function}} ctx Repo context.
 * @return {string[]} Failures.
 */
export function checkOwnerName( { read, list } ) {
	const configPhp = read( 'plugins/ttm-core/src/Config.php' );
	if ( null === configPhp ) {
		return [ 'plugins/ttm-core/src/Config.php is missing' ];
	}

	const match = configPhp.match( /'site\.author_name'\s*=>\s*'([^']+)'/ );
	if ( ! match ) {
		return [ "Could not find 'site.author_name' default in Config.php" ];
	}
	const name = match[ 1 ];

	const ALLOWED_FILES = new Set( [ 'plugins/ttm-core/src/Config.php' ] );
	const failures = [];

	for ( const path of list() ) {
		if (
			! path.startsWith( 'plugins/' ) &&
			! path.startsWith( 'themes/' )
		) {
			continue;
		}
		if ( ALLOWED_FILES.has( path ) ) {
			continue;
		}
		const ext = path.split( '.' ).pop().toLowerCase();
		if ( BINARY_EXTENSIONS.has( ext ) ) {
			continue;
		}

		const contents = read( path );
		if ( null === contents ) {
			continue;
		}

		const isHeaderFile =
			'themes/ttm-theme/style.css' === path ||
			'plugins/ttm-core/ttm-core.php' === path;
		const isReadme = path.endsWith( 'readme.txt' );

		contents.split( '\n' ).forEach( ( line, index ) => {
			if ( ! line.includes( name ) ) {
				return;
			}
			const trimmed = line.replace( /^\s*\*?\s*/, '' );
			if ( isHeaderFile && trimmed.startsWith( 'Author:' ) ) {
				return;
			}
			if (
				isReadme &&
				( trimmed.startsWith( 'Contributors:' ) ||
					trimmed.startsWith( 'Copyright' ) )
			) {
				return;
			}
			failures.push( `${ path }:${ index + 1 }: contains "${ name }"` );
		} );
	}

	return failures;
}
