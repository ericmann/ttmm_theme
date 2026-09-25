/**
 * Tests for the P2-03 pure WXR normalisation (scripts/demo/lib/wxr.mjs).
 */

/* global DOMParser */

const path = require( 'path' );
// wp-scripts' Jest preset already runs under testEnvironment: "jsdom", so the browser's own
// DOMParser global is available without requiring the `jsdom` package directly.

let normalizeWxr;
let rebaseAttachmentUrls;
let countItems;
let attachmentBasenames;

beforeAll( async () => {
	const mod = await import(
		path.join( __dirname, '..', 'demo', 'lib', 'wxr.mjs' )
	);
	normalizeWxr = mod.normalizeWxr;
	rebaseAttachmentUrls = mod.rebaseAttachmentUrls;
	countItems = mod.countItems;
	attachmentBasenames = mod.attachmentBasenames;
} );

const SITE_ORIGIN = 'http://localhost:8888';
const IMAGE_BASE =
	'https://raw.githubusercontent.com/ericmann/ttmm_theme/main/docs/fixtures/demo/images';
const DEMO_FILES = [ 'demo-cable.jpg' ];

/**
 * Build an invented, WXR-1.2-shaped export string. `overrides` lets the determinism test vary
 * ids/stamps without duplicating the whole fixture.
 *
 * @param {Object} overrides `{postIds: [p1, p2, p3, attachmentId, otherAttachmentId, pageId],
 *                           termId, created, channelPubDate}`.
 * @return {string} WXR.
 */
