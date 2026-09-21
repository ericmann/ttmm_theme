<?php
/**
 * Integration tests for the P1-14 seed states and seed command.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;
use TTM\Core\Cli\SeedCommand;
use TTM\Core\Query\SeriesIndex;
use TTM\Core\Support\Dates;

class SeedStatesTest extends TTM_IntegrationTestCase {

	public function test_normal_state_has_series_index_with_six_rows(): void {
		( new Seeder() )->run( 'normal' );

		$this->assertCount( 6, SeriesIndex::all() );
	}

	public function test_normal_state_has_active_serial_with_cover(): void {
		( new Seeder() )->run( 'normal' );

		$row = SeriesIndex::by_slug( 'the-quiet-ledger' );

		$this->assertNotNull( $row );
		$this->assertSame( 'in-progress', $row['status'] );

		$term = get_term_by( 'slug', 'the-quiet-ledger', 'series' );
		$this->assertGreaterThan( 0, (int) get_term_meta( $term->term_id, 'ttm_cover_id', true ) );
	}

	public function test_quiet_state_has_no_post_newer_than_90_days(): void {
		( new Seeder() )->run( 'quiet' );

		$now   = \TTM\Core\Support\Clock::now();
		$posts = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => 200, // the full seed set is well under this bound.
			]
		);

		foreach ( $posts as $post ) {
			$post_date = \TTM\Core\Support\Clock::at( $post->post_date );
			$days      = Dates::days_between( $post_date, $now );
			$this->assertGreaterThanOrEqual( 90, $days, "Post {$post->post_name} is newer than 90 days" );
		}
	}

	public function test_empty_state_has_zero_series_and_no_security_posts(): void {
		( new Seeder() )->run( 'empty' );

		$this->assertCount( 0, SeriesIndex::all() );

		$security = get_term_by( 'slug', 'security', 'category' );
		if ( $security ) {
			$this->assertSame( 0, (int) $security->count );
		}
	}

	public function test_seed_configures_custom_url_dev_accept(): void {
		( new Seeder() )->run( 'normal' );

		$html = (string) do_blocks( '<!-- wp:ttm/newsletter-form /-->' );

		$this->assertStringContainsString( 'data-provider="custom-url"', $html );
		$this->assertStringContainsString( '<form', $html );
	}

	public function test_seed_refuses_on_production(): void {
		$this->assertFalse( SeedCommand::allowed( 'production' ) );
		$this->assertTrue( SeedCommand::allowed( 'local' ) );
	}

	public function test_verse_option_is_seeded_from_fixture_with_attribution_url(): void {
		( new Seeder() )->seed_verse();

		$verse = get_option( 'ttm_verse' );

		$this->assertIsArray( $verse );
		$this->assertNotEmpty( $verse['copyright'] );
		$this->assertStringStartsWith( 'https://dailymedtoday.com/meditation/', $verse['url'] );
	}
}
