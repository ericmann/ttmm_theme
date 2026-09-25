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

	public function test_normal_state_has_series_index_with_seven_rows(): void {
		( new Seeder( false ) )->run( 'normal' );

		$this->assertCount( 7, SeriesIndex::all() );
	}

	public function test_normal_state_has_active_serial_with_cover(): void {
		( new Seeder( false ) )->run( 'normal' );

		$row = SeriesIndex::by_slug( 'the-quiet-ledger' );

		$this->assertNotNull( $row );
		$this->assertSame( 'in-progress', $row['status'] );

		$term = get_term_by( 'slug', 'the-quiet-ledger', 'series' );
		$this->assertGreaterThan( 0, (int) get_term_meta( $term->term_id, 'ttm_cover_id', true ) );
	}

	public function test_quiet_state_has_no_post_newer_than_90_days(): void {
		( new Seeder( false ) )->run( 'quiet' );

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
		( new Seeder( false ) )->run( 'empty' );

		$this->assertCount( 0, SeriesIndex::all() );

		$security = get_term_by( 'slug', 'security', 'category' );
		if ( $security ) {
			$this->assertSame( 0, (int) $security->count );
		}
	}

	public function test_seed_configures_custom_url_dev_accept(): void {
		( new Seeder( false ) )->run( 'normal' );

		$html = (string) do_blocks( '<!-- wp:ttm/newsletter-form /-->' );

		$this->assertStringContainsString( 'data-provider="custom-url"', $html );
		$this->assertStringContainsString( '<form', $html );
	}

	public function test_seed_refuses_on_production(): void {
		$this->assertFalse( SeedCommand::allowed( 'production' ) );
		$this->assertTrue( SeedCommand::allowed( 'local' ) );
	}

	public function test_verse_option_is_seeded_from_fixture_with_attribution_url(): void {
		( new Seeder( false ) )->seed_verse();

		$verse = get_option( 'ttm_verse' );

		$this->assertIsArray( $verse );
		$this->assertNotEmpty( $verse['copyright'] );
		$this->assertStringStartsWith( 'https://dailymedtoday.com/meditation/', $verse['url'] );
	}

	/**
	 * P2-01, SPEC §6.6 step 1 / §6.7: `--starter-only` seeds categories, the starter pages and
	 * the Sections navigation only -- no posts, series, or books.
	 */
	public function test_starter_only_creates_sections_pages_and_navigation_and_no_posts(): void {
		$summary = ( new Seeder( false ) )->run_starter();

		$this->assertGreaterThan( 0, $summary['categories'] );
		$this->assertGreaterThan( 0, $summary['pages'] );
		$this->assertSame( 1, $summary['navigation'] );

		$this->assertNotNull( get_page_by_path( 'series' ) );
		$this->assertNotNull( get_page_by_path( 'writing' ) );
		$this->assertNotNull( get_page_by_path( 'newsletter' ) );
		$this->assertNotNull( get_page_by_path( 'about' ) );

		$posts = get_posts(
			[
				'post_type'      => 'post',
				'post_status'    => 'any',
				'posts_per_page' => 1,
			]
		);
		$this->assertCount( 0, $posts );
		$this->assertCount( 0, SeriesIndex::all() );
	}

	public function test_starter_only_is_idempotent(): void {
		$seeder = new Seeder( false );
		$first  = $seeder->run_starter();
		$second = $seeder->run_starter();

		// navigation is 0 on the second call by design (Seeder::seed_navigation()'s docblock:
		// "0 if it already existed or nothing to create") -- categories/pages counts are what
		// prove idempotency here (no duplicates created).
		$this->assertSame( $first['categories'], $second['categories'] );
		$this->assertSame( $first['pages'], $second['pages'] );

		$pages = get_posts(
			[
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
			]
		);
		$slugs = wp_list_pluck( $pages, 'post_name' );
		$this->assertSame( count( $slugs ), count( array_unique( $slugs ) ), 'starter pages must not duplicate' );
	}

	/**
	 * P2-01, SPEC §6.6 last paragraph, Decision "Seeder::reset() from a live state": reset()
	 * wipes non-seed content too (posts, terms, attachments), the way a live import would leave
	 * it, while keeping the default category (wp_delete_term() refuses to delete it).
	 */
	public function test_reset_removes_foreign_posts_terms_and_attachments_when_present(): void {
		$foreign_post     = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => 'Not seeded',
				'tags_input'  => [ 'foreign-tag' ],
			]
		);
		$attachment_id    = self::factory()->attachment->create_object(
			[
				'file'           => 'foreign.jpg',
				'post_parent'    => $foreign_post,
				'post_mime_type' => 'image/jpeg',
			]
		);
		$default_category = (int) get_option( 'default_category' );

		( new Seeder( false ) )->reset();

		$this->assertNull( get_post( $foreign_post ) );
		$this->assertNull( get_post( $attachment_id ) );
		$this->assertFalse( (bool) term_exists( 'foreign-tag', 'post_tag' ) );
		$this->assertNotNull( get_term( $default_category, 'category' ), 'the default category must survive reset()' );
	}

	/**
	 * R1-08, PLAN Decision "Seeder::reset() from a live state": reset() clears every post type,
	 * not just post/page/attachment -- the inert rows a live import (or the editor itself)
	 * leaves behind (wp_block, wp_navigation, nav_menu_item) and a post type only registered
	 * for this test (simulating e.g. a `feedback` plugin's own type).
	 */
	public function test_reset_removes_inert_imported_post_types(): void {
		register_post_type( 'feedback', [ 'public' => false ] );

		$block    = self::factory()->post->create( [ 'post_type' => 'wp_block' ] );
		$nav      = self::factory()->post->create( [ 'post_type' => 'wp_navigation' ] );
		$feedback = self::factory()->post->create( [ 'post_type' => 'feedback' ] );

		$menu_id       = wp_create_nav_menu( 'ttm-reset-test-menu' );
		$nav_menu_item = wp_update_nav_menu_item(
			$menu_id,
			0,
			[
				'menu-item-title'  => 'Test item',
				'menu-item-url'    => home_url( '/' ),
				'menu-item-status' => 'publish',
			]
		);

		( new Seeder( false ) )->reset();

		$this->assertNull( get_post( $block ) );
		$this->assertNull( get_post( $nav ) );
		$this->assertNull( get_post( $feedback ) );
		$this->assertNull( get_post( $nav_menu_item ) );

		unregister_post_type( 'feedback' );
	}

	public function test_reset_with_only_seed_content_behaves_as_before(): void {
		( new Seeder( false ) )->run( 'normal' );

		( new Seeder( false ) )->reset();

		$posts = get_posts(
			[
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
			]
		);
		$this->assertCount( 0, $posts );
		$this->assertCount( 0, SeriesIndex::all() );
		$this->assertFalse( get_option( 'ttm_books' ) );
		$this->assertFalse( get_option( 'ttm_verse' ) );
	}
}
