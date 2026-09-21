<?php
/**
 * Collects the affected URLs on a post publish/unpublish transition and fires `ttm_purge_urls`.
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
 * `transition_post_status` -> `ttm_purge_urls` (DEPLOYMENT.md §1). The verse, settings, books
 * and series-index modules already fire the action themselves; this only covers post writes.
 */
class Purge {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'transition_post_status', [ self::class, 'on_transition' ], 20, 3 );
	}

	/**
	 * Fire `ttm_purge_urls` when a post enters or leaves `publish`.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post object.
	 */
	public static function on_transition( string $new_status, string $old_status, WP_Post $post ): void {
		if ( 'post' !== $post->post_type ) {
			return;
		}

		if ( 'publish' !== $new_status && 'publish' !== $old_status ) {
			return;
		}

		do_action( 'ttm_purge_urls', self::for_post( $post->ID ) );
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

		$series          = SeriesIndex::for_post( $post_id );
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
