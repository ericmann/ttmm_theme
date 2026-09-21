<?php
/**
 * Unit tests for TTM\Core\Cache\Headers.
 *
 * @package TTM\Tests\Unit\Cache
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Cache;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Cache\Headers;
use TTM\Core\Config;
use TTM\Tests\Unit\TestCase;

class HeadersTest extends TestCase {

	protected function tearDown(): void {
		Config::reset();
		parent::tearDown();
	}

	private function now( string $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'America/Los_Angeles' ) );
	}

	/**
	 * Stub apply_filters so 'ttm_config' merges $overrides into the real defaults, and every
	 * other tag passes its value through unchanged.
	 *
	 * @param array<string, mixed> $overrides Config overrides.
	 */
	private function stub_config( array $overrides = [] ): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) use ( $overrides ) {
				if ( 'ttm_config' === $tag && is_array( $value ) ) {
					return array_merge( $value, $overrides );
				}
				return $value;
			}
		);
	}

	public function test_midday_boundary_is_midnight(): void {
		$this->stub_config();

		$this->assertSame( 43200, Headers::max_age( $this->now( '2026-09-20 12:00:00' ) ) );
	}

	public function test_before_six_boundary_is_six(): void {
		$this->stub_config();

		$this->assertSame( 60, Headers::max_age( $this->now( '2026-09-20 05:59:00' ) ) );
	}

	public function test_dst_end_day_counts_real_seconds(): void {
		$this->stub_config();

		$this->assertSame( 23400, Headers::max_age( $this->now( '2026-11-01 00:30:00' ) ) );
	}

	public function test_dst_start_day_counts_real_seconds(): void {
		$this->stub_config();

		$this->assertSame( 16200, Headers::max_age( $this->now( '2026-03-08 00:30:00' ) ) );
	}

	public function test_min_age_floor(): void {
		$this->stub_config();

		$this->assertSame( 60, Headers::max_age( $this->now( '2026-09-20 23:59:30' ) ) );
	}

	public function test_cap_applies_when_config_hour_is_far(): void {
		$this->stub_config( [ 'cache.max_age_cap_seconds' => 1000 ] );

		// Just after midnight: boundary is ~6 hours away, well over the 1000s cap.
		$this->assertSame( 1000, Headers::max_age( $this->now( '2026-09-20 00:05:00' ) ) );
	}

	public function test_head_request_gets_public_max_age(): void {
		$this->stub_config();

		$value = Headers::for_request(
			[
				'admin'     => false,
				'rest'      => false,
				'feed'      => false,
				'logged_in' => false,
				'method'    => 'HEAD',
			]
		);

		$this->assertNotNull( $value );
		$this->assertStringStartsWith( 'public, max-age=', $value );
	}

	public function test_post_request_gets_no_header(): void {
		$this->stub_config();

		$value = Headers::for_request(
			[
				'admin'     => false,
				'rest'      => false,
				'feed'      => false,
				'logged_in' => false,
				'method'    => 'POST',
			]
		);

		$this->assertNull( $value );
	}

	public function test_ttm_cache_max_age_filter_overrides(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				if ( 'ttm_cache_max_age' === $tag ) {
					return 42;
				}
				return $value;
			}
		);

		$this->assertSame( 42, Headers::max_age( $this->now( '2026-09-20 12:00:00' ) ) );
	}
}
