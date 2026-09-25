/**
 * Pure blueprint rewriting for `demo:check`'s local variant (P2-06). No I/O.
 */

/**
 * `demo:verify`'s `--attachments=<n>` rewritten to 0, in either a plain string command or the
 * last element of an array-form command.
 *
 * @param {string|string[]} command Original `wp-cli` step command.
 * @return {string|string[]} Command with the attachment count zeroed, unchanged if it doesn't
 *   mention `--attachments=`.
 */
function zeroLocalAttachmentCount( command ) {
	const rewrite = ( text ) =>
		text.replace( /--attachments=\d+/, '--attachments=0' );
	if ( Array.isArray( command ) ) {
		return command.map( ( part, index ) =>
			index === command.length - 1 && 'string' === typeof part
				? rewrite( part )
				: part
		);
	}
	return 'string' === typeof command ? rewrite( command ) : command;
}

/**
 * Replace exactly the two release-asset URLs (`installPlugin`/`installTheme`) and the WXR URL
 * (`importWxr`) with local, `base`-relative equivalents. Also zeroes `demo:verify`'s
 * `--attachments=<n>` expectation in any `wp-cli` step: P2-01's spike confirmed Playground's
 * `importWxr` `fetchAttachments` never actually downloads a binary from a bare `127.0.0.1`
 * static server (only the WXR file's own fetch works), so the local variant genuinely never
 * gets any attachments, unlike the real build against `raw.githubusercontent.com`. Everything
 * else (options, other `wp-cli` args) is left untouched.
 *
 * @param {Object} blueprint Rendered blueprint (from `renderBlueprint()`).
 * @param {string} base      Local server base, e.g. `http://127.0.0.1:54321`.
 * @return {Object} A new blueprint object with the URLs rewritten and attachment count zeroed.
 */
export function localBlueprint( blueprint, base ) {
	const root = base.replace( /\/$/, '' );

	const steps = blueprint.steps.map( ( step ) => {
		if ( 'installPlugin' === step.step ) {
			return {
				...step,
				pluginData: {
					...step.pluginData,
					url: `${ root }/ttm-core.zip`,
				},
			};
		}
		if ( 'installTheme' === step.step ) {
			return {
				...step,
				themeData: {
					...step.themeData,
					url: `${ root }/ttm-theme.zip`,
				},
			};
		}
		if ( 'importWxr' === step.step ) {
			return {
				...step,
				file: { ...step.file, url: `${ root }/demo-content.xml` },
			};
		}
		if ( 'wp-cli' === step.step ) {
			return {
				...step,
				command: zeroLocalAttachmentCount( step.command ),
			};
		}
		return step;
	} );

	return { ...blueprint, steps };
}
