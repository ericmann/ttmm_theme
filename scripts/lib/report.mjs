/**
 * Pure block-count reporting for the classic-to-block converter (P8-01, PLAN's report
 * contract). Split out of convert-classic.mjs (which also imports `jsdom` to build the CLI's
 * block-editor environment) so this logic -- and a test of it -- doesn't need `jsdom` at all.
 */

/**
 * A `core/paragraph` block's content contains a blank line (rawHandler failed to split it into
 * separate paragraph blocks -- R2-01, SPEC §6.8: the merged-paragraph signal `autop()` is meant
 * to drive to zero).
 *
 * @param {{name: string, attributes?: {content?: string}}} block
 * @return {boolean} Whether this paragraph block's content has an internal blank line.
 */
function isMergedParagraph( block ) {
	if ( block.name !== 'core/paragraph' ) {
		return false;
	}
	const content = block.attributes && block.attributes.content;
	return typeof content === 'string' && /\n\s*\n/.test( content );
}

/**
 * Count of `core/paragraph` blocks with an internal blank line, recursing into `innerBlocks`
 * (R3-02): a merged paragraph nested inside e.g. `core/quote`/`core/list`/`core/group` is the
 * same wpautop()-ordering regression R2-01 fixes at the top level -- rawHandler() nests child
 * blocks under `innerBlocks`, so a top-level-only count silently missed every one of them.
 *
 * @param {Array<{name: string, attributes?: {content?: string}, innerBlocks?: Array}>} blockList
 * @return {number} The merged-paragraph count across the whole tree.
 */
function countMergedParagraphs( blockList ) {
	let count = 0;
	for ( const block of blockList ) {
		if ( isMergedParagraph( block ) ) {
			count += 1;
		}
		if ( Array.isArray( block.innerBlocks ) && block.innerBlocks.length ) {
			count += countMergedParagraphs( block.innerBlocks );
		}
	}
	return count;
}

/**
 * Tally a rawHandler() block list by name, with `core/freeform` and `core/html` -- the two
 * "conversion needs a human" fallback block types -- broken out as their own counts (each is
 * also present individually in `blockCounts`), plus `mergedParagraphs`: the count of
 * `core/paragraph` blocks whose content still contains a blank line (R2-01), counted anywhere
 * in the tree including inside `innerBlocks` (R3-02). `blockCounts`/`freeform`/`html` stay
 * top-level only (rawHandler()'s own block list is what the CLI's block-count report has always
 * meant by "how many of each block type came out of conversion"; only the merged-paragraph
 * regression signal needed to see inside container blocks).
 *
 * @param {Array<{name: string, attributes?: {content?: string}, innerBlocks?: Array}>} blockList
 * @return {{ blockCounts: Record<string, number>, freeform: number, html: number, mergedParagraphs: number }} The report.
 */
export function buildBlockReport( blockList ) {
	const blockCounts = {};
	let freeform = 0;
	let html = 0;

	for ( const block of blockList ) {
		blockCounts[ block.name ] = ( blockCounts[ block.name ] || 0 ) + 1;
		if ( block.name === 'core/freeform' ) {
			freeform += 1;
		}
		if ( block.name === 'core/html' ) {
			html += 1;
		}
	}

	const mergedParagraphs = countMergedParagraphs( blockList );

	return { blockCounts, freeform, html, mergedParagraphs };
}
