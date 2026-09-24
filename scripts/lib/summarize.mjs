/**
 * Pure exit-decision logic for scripts/convert-classic.mjs's CLI (R1-03). Split out the same
 * way scripts/lib/report.mjs was (see its docblock) so this is testable without jsdom or the
 * WordPress block-library package, and importable at all under this Jest environment --
 * dynamically importing convert-classic.mjs itself (the CLI entry, with its own auto-run
 * guard) does not load cleanly under Jest's module system, unlike a plain lib module.
 */

/**
 * Turns a batch of convertPost() results into the CLI's exit decision and message lines.
 * `--allow-text-mismatch` still reports every text-mismatch line but never sets `failed`;
 * `--allow-freeform` behaves the same as before for freeform/html fallback blocks.
 * `--allow-merged-paragraphs` (R2-01, SPEC §6.8) behaves the same way for
 * `report.mergedParagraphs`: without it, any post with `mergedParagraphs > 0` fails, since that
 * signals `autop()` didn't split a source paragraph break the way it should have; `plan.sh`
 * must never pass this flag (a merged paragraph on the real live import is a bug, not an
 * accepted content-quality issue like a text mismatch).
 *
 * @param {Array<{id: number|string, slug: string, report: {textEqual: boolean, freeform: number, html: number, mergedParagraphs: number}}>} results
 * @param {{allowFreeform?: boolean, allowTextMismatch?: boolean, allowMergedParagraphs?: boolean}}                                          [options]
 * @return {{failed: boolean, messages: string[]}} Whether the CLI should exit 1, and the lines to print.
 */
export function summarizeResults( results, options = {} ) {
	const {
		allowFreeform = false,
		allowTextMismatch = false,
		allowMergedParagraphs = false,
	} = options;
	let failed = false;
	const messages = [];

	for ( const result of results ) {
		if ( ! result.report.textEqual ) {
			messages.push(
				`post ${ result.id } (${ result.slug }): text content changed`
			);
			if ( ! allowTextMismatch ) {
				failed = true;
			}
		}
		const fallbackCount = result.report.freeform + result.report.html;
		if ( ! allowFreeform && fallbackCount > 0 ) {
			messages.push(
				`post ${ result.id } (${ result.slug }): ${ fallbackCount } freeform/html block(s)`
			);
			failed = true;
		}
		const mergedParagraphs = result.report.mergedParagraphs || 0;
		if ( ! allowMergedParagraphs && mergedParagraphs > 0 ) {
			messages.push(
				`post ${ result.id } (${ result.slug }): ${ mergedParagraphs } merged paragraph block(s)`
			);
			failed = true;
		}
	}

	return { failed, messages };
}
