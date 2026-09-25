/**
 * Pure blueprint rewriting for `demo:check`'s local variant (P2-06, R1-01). No I/O.
 */

const LOOPBACK_ALLOW_PLUGIN = `<?php
/**
 * demo:check local variant only: allows WordPress's HTTP API to fetch attachment binaries from
 * this run's own loopback static server, whose host/port would otherwise be rejected by
 * \`wp_http_validate_url()\`'s SSRF guard against non-public hosts (rule 54: this only ever widens
 * access to a server this same script started, on 127.0.0.1, for the lifetime of this one check).
 */
add_filter( 'http_request_host_is_external', function ( $is_external, $host ) {
	if ( '__HOST__' === $host ) {
		return true;
	}
	return $is_external;
}, 10, 2 );

add_filter( 'http_allowed_safe_ports', function ( $ports ) {
	$ports[] = __PORT__;
	return $ports;
} );
`;

/**
 * `demo:verify`'s `--attachments=<n>` step, unchanged: the local variant now allows the importer
 * to actually fetch attachment binaries (see `LOOPBACK_ALLOW_PLUGIN` above), so the expected
 * count must match the real build's, not be zeroed.
 *
 * @param {Object} blueprint Rendered blueprint (from `renderBlueprint()`).
 * @param {string} base      Local server base, e.g. `http://127.0.0.1:54321`.
 * @return {Object} A new blueprint object with the release/WXR URLs rewritten to `base` and a
 *   loopback-allow mu-plugin step inserted immediately before `importWxr`.
 */
export function localBlueprint( blueprint, base ) {
	const root = base.replace( /\/$/, '' );
	const url = new URL( root );

	const mkdirStep = {
		step: 'mkdir',
		path: '/wordpress/wp-content/mu-plugins',
	};
	const writeFileStep = {
		step: 'writeFile',
		path: '/wordpress/wp-content/mu-plugins/ttm-demo-check-loopback-allow.php',
		data: LOOPBACK_ALLOW_PLUGIN.replace( '__HOST__', url.hostname ).replace(
			'__PORT__',
			url.port
		),
	};

	const steps = [];
	for ( const step of blueprint.steps ) {
		if ( 'importWxr' === step.step ) {
			steps.push( mkdirStep, writeFileStep );
		}

		if ( 'installPlugin' === step.step ) {
			steps.push( {
				...step,
				pluginData: {
					...step.pluginData,
					url: `${ root }/ttm-core.zip`,
				},
			} );
			continue;
		}
		if ( 'installTheme' === step.step ) {
			steps.push( {
				...step,
				themeData: {
					...step.themeData,
					url: `${ root }/ttm-theme.zip`,
				},
			} );
			continue;
		}
		if ( 'importWxr' === step.step ) {
			steps.push( {
				...step,
				file: { ...step.file, url: `${ root }/demo-content.xml` },
			} );
			continue;
		}
		steps.push( step );
	}

	return { ...blueprint, steps };
}
