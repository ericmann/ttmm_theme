<?php
/**
 * Title: Series Strip
 * Slug: ttm/series-strip
 * Categories: ttm-front
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-series-strip","layout":{"type":"default"}} -->
<div class="wp-block-group ttm-series-strip">
	<!-- wp:group {"className":"ttm-cell-heading","layout":{"type":"default"}} -->
	<div class="wp-block-group ttm-cell-heading">
		<!-- wp:heading {"level":3,"className":"ttm-cell-heading__label ttm-series-strip__heading"} -->
		<h3 class="ttm-cell-heading__label ttm-series-strip__heading"><?php esc_html_e( 'Series in progress', 'ttm-theme' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:heading {"level":3,"className":"ttm-cell-heading__label ttm-series-strip__heading-fallback"} -->
		<h3 class="ttm-cell-heading__label ttm-series-strip__heading-fallback"><?php esc_html_e( 'Series', 'ttm-theme' ); ?></h3>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-cell-heading__link"} -->
		<p class="ttm-cell-heading__link"><a href="<?php echo esc_url( home_url( '/series/' ) ); ?>"><?php esc_html_e( 'All series', 'ttm-theme' ); ?></a></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->

	<!-- wp:ttm/series-list {"status":"in-progress","limit":3,"layout":"grid-3"} /-->
</div>
<!-- /wp:group -->
