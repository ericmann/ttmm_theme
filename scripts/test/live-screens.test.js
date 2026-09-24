/**
 * Tests for the P2-07 pure screens.json builder (scripts/live/lib/screens.mjs).
 */

const path = require( 'path' );

let buildScreens;
let sectionCategoryArgs;
let checksMergedParagraphs;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'live', 'lib', 'screens.mjs' )
	);
	( { buildScreens, sectionCategoryArgs, checksMergedParagraphs } = mod );
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

	it( "uses a section's own archivePerPageBySection override for its last page (P4-04)", () => {
		const { screens } = buildScreens(
			baseInputs( {
				archivePerPageBySection: { journal: 20 },
				sectionPosts: [
					{
						section: 'journal',
						newest: post( 'journal-newest' ),
						count: 87,
					},
				],
			} )
		);

		const paths = screens.map( ( screen ) => screen.path );

		// ceil(87 / 20) = 5, not ceil(87 / 12) = 8 (the default archivePerPage, which 404'd --
		// see docs/feedback/phase-4/LIVE-TRIAGE.md).
		expect( paths ).toContain( '/category/journal/page/5/' );
		expect( paths ).not.toContain( '/category/journal/page/8/' );
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

	it( 'carries each post’s date through, null when absent (P4-01)', () => {
		const { screens } = buildScreens(
			baseInputs( {
				oldest: post( 'dated-post', { date: '2014-03-05 12:00:00' } ),
			} )
		);

		const dated = screens.find(
			( screen ) => screen.path === '/dated-post/'
		);
		const front = screens.find( ( screen ) => screen.kind === 'front' );

		expect( dated.date ).toBe( '2014-03-05 12:00:00' );
		expect( front.date ).toBeNull();
	} );

	it( "carries each single post's primary category name (R1-02)", () => {
		const { screens } = buildScreens(
			baseInputs( {
				oldest: post( 'primary-post', { primary: 'Security' } ),
			} )
		);

		const primaryPost = screens.find(
			( screen ) => screen.path === '/primary-post/'
		);
		const front = screens.find( ( screen ) => screen.kind === 'front' );

		expect( primaryPost.primary ).toBe( 'Security' );
		expect( front.primary ).toBeNull();
	} );

	it( 'defaults primary to null when a single post has none', () => {
		const { screens } = buildScreens( baseInputs() );

		const oldest = screens.find(
			( screen ) => screen.path === '/oldest-post/'
		);

		expect( oldest.primary ).toBeNull();
	} );

	it( 'carries converted through ref-* screens even though they are no longer classic (R3-01)', () => {
		const { screens } = buildScreens(
			baseInputs( {
				refPosts: [
					post( 'ref-converted', {
						classic: false,
						converted: true,
					} ),
				],
			} )
		);

		const refScreen = screens.find(
			( screen ) => screen.path === '/ref-converted/'
		);

		expect( refScreen.classic ).toBe( false );
		expect( refScreen.converted ).toBe( true );
		expect( checksMergedParagraphs( refScreen ) ).toBe( true );
	} );

	it( 'defaults converted to false when a post omits the field', () => {
		const { screens } = buildScreens(
			baseInputs( {
				oldest: post( 'no-converted-field', { classic: true } ),
			} )
		);

		const oldest = screens.find(
			( screen ) => screen.path === '/no-converted-field/'
		);

		expect( oldest.converted ).toBe( false );
		expect( checksMergedParagraphs( oldest ) ).toBe( false );
	} );
} );

describe( 'checksMergedParagraphs (R3-01)', () => {
	it( 'returns false for a classic:true, converted:false screen', () => {
		expect(
			checksMergedParagraphs( { classic: true, converted: false } )
		).toBe( false );
	} );

	it( 'returns true for a converted:true screen regardless of classic', () => {
		expect(
			checksMergedParagraphs( { classic: false, converted: true } )
		).toBe( true );
	} );

	it( 'returns false when converted is absent', () => {
		expect( checksMergedParagraphs( { classic: false } ) ).toBe( false );
	} );
} );

describe( 'sectionCategoryArgs (P4-04)', () => {
	it( 'filters wp post list by slug via --category_name, not --category', () => {
		const args = sectionCategoryArgs( 'technology' );

		expect( args ).toContain( '--category_name=technology' );
		expect( args.some( ( arg ) => arg.startsWith( '--category=' ) ) ).toBe(
			false
		);
	} );
} );
