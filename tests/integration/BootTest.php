<?php
/**
 * Proves the integration harness boots WordPress with the plugin and theme.
 *
 * @package TTM\Tests\Integration
 */

declare( strict_types=1 );

class BootTest extends WP_UnitTestCase {
	public function test_plugin_and_theme_are_active(): void {
		$this->assertTrue( defined( 'TTM_CORE_API' ) );
		$this->assertSame( 'ttm-theme', get_stylesheet() );
		$this->assertTrue( wp_is_block_theme() );
	}
}
