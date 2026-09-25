/**
 * Tests for the P0-04 licence/version/owner-name checks (scripts/lib/license-checks.mjs).
 */

const fs = require( 'fs' );
const path = require( 'path' );
const { execFileSync } = require( 'child_process' );

let checkLicenseFile;
let checkLicenseFields;
let checkVersions;
let checkRequiredFiles;
let checkOwnerName;

const REPO_ROOT = path.join( __dirname, '..', '..' );
const REAL_LICENSE = fs.readFileSync(
	path.join( REPO_ROOT, 'LICENSE' ),
	'utf8'
);

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'license-checks.mjs' )
	);
	( {
		checkLicenseFile,
		checkLicenseFields,
		checkVersions,
		checkRequiredFiles,
		checkOwnerName,
	} = mod );
} );

/**
 * Build a `{ read, list }` context from a plain `{ path: contents }` object.
 *
 * @param {Object<string, string>} files Path -> contents map.
 * @return {{read: Function, list: Function}} Context.
 */
function fixture( files ) {
	return {
		read: ( p ) =>
			Object.prototype.hasOwnProperty.call( files, p )
				? files[ p ]
				: null,
		list: () => Object.keys( files ),
	};
}

const CONFORMING_FILES = {
	LICENSE: REAL_LICENSE,
	'package.json': JSON.stringify( {
		version: '0.2.0',
		license: 'GPL-2.0-or-later',
	} ),
	'composer.json': JSON.stringify( { license: 'GPL-2.0-or-later' } ),
	'themes/ttm-theme/style.css': [
		'/*',
		'Theme Name:        These Things Matter',
		'Author:            Eric Mann',
		'Version:           0.2.0',
		'License:           GPL-2.0-or-later',
		'License URI:       https://www.gnu.org/licenses/gpl-2.0.html',
		'*/',
	].join( '\n' ),
	'plugins/ttm-core/ttm-core.php': [
		'<?php',
		'/**',
		' * Plugin Name:       These Things Matter — Core',
		' * Version:           0.2.0',
		' * Author:            Eric Mann',
		' * License:           GPL-2.0-or-later',
		' */',
		"define( 'TTM_CORE_VERSION', '0.2.0' );",
	].join( '\n' ),
	'plugins/ttm-core/readme.txt': [
		'=== These Things Matter — Core ===',
		'Contributors: ericmann',
		'Stable tag: 0.2.0',
		'License: GPL-2.0-or-later',
		'License URI: https://www.gnu.org/licenses/gpl-2.0.html',
	].join( '\n' ),
	'themes/ttm-theme/readme.txt': [
		'=== These Things Matter ===',
		'Contributors: ericmann',
		'Stable tag: 0.2.0',
		'License: GPL-2.0-or-later',
		'License URI: https://www.gnu.org/licenses/gpl-2.0.html',
		'== Copyright ==',
		'Copyright 2020 The Archivo Project Authors',
	].join( '\n' ),
	'themes/ttm-theme/assets/fonts/OFL.txt': 'SIL Open Font License',
	'docs/fixtures/demo/LICENSE.md':
		'CC0 1.0, CREDITS.json, OFL, GPL-2.0-or-later',
	'plugins/ttm-core/src/Config.php':
		"<?php\n'site.author_name' => 'Eric Mann',\n",
};

describe( 'checkLicenseFile', () => {
	it( 'passes on a conforming fixture tree', () => {
		expect( checkLicenseFile( fixture( CONFORMING_FILES ) ) ).toEqual( [] );
	} );

	it( 'fails when LICENSE is not the FSF GPL-2.0 text', () => {
		const failures = checkLicenseFile(
			fixture( { ...CONFORMING_FILES, LICENSE: 'not the license' } )
		);
		expect( failures ).toHaveLength( 1 );
		expect( failures[ 0 ] ).toMatch( /does not match/ );
	} );

	it( 'fails when LICENSE is missing', () => {
		const { LICENSE, ...rest } = CONFORMING_FILES;
		expect( checkLicenseFile( fixture( rest ) ) ).toEqual( [
			'LICENSE is missing',
		] );
	} );
} );

describe( 'checkLicenseFields', () => {
	it( 'passes on a conforming fixture tree', () => {
		expect( checkLicenseFields( fixture( CONFORMING_FILES ) ) ).toEqual(
			[]
		);
	} );

	it( 'fails when any License field differs', () => {
		const broken = {
			...CONFORMING_FILES,
			'package.json': JSON.stringify( {
				version: '0.2.0',
				license: 'MIT',
			} ),
		};
		const failures = checkLicenseFields( fixture( broken ) );
		expect( failures.length ).toBeGreaterThan( 0 );
		expect( failures.join( '\n' ) ).toMatch( /package\.json/ );
	} );

	it( 'fails when a readme is missing its License URI', () => {
		const broken = {
			...CONFORMING_FILES,
			'plugins/ttm-core/readme.txt': [
				'=== These Things Matter — Core ===',
				'Stable tag: 0.2.0',
				'License: GPL-2.0-or-later',
			].join( '\n' ),
		};
		const failures = checkLicenseFields( fixture( broken ) );
		expect( failures.join( '\n' ) ).toMatch( /License URI/ );
	} );
} );

