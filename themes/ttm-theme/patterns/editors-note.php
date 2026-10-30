<?php
/**
 * Title: Editor's note
 * Slug: ttm/editors-note
 * Categories: ttm-article
 * Keywords: note, disclaimer, callout, context
 * Description: A shaded note for the top of an older post: context such as a retired plugin, a dead service or a changed opinion.
 * Post Types: post, page
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"is-style-note","layout":{"type":"default"}} -->
<div class="wp-block-group is-style-note">
	<!-- wp:paragraph {"className":"is-style-kicker"} -->
	<p class="is-style-kicker"><?php esc_html_e( 'Editor’s note', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->

	<!-- wp:paragraph {"placeholder":"<?php echo esc_attr__( 'What a reader should know before reading this post…', 'ttm-theme' ); ?>"} -->
	<p></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
