<?php
/**
 * Integration tests for the P1-12 CLI command cores.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\PrimaryCommand;
use TTM\Core\Cli\RecountCommand;
use TTM\Core\Cli\SeriesCommand;
use TTM\Core\Query\SeriesIndex;

class MaintenanceCommandsTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_recount_all_writes_word_counts(): void {
		$a = self::factory()->post->create( [ 'post_content' => 'One two three' ] );
		$b = self::factory()->post->create( [ 'post_content' => 'Four five six seven' ] );

		$result = ( new RecountCommand() )->run( [], [ 'all' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 3, (int) get_post_meta( $a, 'ttm_word_count', true ) );
		$this->assertSame( 4, (int) get_post_meta( $b, 'ttm_word_count', true ) );
	}

	public function test_recount_single_post(): void {
		$a = self::factory()->post->create( [ 'post_content' => 'One two' ] );
		$b = self::factory()->post->create( [ 'post_content' => 'One two three four five' ] );
		update_post_meta( $b, 'ttm_word_count', 0 );

		( new RecountCommand() )->run( [], [ 'post' => (string) $a ] );

		$this->assertSame( 2, (int) get_post_meta( $a, 'ttm_word_count', true ) );
		$this->assertSame( 0, (int) get_post_meta( $b, 'ttm_word_count', true ) );
	}

	public function test_primary_assign_dry_run_changes_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		delete_post_meta( $post, 'ttm_primary_category' );

		$result = ( new PrimaryCommand() )->run( [], [ 'dry-run' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 0, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
	}

	public function test_primary_assign_fills_only_empty_meta(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$empty_post = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		delete_post_meta( $empty_post, 'ttm_primary_category' );

		$set_post = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		update_post_meta( $set_post, 'ttm_primary_category', $business );

		( new PrimaryCommand() )->run( [], [] );

		$this->assertSame( $tech, (int) get_post_meta( $empty_post, 'ttm_primary_category', true ) );
		$this->assertSame( $business, (int) get_post_meta( $set_post, 'ttm_primary_category', true ) );
	}

	public function test_series_assign_creates_term_and_numbers_by_date(): void {
		wp_insert_term( 'my-series-tag', 'post_tag', [ 'slug' => 'my-series-tag' ] );

		$p1 = self::factory()->post->create(
			[
				'tags_input' => [ 'my-series-tag' ],
				'post_date'  => '2024-01-01 00:00:00',
			] 
		);
		$p2 = self::factory()->post->create(
			[
				'tags_input' => [ 'my-series-tag' ],
				'post_date'  => '2024-02-01 00:00:00',
			] 
		);

		$result = ( new SeriesCommand() )->run( [ 'my-new-series' ], [ 'from-tag' => 'my-series-tag' ] );

		$this->assertTrue( $result['ok'] );
		$this->assertNotFalse( term_exists( 'my-new-series', 'series' ) );
		$this->assertSame( 1, (int) get_post_meta( $p1, 'ttm_series_part', true ) );
		$this->assertSame( 2, (int) get_post_meta( $p2, 'ttm_series_part', true ) );
	}

	public function test_series_assign_skips_posts_already_in_a_series(): void {
		wp_insert_term( 'shared-tag', 'post_tag', [ 'slug' => 'shared-tag' ] );
		$other_series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$already = self::factory()->post->create( [ 'tags_input' => [ 'shared-tag' ] ] );
		wp_set_object_terms( $already, [ $other_series ], 'series' );

		$fresh = self::factory()->post->create( [ 'tags_input' => [ 'shared-tag' ] ] );

		$result = ( new SeriesCommand() )->run( [ 'shared-series' ], [ 'from-tag' => 'shared-tag' ] );

		$assigned_ids = array_column( $result['rows'], 'post_id' );
		$this->assertContains( $fresh, $assigned_ids );
		$this->assertNotContains( $already, $assigned_ids );
	}

	public function test_series_assign_marks_stale_series_complete(): void {
		wp_insert_term( 'old-tag', 'post_tag', [ 'slug' => 'old-tag' ] );
		$old_date = gmdate( 'Y-m-d H:i:s', strtotime( '-100 days' ) );
		self::factory()->post->create(
			[
				'tags_input' => [ 'old-tag' ],
				'post_date'  => $old_date,
			]
		);

		( new SeriesCommand() )->run( [ 'old-series' ], [ 'from-tag' => 'old-tag' ] );

		$term = get_term_by( 'slug', 'old-series', 'series' );
		$this->assertSame( 'complete', get_term_meta( $term->term_id, 'ttm_status', true ) );
	}

	public function test_series_rebuild_refreshes_option(): void {
		self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$result = ( new SeriesCommand() )->rebuild();

		$this->assertTrue( $result['ok'] );
		$this->assertSame( SeriesIndex::all(), $result['rows'] );
	}
}
