<?php
/**
 * Title: Archive Header
 * Slug: ttm/archive-header
 * Categories: ttm-lists
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-grid-8-4 ttm-archive-head","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-grid-8-4 ttm-archive-head">
	<!-- wp:group {"layout":{"type":"constrained"}} -->
	<div class="wp-block-group">
		<!-- wp:paragraph {"className":"is-style-kicker"} -->
		<p class="is-style-kicker"><?php esc_html_e( 'Section', 'ttm-theme' ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"is-style-display-xl"} /-->

		<!-- wp:term-description /-->
	</div>
	<!-- /wp:group -->

	<!-- wp:ttm/category-stats /-->
</div>
<!-- /wp:group -->
