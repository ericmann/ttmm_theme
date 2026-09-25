/**
 * Pure blueprint rendering (SPEC §6.4, P2-04). No I/O: takes the parsed template and already-read
 * data, returns a plain object ready for `JSON.stringify()`.
 */

/**
 * Recursively substitute string placeholders inside a JSON-shaped value. Only string leaves are
 * touched; the `{{OPTIONS}}` placeholder is handled separately by `renderBlueprint()` since it
 * needs to become an object, not a string interpolation.
 *
 * @param {*}                      node         Template node.
 * @param {Record<string, string>} replacements Token -> replacement string.
 * @return {*} New node with every token replaced.
 */
function deepReplace( node, replacements ) {
	if ( 'string' === typeof node ) {
		let result = node;
		for ( const [ token, value ] of Object.entries( replacements ) ) {
			result = result.split( token ).join( value );
		}
		return result;
	}
	if ( Array.isArray( node ) ) {
		return node.map( ( item ) => deepReplace( item, replacements ) );
	}
	if ( node && 'object' === typeof node ) {
		const out = {};
		for ( const [ key, value ] of Object.entries( node ) ) {
			out[ key ] = deepReplace( value, replacements );
		}
		return out;
	}
	return node;
}

/**
 * Render `blueprint.template.json` into the final blueprint: fills in the release tag and
 * `demo:verify` args (plain string substitution, since both sit inside larger strings) and
 * replaces the `setSiteOptions` step's `"{{OPTIONS}}"` placeholder with the real options object
 * (a structural replacement, not string concatenation, so the result is valid JSON regardless
 * of what the options contain).
 *
 * @param {Object} template        Parsed `blueprint.template.json`.
 * @param {Object} data            `{release, options, verifyArgs}`.
 * @param {string} data.release    Release tag, e.g. `v0.2.0`.
 * @param {Object} data.options    The `demo:options` payload.
 * @param {string} data.verifyArgs `demo:verify`'s `--posts=… --pages=… --series=… --attachments=…`.
 * @return {Object} Rendered blueprint.
 */
export function renderBlueprint(
	template,
	{ release, options, verifyArgs: verifyArgsStr }
) {
	const rendered = deepReplace( template, {
		'{{RELEASE}}': release,
		'{{VERIFY_ARGS}}': verifyArgsStr,
	} );

	rendered.steps = rendered.steps.map( ( step ) =>
		'setSiteOptions' === step.step ? { ...step, options } : step
	);

	return rendered;
}

/**
 * Read the `Version:` header from a plugin's main PHP file source.
 *
 * @param {string} phpSource `ttm-core.php` contents.
 * @return {string} `v<version>`, e.g. `v0.2.0`.
 */
export function releaseFromPluginHeader( phpSource ) {
	const match = phpSource.match( /^\s*\*\s*Version:\s*(\S+)/m );
	return `v${ match ? match[ 1 ] : '0.0.0' }`;
}

/**
 * `demo:verify`'s four count flags, in order.
 *
 * @param {Object} counts            `{post, page, attachment}` (from `countItems()`).
 * @param {number} counts.post       Post count.
 * @param {number} counts.page       Page count.
 * @param {number} counts.attachment Attachment count.
 * @param {number} series            Series count.
 * @return {string} `--posts=<n> --pages=<n> --series=<n> --attachments=<n>`.
 */
export function verifyArgs( { post, page, attachment }, series ) {
	return `--posts=${ post } --pages=${ page } --series=${ series } --attachments=${ attachment }`;
}
