<?php
/**
 * Unit tests for TTM\Core\Comments\Closing's pure decisions.
 *
 * @package TTM\Tests\Unit\Comments
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Comments;

use TTM\Core\Comments\Closing;
use TTM\Tests\Unit\TestCase;

class ClosingTest extends TestCase {

	private const DAY = 86400;

	public function test_windows_by_kind(): void {
		$this->assertSame( 365, Closing::days( 'discussion' ) );
		$this->assertSame( 14, Closing::days( 'reflections' ) );
		$this->assertSame( 30, Closing::days( 'notes' ) );
	}

	public function test_open_inside_the_window_closed_after_it(): void {
		$published = 1_000_000_000;
		$this->assertFalse( Closing::has_closed( $published, $published + 14 * self::DAY, 14 ) );
		$this->assertTrue( Closing::has_closed( $published, $published + 14 * self::DAY + 1, 14 ) );
		$this->assertFalse( Closing::has_closed( $published, $published + 364 * self::DAY, 365 ) );
	}

	public function test_zero_days_never_closes(): void {
		$this->assertFalse( Closing::has_closed( 0, 2_000_000_000, 0 ) );
	}

	public function test_core_closing_is_held_off(): void {
		$this->assertSame( '0', Closing::core_closing_off() );
	}
}
