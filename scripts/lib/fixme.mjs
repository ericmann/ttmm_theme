/**
 * Pure line filter for the zero-fixme guard (SPEC §6.9 last paragraph, P0-01).
 *
 * During the flight, a `test.fixme(` row is tolerated only when it also
 * carries a `// P<n>-<nn>` tag naming the task that will un-fixme it. An
 * untagged `test.fixme(` always fails; a tagged one fails only once tagging
 * is no longer allowed (`allowTagged === false`, from P5-02 on).
 */

const FIXME_LINE = /test\.fixme\(/;
const TASK_TAG = /\/\/\s*P\d-\d\d\b/;

/**
 * @param {string[]} lines       File lines to scan.
 * @param {boolean}  allowTagged Whether a `// P<n>-<nn>`-tagged fixme is tolerated.
 * @return {{index: number, line: string, tagged: boolean}[]} Offending lines (0-based index).
 */
export function taggedFixmeHits( lines, allowTagged ) {
	const hits = [];

	lines.forEach( ( line, index ) => {
		if ( ! FIXME_LINE.test( line ) ) {
			return;
		}

		const tagged = TASK_TAG.test( line );
		if ( ! tagged || ! allowTagged ) {
			hits.push( { index, line, tagged } );
		}
	} );

	return hits;
}
