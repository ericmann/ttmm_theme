<?php
/**
 * Unit tests for TTM\Theme\register_code_highlighting() and assets/css/code.css. No WordPress.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use TTM\Tests\Unit\TestCase;

class CodeHighlightingTest extends TestCase {

	public function test_plugin_styles_off_and_theme_style_scoped_to_code_block(): void {
		Functions\when( 'get_template_directory' )->justReturn( rtrim( TTM_THEME_DIR, '/' ) );
		Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/ttm-theme' );
		Functions\when( 'wp_get_theme' )->justReturn(
			new class() {
				public function get( string $key ): string {
					return '9.9.9';
				}
			}
		);
		Filters\expectAdded( 'syntax_highlighting_code_block_styling' )->once()->with( '__return_false' );
		Functions\expect( 'wp_enqueue_block_style' )
			->once()
			->with(
				'core/code',
				\Mockery::on(
					static fn ( array $args ): bool => 'ttm-code' === $args['handle']
						&& str_ends_with( $args['src'], '/assets/css/code.css' )
						&& is_file( $args['path'] )
						&& str_starts_with( $args['ver'], '9.9.9.' )
				)
			);

		require_once TTM_THEME_DIR . 'inc/code-highlighting.php';
		\TTM\Theme\register_code_highlighting();

		$this->assertTrue( true, 'filter and block-style expectations verified in tearDown()' );
	}

	public function test_code_css_uses_presets_only(): void {
		$css = (string) file_get_contents( TTM_THEME_DIR . 'assets/css/code.css' );

		$this->assertDoesNotMatchRegularExpression( '/#[0-9a-fA-F]{3,8}(?![\w-])/', $css, 'colours come from theme.json presets' );
		$this->assertDoesNotMatchRegularExpression( '/\b(?:rgb|hsl)a?\(/', $css, 'colours come from theme.json presets' );
		$this->assertStringNotContainsString( 'prefers-color-scheme', $css );
		$this->assertStringContainsString( '.hljs-keyword', $css );
		$this->assertStringContainsString( '.shcb-language', $css );
	}
}
