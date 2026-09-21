<?php
/**
 * Unit tests for TTM\Theme\block_styles() and image-sizes.php. No WordPress.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use Brain\Monkey\Functions;
use TTM\Tests\Unit\TestCase;

class BlockStylesTest extends TestCase {

	public function test_style_list_matches_spec_fixture(): void {
		require_once TTM_THEME_DIR . 'inc/block-styles.php';

		$expected = require __DIR__ . '/fixtures/block-styles.php';

		$actual = array_map(
			static fn ( array $style ): string => $style['block'] . ':' . $style['name'],
			\TTM\Theme\block_styles()
		);

		sort( $expected );
		sort( $actual );

		$this->assertSame( $expected, $actual );
	}

	public function test_every_style_has_block_name_and_label(): void {
		require_once TTM_THEME_DIR . 'inc/block-styles.php';

		foreach ( \TTM\Theme\block_styles() as $style ) {
			$this->assertNotEmpty( $style['block'] );
			$this->assertNotEmpty( $style['name'] );
			$this->assertNotEmpty( $style['label'] );
		}
	}

	public function test_image_sizes_match_design(): void {
		Functions\expect( 'add_image_size' )->times( 4 );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		require_once TTM_THEME_DIR . 'inc/image-sizes.php';
		\TTM\Theme\register_image_sizes();

		$this->assertTrue( true, 'add_image_size(4x) expectation verified in tearDown()' );
	}
}
