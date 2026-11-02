<?php
/**
 * Title: Comments
 * Slug: ttm/comments
 * Categories: ttm-article
 * Inserter: no
 *
 * One section for every post. Its words come from the post's section (ttm/comments-label):
 * a Discussion on articles, Reflections on journal entries, Reader notes on fiction. The body
 * class ttm-comments-{kind} styles it, and assets/js/comments.js keeps the form behind the
 * button until someone asks for it.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

?>
<!-- wp:comments {"anchor":"comments","tagName":"section","className":"ttm-comments"} -->
<section id="comments" class="wp-block-comments ttm-comments">
	<!-- wp:group {"className":"ttm-comments__head","layout":{"type":"default"}} -->
	<div class="wp-block-group ttm-comments__head">
		<!-- wp:heading {"className":"ttm-comments__title","metadata":{"bindings":{"content":{"source":"ttm/comments-label","args":{"part":"title"}}}}} -->
		<h2 class="wp-block-heading ttm-comments__title"><?php esc_html_e( 'Discussion', 'ttm-theme' ); ?></h2>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"className":"ttm-comments__count","metadata":{"bindings":{"content":{"source":"ttm/comments-label","args":{"part":"count"}}}}} -->
		<p class="ttm-comments__count"></p>
		<!-- /wp:paragraph -->

		<!-- wp:buttons {"className":"ttm-comments__cta"} -->
		<div class="wp-block-buttons ttm-comments__cta">
			<!-- wp:button {"className":"is-style-secondary ttm-comments__open","metadata":{"bindings":{"text":{"source":"ttm/comments-label","args":{"part":"cta"}}}}} -->
			<div class="wp-block-button is-style-secondary ttm-comments__open"><a class="wp-block-button__link wp-element-button" href="#respond"><?php esc_html_e( 'Join the discussion', 'ttm-theme' ); ?></a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->

	<!-- wp:group {"className":"ttm-comments__body","layout":{"type":"default"}} -->
	<div class="wp-block-group ttm-comments__body">
		<!-- wp:post-comments-form /-->

		<!-- wp:paragraph {"className":"ttm-comments__note","metadata":{"bindings":{"content":{"source":"ttm/comments-label","args":{"part":"note"}}}}} -->
		<p class="ttm-comments__note"></p>
		<!-- /wp:paragraph -->

		<!-- wp:comment-template {"className":"ttm-comments__list"} -->
		<!-- wp:group {"className":"ttm-comment","layout":{"type":"default"}} -->
		<div class="wp-block-group ttm-comment">
			<!-- wp:group {"className":"ttm-comment__meta","layout":{"type":"default"}} -->
			<div class="wp-block-group ttm-comment__meta">
				<!-- wp:comment-author-name {"isLink":false,"className":"ttm-comment__name"} /-->

				<!-- wp:comment-date {"format":"F j, Y","className":"ttm-comment__date"} /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:comment-content {"className":"ttm-comment__body"} /-->

			<!-- wp:comment-reply-link {"className":"ttm-comment__reply"} /-->
		</div>
		<!-- /wp:group -->
		<!-- /wp:comment-template -->
	</div>
	<!-- /wp:group -->
</section>
<!-- /wp:comments -->
