<?php
/**
 * Title: Journal Stream
 * Slug: ttm/journal-stream
 * Categories: ttm-article
 * Inserter: no
 *
 * SPEC §6.4 "stream": four earlier journal entries as whole-row links (`Blocks\Helpers::link_rows()`
 * turns each `.ttm-journal-row` group into the row's single anchor; the title comes first in the
 * DOM for the accessible name, the date is placed visually by CSS -- rule 33).
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-journal-stream","layout":{"type":"default"}} -->
<div class="wp-block-group ttm-journal-stream">
	<!-- wp:group {"className":"ttm-cell-heading","layout":{"type":"default"}} -->
	<div class="wp-block-group ttm-cell-heading">
		<!-- wp:heading {"level":3,"className":"ttm-cell-heading__label"} -->
		<h3 class="wp-block-heading ttm-cell-heading__label"><?php esc_html_e( 'Earlier', 'ttm-theme' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-cell-heading__link","metadata":{"bindings":{"content":{"source":"ttm/category-count","args":{"category":"journal","format":"journal-full"}}}}} -->
		<p class="ttm-cell-heading__link"></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"namespace":"ttm/journal-stream-query","query":{"perPage":4,"postType":"post","inherit":false,"ttmSection":"journal","ttmExcludeLead":false,"ttmPrimaryOnly":true,"ttmExcludeCurrent":true}} -->
	<div class="wp-block-query">
		<!-- wp:post-template -->
			<!-- wp:group {"className":"ttm-journal-row","layout":{"type":"default"}} -->
			<div class="wp-block-group ttm-journal-row">
				<!-- wp:post-title {"isLink":false,"level":3} /-->

				<!-- wp:paragraph {"className":"is-style-journal-stream-date","metadata":{"bindings":{"content":{"source":"ttm/short-date"}}}} -->
				<p class="is-style-journal-stream-date"></p>
				<!-- /wp:paragraph -->

				<!-- wp:post-excerpt /-->

				<!-- wp:paragraph {"className":"ttm-journal-row__words","metadata":{"bindings":{"content":{"source":"ttm/word-count"}}}} -->
				<p class="ttm-journal-row__words"></p>
				<!-- /wp:paragraph -->
			</div>
			<!-- /wp:group -->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->
</div>
<!-- /wp:group -->
