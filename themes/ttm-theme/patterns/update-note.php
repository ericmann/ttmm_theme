<?php
/**
 * Title: Update
 * Slug: ttm/update-note
 * Categories: ttm-article
 * Keywords: update, correction, disclaimer, callout
 * Description: An accent-ruled update for a point-in-time post that needs a correction or a critical change.
 * Post Types: post, page
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-update","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-update">
	<!-- wp:paragraph {"className":"is-style-kicker"} -->
	<p class="is-style-kicker"><?php esc_html_e( 'Update', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"placeholder":"<?php echo esc_attr__( 'What changed, and when…', 'ttm-theme' ); ?>"} -->
	<p></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
