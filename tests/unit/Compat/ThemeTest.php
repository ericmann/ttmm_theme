<?php
/**
 * Unit tests for TTM\Core\Compat\Theme.
 *
 * @package TTM\Tests\Unit\Compat
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Compat;

use TTM\Core\Compat\Theme;
use TTM\Tests\Unit\TestCase;

class ThemeTest extends TestCase {

	public function test_mismatch_false_when_theme_declares_nothing(): void {
		$this->assertFalse( Theme::mismatch( null, 1 ) );
	}

	public function test_mismatch_false_when_equal(): void {
		$this->assertFalse( Theme::mismatch( 1, 1 ) );
	}

	public function test_mismatch_true_when_different(): void {
		$this->assertTrue( Theme::mismatch( 2, 1 ) );
	}
}
