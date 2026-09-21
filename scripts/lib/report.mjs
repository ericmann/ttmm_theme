/**
 * Pure block-count reporting for the classic-to-block converter (P8-01, PLAN's report
 * contract). Split out of convert-classic.mjs (which also imports `jsdom` to build the CLI's
 * block-editor environment) so this logic -- and a test of it -- doesn't need `jsdom` at all.
 */

/**
 * Tally a rawHandler() block list by name, with `core/freeform` and `core/html` -- the two
 * "conversion needs a human" fallback block types -- broken out as their own counts (each is
 * also present individually in `blockCounts`).
 *
 * @param {Array<{name: string}>} blockList
 * @return {{ blockCounts: Record<string, number>, freeform: number, html: number }} The report.
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

	return { blockCounts, freeform, html };
}
