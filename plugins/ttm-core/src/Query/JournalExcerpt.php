<?php
/**
 * Derived excerpt for Journal posts without a manual one (03 §9).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Support\Text;
use WP_Post;

/**
 * A Journal post with no `post_excerpt` gets a sentence-trimmed excerpt of its content,
 * without core's "[…]" `excerpt_more` marker.
 */
class JournalExcerpt {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'get_the_excerpt', [ self::class, 'filter_excerpt' ], 5, 2 );
		add_filter( 'excerpt_more', [ self::class, 'filter_excerpt_more' ] );
	}

	/**
	 * `get_the_excerpt`: derive one for a Journal post with an empty `post_excerpt`.
	 *
	 * @param string  $excerpt Existing excerpt.
	 * @param WP_Post $post    Post object.
	 * @return string
	 */
	public static function filter_excerpt( string $excerpt, $post ): string {
		if ( ! $post instanceof WP_Post || '' !== $post->post_excerpt ) {
			return $excerpt;
		}

		if ( ! self::is_journal( $post ) ) {
			return $excerpt;
		}

		$content = wp_strip_all_tags( strip_shortcodes( $post->post_content ) );

		return Text::sentence_excerpt(
			$content,
			(int) Config::get( 'journal.excerpt_words', 40 ),
			(int) Config::get( 'journal.excerpt_max_words', 55 )
		);
	}

	/**
	 * `excerpt_more`: no "[…]" marker for Journal posts (their excerpt is already
	 * sentence-trimmed, not truncated mid-word).
	 *
	 * @param string $more Existing marker.
	 * @return string
	 */
	public static function filter_excerpt_more( string $more ): string {
		$post = get_post();

		if ( $post instanceof WP_Post && self::is_journal( $post ) ) {
			return '';
		}

		return $more;
	}

	/**
	 * Whether a post's primary category is Journal.
	 *
	 * @param WP_Post $post Post object.
	 * @return bool
	 */
	private static function is_journal( WP_Post $post ): bool {
		$category_id = PrimaryCategory::id( $post->ID );
		if ( ! $category_id ) {
			return false;
		}

		$category = get_term( $category_id, 'category' );

		return $category && ! is_wp_error( $category ) && (string) Config::get( 'sections.journal_slug', 'journal' ) === $category->slug;
	}
}
