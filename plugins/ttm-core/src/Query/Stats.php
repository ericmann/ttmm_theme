<?php
/**
 * Cached per-category statistics and top tags (SPEC §5.3).
 *
 * @package TTM\Core\Query
 */

declare( strict_types=1 );

namespace TTM\Core\Query;

use TTM\Core\Config;
use TTM\Core\Support\Clock;
use WP_Post;

/**
 * Reads/caches ttm_category_stats_{id} and ttm_top_tags_{id} transients.
 */
class Stats {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'transition_post_status', [ self::class, 'on_transition' ], 10, 3 );
	}

	/**
	 * Flush caches when a post enters or leaves `publish`.
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

		self::flush_for_post( $post->ID );
	}

	/**
	 * `{count, first_year, last_year, series_count}` for a category, cached.
	 *
	 * @param int $term_id Category term id.
	 * @return array{count:int, first_year:int, last_year:int, series_count:int}
	 */
	public static function category( int $term_id ): array {
		$key    = "ttm_category_stats_{$term_id}";
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		// WordPress maintains this count automatically (publish-only, for public post types); no query needed.
		$category = get_term( $term_id, 'category' );
		$count    = $category && ! is_wp_error( $category ) ? (int) $category->count : 0;

		$first = get_posts(
			[
				'category'       => $term_id,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'ASC',
			]
		);
		$last  = get_posts(
			[
				'category'       => $term_id,
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'orderby'        => 'date',
				'order'          => 'DESC',
			]
		);

		$first_year = ! empty( $first ) ? (int) Clock::at( get_post( $first[0] )->post_date )->format( 'Y' ) : 0;
		$last_year  = ! empty( $last ) ? (int) Clock::at( get_post( $last[0] )->post_date )->format( 'Y' ) : 0;

		$series_count = 0;
		foreach ( SeriesIndex::all() as $row ) {
			if ( in_array( $term_id, $row['categories'], true ) ) {
				++$series_count;
			}
		}

		$stats = [
			'count'        => $count,
			'first_year'   => $first_year,
			'last_year'    => $last_year,
			'series_count' => $series_count,
		];

		set_transient( $key, $stats, (int) Config::get( 'stats.cache_seconds', 3600 ) );

		return $stats;
	}

	/**
	 * Top tags for posts in a category, cached.
	 *
	 * @param int $term_id Category term id.
	 * @return array<int, array{term_id:int, slug:string, name:string, count:int}>
	 */
	public static function top_tags( int $term_id ): array {
		global $wpdb;

		$key    = "ttm_top_tags_{$term_id}";
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$limit = (int) Config::get( 'archive.tag_filter_limit', 5 );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.term_id, t.slug, t.name, COUNT(*) AS cnt
				FROM {$wpdb->term_relationships} tr
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'post_tag'
				INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				WHERE tr.object_id IN (
					SELECT tr2.object_id FROM {$wpdb->term_relationships} tr2
					INNER JOIN {$wpdb->term_taxonomy} tt2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id AND tt2.taxonomy = 'category'
					WHERE tt2.term_id = %d
				)
				AND tr.object_id IN ( SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' )
				GROUP BY t.term_id, t.slug, t.name
				ORDER BY cnt DESC
				LIMIT %d",
				$term_id,
				$limit
			),
			ARRAY_A
		);

		$tags = array_map(
			static fn ( array $row ): array => [
				'term_id' => (int) $row['term_id'],
				'slug'    => $row['slug'],
				'name'    => $row['name'],
				'count'   => (int) $row['cnt'],
			],
			(array) $rows
		);

		set_transient( $key, $tags, (int) Config::get( 'stats.tags_cache_seconds', 43200 ) );

		return $tags;
	}

	/**
	 * Delete both transients for a category.
	 *
	 * @param int $term_id Category term id.
	 */
	public static function flush( int $term_id ): void {
		delete_transient( "ttm_category_stats_{$term_id}" );
		delete_transient( "ttm_top_tags_{$term_id}" );
	}

	/**
	 * Flush every category a post belongs to.
	 *
	 * @param int $post_id Post id.
	 */
	public static function flush_for_post( int $post_id ): void {
		foreach ( wp_get_post_categories( $post_id ) as $term_id ) {
			self::flush( (int) $term_id );
		}
	}
}
