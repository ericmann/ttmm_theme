/**
 * Pure WXR 1.2 normalisation (SPEC §6.3 step 2, P2-03). No XML parser dependency: works on
 * `<item>…</item>`/`<wp:term>…</wp:term>`-shaped blocks and single-element regexes over the
 * known WXR 1.2 shape `wp export` (and the spike's no-filter invocation) actually emits.
 * `docs/spikes/P2-01.md`/PLAN Decision "WXR normalisation" is the source of truth for every
 * transform below.
 */

/**
 * Escape a literal string for use inside a `new RegExp()` pattern.
 *
 * @param {string} value Literal string.
 * @return {string} Escaped string.
 */
function escapeRegExp( value ) {
	return value.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
}

/**
 * Strip a `<![CDATA[...]]>` wrapper, if present.
 *
 * @param {string} value Raw tag content.
 * @return {string} Unwrapped value.
 */
function unwrapCdata( value ) {
	const match = value.match( /^<!\[CDATA\[([\s\S]*)\]\]>$/ );
	return match ? match[ 1 ] : value;
}

/**
 * Read a tag's content (CDATA-unwrapped), handling `<tag/>` and `<tag></tag>` as empty.
 * Returns null when the tag isn't present at all.
 *
 * @param {string} str String to search.
 * @param {string} tag Tag name (may contain a namespace prefix, e.g. `wp:post_id`).
 * @return {string|null} Value, or null if the tag is absent.
 */
function getTag( str, tag ) {
	const match = str.match(
		new RegExp( `<${ tag }\\s*/>|<${ tag }>([\\s\\S]*?)<\\/${ tag }>` )
	);
	if ( ! match ) {
		return null;
	}
	return match[ 1 ] === undefined ? '' : unwrapCdata( match[ 1 ] );
}

/**
 * Replace a tag's content in place. A no-op when the tag isn't present.
 *
 * @param {string}  str                  String to modify.
 * @param {string}  tag                  Tag name.
 * @param {string}  value                New value.
 * @param {Object}  [options]            Options.
 * @param {boolean} [options.cdata=true] Wrap the value in CDATA.
 * @return {string} Modified string.
 */
function setTag( str, tag, value, { cdata = true } = {} ) {
	const re = new RegExp(
		`<${ tag }\\s*/>|<${ tag }>[\\s\\S]*?<\\/${ tag }>`
	);
	if ( ! re.test( str ) ) {
		return str;
	}
	const inner = cdata ? `<![CDATA[${ value }]]>` : value;
	return str.replace( re, `<${ tag }>${ inner }</${ tag }>` );
}

/**
 * The last path segment of a URL (ignoring any query string).
 *
 * @param {string} url URL.
 * @return {string} Basename.
 */
function basename( url ) {
	const clean = url.split( '?' )[ 0 ];
	return clean.substring( clean.lastIndexOf( '/' ) + 1 );
}

/**
 * A demo image's bare filename with any WordPress size suffix (`-800x533`) removed.
 *
 * @param {string} file Filename, possibly sized.
 * @return {string} Bare filename.
 */
function bareFilename( file ) {
	return file.replace( /-\d+x\d+(?=\.[a-zA-Z0-9]+$)/, '' );
}

/**
 * Rewrite a `<content:encoded>`/`<excerpt:encoded>` (or postmeta value) body: demo image URLs
 * under `siteOrigin` become absolute `imageBase` URLs (size suffix stripped); every other
 * occurrence of `siteOrigin` becomes root-relative.
 *
 * @param {string}      text       Raw body (CDATA already unwrapped).
 * @param {string}      siteOrigin Source site origin, e.g. `http://localhost:8888`.
 * @param {string}      imageBase  Demo image base URL (trailing slash optional).
 * @param {Set<string>} demoSet    Bare demo filenames.
 * @return {string} Rewritten body.
 */
function rewriteBody( text, siteOrigin, imageBase, demoSet ) {
	const origin = siteOrigin.replace( /\/$/, '' );
	const base = imageBase.replace( /\/$/, '' );
	const originPattern = escapeRegExp( origin );

	let out = text.replace(
		new RegExp( `${ originPattern }(/wp-content/uploads/[^"'\\s)]+)`, 'g' ),
		( match, path ) => {
			const file = basename( path );
			const bare = bareFilename( file );
			if ( demoSet.has( bare ) ) {
				return `${ base }/${ bare }`;
			}
			return path;
		}
	);

	out = out.replace( new RegExp( originPattern, 'g' ), '' );

	return out;
}

