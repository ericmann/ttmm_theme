<?php
/**
 * Word count computation on save (SPEC §5.2).
 *
 * @package TTM\Core\Meta
 */

declare( strict_types=1 );

namespace TTM\Core\Meta;

use TTM\Core\Support\Text;
use WP_Post;

/**
 * Writes ttm_word_count on every save_post_post.
 */
class WordCount {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'save_post_post', [ self::class, 'on_save' ], 30, 2 );
	}

	/**
	 * Compute and store the word count, skipping autosaves/revisions.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, 'ttm_word_count', Text::word_count( $post->post_content ) );
	}
}
