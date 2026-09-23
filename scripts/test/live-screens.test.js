/**
 * Tests for the P2-07 pure screens.json builder (scripts/live/lib/screens.mjs).
 */

const path = require( 'path' );

let buildScreens;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'live', 'lib', 'screens.mjs' )
	);
	( { buildScreens } = mod );
} );

const SECTIONS = [
	'technology',
	'business',
	'faith',
	'journal',
	'writing',
	'security',
	'opinion',
];

function post( slug, overrides = {} ) {
	return { id: 1, slug, classic: false, freeform: false, ...overrides };
}

function baseInputs( overrides = {} ) {
	return {
		host: 'http://localhost:8888',
		archivePerPage: 12,
		sectionPosts: SECTIONS.map( ( section ) => ( {
			section,
			newest: post( `${ section }-newest` ),
			count: 3,
		} ) ),
		oldest: post( 'oldest-post' ),
		journalNewest: post( 'journal-newest-post' ),
		refPosts: [ post( 'ref-one' ), post( 'ref-two' ) ],
		ccPosts: [ post( 'cc-one' ), post( 'cc-two' ) ],
		mfnPosts: [ post( 'mfn-one' ) ],
		asidePost: post( 'aside-post' ),
		featuredPost: post( 'featured-post' ),
		unfeaturedPost: post( 'unfeatured-post' ),
		seriesSlugs: [ 'boundless-summer-challenge', 'cryptopals' ],
		...overrides,
	};
}

describe( 'buildScreens', () => {
	it( 'builds one entry per required kind from synthetic inputs', () => {
		const { screens } = buildScreens( baseInputs() );
		const kinds = new Set( screens.map( ( screen ) => screen.kind ) );

		expect( kinds ).toEqual(
			new Set( [ 'front', 'single', 'archive', 'page', 'search', '404' ] )
		);

		const notFound = screens.find( ( screen ) => screen.kind === '404' );
		expect( notFound.expectStatus ).toBe( 404 );
	} );

	it( 'marks classic and freeform posts', () => {
		const { screens } = buildScreens(
			baseInputs( {
				oldest: post( 'classic-post', {
					classic: true,
					freeform: false,
				} ),
				featuredPost: post( 'freeform-post', {
					classic: false,
					freeform: true,
				} ),
			} )
		);

		const classicScreen = screens.find(
			( screen ) => screen.path === '/classic-post/'
		);
		const freeformScreen = screens.find(
			( screen ) => screen.path === '/freeform-post/'
		);

		expect( classicScreen.classic ).toBe( true );
		expect( classicScreen.freeform ).toBe( false );
		expect( freeformScreen.classic ).toBe( false );
		expect( freeformScreen.freeform ).toBe( true );
	} );

	it( 'includes page 1 and last page of every section', () => {
		const { screens } = buildScreens(
			baseInputs( {
				sectionPosts: [
					{
						section: 'technology',
						newest: post( 'tech-newest' ),
						count: 30,
					},
				],
			} )
		);

		const paths = screens.map( ( screen ) => screen.path );

		expect( paths ).toContain( '/category/technology/' );
		expect( paths ).toContain( '/category/technology/page/3/' );
	} );

	it( 'never includes a duplicate path', () => {
		// The same post slug surfaces from two different discovery paths (section-newest and
		// journal-newest both resolve to the same post) -- the second must be dropped, not
		// duplicated.
		const shared = post( 'shared-slug' );
		const { screens } = buildScreens(
			baseInputs( {
				sectionPosts: SECTIONS.map( ( section ) => ( {
					section,
					newest:
						'journal' === section
							? shared
							: post( `${ section }-newest` ),
					count: 3,
				} ) ),
				journalNewest: shared,
			} )
		);

		const paths = screens.map( ( screen ) => screen.path );
		expect( new Set( paths ).size ).toBe( paths.length );
	} );
} );