/**
 * Apply `rewriteBody()` to a CDATA-or-not tag body, preserving the CDATA wrapper.
 *
 * @param {string}      raw        Raw tag inner content, as matched (may be CDATA-wrapped).
 * @param {string}      siteOrigin Source site origin.
 * @param {string}      imageBase  Demo image base URL.
 * @param {Set<string>} demoSet    Bare demo filenames.
 * @return {string} Rewritten raw content, same wrapping as the input.
 */
function rewriteTagBody( raw, siteOrigin, imageBase, demoSet ) {
	const isCdata = /^<!\[CDATA\[[\s\S]*\]\]>$/.test( raw );
	const text = isCdata ? raw.slice( 9, -3 ) : raw;
	const rewritten = rewriteBody( text, siteOrigin, imageBase, demoSet );
	return isCdata ? `<![CDATA[${ rewritten }]]>` : rewritten;
}

const DROPPED_POST_META = new Set( [
	'_edit_lock',
	'_edit_last',
	'_ttm_seed',
	'ttm_primary_category',
	'_wp_attached_file',
	'_wp_attachment_metadata',
] );

/**
 * Rewrite one `<item>` block's `<wp:postmeta>` rows: drops the listed keys and any
 * `_thumbnail_id` pointing at a dropped attachment, remaps a surviving `_thumbnail_id`,
 * root-relatives/rebases any remaining meta value that carries the site origin.
 *
 * @param {string}              item       Item block.
 * @param {Map<number, number>} idMap      Old post id -> new post id (kept items only).
 * @param {string}              siteOrigin Source site origin.
 * @param {string}              imageBase  Demo image base URL.
 * @param {Set<string>}         demoSet    Bare demo filenames.
 * @return {string} Item block with postmeta rewritten.
 */
function rewritePostMeta( item, idMap, siteOrigin, imageBase, demoSet ) {
	return item.replace(
		/<wp:postmeta>\s*<wp:meta_key>([\s\S]*?)<\/wp:meta_key>\s*<wp:meta_value>([\s\S]*?)<\/wp:meta_value>\s*<\/wp:postmeta>\n?/g,
		( whole, rawKey, rawValue ) => {
			const key = unwrapCdata( rawKey );

			if ( DROPPED_POST_META.has( key ) ) {
				return '';
			}

			if ( '_thumbnail_id' === key ) {
				const oldThumb = parseInt( unwrapCdata( rawValue ), 10 );
				if ( ! idMap.has( oldThumb ) ) {
					return '';
				}
				return whole.replace(
					rawValue,
					`<![CDATA[${ idMap.get( oldThumb ) }]]>`
				);
			}

			const rewrittenValue = rewriteTagBody(
				rawValue,
				siteOrigin,
				imageBase,
				demoSet
			);
			return whole.replace( rawValue, rewrittenValue );
		}
	);
}

/**
 * Normalise one kept `<item>` block: renumber its id/parent, zero `post_modified` against
 * `post_date`, rewrite postmeta, and flatten guid/link/attachment URLs.
 *
 * @param {string} item  Original item block.
 * @param {number} newId This item's new `wp:post_id`.
 * @param {Object} idMap Old id -> new id map (kept items).
 * @param {Object} opts  `{siteOrigin, imageBase, demoSet, placeholderHost}`.
 * @return {string} Normalised item block.
 */
