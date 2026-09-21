<?php
/**
 * Pattern categories (04 §7).
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * Register the five pattern categories.
 */
function register_pattern_categories(): void {
	register_block_pattern_category( 'ttm-front', [ 'label' => __( 'TTM: Front', 'ttm-theme' ) ] );
	register_block_pattern_category( 'ttm-article', [ 'label' => __( 'TTM: Article', 'ttm-theme' ) ] );
	register_block_pattern_category( 'ttm-lists', [ 'label' => __( 'TTM: Lists', 'ttm-theme' ) ] );
	register_block_pattern_category( 'ttm-fiction', [ 'label' => __( 'TTM: Fiction', 'ttm-theme' ) ] );
	register_block_pattern_category( 'ttm-marketing', [ 'label' => __( 'TTM: Marketing', 'ttm-theme' ) ] );
}
add_action( 'init', __NAMESPACE__ . '\\register_pattern_categories' );
