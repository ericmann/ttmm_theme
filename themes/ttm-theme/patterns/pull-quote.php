<?php
/**
 * Title: Pull Quote
 * Slug: ttm/pull-quote
 * Categories: ttm-article
 * Inserter: yes
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:quote {"className":"is-style-pull"} -->
<blockquote class="wp-block-quote is-style-pull">
	<!-- wp:paragraph -->
	<p><?php esc_html_e( 'Replace this with the quotation.', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->
	<cite><?php esc_html_e( 'Source', 'ttm-theme' ); ?></cite>
</blockquote>
<!-- /wp:quote -->
