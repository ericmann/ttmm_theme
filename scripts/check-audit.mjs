#!/usr/bin/env node
/* eslint-disable no-console */
// npm audit gate (high/critical): wires the pure checks in scripts/lib/audit-checks.mjs to
// `npm audit --json` and the committed allowlist in scripts/audit-allowlist.json.
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { checkAudit } from './lib/audit-checks.mjs';

// npm audit exits non-zero whenever anything is reported; the JSON on stdout is what counts.
const result = spawnSync( 'npm', [ 'audit', '--json' ], { encoding: 'utf8' } );
let report;
try {
	report = JSON.parse( result.stdout );
} catch {
	console.error( result.stderr || 'check-audit: npm audit produced no JSON' );
	process.exit( 1 );
}

const allow = JSON.parse(
	readFileSync( new URL( './audit-allowlist.json', import.meta.url ), 'utf8' )
);
const today = new Date().toISOString().slice( 0, 10 );
const failures = checkAudit( report, allow, today );

if ( failures.length > 0 ) {
	for ( const failure of failures ) {
		console.error( failure );
	}
	console.error( `check-audit: ${ failures.length } failure(s)` );
	process.exit( 1 );
}

console.log( `check-audit: clean (${ allow.length } allowlisted)` );
