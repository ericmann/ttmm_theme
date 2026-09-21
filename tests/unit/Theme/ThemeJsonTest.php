<?php
/**
 * theme.json preset checks. No WordPress — plain JSON assertions.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use TTM\Tests\Unit\TestCase;

class ThemeJsonTest extends TestCase {

	/** @return array<string, mixed> */
	private function theme_json(): array {
		return json_decode( (string) file_get_contents( TTM_THEME_DIR . 'theme.json' ), true );
	}

	public function test_palette_has_no_colors_outside_the_token_sheet(): void {
		$json    = $this->theme_json();
		$palette = $json['settings']['color']['palette'];

		$allowed_slugs = [ 'bg', 'surface', 'text', 'accent', 'accent-100', 'accent-600', 'accent-700', 'divider' ];
		for ( $i = 100; $i <= 900; $i += 100 ) {
			$allowed_slugs[] = "neutral-{$i}";
		}

		foreach ( $palette as $entry ) {
			$this->assertContains( $entry['slug'], $allowed_slugs, "Unexpected palette slug {$entry['slug']}" );
		}
	}

	public function test_every_font_size_is_a_preset_with_px_value(): void {
		$json = $this->theme_json();

		foreach ( $json['settings']['typography']['fontSizes'] as $entry ) {
			$this->assertArrayHasKey( 'slug', $entry );
			$this->assertMatchesRegularExpression( '/^\d+px$/', $entry['size'] );
		}
	}

	public function test_custom_colors_and_font_sizes_are_disabled(): void {
		$json = $this->theme_json();

		$this->assertFalse( $json['settings']['color']['custom'] );
		$this->assertFalse( $json['settings']['color']['customGradient'] );
		$this->assertFalse( $json['settings']['color']['defaultPalette'] );
		$this->assertFalse( $json['settings']['typography']['customFontSize'] );
		$this->assertFalse( $json['settings']['typography']['defaultFontSizes'] );
	}

	public function test_no_dark_mode_keys(): void {
		$raw = (string) file_get_contents( TTM_THEME_DIR . 'theme.json' );

		$this->assertStringNotContainsString( 'prefers-color-scheme', $raw );

		$json    = $this->theme_json();
		$palette = $json['settings']['color']['palette'];
		foreach ( $palette as $entry ) {
			$this->assertStringNotContainsStringIgnoringCase( 'dark', $entry['slug'] );
		}
	}
}