function buildFixture( overrides = {} ) {
	const ids = Object.assign(
		{
			post1: 501,
			post2: 502,
			post3: 503,
			page: 510,
			attachment: 520,
			otherAttachment: 521,
		},
		overrides.postIds || {}
	);
	const termId = overrides.termId ?? 900;
	const created = overrides.created ?? '2026-01-01 00:00';
	const channelPubDate =
		overrides.channelPubDate ?? 'Thu, 01 Jan 2026 00:00:00 +0000';

	return `<?xml version="1.0" encoding="UTF-8" ?>
<!-- This is a WordPress eXtended RSS file -->
<!-- generator="WordPress/7.1.1" created="${ created }" -->
<rss version="2.0"
	xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:wp="http://wordpress.org/export/1.2/">
<channel>
<title>These Things Matter</title>
<link>${ SITE_ORIGIN }</link>
<description>Technology, business, faith and the occasional story.</description>
<pubDate>${ channelPubDate }</pubDate>
<language>en-US</language>
<wp:wxr_version>1.2</wp:wxr_version>
<wp:base_site_url>${ SITE_ORIGIN }</wp:base_site_url>
<wp:base_blog_url>${ SITE_ORIGIN }</wp:base_blog_url>
<wp:author><wp:author_id>1</wp:author_id><wp:author_login><![CDATA[eric]]></wp:author_login><wp:author_email><![CDATA[eric@example.com]]></wp:author_email><wp:author_display_name><![CDATA[Eric Mann]]></wp:author_display_name><wp:author_first_name><![CDATA[Eric]]></wp:author_first_name><wp:author_last_name><![CDATA[Mann]]></wp:author_last_name></wp:author>
<wp:author><wp:author_id>2</wp:author_id><wp:author_login><![CDATA[editor]]></wp:author_login><wp:author_email><![CDATA[editor@example.com]]></wp:author_email><wp:author_display_name><![CDATA[Eric Mann]]></wp:author_display_name><wp:author_first_name><![CDATA[Ed]]></wp:author_first_name><wp:author_last_name><![CDATA[Itor]]></wp:author_last_name></wp:author>
<wp:term>
	<wp:term_id>${ termId }</wp:term_id>
	<wp:term_taxonomy>series</wp:term_taxonomy>
	<wp:term_slug>the-quiet-ledger</wp:term_slug>
	<wp:term_parent></wp:term_parent>
	<wp:term_name><![CDATA[The Quiet Ledger]]></wp:term_name>
	<wp:termmeta>
		<wp:meta_key><![CDATA[ttm_status]]></wp:meta_key>
		<wp:meta_value><![CDATA[in-progress]]></wp:meta_value>
	</wp:termmeta>
	<wp:termmeta>
		<wp:meta_key><![CDATA[ttm_form]]></wp:meta_key>
		<wp:meta_value><![CDATA[novel]]></wp:meta_value>
	</wp:termmeta>
	<wp:termmeta>
		<wp:meta_key><![CDATA[ttm_cover_id]]></wp:meta_key>
		<wp:meta_value><![CDATA[999]]></wp:meta_value>
	</wp:termmeta>
</wp:term>
<item>
	<title><![CDATA[Wired Post]]></title>
	<link>${ SITE_ORIGIN }/wired-post/</link>
	<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
	<dc:creator><![CDATA[eric]]></dc:creator>
	<guid isPermaLink="false">${ SITE_ORIGIN }/?p=${ ids.post1 }</guid>
	<description></description>
	<content:encoded><![CDATA[<p>See <a href="${ SITE_ORIGIN }/other-post/">the other post</a> and <img src="${ SITE_ORIGIN }/wp-content/uploads/2026/01/demo-cable-800x533.jpg"> and a stray <img src="${ SITE_ORIGIN }/wp-content/uploads/2026/01/not-a-demo-file.jpg">.</p>]]></content:encoded>
	<excerpt:encoded><![CDATA[About a cable at ${ SITE_ORIGIN }/wp-content/uploads/2026/01/demo-cable.jpg.]]></excerpt:encoded>
	<wp:post_id>${ ids.post1 }</wp:post_id>
	<wp:post_date>2026-01-01 09:00:00</wp:post_date>
	<wp:post_date_gmt>2026-01-01 09:00:00</wp:post_date_gmt>
	<wp:post_modified>2026-09-24 16:00:00</wp:post_modified>
	<wp:post_modified_gmt>2026-09-24 16:00:00</wp:post_modified_gmt>
	<wp:comment_status><![CDATA[closed]]></wp:comment_status>
	<wp:ping_status><![CDATA[closed]]></wp:ping_status>
	<wp:post_name><![CDATA[wired-post]]></wp:post_name>
	<wp:status><![CDATA[publish]]></wp:status>
	<wp:post_parent>0</wp:post_parent>
	<wp:menu_order>0</wp:menu_order>
	<wp:post_type><![CDATA[post]]></wp:post_type>
	<wp:post_password><![CDATA[]]></wp:post_password>
	<wp:is_sticky>0</wp:is_sticky>
	<category domain="series" nicename="the-quiet-ledger"><![CDATA[The Quiet Ledger]]></category>
	<wp:postmeta>
		<wp:meta_key>_thumbnail_id</wp:meta_key>
		<wp:meta_value><![CDATA[${ ids.attachment }]]></wp:meta_value>
	</wp:postmeta>
	<wp:postmeta>
		<wp:meta_key>_edit_lock</wp:meta_key>
		<wp:meta_value><![CDATA[1700000000:1]]></wp:meta_value>
	</wp:postmeta>
	<wp:postmeta>
		<wp:meta_key>ttm_word_count</wp:meta_key>
		<wp:meta_value><![CDATA[1200]]></wp:meta_value>
	</wp:postmeta>
</item>
<item>
	<title><![CDATA[Second Post]]></title>
	<link>${ SITE_ORIGIN }/second-post/</link>
	<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
	<dc:creator><![CDATA[editor]]></dc:creator>
	<guid isPermaLink="false">${ SITE_ORIGIN }/?p=${ ids.post2 }</guid>
	<description></description>
	<content:encoded><![CDATA[<p>Second post body.</p>]]></content:encoded>
	<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	<wp:post_id>${ ids.post2 }</wp:post_id>
	<wp:post_date>2026-01-02 09:00:00</wp:post_date>
	<wp:post_date_gmt>2026-01-02 09:00:00</wp:post_date_gmt>
	<wp:post_modified>2026-09-24 16:00:00</wp:post_modified>
	<wp:post_modified_gmt>2026-09-24 16:00:00</wp:post_modified_gmt>
	<wp:comment_status><![CDATA[closed]]></wp:comment_status>
	<wp:ping_status><![CDATA[closed]]></wp:ping_status>
	<wp:post_name><![CDATA[second-post]]></wp:post_name>
	<wp:status><![CDATA[publish]]></wp:status>
	<wp:post_parent>0</wp:post_parent>
	<wp:menu_order>0</wp:menu_order>
	<wp:post_type><![CDATA[post]]></wp:post_type>
	<wp:post_password><![CDATA[]]></wp:post_password>
	<wp:is_sticky>0</wp:is_sticky>
	<wp:postmeta>
		<wp:meta_key>_ttm_seed</wp:meta_key>
		<wp:meta_value><![CDATA[1]]></wp:meta_value>
	</wp:postmeta>
</item>
<item>
	<title><![CDATA[Orphaned Cover Post]]></title>
	<link>${ SITE_ORIGIN }/orphaned-cover-post/</link>
	<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
	<dc:creator><![CDATA[eric]]></dc:creator>
	<guid isPermaLink="false">${ SITE_ORIGIN }/?p=${ ids.post3 }</guid>
	<description></description>
	<content:encoded><![CDATA[<p>Third post body.</p>]]></content:encoded>
	<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	<wp:post_id>${ ids.post3 }</wp:post_id>
	<wp:post_date>2026-01-03 09:00:00</wp:post_date>
	<wp:post_date_gmt>2026-01-03 09:00:00</wp:post_date_gmt>
	<wp:post_modified>2026-09-24 16:00:00</wp:post_modified>
	<wp:post_modified_gmt>2026-09-24 16:00:00</wp:post_modified_gmt>
	<wp:comment_status><![CDATA[closed]]></wp:comment_status>
	<wp:ping_status><![CDATA[closed]]></wp:ping_status>
	<wp:post_name><![CDATA[orphaned-cover-post]]></wp:post_name>
	<wp:status><![CDATA[publish]]></wp:status>
	<wp:post_parent>0</wp:post_parent>
	<wp:menu_order>0</wp:menu_order>
	<wp:post_type><![CDATA[post]]></wp:post_type>
	<wp:post_password><![CDATA[]]></wp:post_password>
	<wp:is_sticky>0</wp:is_sticky>
	<wp:postmeta>
		<wp:meta_key>_thumbnail_id</wp:meta_key>
		<wp:meta_value><![CDATA[${ ids.otherAttachment }]]></wp:meta_value>
	</wp:postmeta>
</item>
<item>
	<title><![CDATA[About]]></title>
	<link>${ SITE_ORIGIN }/about/</link>
	<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
	<dc:creator><![CDATA[eric]]></dc:creator>
	<guid isPermaLink="false">${ SITE_ORIGIN }/?page_id=${ ids.page }</guid>
	<description></description>
	<content:encoded><![CDATA[<p>About page body.</p>]]></content:encoded>
	<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	<wp:post_id>${ ids.page }</wp:post_id>
	<wp:post_date>2026-01-04 09:00:00</wp:post_date>
	<wp:post_date_gmt>2026-01-04 09:00:00</wp:post_date_gmt>
	<wp:post_modified>2026-09-24 16:00:00</wp:post_modified>
	<wp:post_modified_gmt>2026-09-24 16:00:00</wp:post_modified_gmt>
	<wp:comment_status><![CDATA[closed]]></wp:comment_status>
	<wp:ping_status><![CDATA[closed]]></wp:ping_status>
	<wp:post_name><![CDATA[about]]></wp:post_name>
	<wp:status><![CDATA[publish]]></wp:status>
	<wp:post_parent>0</wp:post_parent>
	<wp:menu_order>0</wp:menu_order>
	<wp:post_type><![CDATA[page]]></wp:post_type>
	<wp:post_password><![CDATA[]]></wp:post_password>
	<wp:is_sticky>0</wp:is_sticky>
</item>
<item>
	<title><![CDATA[demo-cable.jpg]]></title>
	<link>${ SITE_ORIGIN }/demo-cable-jpg/</link>
	<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
	<dc:creator/>
	<guid isPermaLink="false">${ SITE_ORIGIN }/wp-content/uploads/2026/01/demo-cable.jpg</guid>
	<description></description>
	<content:encoded><![CDATA[]]></content:encoded>
	<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	<wp:post_id>${ ids.attachment }</wp:post_id>
	<wp:post_date>2026-01-01 09:00:00</wp:post_date>
	<wp:post_date_gmt>2026-01-01 09:00:00</wp:post_date_gmt>
	<wp:post_modified>2026-09-24 16:00:00</wp:post_modified>
	<wp:post_modified_gmt>2026-09-24 16:00:00</wp:post_modified_gmt>
	<wp:comment_status><![CDATA[closed]]></wp:comment_status>
	<wp:ping_status><![CDATA[closed]]></wp:ping_status>
	<wp:post_name><![CDATA[demo-cable-jpg]]></wp:post_name>
	<wp:status><![CDATA[inherit]]></wp:status>
	<wp:post_parent>${ ids.post1 }</wp:post_parent>
	<wp:menu_order>0</wp:menu_order>
	<wp:post_type><![CDATA[attachment]]></wp:post_type>
	<wp:post_password><![CDATA[]]></wp:post_password>
	<wp:is_sticky>0</wp:is_sticky>
	<wp:attachment_url>${ SITE_ORIGIN }/wp-content/uploads/2026/01/demo-cable.jpg</wp:attachment_url>
	<wp:postmeta>
		<wp:meta_key>_wp_attached_file</wp:meta_key>
		<wp:meta_value><![CDATA[2026/01/demo-cable.jpg]]></wp:meta_value>
	</wp:postmeta>
	<wp:postmeta>
		<wp:meta_key>_wp_attachment_metadata</wp:meta_key>
		<wp:meta_value><![CDATA[a:1:{s:5:"width";i:1600;}]]></wp:meta_value>
	</wp:postmeta>
</item>
<item>
	<title><![CDATA[not-a-demo-file.png]]></title>
	<link>${ SITE_ORIGIN }/not-a-demo-file-png/</link>
	<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>
	<dc:creator/>
	<guid isPermaLink="false">${ SITE_ORIGIN }/wp-content/uploads/2026/01/not-a-demo-file.png</guid>
	<description></description>
	<content:encoded><![CDATA[]]></content:encoded>
	<excerpt:encoded><![CDATA[]]></excerpt:encoded>
	<wp:post_id>${ ids.otherAttachment }</wp:post_id>
	<wp:post_date>2026-01-03 09:00:00</wp:post_date>
	<wp:post_date_gmt>2026-01-03 09:00:00</wp:post_date_gmt>
	<wp:post_modified>2026-09-24 16:00:00</wp:post_modified>
	<wp:post_modified_gmt>2026-09-24 16:00:00</wp:post_modified_gmt>
	<wp:comment_status><![CDATA[closed]]></wp:comment_status>
	<wp:ping_status><![CDATA[closed]]></wp:ping_status>
	<wp:post_name><![CDATA[not-a-demo-file-png]]></wp:post_name>
	<wp:status><![CDATA[inherit]]></wp:status>
	<wp:post_parent>${ ids.post3 }</wp:post_parent>
	<wp:menu_order>0</wp:menu_order>
	<wp:post_type><![CDATA[attachment]]></wp:post_type>
	<wp:post_password><![CDATA[]]></wp:post_password>
	<wp:is_sticky>0</wp:is_sticky>
	<wp:attachment_url>${ SITE_ORIGIN }/wp-content/uploads/2026/01/not-a-demo-file.png</wp:attachment_url>
</item>
</channel>
</rss>
`;
}

