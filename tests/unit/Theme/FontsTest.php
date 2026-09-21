<?php
/**
 * Font self-hosting checks: no WordPress, plain file/string assertions.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use TTM\Tests\Unit\TestCase;

class FontsTest extends TestCase {

	private function fonts_dir(): string {
		return TTM_THEME_DIR . 'assets/fonts/';
	}

	public function test_font_files_exist_for_each_style_and_range(): void {
		$dir = $this->fonts_dir();

		if ( ! is_dir( $dir ) || [] === glob( $dir . '*.woff2' ) ) {
			$this->markTestSkipped( 'No fonts downloaded (network unavailable at build time); network-failure path stays green.' );
		}

		foreach ( [ '400', '600', '800', '400i' ] as $style ) {
			foreach ( [ 'latin', 'latin-ext' ] as $range ) {
				$file = $dir . "archivo-{$style}-{$range}.woff2";
				$this->assertFileExists( $file, "Missing {$file}" );
			}
		}
	}

	public function test_functions_php_does_not_reference_google_fonts(): void {
		foreach ( [ 'functions.php', 'style.css', 'assets/css/ttm.css', 'assets/css/editor.css' ] as $relative ) {
			$src = (string) file_get_contents( TTM_THEME_DIR . $relative );
			$this->assertStringNotContainsString( 'fonts.googleapis.com', $src, $relative );
			$this->assertStringNotContainsString( 'fonts.gstatic.com', $src, $relative );
		}
	}

	public function test_only_nav_js_is_enqueued_on_the_front_end(): void {
		$src = (string) file_get_contents( TTM_THEME_DIR . 'functions.php' );

		// Scope to the wp_enqueue_scripts (front-end) callback only; enqueues under other
		// hooks (e.g. enqueue_block_editor_assets, editor-only) are out of scope for this rule.
		$this->assertMatchesRegularExpression( "/add_action\\(\\s*'wp_enqueue_scripts'/", $src );
		preg_match( "/add_action\\(\\s*'wp_enqueue_scripts',(.*?)\\n\\);/s", $src, $block );
		$this->assertNotEmpty( $block );

		preg_match_all( '/wp_enqueue_script\(\s*[^,]+,\s*[^,]+\/assets\/js\/([a-z0-9_-]+\.js)/', $block[1], $matches );

		$this->assertNotEmpty( $matches[1] );
		foreach ( $matches[1] as $script ) {
			$this->assertSame( 'nav.js', $script );
		}
	}
}
