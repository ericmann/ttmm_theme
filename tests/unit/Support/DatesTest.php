<?php
/**
 * Unit tests for TTM\Core\Support\Dates.
 *
 * @package TTM\Tests\Unit\Support
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Support;

use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Support\Dates;
use TTM\Tests\Unit\TestCase;

class DatesTest extends TestCase {

	private function date( string $s ): DateTimeImmutable {
		return new DateTimeImmutable( $s, new DateTimeZone( 'America/Los_Angeles' ) );
	}

	public function test_short_month_returns_sept_for_september(): void {
		$this->assertSame( 'Sept', Dates::short_month( $this->date( '2026-09-19' ) ) );
	}

	public function test_short_month_keeps_three_letters_otherwise(): void {
		$this->assertSame( 'Jul', Dates::short_month( $this->date( '2025-07-30' ) ) );
	}

	public function test_short_adds_year_when_year_differs(): void {
		$now = $this->date( '2026-09-20' );

		$this->assertSame( 'Sept 19', Dates::short( $this->date( '2026-09-19' ), $now ) );
		$this->assertSame( 'Jul 30, 2025', Dates::short( $this->date( '2025-07-30' ), $now ) );
	}

	public function test_compact_format(): void {
		$this->assertSame( 'Sun, Sept 20, 2026', Dates::compact( $this->date( '2026-09-20' ) ) );
	}

	public function test_masthead_format(): void {
		$this->assertSame( 'Sunday, September 20, 2026', Dates::masthead( $this->date( '2026-09-20' ) ) );
	}

	public function test_relative_day_today_yesterday_weekday_and_null(): void {
		$now = $this->date( '2026-09-20 12:00:00' );

		$this->assertSame( 'Today', Dates::relative_day( $this->date( '2026-09-20' ), $now, 6 ) );
		$this->assertSame( 'Yesterday', Dates::relative_day( $this->date( '2026-09-19' ), $now, 6 ) );
		// 2026-09-18 is a Friday; within the 6-day window.
		$this->assertSame( 'Friday', Dates::relative_day( $this->date( '2026-09-18' ), $now, 6 ) );
		// 2026-09-13 is 7 days before now; outside the 6-day window.
		$this->assertNull( Dates::relative_day( $this->date( '2026-09-13' ), $now, 6 ) );
	}
}
