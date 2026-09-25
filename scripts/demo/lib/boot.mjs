/**
 * Pure Playground CLI boot-readiness detection (R1-01). No I/O.
 *
 * `npx @wp-playground/cli server` only calls its own `printReady()` after every blueprint step
 * -- including the final `wp eval` rebuild + `demo:verify` -- has finished, and writes the line
 * "WordPress is running on <url>" when it does. Polling the front page's HTML for signs of real
 * content (the previous approach) raced that last step: the worker pool starts answering HTTP
 * requests before the blueprint has finished writing the imported content, so a page fetched too
 * early can look "up" (200 status) while still serving a pre-import, empty-looking state. Waiting
 * for this line instead of any HTTP probe removes the race entirely.
 */

const READY_MARKER = 'WordPress is running on';

/**
 * Whether the CLI process's combined stdout/stderr output collected so far includes the ready
 * line.
 *
 * @param {string} output Combined stdout/stderr collected so far.
 * @return {boolean} True once the ready line has appeared.
 */
export function isReady( output ) {
	return output.includes( READY_MARKER );
}
