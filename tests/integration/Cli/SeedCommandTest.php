<?php
/**
 * Integration tests for TTM\Core\Cli\SeedCommand.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\SeedCommand;

class SeedCommandTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_filters( 'ttm_seed_demo_images' );
		parent::tear_down();
	}

	/**
	 * P1-02: `--no-demo-images` reaches `Seeder`'s constructor, which applies the
	 * `ttm_seed_demo_images` filter -- record what the filter actually saw rather than
	 * asserting on rendered content, so this stays a fast, direct test of the wiring.
	 */
	public function test_no_demo_images_flag_reaches_the_seeder(): void {
		$seen = null;
		add_filter(
			'ttm_seed_demo_images',
			static function ( bool $demo_images ) use ( &$seen ): bool {
				$seen = $demo_images;
				return $demo_images;
			}
		);

		( new SeedCommand() )->run(
			[],
			[
				'no-demo-images' => true,
				'starter-only'   => true,
			]
		);

		$this->assertFalse( $seen );
	}

	public function test_without_the_flag_demo_images_stay_on(): void {
		$seen = null;
		add_filter(
			'ttm_seed_demo_images',
			static function ( bool $demo_images ) use ( &$seen ): bool {
				$seen = $demo_images;
				return $demo_images;
			}
		);

		( new SeedCommand() )->run( [], [ 'starter-only' => true ] );

		$this->assertTrue( $seen );

		// P1-04: with the filter genuinely on, --starter-only's seed_pages() call now
		// sideloads the real demo-about.jpg (pages.json names it) -- clean it up so it
		// doesn't collide with SeederTest.php's own assertion on that exact bare filename.
		$about = get_page_by_path( 'about', OBJECT, 'page' );
		if ( $about ) {
			$thumbnail_id = get_post_thumbnail_id( $about->ID );
			if ( $thumbnail_id ) {
				wp_delete_attachment( $thumbnail_id, true );
			}
		}
	}

	/**
	 * P2-02: `--now` pins Clock::now() for the run, so the seed's `days_ago` arithmetic (SPEC
	 * §6.4 "Demo dates") is reproducible from any wall-clock day.
	 */
	public function test_now_flag_pins_post_dates(): void {
		( new SeedCommand() )->run(
			[],
			[
				'now'            => '2026-01-15 12:00:00',
				'no-demo-images' => true,
			]
		);

		$post = get_page_by_path( 'signing-your-options-table', OBJECT, 'post' );
		$this->assertNotNull( $post );
		$this->assertSame( '2026-01-14 12:00:00', $post->post_date );
	}

	public function test_invalid_now_is_rejected(): void {
		$result = ( new SeedCommand() )->run(
			[],
			[
				'now'          => 'not a date',
				'starter-only' => true,
			]
		);

		$this->assertFalse( $result['ok'] );
		$this->assertSame( [ 'invalid --now' ], $result['messages'] );
	}

	public function test_now_filter_is_removed_after_the_run(): void {
		( new SeedCommand() )->run(
			[],
			[
				'now'          => '2026-01-15 12:00:00',
				'starter-only' => true,
			]
		);

		$this->assertFalse( has_filter( 'ttm_now' ) );
	}
}
