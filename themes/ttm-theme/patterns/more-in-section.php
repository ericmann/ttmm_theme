<?php
/**
 * Title: More In Section
 * Slug: ttm/more-in-section
 * Categories: ttm-article
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-more-in","layout":{"type":"default"}} -->
<div class="wp-block-group ttm-more-in">
	<!-- wp:group {"className":"ttm-cell-heading is-rail","layout":{"type":"default"}} -->
	<div class="wp-block-group ttm-cell-heading is-rail">
		<!-- wp:heading {"level":3,"className":"ttm-cell-heading__label","metadata":{"bindings":{"content":{"source":"ttm/section-label","args":{"format":"more-in"}}}}} -->
		<h3 class="wp-block-heading ttm-cell-heading__label"><?php esc_html_e( 'More in', 'ttm-theme' ); ?></h3>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"query":{"perPage":3,"postType":"post","inherit":false,"ttmSection":"","ttmExcludeLead":false,"ttmPrimaryOnly":true,"ttmSameSection":true,"ttmExcludeCurrent":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:group {"className":"ttm-item","layout":{"type":"default"}} -->
			<div class="wp-block-group ttm-item">
				<!-- wp:post-title {"isLink":true,"level":4} /-->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