describe( 'checkVersions', () => {
	it( 'passes on a conforming fixture tree', () => {
		expect( checkVersions( fixture( CONFORMING_FILES ) ) ).toEqual( [] );
	} );

	it( 'fails when any version differs from package.json', () => {
		const broken = {
			...CONFORMING_FILES,
			'themes/ttm-theme/style.css': CONFORMING_FILES[
				'themes/ttm-theme/style.css'
			].replace( 'Version:           0.2.0', 'Version:           0.1.0' ),
		};
		const failures = checkVersions( fixture( broken ) );
		expect( failures.length ).toBeGreaterThan( 0 );
		expect( failures.join( '\n' ) ).toMatch( /style\.css/ );
	} );
} );

describe( 'checkRequiredFiles', () => {
	it( 'passes on a conforming fixture tree', () => {
		expect( checkRequiredFiles( fixture( CONFORMING_FILES ) ) ).toEqual(
			[]
		);
	} );

	it( 'fails when OFL.txt or the demo LICENSE.md is missing', () => {
		const { 'themes/ttm-theme/assets/fonts/OFL.txt': ofl, ...withoutOfl } =
			CONFORMING_FILES;
		const failuresNoOfl = checkRequiredFiles( fixture( withoutOfl ) );
		expect( failuresNoOfl.join( '\n' ) ).toMatch( /OFL\.txt/ );

		const {
			'docs/fixtures/demo/LICENSE.md': license,
			...withoutLicenseMd
		} = CONFORMING_FILES;
		const failuresNoLicense = checkRequiredFiles(
			fixture( withoutLicenseMd )
		);
		expect( failuresNoLicense.join( '\n' ) ).toMatch( /LICENSE\.md/ );
	} );
} );

describe( 'checkOwnerName', () => {
	it( 'passes on a conforming fixture tree', () => {
		expect( checkOwnerName( fixture( CONFORMING_FILES ) ) ).toEqual( [] );
	} );

	it( 'fails when the owner name appears outside the allowed lines', () => {
		const broken = {
			...CONFORMING_FILES,
			'plugins/ttm-core/src/Blocks/Helpers.php':
				'<?php\n// Written by Eric Mann.\n',
		};
		const failures = checkOwnerName( fixture( broken ) );
		expect( failures.length ).toBeGreaterThan( 0 );
		expect( failures.join( '\n' ) ).toMatch( /Helpers\.php/ );
	} );

	it( 'fails on an Author: line in a non-header plugin file', () => {
		const broken = {
			...CONFORMING_FILES,
			'plugins/ttm-core/src/Blocks/Helpers.php': [
				'<?php',
				'/**',
				' * Author:            Eric Mann',
				' */',
			].join( '\n' ),
		};
		const failures = checkOwnerName( fixture( broken ) );
		expect( failures.length ).toBeGreaterThan( 0 );
		expect( failures.join( '\n' ) ).toMatch( /Helpers\.php/ );
	} );

	it( 'allows the name on Author: header lines, readme Contributors/Copyright lines and Config.php', () => {
		const withName = {
			...CONFORMING_FILES,
			'themes/ttm-theme/readme.txt':
				CONFORMING_FILES[ 'themes/ttm-theme/readme.txt' ] +
				'\nCopyright Eric Mann.',
		};
		expect( checkOwnerName( fixture( withName ) ) ).toEqual( [] );
	} );

	it( 'skips binary extensions', () => {
		const withBinary = {
			...CONFORMING_FILES,
			'themes/ttm-theme/assets/img/Eric Mann.png': 'not real png data',
		};
		expect( checkOwnerName( fixture( withBinary ) ) ).toEqual( [] );
	} );
} );

describe( 'the real repository', () => {
	it( 'passes', () => {
		const trackedFiles = execFileSync( 'git', [ 'ls-files' ], {
			cwd: REPO_ROOT,
			encoding: 'utf8',
		} )
			.split( '\n' )
			.filter( Boolean );

		const ctx = {
			read: ( p ) => {
				try {
					return fs.readFileSync( path.join( REPO_ROOT, p ), 'utf8' );
				} catch {
					return null;
				}
			},
			list: () => trackedFiles,
		};

		const failures = [
			...checkLicenseFile( ctx ),
			...checkLicenseFields( ctx ),
			...checkVersions( ctx ),
			...checkRequiredFiles( ctx ),
			...checkOwnerName( ctx ),
		];

		expect( failures ).toEqual( [] );
	} );
} );
