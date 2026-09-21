<?php
/**
 * Title: Code Figure
 * Slug: ttm/code-figure
 * Categories: ttm-article
 * Inserter: yes
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"tagName":"figure","layout":{"type":"constrained"}} -->
<figure class="wp-block-group">
	<!-- wp:code -->
	<pre class="wp-block-code"><code></code></pre>
	<!-- /wp:code -->

	<!-- wp:paragraph {"className":"is-style-meta"} -->
	<p class="is-style-meta"><?php esc_html_e( 'Caption', 'ttm-theme' ); ?></p>
	<!-- /wp:paragraph -->
</figure>
<!-- /wp:group -->
