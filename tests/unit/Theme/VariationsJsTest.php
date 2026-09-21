<?php
/**
 * Static checks on variations.js. No WordPress.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use TTM\Tests\Unit\TestCase;

class VariationsJsTest extends TestCase {

	private function source(): string {
		return (string) file_get_contents( TTM_THEME_DIR . 'assets/js/variations.js' );
	}

	public function test_three_variations_are_registered(): void {
		$src = $this->source();

		$this->assertSame( 3, substr_count( $src, 'registerBlockVariation' ) );
		$this->assertStringContainsString( "'ttm/section-query'", $src );
		$this->assertStringContainsString( "'ttm/journal-query'", $src );
		$this->assertStringContainsString( "'ttm/sections-nav'", $src );
	}

	public function test_variations_js_has_no_network_calls(): void {
		$src = $this->source();

		foreach ( [ 'fetch(', 'XMLHttpRequest', 'apiFetch', 'wp-json', 'admin-ajax' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $src, "variations.js must not contain {$needle}" );
		}
	}
}
