<?php
/**
 * Smoke tests for the repository scaffold. Replace/extend as real code lands.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

class ScaffoldTest extends TestCase {
	public function test_plugin_bootstrap_declares_api_version(): void {
		$src = (string) file_get_contents( TTM_CORE_DIR . 'ttm-core.php' );
		$this->assertStringContainsString( "define( 'TTM_CORE_API', 1 )", $src );
		$this->assertMatchesRegularExpression( '/Text Domain:\s+ttm-core/', $src );
	}

	public function test_theme_style_header_and_theme_json_are_present(): void {
		$style = (string) file_get_contents( TTM_THEME_DIR . 'style.css' );
		$this->assertMatchesRegularExpression( '/Text Domain:\s+ttm-theme/', $style );
		$json = json_decode( (string) file_get_contents( TTM_THEME_DIR . 'theme.json' ), true );
		$this->assertIsArray( $json );
		$this->assertSame( 3, $json['version'] );
	}

	public function test_brain_monkey_is_wired(): void {
		\Brain\Monkey\Functions\when( 'esc_html' )->returnArg();
		$this->assertSame( 'ok', esc_html( 'ok' ) );
	}
}
