<?php
/**
 * Title: Newsletter Poster
 * Slug: ttm/newsletter-poster
 * Categories: ttm-marketing
 * Inserter: yes
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-poster ttm-poster","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-poster ttm-poster">
	<!-- wp:heading {"level":3,"className":"is-style-poster"} -->
	<h3 class="is-style-poster"><?php esc_html_e( 'Everything above, once a week, in your inbox.', 'ttm-theme' ); ?></h3>
	<!-- /wp:heading -->

	<!-- wp:ttm/newsletter-form {"placement":"poster"} /-->
</div>
<!-- /wp:group -->
