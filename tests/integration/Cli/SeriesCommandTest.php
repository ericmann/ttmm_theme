<?php
/**
 * Integration tests for `wp ttm series:assign --from-tags/--form/--status/--total/--name`
 * (P2-02, SPEC §6.7).
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\SeriesCommand;

class SeriesCommandTest extends TTM_IntegrationTestCase {

	public function test_from_tags_unions_posts_and_numbers_by_date(): void {
		wp_insert_term( 'tag-a', 'post_tag', [ 'slug' => 'tag-a' ] );
		wp_insert_term( 'tag-b', 'post_tag', [ 'slug' => 'tag-b' ] );

		$p1 = self::factory()->post->create(
			[
				'tags_input' => [ 'tag-a' ],
				'post_date'  => '2024-01-01 00:00:00',
			]
		);
		$p2 = self::factory()->post->create(
			[
				'tags_input' => [ 'tag-b' ],
				'post_date'  => '2024-02-01 00:00:00',
			]
		);
		// In both tags -- union must not duplicate it.
		$p3 = self::factory()->post->create(
			[
				'tags_input' => [ 'tag-a', 'tag-b' ],
				'post_date'  => '2024-03-01 00:00:00',
			]
		);

		$result = ( new SeriesCommand() )->run( [ 'union-series' ], [ 'from-tags' => 'tag-a,tag-b' ] );

		$this->assertTrue( $result['ok'] );
		$this->assertNotFalse( term_exists( 'union-series', 'series' ) );
		$this->assertSame( 1, (int) get_post_meta( $p1, 'ttm_series_part', true ) );
		$this->assertSame( 2, (int) get_post_meta( $p2, 'ttm_series_part', true ) );
		$this->assertSame( 3, (int) get_post_meta( $p3, 'ttm_series_part', true ) );
	}

	public function test_explicit_status_form_total_and_name_are_written(): void {
		wp_insert_term( 'named-tag', 'post_tag', [ 'slug' => 'named-tag' ] );
		self::factory()->post->create( [ 'tags_input' => [ 'named-tag' ] ] );

		$result = ( new SeriesCommand() )->run(
			[ 'named-series' ],
			[
				'from-tags' => 'named-tag',
				'form'      => 'novel',
				'status'    => 'hiatus',
				'total'     => '12',
				'name'      => 'A Custom Name',
			]
		);

		$this->assertTrue( $result['ok'] );
		$term = get_term_by( 'slug', 'named-series', 'series' );
		$this->assertSame( 'A Custom Name', $term->name );
		$this->assertSame( 'novel', get_term_meta( $term->term_id, 'ttm_form', true ) );
		$this->assertSame( 'hiatus', get_term_meta( $term->term_id, 'ttm_status', true ) );
		$this->assertSame( 12, (int) get_term_meta( $term->term_id, 'ttm_total_parts', true ) );
	}

	public function test_inferred_status_when_no_status_flag(): void {
		wp_insert_term( 'fresh-tag', 'post_tag', [ 'slug' => 'fresh-tag' ] );
		self::factory()->post->create(
			[
				'tags_input' => [ 'fresh-tag' ],
				'post_date'  => gmdate( 'Y-m-d H:i:s' ),
			]
		);

		$result = ( new SeriesCommand() )->run( [ 'inferred-series' ], [ 'from-tags' => 'fresh-tag' ] );

		$this->assertTrue( $result['ok'] );
		$term = get_term_by( 'slug', 'inferred-series', 'series' );
		$this->assertSame( 'in-progress', get_term_meta( $term->term_id, 'ttm_status', true ) );
	}

	public function test_dry_run_creates_no_term(): void {
		wp_insert_term( 'dry-tag', 'post_tag', [ 'slug' => 'dry-tag' ] );
		self::factory()->post->create( [ 'tags_input' => [ 'dry-tag' ] ] );

		$result = ( new SeriesCommand() )->run(
			[ 'dry-series' ],
			[
				'from-tags' => 'dry-tag',
				'dry-run'   => true,
			]
		);

		$this->assertTrue( $result['ok'] );
		$this->assertFalse( (bool) term_exists( 'dry-series', 'series' ) );
	}

	public function test_posts_already_in_a_series_are_skipped_and_reported(): void {
		wp_insert_term( 'shared-tag', 'post_tag', [ 'slug' => 'shared-tag' ] );
		$other_series = self::factory()->term->create( [ 'taxonomy' => 'series' ] );

		$already = self::factory()->post->create( [ 'tags_input' => [ 'shared-tag' ] ] );
		wp_set_object_terms( $already, [ $other_series ], 'series' );

		$fresh = self::factory()->post->create( [ 'tags_input' => [ 'shared-tag' ] ] );

		$result = ( new SeriesCommand() )->run( [ 'shared-series' ], [ 'from-tags' => 'shared-tag' ] );

		$assigned_ids = array_column( $result['rows'], 'post_id' );
		$this->assertContains( $fresh, $assigned_ids );
		$this->assertNotContains( $already, $assigned_ids );
		$this->assertStringContainsString( '1 already in a series, skipped', $result['messages'][0] );
	}
}
