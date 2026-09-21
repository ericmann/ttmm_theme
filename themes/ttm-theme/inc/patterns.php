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

/**
 * Register `ttm/section-cell-{slug}` for each non-Technology, non-Writing section by including
 * the header-less `section-cell.php` template once per section with `$ttm_section` set.
 *
 * That template lives under `inc/pattern-templates/`, not `patterns/`, because WordPress's own
 * pattern-directory scanner walks every `.php` file directly under `patterns/` and logs an
 * `_doing_it_wrong()` notice for any file missing the required `Slug:` header comment -- this
 * file is deliberately headerless (it's captured and registered manually, once per section,
 * right below) so it must live outside that scanned directory entirely.
 *
 * Reads `cells.counts` through the plugin's Config when active (guarded: the theme must render
 * without erroring when ttm-core is inactive, SPEC §9), else falls back to 3.
 */
function register_section_cells(): void {
	$sections = [
		'business' => __( 'Business', 'ttm-theme' ),
		'security' => __( 'Security', 'ttm-theme' ),
		'faith'    => __( 'Faith', 'ttm-theme' ),
		'opinion'  => __( 'Opinion', 'ttm-theme' ),
	];

	$counts = class_exists( '\TTM\Core\Config' ) ? (array) \TTM\Core\Config::get( 'cells.counts', [] ) : [];

	foreach ( $sections as $ttm_slug => $ttm_name ) {
		$ttm_section = [
			'slug'     => $ttm_slug,
			'name'     => $ttm_name,
			'per_page' => $counts[ $ttm_slug ] ?? 3,
		];

		ob_start();
		require __DIR__ . '/pattern-templates/section-cell.php';
		$ttm_content = (string) ob_get_clean();

		register_block_pattern(
			'ttm/section-cell-' . $ttm_slug,
			[
				/* translators: %s: section name (e.g. Business). */
				'title'      => sprintf( __( 'Section Cell — %s', 'ttm-theme' ), $ttm_name ),
				'categories' => [ 'ttm-front' ],
				'content'    => $ttm_content,
				'inserter'   => false,
			]
		);
	}//end foreach
}
add_action( 'init', __NAMESPACE__ . '\\register_section_cells' );
