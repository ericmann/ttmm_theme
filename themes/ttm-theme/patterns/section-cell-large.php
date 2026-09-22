<?php
/**
 * Title: Section Cell — Large (Technology)
 * Slug: ttm/section-cell-large
 * Categories: ttm-front
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-cell is-style-span-2 ttm-cell"} -->
<div class="wp-block-group is-style-cell is-style-span-2 ttm-cell">
	<!-- wp:group {"className":"ttm-cell-heading","layout":{"type":"flex","justifyContent":"space-between"}} -->
	<div class="wp-block-group ttm-cell-heading">
		<!-- wp:heading {"level":6,"className":"ttm-cell-heading__label"} -->
		<h6 class="ttm-cell-heading__label"><?php esc_html_e( 'Technology', 'ttm-theme' ); ?></h6>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-cell-heading__link","metadata":{"bindings":{"content":{"source":"ttm/category-count","args":{"category":"technology","format":"articles"}}}}} -->
		<p class="ttm-cell-heading__link"></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"namespace":"ttm/section-query","query":{"perPage":3,"postType":"post","inherit":false,"ttmSection":"technology","ttmExcludeLead":true,"ttmPrimaryOnly":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:group {"className":"ttm-item"} -->
			<div class="wp-block-group ttm-item">
				<!-- wp:post-featured-image {"isLink":true,"className":"ttm-item-featured__media","sizeSlug":"ttm-thumb"} /-->

				<!-- wp:post-title {"isLink":true,"className":"is-style-cell-lead-l"} /-->

				<!-- wp:post-excerpt {"className":"ttm-item__dek"} /-->

				<!-- wp:paragraph {"className":"ttm-item__meta","metadata":{"bindings":{"content":{"source":"ttm/meta-line","args":{"parts":["date","reading"],"readingFormat":"short"}}}}} -->
				<p class="ttm-item__meta"></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
