<?php
/**
 * Title: Newsletter Box
 * Slug: ttm/newsletter-box
 * Categories: ttm-marketing
 * Inserter: yes
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"anchor":"newsletter","className":"is-style-surface-box ttm-newsletter-box","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-surface-box ttm-newsletter-box" id="newsletter">
	<!-- wp:paragraph {"className":"ttm-newsletter-box__title","metadata":{"bindings":{"content":{"source":"ttm/newsletter-copy"}}}} -->
	<p class="ttm-newsletter-box__title"><?php esc_html_e( 'New posts by email.', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"className":"ttm-newsletter-box__copy"} -->
	<p class="ttm-newsletter-box__copy"><?php esc_html_e( 'Each post by email as it publishes, or as a daily or weekly digest.', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:ttm/newsletter-form {"placement":"box"} /-->
</div>
<!-- /wp:group -->
