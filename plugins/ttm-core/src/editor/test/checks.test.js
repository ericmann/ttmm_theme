import { runChecks } from '../checks';

const complete = {
	isJournal: false,
	excerpt: 'A dek.',
	featuredAltMissing: false,
	seriesId: 0,
	part: 0,
	takenParts: [],
	inWriting: false,
	form: 'article',
};

describe( 'runChecks', () => {
	test( 'warns on missing dek for non journal', () => {
		const warnings = runChecks( { ...complete, excerpt: '' } );
		expect( warnings.map( ( w ) => w.id ) ).toContain( 'missing-dek' );
	} );

	test( 'does not warn on missing dek for journal', () => {
		const warnings = runChecks( {
			...complete,
			isJournal: true,
			excerpt: '',
		} );
		expect( warnings.map( ( w ) => w.id ) ).not.toContain( 'missing-dek' );
	} );

	test( 'warns on series without part', () => {
		const warnings = runChecks( { ...complete, seriesId: 5, part: 0 } );
		expect( warnings.map( ( w ) => w.id ) ).toContain( 'missing-part' );
	} );

	test( 'warns on duplicate part', () => {
		const warnings = runChecks( {
			...complete,
			seriesId: 5,
			part: 2,
			takenParts: [ 2, 3 ],
		} );
		expect( warnings.map( ( w ) => w.id ) ).toContain( 'duplicate-part' );
	} );

	test( 'warns on writing post without form', () => {
		const warnings = runChecks( {
			...complete,
			inWriting: true,
			seriesId: 0,
			form: '',
		} );
		expect( warnings.map( ( w ) => w.id ) ).toContain( 'missing-form' );
	} );

	test( 'returns empty for a complete post', () => {
		expect( runChecks( complete ) ).toEqual( [] );
	} );
} );