function normalizeItem( item, newId, idMap, opts ) {
	const { siteOrigin, imageBase, demoSet, placeholderHost } = opts;

	let out = setTag( item, 'wp:post_id', String( newId ), { cdata: false } );

	const oldParent = parseInt( getTag( out, 'wp:post_parent' ) || '0', 10 );
	const newParent =
		oldParent && idMap.has( oldParent ) ? idMap.get( oldParent ) : 0;
	out = setTag( out, 'wp:post_parent', String( newParent ), {
		cdata: false,
	} );

	const postDate = getTag( out, 'wp:post_date' );
	const postDateGmt = getTag( out, 'wp:post_date_gmt' );
	if ( null !== postDate ) {
		out = setTag( out, 'wp:post_modified', postDate, { cdata: false } );
	}
	if ( null !== postDateGmt ) {
		out = setTag( out, 'wp:post_modified_gmt', postDateGmt, {
			cdata: false,
		} );
	}

	out = rewritePostMeta( out, idMap, siteOrigin, imageBase, demoSet );

	const type = getTag( out, 'wp:post_type' );

	if ( 'attachment' === type ) {
		const file = basename( getTag( out, 'wp:attachment_url' ) || '' );
		const target = `${ imageBase.replace( /\/$/, '' ) }/${ file }`;
		out = setTag( out, 'wp:attachment_url', target, { cdata: false } );
		out = out.replace(
			/<guid[^>]*>[\s\S]*?<\/guid>/,
			`<guid isPermaLink="false">${ target }</guid>`
		);
	} else {
		out = out.replace(
			/<guid[^>]*>[\s\S]*?<\/guid>/,
			`<guid isPermaLink="false">${ placeholderHost }</guid>`
		);
	}

	out = out.replace(
		/<link>[\s\S]*?<\/link>/,
		`<link>${ placeholderHost }</link>`
	);

	out = out.replace(
		/<content:encoded>([\s\S]*?)<\/content:encoded>/,
		( whole, raw ) =>
			`<content:encoded>${ rewriteTagBody(
				raw,
				siteOrigin,
				imageBase,
				demoSet
			) }</content:encoded>`
	);
	out = out.replace(
		/<excerpt:encoded>([\s\S]*?)<\/excerpt:encoded>/,
		( whole, raw ) =>
			`<excerpt:encoded>${ rewriteTagBody(
				raw,
				siteOrigin,
				imageBase,
				demoSet
			) }</excerpt:encoded>`
	);

	return out;
}

/**
 * Normalise a `wp export` (or the spike's no-filter form) WXR into a deterministic, host-free,
 * owner-free demo WXR.
 *
 * @param {string}   xml                                             Raw WXR.
 * @param {Object}   options                                         Options.
 * @param {string}   options.siteOrigin                              Source site origin, e.g. `http://localhost:8888`.
 * @param {string}   options.imageBase                               Demo image base URL (e.g. the raw GitHub base).
 * @param {string[]} options.demoFiles                               Bare filenames present in `docs/fixtures/demo/images/`.
 * @param {string}   [options.placeholderHost='https://example.com'] Flattened host for
 *                                                                   base_site_url/base_blog_url/link/non-attachment guid.
 * @param {number}   [options.firstPostId=1001]                      First renumbered post id.
 * @return {string} Normalised WXR.
 */
