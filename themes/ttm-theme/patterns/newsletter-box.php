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
<!-- wp:group {"anchor":"newsletter","className":"is-style-surface-box ttm-newsletter-box","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-surface-box ttm-newsletter-box" id="newsletter">
	<!-- wp:paragraph {"className":"ttm-newsletter-box__title"} -->
	<p class="ttm-newsletter-box__title"><?php esc_html_e( 'The weekly issue.', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"className":"ttm-newsletter-box__copy"} -->
	<p class="ttm-newsletter-box__copy"><?php esc_html_e( 'Series land in the newsletter the week they publish.', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:ttm/newsletter-form {"placement":"box"} /-->
</div>
<!-- /wp:group -->
