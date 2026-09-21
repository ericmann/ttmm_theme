import { deriveForm } from '../derive';

describe( 'deriveForm', () => {
	test( 'returns chapter for fiction series', () => {
		expect( deriveForm( { seriesForm: 'novel', inWriting: false } ) ).toBe(
			'chapter'
		);
	} );

	test( 'returns story for writing post without series', () => {
		expect( deriveForm( { seriesForm: null, inWriting: true } ) ).toBe(
			'story'
		);
	} );

	test( 'returns article otherwise', () => {
		expect( deriveForm( { seriesForm: null, inWriting: false } ) ).toBe(
			'article'
		);
		expect(
			deriveForm( { seriesForm: 'nonfiction', inWriting: false } )
		).toBe( 'article' );
		expect(
			deriveForm( { seriesForm: 'nonfiction', inWriting: true } )
		).toBe( 'article' );
	} );
} );
