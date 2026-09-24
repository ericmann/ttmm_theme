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
	}
}
