<?php
/**
 * Unit tests for TTM\Core\Support\Clock.
 *
 * @package TTM\Tests\Unit\Support
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Support;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Support\Clock;
use TTM\Tests\Unit\TestCase;

class ClockTest extends TestCase {

	public function test_now_is_in_site_timezone(): void {
		$this->assertSame( 'America/Los_Angeles', Clock::now()->getTimezone()->getName() );
	}

	public function test_ttm_now_filter_replaces_now(): void {
		$fixed = new DateTimeImmutable( '2026-01-01 00:00:00', new DateTimeZone( 'America/Los_Angeles' ) );

		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) use ( $fixed ) {
				return 'ttm_now' === $tag ? $fixed : $value;
			}
		);

		$this->assertSame( $fixed, Clock::now() );
	}

	public function test_at_parses_mysql_datetime_in_site_tz(): void {
		$parsed = Clock::at( '2026-03-15 08:30:00' );

		$this->assertNotNull( $parsed );
		$this->assertSame( '2026-03-15 08:30:00', $parsed->format( 'Y-m-d H:i:s' ) );
		$this->assertSame( 'America/Los_Angeles', $parsed->getTimezone()->getName() );
	}

	public function test_at_returns_null_on_garbage(): void {
		$this->assertNull( Clock::at( 'not a date at all' ) );
	}

	public function test_today_uses_filtered_now(): void {
		$fixed = new DateTimeImmutable( '2026-07-04 12:00:00', new DateTimeZone( 'America/Los_Angeles' ) );

		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) use ( $fixed ) {
				return 'ttm_now' === $tag ? $fixed : $value;
			}
		);

		$this->assertSame( '2026-07-04', Clock::today() );
	}
}
