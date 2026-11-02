<?php
/**
 * The comments section's form and comment chrome (patterns/comments.php). Presentation only:
 * how threads nest and who skips moderation live in ttm-core (Comments\Threading, Moderation).
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * A quiet form: no "Leave a Reply" banner (the section's button already says what it is), no
 * website field, "Replying to Dana · Cancel" when it moves under a comment.
 *
 * @param array<string, mixed> $defaults comment_form() defaults.
 * @return array<string, mixed>
 */
function comment_form_defaults( array $defaults ): array {
	$defaults['title_reply'] = __( 'Your comment', 'ttm-theme' );
	/* translators: %s: name of the person being replied to. */
	$defaults['title_reply_to']       = __( 'Replying to %s', 'ttm-theme' );
	$defaults['title_reply_before']   = '<p id="reply-title" class="comment-reply-title">';
	$defaults['title_reply_after']    = '</p>';
	$defaults['cancel_reply_before']  = ' <span class="ttm-comments__cancel">';
	$defaults['cancel_reply_after']   = '</span>';
	$defaults['cancel_reply_link']    = __( 'Cancel', 'ttm-theme' );
	$defaults['comment_notes_before'] = '<p class="comment-notes">' . esc_html__( 'Your email address is never shown.', 'ttm-theme' ) . '</p>';
	$defaults['label_submit']         = __( 'Post', 'ttm-theme' );
	$defaults['comment_field']        = sprintf(
		'<p class="comment-form-comment"><label for="comment">%s</label><textarea id="comment" name="comment" rows="5" maxlength="65525" required></textarea></p>',
		esc_html__( 'Comment', 'ttm-theme' )
	);
	unset( $defaults['fields']['url'] );

	return $defaults;
}

add_filter( 'comment_form_defaults', __NAMESPACE__ . '\\comment_form_defaults' );

/**
 * No website field, and the remember-me line says only what it keeps. The box starts unticked:
 * the page is cached, so it cannot know who has ticked it before.
 *
 * @param array<string, string> $fields Default fields.
 * @return array<string, string>
 */
function comment_form_fields( array $fields ): array {
	unset( $fields['url'] );
	if ( isset( $fields['cookies'] ) ) {
		$fields['cookies'] = sprintf(
			'<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes" /><label for="wp-comment-cookies-consent">%s</label></p>',
			esc_html__( 'Remember my name and email in this browser.', 'ttm-theme' )
		);
	}

	return $fields;
}

add_filter( 'comment_form_default_fields', __NAMESPACE__ . '\\comment_form_fields' );

/**
 * The reply link's label and accessible name.
 *
 * @param array<string, mixed> $args get_comment_reply_link() arguments.
 * @return array<string, mixed>
 */
function comment_reply_link_args( array $args ): array {
	$args['reply_text'] = __( 'Reply', 'ttm-theme' );
	// The link's accessible name, so it must contain the visible "Reply" (assets/js/comments.js
	// writes "Replying to …" into the form's heading).
	/* translators: %s: name of the person being replied to. */
	$args['reply_to_text'] = __( 'Reply to %s', 'ttm-theme' );

	return $args;
}

add_filter( 'comment_reply_link_args', __NAMESPACE__ . '\\comment_reply_link_args' );

/**
 * "Held for review" in place of core's "Your comment is awaiting moderation." Only the person
 * who wrote it sees a held comment, on the uncached ?unapproved= page core redirects them to.
 *
 * @param string $content Rendered core/comment-content.
 */
function held_for_review( string $content ): string {
	return (string) preg_replace(
		'#<p><em class="comment-awaiting-moderation">.*?</em></p>#s',
		'<p class="ttm-comment__held"><strong>' . esc_html__( 'Held for review.', 'ttm-theme' ) . '</strong> ' . esc_html__( 'Only you can see this until it is approved.', 'ttm-theme' ) . '</p>',
		$content,
		1
	);
}

add_filter( 'render_block_core/comment-content', __NAMESPACE__ . '\\held_for_review' );

/**
 * An "Author" tag beside the post author's own comments.
 *
 * @param string    $content Rendered core/comment-author-name.
 * @param array     $block   Parsed block.
 * @param \WP_Block $instance Block instance.
 */
function author_tag( string $content, array $block, $instance ): string {
	$comment = get_comment( (int) ( $instance->context['commentId'] ?? 0 ) );
	$post    = $comment ? get_post( (int) $comment->comment_post_ID ) : null;
	if ( ! $comment || ! $post || ! $comment->user_id || (int) $comment->user_id !== (int) $post->post_author ) {
		return $content;
	}

	return $content . '<span class="ttm-comment__tag">' . esc_html__( 'Author', 'ttm-theme' ) . '</span>';
}

add_filter( 'render_block_core/comment-author-name', __NAMESPACE__ . '\\author_tag', 10, 3 );

/**
 * Load assets/js/comments.js, with its words, only on pages that render the comments section.
 *
 * @param string $content Rendered core/comments.
 */
function enqueue_comments_js( string $content ): string {
	if ( '' === $content ) {
		return $content;
	}

	$path = get_template_directory() . '/assets/js/comments.js';
	wp_enqueue_script(
		'ttm-comments',
		get_template_directory_uri() . '/assets/js/comments.js',
		[],
		wp_get_theme()->get( 'Version' ) . '.' . filemtime( $path ),
		[
			'in_footer' => true,
			'strategy'  => 'defer',
		]
	);
	wp_add_inline_script(
		'ttm-comments',
		'window.ttmComments = ' . wp_json_encode(
			[
				'collapse'    => __( 'Collapse', 'ttm-theme' ),
				'show'        => __( 'Show', 'ttm-theme' ),
				/* translators: %d: number of replies. */
				'reply'       => __( '%d reply', 'ttm-theme' ),
				/* translators: %d: number of replies. */
				'replies'     => __( '%d replies', 'ttm-theme' ),
				'collapseAll' => __( 'Collapse all', 'ttm-theme' ),
				'expandAll'   => __( 'Expand all', 'ttm-theme' ),
				/* translators: %d: comments posted since the reader last opened the page. */
				'newSince'    => __( '%d new since your last visit', 'ttm-theme' ),
				'newTag'      => __( 'New', 'ttm-theme' ),
				/* translators: %d: number of reflections. */
				'showAll'     => __( 'Show all %d', 'ttm-theme' ),
				'readNotes'   => __( 'Read or leave a note', 'ttm-theme' ),
				'readClosed'  => __( 'Read the notes', 'ttm-theme' ),
				'close'       => __( 'Close', 'ttm-theme' ),
				/* translators: %s: name of the person being replied to. */
				'replyingTo'  => __( 'Replying to %s', 'ttm-theme' ),
			]
		) . ';',
		'before'
	);

	return $content;
}

add_filter( 'render_block_core/comments', __NAMESPACE__ . '\\enqueue_comments_js' );
