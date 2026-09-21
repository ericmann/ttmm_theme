<?php
/**
 * Title: Journal Rail
 * Slug: ttm/journal-rail
 * Categories: ttm-front
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-journal-rail","layout":{"type":"constrained"}} -->
<div class="wp-block-group ttm-journal-rail">
	<!-- wp:group {"className":"ttm-cell-heading is-rail","layout":{"type":"flex","justifyContent":"space-between"}} -->
	<div class="wp-block-group ttm-cell-heading is-rail">
		<!-- wp:heading {"level":3,"className":"ttm-cell-heading__label"} -->
		<h3 class="ttm-cell-heading__label"><?php esc_html_e( 'Journal', 'ttm-theme' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-cell-heading__link","metadata":{"bindings":{"content":{"source":"ttm/category-count","args":{"category":"journal","format":"short"}}}}} -->
		<p class="ttm-cell-heading__link"></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"namespace":"ttm/journal-query","query":{"perPage":3,"postType":"post","inherit":false,"ttmSection":"journal","ttmExcludeLead":false,"ttmPrimaryOnly":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:paragraph {"className":"is-style-micro ttm-journal-excerpt__date","metadata":{"bindings":{"content":{"source":"ttm/relative-date"}}}} -->
			<p class="is-style-micro ttm-journal-excerpt__date"></p>
			<!-- /wp:paragraph -->

			<!-- wp:post-title {"level":4,"isLink":true} /-->

			<!-- wp:post-excerpt {"excerptLength":40,"moreText":"","className":"ttm-item__dek"} /-->

			<!-- wp:read-more {"content":"<?php echo esc_attr__( 'Continue →', 'ttm-theme' ); ?>"} /-->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
