#!/usr/bin/env node
/* eslint-disable no-console */
// Licence/version/owner-name lint (P0-04, SPEC §3.2 rule 55, §6.6, §6.7): wires the pure
// checks in scripts/lib/license-checks.mjs to the real, git-tracked working tree.
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import {
	checkLicenseFile,
	checkLicenseFields,
	checkVersions,
	checkRequiredFiles,
	checkOwnerName,
} from './lib/license-checks.mjs';

const files = execFileSync( 'git', [ 'ls-files' ], { encoding: 'utf8' } )
	.split( '\n' )
	.filter( Boolean );

const ctx = {
	read( path ) {
		try {
			return readFileSync( path, 'utf8' );
		} catch {
			return null;
		}
	},
	list() {
		return files;
	},
};

const failures = [
	...checkLicenseFile( ctx ),
	...checkLicenseFields( ctx ),
	...checkVersions( ctx ),
	...checkRequiredFiles( ctx ),
	...checkOwnerName( ctx ),
];

if ( failures.length > 0 ) {
	for ( const failure of failures ) {
		console.error( failure );
	}
	console.error( `check-license: ${ failures.length } failure(s)` );
	process.exit( 1 );
}

console.log( 'check-license: clean' );
