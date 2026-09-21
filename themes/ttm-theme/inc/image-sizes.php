<?php
/**
 * The four image sizes (01 §6). A local array, filterable via `ttm_image_sizes`
 * (Decisions) — the theme never reads plugin Config.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * Register the four image sizes.
 */
function register_image_sizes(): void {
	$sizes = apply_filters(
		'ttm_image_sizes',
		[
			'ttm-lead'  => [ 1600, 900, true ],
			'ttm-thumb' => [ 800, 533, true ],
			'ttm-cover' => [ 600, 900, true ],
			'ttm-tile'  => [ 800, 600, true ],
		]
	);

	foreach ( $sizes as $name => $dimensions ) {
		add_image_size( $name, $dimensions[0], $dimensions[1], $dimensions[2] );
	}
}
add_action( 'after_setup_theme', __NAMESPACE__ . '\\register_image_sizes' );
