<?php
/**
 * How a post's comments thread: articles hold a discussion, journal entries collect flat
 * reflections, fiction gathers shallower reader notes.
 *
 * @package TTM\Core\Comments
 */

declare( strict_types=1 );

namespace TTM\Core\Comments;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;

/**
 * Filters the threading options on a single post's page, from the post's section and form
 * (never its template: a Technology post on the journal layout still holds a discussion). The
 * answer depends only on the post, so the cached page is the same for every reader.
 */
class Threading {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'option_thread_comments', [ self::class, 'thread_comments' ] );
		add_filter( 'option_thread_comments_depth', [ self::class, 'thread_depth' ] );
		add_filter( 'option_comment_order', [ self::class, 'comment_order' ] );
	}

	/**
	 * Pure: `reflections` in the Journal section, `notes` on a story or chapter, else
	 * `discussion`. Journal wins over form.
	 *
	 * @param string $section_slug The post's primary category slug, or '' when none.
	 * @param string $form         The post's `ttm_form`.
	 */
	public static function kind( string $section_slug, string $form ): string {
		if ( '' !== $section_slug && (string) Config::get( 'sections.journal_slug', 'journal' ) === $section_slug ) {
			return 'reflections';
		}

		return in_array( $form, [ 'story', 'chapter' ], true ) ? 'notes' : 'discussion';
	}

	/**
	 * The kind of a post's comments.
	 *
	 * @param int $post_id Post id.
	 */
	public static function kind_of( int $post_id ): string {
		return self::kind( (string) PrimaryCategory::slug( $post_id ), (string) get_post_meta( $post_id, 'ttm_form', true ) );
	}

	/**
	 * Pure: reply depth for a kind (1 = no replies).
	 *
	 * @param string $kind A kind() value.
	 */
	public static function depth( string $kind ): int {
		if ( 'reflections' === $kind ) {
			return 1;
		}

		return 'notes' === $kind
			? (int) Config::get( 'comments.fiction_depth', 2 )
			: (int) Config::get( 'comments.depth', 3 );
	}

	/**
	 * The single post being viewed, or 0 anywhere else (admin, archives, REST).
	 */
	private static function viewed_post(): int {
		if ( is_admin() || ! did_action( 'wp' ) || ! is_singular( 'post' ) ) {
			return 0;
		}

		return (int) get_queried_object_id();
	}

	/**
	 * `thread_comments`: off on a journal entry.
	 *
	 * @param mixed $value Stored option.
	 * @return mixed
	 */
	public static function thread_comments( $value ) {
		$post_id = self::viewed_post();

		return $post_id && 1 === self::depth( self::kind_of( $post_id ) ) ? '0' : $value;
	}

	/**
	 * `thread_comments_depth`: the kind's depth.
	 *
	 * @param mixed $value Stored option.
	 * @return mixed
	 */
	public static function thread_depth( $value ) {
		$post_id = self::viewed_post();

		return $post_id ? (string) self::depth( self::kind_of( $post_id ) ) : $value;
	}

	/**
	 * `comment_order`: newest first on a journal entry, where nobody is answering anybody.
	 *
	 * @param mixed $value Stored option.
	 * @return mixed
	 */
	public static function comment_order( $value ) {
		$post_id = self::viewed_post();

		return $post_id && 'reflections' === self::kind_of( $post_id ) ? 'desc' : $value;
	}
}
