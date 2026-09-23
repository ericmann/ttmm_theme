<?php
/**
 * Integration tests for TTM\Core\Query\Stats.
 *
 * @package TTM\Tests\Integration\Query
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;
use TTM\Core\Query\Stats;

class StatsTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_category_stats_count_and_year_range(): void {
		$cat = $this->category_id( 'technology', 'Technology' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
				'post_date'     => '2020-01-01 12:00:00',
			]
		);
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
				'post_date'     => '2024-06-01 12:00:00',
			]
		);

		$stats = Stats::category( $cat );

		$this->assertSame( 2, $stats['count'] );
		$this->assertSame( 2020, $stats['first_year'] );
		$this->assertSame( 2024, $stats['last_year'] );
		$this->assertSame( '2024-06-01 12:00:00', $stats['newest_date'] );
	}

	public function test_newest_date_is_null_for_an_empty_category(): void {
		$cat = $this->category_id( 'empty-category', 'Empty Category' );

		$stats = Stats::category( $cat );

		$this->assertSame( 0, $stats['count'] );
		$this->assertNull( $stats['newest_date'] );
	}

	public function test_series_count_uses_index_categories(): void {
		$cat    = $this->category_id( 'business', 'Business' );
		$series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$post   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
			]
		);
		update_post_meta( $post, 'ttm_series_part', 1 );
		wp_set_object_terms( $post, [ $series ], 'series' );
		SeriesIndex::rebuild();

		$stats = Stats::category( $cat );

		$this->assertSame( 1, $stats['series_count'] );
	}

	public function test_top_tags_are_ordered_by_count_and_limited(): void {
		$cat = $this->category_id( 'faith', 'Faith' );

		for ( $i = 0; $i < 3; $i++ ) {
			self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $cat ],
					'tags_input'    => [ 'popular' ],
				]
			);
		}
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
				'tags_input'    => [ 'rare' ],
			]
		);

		$tags = Stats::top_tags( $cat );

		$this->assertSame( 'popular', $tags[0]['slug'] );
		$this->assertSame( 3, $tags[0]['count'] );
	}

	public function test_publish_flushes_both_transients(): void {
		$cat  = $this->category_id( 'opinion', 'Opinion' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
			]
		);
		Stats::category( $cat );
		Stats::top_tags( $cat );

		$this->assertNotFalse( get_transient( "ttm_category_stats_{$cat}" ) );

		wp_update_post(
			[
				'ID'          => $post,
				'post_status' => 'draft',
			] 
		);

		$this->assertFalse( get_transient( "ttm_category_stats_{$cat}" ) );
		$this->assertFalse( get_transient( "ttm_top_tags_{$cat}" ) );
	}

	public function test_stats_are_served_from_transient_on_second_call(): void {
		global $wpdb;

		$cat = $this->category_id( 'security', 'Security' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
			]
		);

		Stats::category( $cat );
		$queries_before = $wpdb->num_queries;
		Stats::category( $cat );

		$this->assertSame( $queries_before, $wpdb->num_queries );
	}

	/**
	 * P0-05: SPEC rule 24 -- ties are broken deterministically (count desc, then slug asc), not
	 * left to whatever order MySQL happens to return.
	 */
	public function test_top_tags_tiebreak_is_count_desc_then_slug_asc(): void {
		$cat = $this->category_id( 'tiebreak', 'Tiebreak' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
				'tags_input'    => [ 'zeta' ],
			]
		);
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
				'tags_input'    => [ 'alpha' ],
			]
		);
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
				'tags_input'    => [ 'mid' ],
			]
		);

		$tags = Stats::top_tags( $cat );

		$this->assertSame( [ 'alpha', 'mid', 'zeta' ], array_column( $tags, 'slug' ) );
	}

	/**
	 * P0-05: SPEC §4 -- attaching a tag to an already-published post refreshes top_tags for
	 * every category the post belongs to (the `post_tag` branch of `on_set_object_terms`).
	 */
	public function test_attaching_a_tag_to_a_published_post_refreshes_top_tags(): void {
		$cat  = $this->category_id( 'refresh-tags', 'Refresh Tags' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
			]
		);

		$this->assertSame( [], Stats::top_tags( $cat ) );

		wp_set_post_tags( $post, [ 'late-tag' ] );

		$tags = Stats::top_tags( $cat );
		$this->assertSame( [ 'late-tag' ], array_column( $tags, 'slug' ) );
	}

	/**
	 * P0-05: SPEC §4 -- moving a post between categories flushes both the category it left and
	 * the one it joined (the `category` branch of `on_set_object_terms`).
	 */
	public function test_changing_categories_flushes_old_and_new_category(): void {
		$from = $this->category_id( 'move-from', 'Move From' );
		$to   = $this->category_id( 'move-to', 'Move To' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $from ],
			]
		);

		Stats::category( $from );
		Stats::category( $to );
		$this->assertNotFalse( get_transient( "ttm_category_stats_{$from}" ) );
		$this->assertNotFalse( get_transient( "ttm_category_stats_{$to}" ) );

		wp_set_post_categories( $post, [ $to ] );

		$this->assertFalse( get_transient( "ttm_category_stats_{$from}" ) );
		$this->assertFalse( get_transient( "ttm_category_stats_{$to}" ) );
	}

	/**
	 * P0-05: SPEC §4 -- an empty top_tags result is cached for the shorter
	 * stats.cache_seconds (3600), not the full stats.tags_cache_seconds (43200).
	 */
	public function test_empty_top_tags_result_is_cached_for_stats_cache_seconds(): void {
		$cat = $this->category_id( 'no-tagged-posts', 'No Tagged Posts' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $cat ],
			]
		);

		$this->assertSame( [], Stats::top_tags( $cat ) );

		$timeout = (int) get_option( "_transient_timeout_ttm_top_tags_{$cat}" );
		$this->assertEqualsWithDelta( time() + 3600, $timeout, 5 );
	}

	/**
	 * P0-05: SPEC §4 -- `Stats::flush_all()` deletes every stats/top-tags transient outright,
	 * used by the seeder before a fresh run/reset.
	 */
	public function test_flush_all_deletes_every_stats_transient(): void {
		$one = $this->category_id( 'flush-all-one', 'Flush All One' );
		$two = $this->category_id( 'flush-all-two', 'Flush All Two' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $one ],
				'tags_input'    => [ 'flush-tag' ],
			]
		);

		Stats::category( $one );
		Stats::top_tags( $one );
		Stats::category( $two );
		Stats::top_tags( $two );

		Stats::flush_all();

		$this->assertFalse( get_transient( "ttm_category_stats_{$one}" ) );
		$this->assertFalse( get_transient( "ttm_top_tags_{$one}" ) );
		$this->assertFalse( get_transient( "ttm_category_stats_{$two}" ) );
		$this->assertFalse( get_transient( "ttm_top_tags_{$two}" ) );
	}
}
