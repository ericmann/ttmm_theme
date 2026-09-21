<?php
/**
 * Title: Article Header
 * Slug: ttm/article-header
 * Categories: ttm-article
 * Inserter: no
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:group {"className":"ttm-article-head","layout":{"type":"constrained"}} -->
<div class="wp-block-group ttm-article-head">
	<!-- wp:post-terms {"term":"category","className":"is-style-kicker"} /-->

	<!-- wp:post-title {"level":1} /-->

	<!-- wp:post-excerpt {"className":"is-style-dek-l"} /-->

	<!-- wp:group {"className":"ttm-byline","layout":{"type":"flex"}} -->
	<div class="wp-block-group ttm-byline">
		<!-- wp:post-author-name {"prefix":"By "} /-->

		<!-- wp:post-date {"format":"F j, Y"} /-->

		<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"ttm/reading-time","args":{"format":"long"}}}}} -->
		<p></p>
		<!-- /wp:paragraph -->

		<!-- wp:post-terms {"term":"post_tag","className":"is-style-tags"} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->
