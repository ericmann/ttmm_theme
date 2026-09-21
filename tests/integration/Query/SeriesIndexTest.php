<?php
/**
 * Integration tests for TTM\Core\Query\SeriesIndex.
 *
 * @package TTM\Tests\Integration\Query
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesIndexTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * Force the debounced rebuild immediately, without firing WP's real 'shutdown'
	 * action (which also flushes output buffers and trips PHPUnit's risky-test check).
	 */
	private function flush(): array {
		return SeriesIndex::rebuild();
	}

	public function test_index_rebuilds_on_publish_and_lists_parts_in_order(): void {
		$series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$p2 = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $p2, 'ttm_series_part', 2 );
		wp_set_object_terms( $p2, [ $series ], 'series' );

		$p1 = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $p1, 'ttm_series_part', 1 );
		wp_set_object_terms( $p1, [ $series ], 'series' );

		$rows = $this->flush();
		$row  = SeriesIndex::get( $series );

		$this->assertNotNull( $row );
		$this->assertSame( [ $p1, $p2 ], array_column( $row['parts'], 'post_id' ) );
		$this->assertSame( 2, $row['published'] );
	}

	public function test_unpublish_removes_part_and_updates_published_count(): void {
		$series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$p1     = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $p1, 'ttm_series_part', 1 );
		wp_set_object_terms( $p1, [ $series ], 'series' );
		$this->flush();

		wp_update_post(
			[
				'ID'          => $p1,
				'post_status' => 'draft',
			] 
		);
		$this->flush();

		$row = SeriesIndex::get( $series );
		$this->assertSame( 0, $row['published'] );
		$this->assertSame( 'draft', $row['parts'][0]['status'] );
	}

	public function test_scheduled_post_appears_with_future_status_and_date(): void {
		$series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$future = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		$p1     = self::factory()->post->create(
			[
				'post_status' => 'future',
				'post_date'   => $future,
			]
		);
		update_post_meta( $p1, 'ttm_series_part', 1 );
		wp_set_object_terms( $p1, [ $series ], 'series' );

		$this->flush();
		$row = SeriesIndex::get( $series );

		$this->assertSame( 'future', $row['parts'][0]['status'] );
		$this->assertSame( $future, $row['parts'][0]['date'] );
	}

	public function test_categories_follow_nav_order(): void {
		$security   = $this->category_id( 'security', 'Security' );
		$technology = $this->category_id( 'technology', 'Technology' );
		$series     = self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$p1 = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
			] 
		);
		update_post_meta( $p1, 'ttm_series_part', 1 );
		wp_set_object_terms( $p1, [ $series ], 'series' );

		$p2 = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $technology ],
			] 
		);
		update_post_meta( $p2, 'ttm_series_part', 2 );
		wp_set_object_terms( $p2, [ $series ], 'series' );

		$this->flush();
		$row = SeriesIndex::get( $series );

		$this->assertSame( [ $technology, $security ], $row['categories'] );
	}

	public function test_open_ended_series_total_equals_published(): void {
		$series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$p1     = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $p1, 'ttm_series_part', 1 );
		wp_set_object_terms( $p1, [ $series ], 'series' );

		$this->flush();
		$row = SeriesIndex::get( $series );

		$this->assertSame( 1, $row['total'] );
	}

	public function test_ttm_series_index_filter_can_alter_rows(): void {
		self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		add_filter(
			'ttm_series_index',
			static function ( array $rows ): array {
				foreach ( $rows as &$row ) {
					$row['name'] = 'Filtered';
				}
				return $rows;
			}
		);

		$rows = $this->flush();

		$this->assertSame( 'Filtered', $rows[0]['name'] );
	}

	public function test_delete_series_term_removes_row(): void {
		$series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$this->flush();
		$this->assertNotNull( SeriesIndex::get( $series ) );

		wp_delete_term( $series, 'series' );
		$this->flush();

		$this->assertNull( SeriesIndex::get( $series ) );
	}
}
