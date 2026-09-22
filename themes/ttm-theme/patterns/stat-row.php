<?php
/**
 * Title: Stat Row
 * Slug: ttm/stat-row
 * Categories: ttm-lists
 * Inserter: yes
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-stats","layout":{"type":"grid","columns":3}} -->
<div class="wp-block-group ttm-stats">
	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
		<p>12 / 31</p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"is-style-meta"} -->
		<p class="is-style-meta"><?php esc_html_e( 'chapters published', 'ttm-theme' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
		<p>Monthly</p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"is-style-meta"} -->
		<p class="is-style-meta"><?php esc_html_e( 'next: Oct 17', 'ttm-theme' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph -->
		<p>~14 min</p>
		<!-- /wp:paragraph -->
		<!-- wp:paragraph {"className":"is-style-meta"} -->
		<p class="is-style-meta"><?php esc_html_e( 'per chapter', 'ttm-theme' ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
