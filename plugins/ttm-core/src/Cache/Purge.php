<?php
/**
 * Collects the affected URLs when a post enters, leaves or changes while in `publish`, and fires
 * `ttm_purge_urls`.
 *
 * @package TTM\Core\Cache
 */

declare( strict_types=1 );

namespace TTM\Core\Cache;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;
use WP_Post;

/**
 * `wp_after_insert_post` -> `ttm_purge_urls` (DEPLOYMENT.md §1), and a published comment's
 * post page. The verse, settings, books and series-index modules already fire the action
 * themselves; this only covers post and comment writes.
 *
 * Two things decide the hooks. The block editor saves the post (status included) and only then
 * writes its terms and meta, so `transition_post_status` would collect yesterday's sections;
 * `wp_after_insert_post` runs after both, in the classic, REST and cron (`wp_publish_post()`)
 * paths. And once a post is trashed or unpublished `get_permalink()` answers `?p=N`, so the
 * pretty URL it was served at is remembered on `wp_trash_post`/`pre_post_update`, while it is
 * still published.
 */
class Purge {

	/**
	 * Pretty permalinks of published posts about to be updated, by post id.
	 *
	 * @var array<int, string>
	 */
	private static array $before = [];

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'wp_trash_post', [ self::class, 'remember_permalink' ] );
		add_action( 'pre_post_update', [ self::class, 'remember_permalink' ] );
		add_action( 'wp_after_insert_post', [ self::class, 'on_saved' ], 20, 4 );
		add_action( 'before_delete_post', [ self::class, 'on_delete' ], 20, 2 );
		add_action( 'transition_comment_status', [ self::class, 'on_comment_transition' ], 20, 3 );
		add_action( 'comment_post', [ self::class, 'on_comment_post' ], 20, 2 );
		add_action( 'edit_comment', [ self::class, 'on_comment_edit' ], 20 );
		add_filter( 'comment_post_redirect', [ self::class, 'comment_redirect' ], 10, 2 );
	}

	/**
	 * A comment entering or leaving `approved` changes what its post page shows.
	 *
	 * @param string      $new_status New status.
	 * @param string      $old_status Old status.
	 * @param \WP_Comment $comment    Comment.
	 */
	public static function on_comment_transition( string $new_status, string $old_status, $comment ): void {
		if ( 'approved' === $new_status || 'approved' === $old_status ) {
			self::purge_comment_post( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * A comment published straight away (a trusted commenter, or the author replying).
	 *
	 * @param int        $comment_id Comment id.
	 * @param int|string $approved   1, 0, 'spam' or 'trash'.
	 */
	public static function on_comment_post( int $comment_id, $approved ): void {
		if ( 1 === $approved || '1' === $approved ) {
			$comment = get_comment( $comment_id );
			if ( $comment ) {
				self::purge_comment_post( (int) $comment->comment_post_ID );
			}
		}
	}

	/**
	 * An approved comment edited from the dashboard.
	 *
	 * @param int $comment_id Comment id.
	 */
	public static function on_comment_edit( int $comment_id ): void {
		$comment = get_comment( $comment_id );
		if ( $comment && '1' === $comment->comment_approved ) {
			self::purge_comment_post( (int) $comment->comment_post_ID );
		}
	}

	/**
	 * `comment_post_redirect`: a commenter whose comment published at once is sent to a URL the
	 * edge has never seen (`?c=<id>`), so their first view is rendered fresh rather than served
	 * from the copy the purge is still replacing. A held comment already goes to `?unapproved=`,
	 * which is never cached.
	 *
	 * @param string      $location Redirect target, usually `permalink#comment-N`.
	 * @param \WP_Comment $comment  The comment just posted.
	 * @return string
	 */
	public static function comment_redirect( string $location, $comment ): string {
		if ( ! $comment || '1' !== (string) $comment->comment_approved ) {
			return $location;
		}

		return add_query_arg( 'c', (int) $comment->comment_ID, $location );
	}

	/**
	 * Purge a published post's page and its comment feed.
	 *
	 * @param int $post_id Post id.
	 */
	private static function purge_comment_post( int $post_id ): void {
		if ( ! $post_id || 'publish' !== get_post_status( $post_id ) ) {
			return;
		}

		do_action( 'ttm_purge_urls', self::for_comments( $post_id ) );
	}

	/**
	 * The URLs a comment change touches: the post page and its comment feed.
	 *
	 * @param int $post_id Post id.
	 * @return string[]
	 */
	public static function for_comments( int $post_id ): array {
		return array_values( array_filter( [ (string) get_permalink( $post_id ), (string) get_post_comments_feed_link( $post_id ) ] ) );
	}

	/**
	 * `wp_trash_post` and `pre_post_update`: keep the pretty URL of a published post before the
	 * update that may trash, unpublish or rename it. Trashing fires both, and core has already
	 * written the `__trashed` slug by the second, so the first value kept wins.
	 *
	 * @param int $post_id Post id.
	 */
	public static function remember_permalink( int $post_id ): void {
		if ( isset( self::$before[ $post_id ] ) ) {
			return;
		}

		if ( 'post' === get_post_type( $post_id ) && 'publish' === get_post_status( $post_id ) ) {
			self::$before[ $post_id ] = (string) get_permalink( $post_id );
		}
	}

	/**
	 * `wp_after_insert_post`: fire `ttm_purge_urls` when a post is, or was, published.
	 *
	 * @param int          $post_id     Post id.
	 * @param WP_Post      $post        Post as saved.
	 * @param bool         $update      Whether this was an update (unused: `$post_before` says).
	 * @param WP_Post|null $post_before The post before the update, null for a new one.
	 */
	public static function on_saved( int $post_id, WP_Post $post, bool $update, $post_before ): void {
		unset( $update );

		$previous = self::$before[ $post_id ] ?? '';
		unset( self::$before[ $post_id ] );

		if ( 'post' !== $post->post_type ) {
			return;
		}

		$old = $post_before instanceof WP_Post ? $post_before->post_status : 'new';
		if ( 'publish' !== $post->post_status && 'publish' !== $old ) {
			return;
		}

		$urls = self::for_post( $post_id );
		if ( '' !== $previous ) {
			$urls[] = $previous;
		}

		do_action( 'ttm_purge_urls', array_values( array_unique( $urls ) ) );
	}

	/**
	 * `before_delete_post`: a published post deleted outright (no trash) leaves the same pages.
	 *
	 * @param int          $post_id Post id.
	 * @param WP_Post|null $post    The post being deleted.
	 */
	public static function on_delete( int $post_id, $post ): void {
		if ( $post instanceof WP_Post && 'post' === $post->post_type && 'publish' === $post->post_status ) {
			do_action( 'ttm_purge_urls', self::for_post( $post_id ) );
		}
	}

	/**
	 * The affected URL set for a post: front page, the post itself, every section archive it's
	 * in plus their feeds, its series archive and the hub (when in a series), `/writing/` (when
	 * in Writing or a fiction series), the main feed, and the `ttm/v1` `series`/`lead` routes.
	 *
	 * @param int $post_id Post id.
	 * @return string[]
	 */
	public static function for_post( int $post_id ): array {
		$urls = [
			home_url( '/' ),
			(string) get_permalink( $post_id ),
			home_url( '/feed/' ),
			rest_url( 'ttm/v1/series' ),
			rest_url( 'ttm/v1/lead' ),
		];

		foreach ( get_the_category( $post_id ) as $category ) {
			$link = get_category_link( $category );
			if ( is_string( $link ) ) {
				$urls[] = $link;
			}

			$feed = get_category_feed_link( $category->term_id );
			if ( is_string( $feed ) ) {
				$urls[] = $feed;
			}
		}

		$series            = SeriesIndex::for_post( $post_id );
		$is_fiction_series = $series && 'nonfiction' !== $series['form'];

		if ( $series ) {
			$urls[] = home_url( '/series/' . $series['slug'] . '/' );
			$urls[] = home_url( '/series/' );
		}

		$writing_slug = (string) Config::get( 'sections.writing_slug', 'writing' );
		if ( PrimaryCategory::slug( $post_id ) === $writing_slug || $is_fiction_series ) {
			$urls[] = home_url( '/writing/' );
		}

		return array_values( array_unique( $urls ) );
	}
}
