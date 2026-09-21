<?php
/**
 * Unit tests for themes/ttm-theme/assets/css/ttm.css's honeypot rule. No WordPress.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use TTM\Tests\Unit\TestCase;

class CssBudgetTest extends TestCase {

	public function test_ttm_css_has_visually_hidden_honeypot_rule(): void {
		$css = (string) file_get_contents( TTM_THEME_DIR . 'assets/css/ttm.css' );

		$this->assertMatchesRegularExpression(
			'/\.ttm-hp\s*\{[^}]*\}/s',
			$css,
			'ttm.css is missing a .ttm-hp rule for the newsletter honeypot field.'
		);

		preg_match( '/\.ttm-hp\s*\{([^}]*)\}/s', $css, $matches );
		$rule = $matches[1] ?? '';

		// Visually hidden but still DOM/tab-reachable (display:none or visibility:hidden would
		// also make it unreachable by a bot that fills every visible-and-focusable field,
		// defeating the honeypot).
		$this->assertStringContainsString( 'position: absolute', $rule );
		$this->assertStringNotContainsString( 'display: none', $rule );
		$this->assertStringNotContainsString( 'display:none', $rule );
		$this->assertStringNotContainsString( 'visibility: hidden', $rule );
		$this->assertStringNotContainsString( 'visibility:hidden', $rule );
	}
}
