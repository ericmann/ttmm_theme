<?php
/**
 * Title: Journal Stream
 * Slug: ttm/journal-stream
 * Categories: ttm-article
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-journal-stream","layout":{"type":"constrained"}} -->
<div class="wp-block-group ttm-journal-stream">
	<!-- wp:group {"className":"ttm-cell-heading","layout":{"type":"flex","justifyContent":"space-between"}} -->
	<div class="wp-block-group ttm-cell-heading">
		<!-- wp:heading {"level":3,"className":"ttm-cell-heading__label"} -->
		<h3 class="ttm-cell-heading__label"><?php esc_html_e( 'Earlier', 'ttm-theme' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-cell-heading__link","metadata":{"bindings":{"content":{"source":"ttm/category-count","args":{"category":"journal","format":"short"}}}}} -->
		<p class="ttm-cell-heading__link"></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"namespace":"ttm/journal-stream-query","query":{"perPage":4,"postType":"post","inherit":false,"ttmSection":"journal","ttmExcludeLead":false,"ttmPrimaryOnly":true,"ttmExcludeCurrent":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:group {"className":"ttm-journal-row"} -->
			<div class="wp-block-group ttm-journal-row">
				<!-- wp:paragraph {"className":"is-style-journal-stream-date","metadata":{"bindings":{"content":{"source":"ttm/short-date"}}}} -->
				<p class="is-style-journal-stream-date"></p>
				<!-- /wp:paragraph -->

				<!-- wp:post-title {"isLink":true} /-->

				<!-- wp:post-excerpt /-->

				<!-- wp:paragraph {"className":"is-style-micro","metadata":{"bindings":{"content":{"source":"ttm/word-count"}}}} -->
				<p class="is-style-micro"></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