function normalize( xml ) {
	return normalizeWxr( xml, {
		siteOrigin: SITE_ORIGIN,
		imageBase: IMAGE_BASE,
		demoFiles: DEMO_FILES,
	} );
}

describe( 'normalizeWxr', () => {
	it( 'keeps posts, pages and demo attachments only', () => {
		const out = normalize( buildFixture() );
		const counts = countItems( out );

		expect( counts ).toEqual( { post: 3, page: 1, attachment: 1 } );
		// The non-demo attachment item is dropped entirely (its own post_name is unique to it --
		// an unrelated in-content <img> reference to a same-named file elsewhere is untouched).
		expect( out ).not.toContain( 'not-a-demo-file-png' );
	} );

	it( 'renumbers post ids from 1001 and rewrites parents and _thumbnail_id', () => {
		const out = normalize( buildFixture() );

		// Sorted by original id: post1(501) < post2(502) < post3(503) < page(510) <
		// attachment(520) -- the dropped attachment(521) is excluded from the sequence.
		expect( out ).toMatch( /<wp:post_id>1001<\/wp:post_id>/ );
		expect( out ).toMatch( /<wp:post_id>1005<\/wp:post_id>/ );

		// The kept attachment's post_parent (originally post1/501) is rewritten to 1001.
		const attachmentBlock = out.match(
			/<item>\s*<title><!\[CDATA\[demo-cable\.jpg\]\]>[\s\S]*?<\/item>/
		)[ 0 ];
		expect( attachmentBlock ).toMatch(
			/<wp:post_parent>1001<\/wp:post_parent>/
		);

		// post1's _thumbnail_id (originally 520) is rewritten to the attachment's new id.
		const post1Block = out.match(
			/<item>(?:(?!<\/item>)[\s\S])*Wired Post[\s\S]*?<\/item>/
		)[ 0 ];
		expect( post1Block ).toMatch(
			/<wp:meta_key>_thumbnail_id<\/wp:meta_key>\s*<wp:meta_value><!\[CDATA\[1005\]\]><\/wp:meta_value>/
		);
	} );

	it( 'drops _thumbnail_id that points at a dropped attachment', () => {
		const out = normalize( buildFixture() );

		const post3Block = out.match(
			/<item>(?:(?!<\/item>)[\s\S])*Orphaned Cover Post[\s\S]*?<\/item>/
		)[ 0 ];
		expect( post3Block ).not.toContain( '_thumbnail_id' );
	} );

	it( 'drops the listed post meta and ttm_cover_id term meta, keeps ttm_status and ttm_form', () => {
		const out = normalize( buildFixture() );

		expect( out ).not.toContain( '_edit_lock' );
		expect( out ).not.toContain( '_ttm_seed' );
		expect( out ).not.toContain( '_wp_attached_file' );
		expect( out ).not.toContain( '_wp_attachment_metadata' );
		expect( out ).not.toContain( 'ttm_cover_id' );
		expect( out ).toContain( 'ttm_status' );
		expect( out ).toContain( 'ttm_form' );
		expect( out ).toContain( 'ttm_word_count' );
	} );

	it( 'injects seriesTermMeta since wp export never emits <wp:termmeta> itself', () => {
		// The invented fixture's <wp:term> already carries wp:termmeta (matching the task's own
		// described shape), but a real `wp export` never does (docs/spikes/P2-01.md) -- strip it
		// out here to prove injection, not mere preservation, is what makes it reappear.
		const xmlWithoutTermMeta = buildFixture().replace(
			/<wp:termmeta>[\s\S]*?<\/wp:termmeta>\n?/g,
			''
		);
		expect( xmlWithoutTermMeta ).not.toContain( 'wp:termmeta' );

		const out = normalizeWxr( xmlWithoutTermMeta, {
			siteOrigin: SITE_ORIGIN,
			imageBase: IMAGE_BASE,
			demoFiles: DEMO_FILES,
			seriesTermMeta: {
				'the-quiet-ledger': {
					ttm_status: 'in-progress',
					ttm_form: 'novel',
				},
			},
		} );

		expect( out ).toContain(
			'<wp:meta_key><![CDATA[ttm_status]]></wp:meta_key>\n\t\t<wp:meta_value><![CDATA[in-progress]]></wp:meta_value>'
		);
		expect( out ).toContain(
			'<wp:meta_key><![CDATA[ttm_form]]></wp:meta_key>\n\t\t<wp:meta_value><![CDATA[novel]]></wp:meta_value>'
		);
	} );

	it( 'replaces the author with demo and removes e-mail and names', () => {
		const out = normalize( buildFixture() );

		expect( out ).not.toContain( 'eric@example.com' );
		expect( out ).not.toContain( 'editor@example.com' );
		expect(
			out.match(
				/<wp:author_login><!\[CDATA\[demo\]\]><\/wp:author_login>/g
			)
		).toHaveLength( 2 );
		expect( out ).toContain(
			'<wp:author_display_name><![CDATA[Eric Mann]]></wp:author_display_name>'
		);
		expect(
			out.match( /<dc:creator><!\[CDATA\[demo\]\]><\/dc:creator>/g )
				.length
		).toBeGreaterThanOrEqual( 3 );
	} );

	it( 'rewrites attachment and content image URLs to the raw GitHub base by basename', () => {
		const out = normalize( buildFixture() );

		expect( out ).toContain(
			`<wp:attachment_url>${ IMAGE_BASE }/demo-cable.jpg</wp:attachment_url>`
		);
		expect( out ).toContain(
			`<guid isPermaLink="false">${ IMAGE_BASE }/demo-cable.jpg</guid>`
		);
		// The sized in-content reference (`-800x533`) loses its size suffix.
		expect( out ).toContain( `${ IMAGE_BASE }/demo-cable.jpg` );
		expect( out ).not.toContain( 'demo-cable-800x533.jpg' );
		// A non-demo in-content image keeps its filename but loses the site origin (it isn't
		// rehosted -- only demo files get the raw GitHub base).
		expect( out ).toContain(
			'src="/wp-content/uploads/2026/01/not-a-demo-file.jpg"'
		);
	} );

	it( 'makes other local links root-relative and hosts example.com', () => {
		const out = normalize( buildFixture() );

		expect( out ).toContain( '<link>https://example.com</link>' );
		expect( out ).toContain(
			'<wp:base_site_url>https://example.com</wp:base_site_url>'
		);
		expect( out ).toContain(
			'<wp:base_blog_url>https://example.com</wp:base_blog_url>'
		);
		expect( out ).toContain( 'href="/other-post/"' );
		expect( out ).not.toContain( SITE_ORIGIN );
	} );

	it( 'sets post_modified to post_date and removes the export stamp and channel pubDate', () => {
		const out = normalize( buildFixture() );

		expect( out ).toContain(
			'<wp:post_modified>2026-01-01 09:00:00</wp:post_modified>'
		);
		expect( out ).toContain(
			'<wp:post_modified_gmt>2026-01-01 09:00:00</wp:post_modified_gmt>'
		);
		expect( out ).not.toMatch( /generator="[^"]*"\s*created=/ );
		// The channel's own <pubDate> (before <wp:wxr_version>) is gone; each item keeps its own.
		expect( out.indexOf( '<pubDate>' ) ).toBeGreaterThan(
			out.indexOf( '<wp:wxr_version>' )
		);
	} );

	it( 'flattens page post_date/post_date_gmt/pubDate to a fixed sentinel', () => {
		// `wp_insert_post()` never sets an explicit post_date for a page (unlike posts, which
		// carry a `days_ago`-derived date) -- WP defaults it to the real wall-clock moment of
		// the seed run, which would otherwise break byte-for-byte determinism across two builds
		// on the same day. The fixture's About page is given an arbitrary, non-sentinel date to
		// prove it gets overwritten rather than merely coinciding.
		const out = normalize( buildFixture() );

		const aboutBlock = out.match(
			/<item>\s*<title><!\[CDATA\[About\]\]>[\s\S]*?<\/item>/
		)[ 0 ];

		expect( aboutBlock ).toContain(
			'<wp:post_date>2026-01-01 00:00:00</wp:post_date>'
		);
		expect( aboutBlock ).toContain(
			'<wp:post_date_gmt>2026-01-01 00:00:00</wp:post_date_gmt>'
		);
		expect( aboutBlock ).toContain(
			'<pubDate>Thu, 01 Jan 2026 00:00:00 +0000</pubDate>'
		);
		expect( aboutBlock ).toContain(
			'<wp:post_modified>2026-01-01 00:00:00</wp:post_modified>'
		);

		// A post's real, days_ago-derived date is untouched.
		const post1Block = out.match(
			/<item>\s*<title><!\[CDATA\[Wired Post\]\]>[\s\S]*?<\/item>/
		)[ 0 ];
		expect( post1Block ).toContain(
			'<wp:post_date>2026-01-01 09:00:00</wp:post_date>'
		);
	} );

	it( 'is deterministic across different source ids and stamps', () => {
		const a = normalize( buildFixture() );
		const b = normalize(
			buildFixture( {
				postIds: {
					post1: 9001,
					post2: 9002,
					post3: 9003,
					page: 9010,
					attachment: 9020,
					otherAttachment: 9021,
				},
				termId: 4242,
				created: '2027-06-15 12:00',
				channelPubDate: 'Tue, 15 Jun 2027 12:00:00 +0000',
			} )
		);

		expect( a ).toBe( b );
	} );

	it( 'is idempotent', () => {
		const once = normalize( buildFixture() );
		const twice = normalize( once );

		expect( twice ).toBe( once );
	} );

	it( 'output parses as XML and contains no localhost or e-mail address', () => {
		const out = normalize( buildFixture() );

		const doc = new DOMParser().parseFromString( out, 'text/xml' );
		expect( doc.getElementsByTagName( 'parsererror' ).length ).toBe( 0 );
		expect( out ).not.toMatch( /localhost/i );
		expect( out ).not.toMatch( /[\w.+-]+@[\w-]+\.[\w.-]+/ );
	} );
} );

describe( 'rebaseAttachmentUrls', () => {
	it( 'swaps the image base only', () => {
		const out = normalize( buildFixture() );
		const rebased = rebaseAttachmentUrls(
			out,
			IMAGE_BASE,
			'http://127.0.0.1:9999/images'
		);

		expect( rebased ).toContain(
			'<wp:attachment_url>http://127.0.0.1:9999/images/demo-cable.jpg</wp:attachment_url>'
		);
		expect( rebased ).not.toContain( IMAGE_BASE );
		// Nothing else in the document changes: swapping the base back reproduces the input.
		expect(
			rebaseAttachmentUrls(
				rebased,
				'http://127.0.0.1:9999/images',
				IMAGE_BASE
			)
		).toBe( out );
	} );
} );

describe( 'countItems and attachmentBasenames', () => {
	it( 'read the normalised output', () => {
		const out = normalize( buildFixture() );

		expect( countItems( out ) ).toEqual( {
			post: 3,
			page: 1,
			attachment: 1,
		} );
		expect( attachmentBasenames( out ) ).toEqual( [ 'demo-cable.jpg' ] );
	} );
} );
