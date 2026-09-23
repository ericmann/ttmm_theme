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
		add_action( 'set_object_terms', [ self::class, 'on_set_object_terms' ], 10, 6 );
	}

	/**
	 * Flush the affected category's stats/top-tags transients when a post's terms change
	 * (SPEC §4, Decision "Stats invalidation"). `post_tag` changes flush every category the
	 * post belongs to (its top tags moved); `category` changes flush both the categories it
	 * is leaving (`$old_tt_ids`) and the ones it is joining (`$tt_ids`).
	 *
	 * @param int      $object_id  Post id.
	 * @param string[] $terms      Term slugs or ids as passed to `wp_set_object_terms()`.
	 * @param int[]    $tt_ids     Term taxonomy ids now set.
	 * @param string   $taxonomy   Taxonomy slug.
	 * @param bool     $append     Whether terms were appended.
	 * @param int[]    $old_tt_ids Term taxonomy ids that were set before this change.
	 */
	public static function on_set_object_terms( int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids ): void {
		if ( 'post_tag' === $taxonomy ) {
			self::flush_for_post( $object_id );
			return;
		}

		if ( 'category' !== $taxonomy ) {
			return;
		}

		foreach ( array_unique( array_map( 'intval', array_merge( $tt_ids, $old_tt_ids ) ) ) as $tt_id ) {
			$term_id = self::term_id_for_term_taxonomy_id( $tt_id );
			if ( $term_id > 0 ) {
				self::flush( $term_id );
			}
		}
	}

	/**
	 * A category's `term_id` from its `term_taxonomy_id` (the ids `set_object_terms` passes),
	 * which are equal for non-shared taxonomies but not guaranteed to be.
	 *
	 * @param int $term_taxonomy_id Term taxonomy id.
	 * @return int Term id, or 0 if not found.
	 */
	private static function term_id_for_term_taxonomy_id( int $term_taxonomy_id ): int {
		global $wpdb;

		$term_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT term_id FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id = %d AND taxonomy = 'category'",
				$term_taxonomy_id
			)
		);

		return (int) $term_id;
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
	 * `{count, first_year, last_year, series_count, newest_date}` for a category, cached.
	 *
	 * `newest_date` (the newest publish-status post's `post_date`, or null with zero posts) is
	 * what `Query\Cells::is_stale_year()` reads (F9) so no request-time `WP_Query` runs there --
	 * this transient, flushed on the same `transition_post_status` hook, is the single source.
	 *
	 * @param int $term_id Category term id.
	 * @return array{count:int, first_year:int, last_year:int, series_count:int, newest_date:?string}
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

		$last_post = ! empty( $last ) ? get_post( $last[0] ) : null;

		$first_year  = ! empty( $first ) ? (int) Clock::at( get_post( $first[0] )->post_date )->format( 'Y' ) : 0;
		$last_year   = $last_post ? (int) Clock::at( $last_post->post_date )->format( 'Y' ) : 0;
		$newest_date = $last_post ? $last_post->post_date : null;

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
			'newest_date'  => $newest_date,
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
				ORDER BY cnt DESC, t.slug ASC
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

		// Rule 24 / SPEC §4: an empty result (no tagged posts yet) is cached for the shorter
		// stats.cache_seconds so a newly tagged post shows up sooner than the full
		// stats.tags_cache_seconds window a populated result gets.
		$ttl = [] === $tags
			? (int) Config::get( 'stats.cache_seconds', 3600 )
			: (int) Config::get( 'stats.tags_cache_seconds', 43200 );

		set_transient( $key, $tags, $ttl );

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

	/**
	 * Delete every `ttm_category_stats_*`/`ttm_top_tags_*` transient outright (SPEC §4,
	 * Decision "Stats invalidation"), used by the seeder before a fresh run/reset and by the
	 * `wp ttm stats:flush` CLI command (P2-01). There is no bulk `delete_transient()` by
	 * pattern, so the value rows (never the `_transient_timeout_*` rows -- the LIKE pattern
	 * below doesn't match their name) are found by a direct `$wpdb->options` query and each
	 * key is then deleted through `delete_transient()`, which removes both the value and
	 * timeout options through core's normal option-cache invalidation (an `$wpdb->query()`
	 * `DELETE` alone would leave a stale `alloptions` cache entry behind).
	 */
	public static function flush_all(): void {
		global $wpdb;

		$names = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_ttm\\_category\\_stats\\_%' OR option_name LIKE '\\_transient\\_ttm\\_top\\_tags\\_%'"
		);

		foreach ( (array) $names as $name ) {
			delete_transient( substr( (string) $name, strlen( '_transient_' ) ) );
		}
	}
}
