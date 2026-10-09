/**
 * Tests for the npm audit allowlist gate (scripts/lib/audit-checks.mjs).
 */

const path = require( 'path' );

let advisories;
let checkAudit;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'lib', 'audit-checks.mjs' )
	);
	( { advisories, checkAudit } = mod );
} );

/**
 * Build an `npm audit --json`-shaped report.
 *
 * @param {Array<{id: string, name: string, severity: string}>} list Advisories.
 * @return {Object} Report with each advisory plus a transitive dependent.
 */
function report( list ) {
	const vulnerabilities = {};
	for ( const { id, name, severity } of list ) {
		vulnerabilities[ name ] = {
			severity,
			via: [
				{
					name,
					severity,
					title: `${ name } issue`,
					url: `https://github.com/advisories/${ id }`,
				},
			],
		};
	}
	vulnerabilities.dependent = { severity: 'critical', via: [ 'braces' ] };
	return { vulnerabilities };
}

const entry = ( id, until = '2026-11-09' ) => ( {
	id,
	package: 'braces',
	reason: 'dev-only',
	until,
} );

describe( 'advisories', () => {
	it( 'collects advisory objects and skips transitive string entries', () => {
		const found = advisories(
			report( [ { id: 'GHSA-a', name: 'braces', severity: 'high' } ] )
		);
		expect( found ).toEqual( [
			{
				id: 'GHSA-a',
				name: 'braces',
				severity: 'high',
				title: 'braces issue',
			},
		] );
	} );

	it( 'treats an empty report as no advisories', () => {
		expect( advisories( {} ) ).toEqual( [] );
	} );
} );

describe( 'checkAudit', () => {
	it( 'is clean when every high/critical advisory is allowlisted', () => {
		const r = report( [
			{ id: 'GHSA-a', name: 'braces', severity: 'high' },
			{ id: 'GHSA-b', name: 'sprintf-js', severity: 'moderate' },
		] );
		expect( checkAudit( r, [ entry( 'GHSA-a' ) ], '2026-10-09' ) ).toEqual(
			[]
		);
	} );

	it( 'fails on a high or critical advisory missing from the allowlist', () => {
		const r = report( [
			{ id: 'GHSA-a', name: 'braces', severity: 'high' },
			{ id: 'GHSA-c', name: 'simple-git', severity: 'critical' },
		] );
		const failures = checkAudit( r, [ entry( 'GHSA-a' ) ], '2026-10-09' );
		expect( failures ).toHaveLength( 1 );
		expect( failures[ 0 ] ).toMatch( /critical simple-git GHSA-c/ );
	} );

	it( 'fails on an expired allowlist entry', () => {
		const r = report( [
			{ id: 'GHSA-a', name: 'braces', severity: 'high' },
		] );
		const failures = checkAudit(
			r,
			[ entry( 'GHSA-a', '2026-10-01' ) ],
			'2026-10-09'
		);
		expect( failures ).toEqual( [
			expect.stringMatching( /GHSA-a .*expired 2026-10-01/ ),
		] );
	} );

	it( 'fails on an entry without a reason', () => {
		const r = report( [
			{ id: 'GHSA-a', name: 'braces', severity: 'high' },
		] );
		const failures = checkAudit(
			r,
			[ { ...entry( 'GHSA-a' ), reason: '' } ],
			'2026-10-09'
		);
		expect( failures ).toEqual( [
			expect.stringMatching( /needs a reason/ ),
		] );
	} );

	it( 'fails on an entry that is no longer reported', () => {
		const failures = checkAudit(
			report( [] ),
			[ entry( 'GHSA-gone' ) ],
			'2026-10-09'
		);
		expect( failures ).toEqual( [
			expect.stringMatching( /GHSA-gone .*no longer reported/ ),
		] );
	} );
} );
