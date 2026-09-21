<?php
/**
 * Static checks on nav.js. No WordPress.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use TTM\Tests\Unit\TestCase;

class NavJsTest extends TestCase {

	private function source(): string {
		return (string) file_get_contents( TTM_THEME_DIR . 'assets/js/nav.js' );
	}

	public function test_nav_js_has_no_network_calls(): void {
		$src = $this->source();

		foreach ( [ 'fetch(', 'XMLHttpRequest', 'apiFetch', 'wp-json', 'admin-ajax' ] as $needle ) {
			$this->assertStringNotContainsString( $needle, $src, "nav.js must not contain {$needle}" );
		}
	}

	public function test_nav_js_is_under_two_kilobytes(): void {
		$this->assertLessThan( 2048, strlen( $this->source() ) );
	}
}
