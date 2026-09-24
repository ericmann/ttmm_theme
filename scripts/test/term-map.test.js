/**
 * Tests for the R2-03 WXR `<wp:category>` term-id -> slug extractor
 * (scripts/live/term-map.mjs).
 */

const path = require( 'path' );

let extractTermMap;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'live', 'term-map.mjs' )
	);
	( { extractTermMap } = mod );
} );

describe( 'extractTermMap', () => {
	it( 'maps every <wp:category> term_id to its nicename', () => {
		const xml = `<?xml version="1.0"?>
<rss><channel>
	<wp:category>
		<wp:term_id>52</wp:term_id>
		<wp:category_nicename><![CDATA[business]]></wp:category_nicename>
		<wp:category_parent><![CDATA[]]></wp:category_parent>
		<wp:cat_name><![CDATA[Business]]></wp:cat_name>
	</wp:category>
	<wp:category>
		<wp:term_id>106</wp:term_id>
		<wp:category_nicename><![CDATA[faith]]></wp:category_nicename>
		<wp:category_parent><![CDATA[]]></wp:category_parent>
		<wp:cat_name><![CDATA[Faith]]></wp:cat_name>
	</wp:category>
</channel></rss>`;

		expect( extractTermMap( xml ) ).toEqual( {
			52: 'business',
			106: 'faith',
		} );
	} );

	it( 'handles a nicename with no CDATA wrapper', () => {
		const xml = `<wp:category>
			<wp:term_id>9</wp:term_id>
			<wp:category_nicename>journal</wp:category_nicename>
		</wp:category>`;

		expect( extractTermMap( xml ) ).toEqual( { 9: 'journal' } );
	} );

	it( 'skips a category block missing a term_id or nicename', () => {
		const xml = `<wp:category>
			<wp:category_nicename><![CDATA[orphan]]></wp:category_nicename>
		</wp:category>`;

		expect( extractTermMap( xml ) ).toEqual( {} );
	} );

	it( 'returns an empty map for a WXR with no categories', () => {
		expect( extractTermMap( '<rss><channel></channel></rss>' ) ).toEqual(
			{}
		);
	} );
} );