export function normalizeWxr(
	xml,
	{
		siteOrigin,
		imageBase,
		demoFiles,
		placeholderHost = 'https://example.com',
		firstPostId = 1001,
	}
) {
	const demoSet = new Set( demoFiles || [] );

	let out = xml;

	// The export's `created="…"` stamp and the channel's own `<pubDate>` are the only
	// non-reproducible header fields; both are removed rather than flattened. The channel
	// `<pubDate>` always precedes `<wp:wxr_version>`, unlike every item's own `<pubDate>` (which
	// is left alone) -- scoping the removal to that header region keeps this idempotent, since
	// a second pass would otherwise strip the first item's `<pubDate>` once the channel's is gone.
	out = out.replace(
		/<!--\s*generator="[^"]*"\s*created="[^"]*"\s*-->\n?/,
		''
	);
	const wxrVersionIndex = out.indexOf( '<wp:wxr_version>' );
	if ( -1 !== wxrVersionIndex ) {
		const head = out.slice( 0, wxrVersionIndex );
		const rest = out.slice( wxrVersionIndex );
		out = head.replace( /<pubDate>[^<]*<\/pubDate>\n?/, '' ) + rest;
	}

	out = setTag( out, 'wp:base_site_url', placeholderHost, {
		cdata: false,
	} );
	out = setTag( out, 'wp:base_blog_url', placeholderHost, {
		cdata: false,
	} );
	out = out.replace(
		/<link>[^<]*<\/link>/,
		`<link>${ placeholderHost }</link>`
	);

	out = out.replace( /<wp:author>[\s\S]*?<\/wp:author>/g, ( block ) => {
		let b = setTag( block, 'wp:author_login', 'demo' );
		b = setTag( b, 'wp:author_email', '' );
		b = setTag( b, 'wp:author_first_name', '' );
		b = setTag( b, 'wp:author_last_name', '' );
		return b;
	} );

	out = out.replace(
		/<dc:creator\s*\/>|<dc:creator>[\s\S]*?<\/dc:creator>/g,
		'<dc:creator><![CDATA[demo]]></dc:creator>'
	);

	// Term definitions (`<wp:category>`, `<wp:tag>`, `<wp:term>`) are renumbered in place --
	// posts reference terms by nicename/slug, never by id, so nothing else needs updating.
	const termDefRegex =
		/<wp:(?:category|tag|term)>[\s\S]*?<\/wp:(?:category|tag|term)>/g;
	const termIds = [ ...out.matchAll( termDefRegex ) ].map( ( m ) =>
		parseInt( getTag( m[ 0 ], 'wp:term_id' ) || '0', 10 )
	);
	const sortedTermIds = [ ...new Set( termIds ) ].sort( ( a, b ) => a - b );
	const termIdMap = new Map(
		sortedTermIds.map( ( id, index ) => [ id, index + 1 ] )
	);
	out = out.replace( termDefRegex, ( block ) => {
		const oldId = parseInt( getTag( block, 'wp:term_id' ) || '0', 10 );
		let b = setTag( block, 'wp:term_id', String( termIdMap.get( oldId ) ), {
			cdata: false,
		} );
		b = b.replace(
			/<wp:termmeta>\s*<wp:meta_key>([\s\S]*?)<\/wp:meta_key>\s*<wp:meta_value>[\s\S]*?<\/wp:meta_value>\s*<\/wp:termmeta>\n?/g,
			( whole, rawKey ) =>
				'ttm_cover_id' === unwrapCdata( rawKey ) ? '' : whole
		);
		return b;
	} );

	// Items: keep post/page/any attachment whose file is a demo photograph; everything else
	// (wp_navigation, wp_global_styles, a non-demo attachment, …) is dropped.
	const itemRegex = /<item>[\s\S]*?<\/item>\n?/g;
	const rawItems = [ ...out.matchAll( itemRegex ) ].map( ( m ) => m[ 0 ] );

	const kept = rawItems.filter( ( item ) => {
		const type = getTag( item, 'wp:post_type' );
		if ( 'post' === type || 'page' === type ) {
			return true;
		}
		if ( 'attachment' === type ) {
			const file = basename( getTag( item, 'wp:attachment_url' ) || '' );
			return demoSet.has( file );
		}
		return false;
	} );

	const withOldIds = kept
		.map( ( item ) => ( {
			item,
			oldId: parseInt( getTag( item, 'wp:post_id' ) || '0', 10 ),
		} ) )
		.sort( ( a, b ) => a.oldId - b.oldId );

	const idMap = new Map();
	withOldIds.forEach( ( { oldId }, index ) => {
		idMap.set( oldId, firstPostId + index );
	} );

	const normalizedItems = withOldIds.map( ( { item }, index ) =>
		normalizeItem( item, firstPostId + index, idMap, {
			siteOrigin,
			imageBase,
			demoSet,
			placeholderHost,
		} )
	);

	let inserted = false;
	out = out.replace( itemRegex, () => {
		if ( inserted ) {
			return '';
		}
		inserted = true;
		return normalizedItems.join( '' );
	} );

	return out;
}

/**
 * Swap exactly one image base URL for another, everywhere it appears (attachment/content
 * image URLs already point at `fromBase` after `normalizeWxr`; nothing else in the document
 * shares that string).
 *
 * @param {string} xml      WXR.
 * @param {string} fromBase Current image base URL.
 * @param {string} toBase   Replacement image base URL.
 * @return {string} Rebased WXR.
 */
export function rebaseAttachmentUrls( xml, fromBase, toBase ) {
	const from = fromBase.replace( /\/$/, '' );
	const to = toBase.replace( /\/$/, '' );
	return xml.split( from ).join( to );
}

/**
 * Count `<item>` rows by `wp:post_type`.
 *
 * @param {string} xml WXR.
 * @return {{post: number, page: number, attachment: number}} Counts.
 */
export function countItems( xml ) {
	const items = [ ...xml.matchAll( /<item>[\s\S]*?<\/item>/g ) ].map(
		( m ) => m[ 0 ]
	);
	const counts = { post: 0, page: 0, attachment: 0 };
	for ( const item of items ) {
		const type = getTag( item, 'wp:post_type' );
		if ( type && Object.prototype.hasOwnProperty.call( counts, type ) ) {
			counts[ type ]++;
		}
	}
	return counts;
}

/**
 * Every attachment item's file basename, in document order.
 *
 * @param {string} xml WXR.
 * @return {string[]} Basenames.
 */
export function attachmentBasenames( xml ) {
	const items = [ ...xml.matchAll( /<item>[\s\S]*?<\/item>/g ) ].map(
		( m ) => m[ 0 ]
	);
	const names = [];
	for ( const item of items ) {
		if ( 'attachment' === getTag( item, 'wp:post_type' ) ) {
			names.push( basename( getTag( item, 'wp:attachment_url' ) || '' ) );
		}
	}
	return names;
}
