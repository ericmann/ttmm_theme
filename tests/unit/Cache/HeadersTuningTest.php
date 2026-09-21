<?php
/**
 * Tuning verification for cache.verse_boundary_hour / cache.max_age_cap_seconds /
 * cache.min_age_seconds against the verse fetch/retry schedule (SPEC §5.4, §6.10).
 *
 * @package TTM\Tests\Unit\Cache
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Cache;

use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Cache\Headers;
use TTM\Core\Config;
use TTM\Tests\Unit\TestCase;

class HeadersTuningTest extends TestCase {

	protected function tearDown(): void {
		Config::reset();
		parent::tearDown();
	}

	public function test_max_age_never_exceeds_cap_over_a_full_day(): void {
		$cap = (int) Config::get( 'cache.max_age_cap_seconds', 86400 );
		$tz  = new DateTimeZone( 'America/Los_Angeles' );

		for ( $minutes = 0; $minutes < 24 * 60; $minutes += 15 ) {
			$now = ( new DateTimeImmutable( '2026-09-20 00:00:00', $tz ) )->modify( "+{$minutes} minutes" );

			$this->assertLessThanOrEqual(
				$cap,
				Headers::max_age( $now ),
				"max_age() exceeded the cap at {$now->format( 'H:i' )}"
			);
		}
	}

	public function test_max_age_never_below_min(): void {
		$min = (int) Config::get( 'cache.min_age_seconds', 60 );
		$tz  = new DateTimeZone( 'America/Los_Angeles' );

		for ( $minutes = 0; $minutes < 24 * 60; $minutes += 15 ) {
			$now = ( new DateTimeImmutable( '2026-09-20 00:00:00', $tz ) )->modify( "+{$minutes} minutes" );

			$this->assertGreaterThanOrEqual(
				$min,
				Headers::max_age( $now ),
				"max_age() fell below the floor at {$now->format( 'H:i' )}"
			);
		}
	}

	public function test_token_ttl_is_at_least_max_age_cap(): void {
		$defaults = Config::defaults();

		$this->assertGreaterThanOrEqual(
			$defaults['cache.max_age_cap_seconds'],
			$defaults['newsletter.token_ttl'],
			'newsletter.token_ttl must be at least cache.max_age_cap_seconds so a cached form\'s token is never older than one accepted window.'
		);
	}
}
