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
}
