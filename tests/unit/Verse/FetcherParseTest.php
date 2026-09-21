<?php
/**
 * Unit tests for TTM\Core\Verse\Fetcher::parse_item()'s timezone handling.
 *
 * @package TTM\Tests\Unit\Verse
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Verse;

use Brain\Monkey\Functions;
use TTM\Core\Config;
use TTM\Core\Verse\Fetcher;
use TTM\Tests\Unit\TestCase;

class FetcherParseTest extends TestCase {

	protected function tearDown(): void {
		Config::reset();
		parent::tearDown();
	}

	protected function setUp(): void {
		parent::setUp();

		// The base TestCase stubs wp_timezone() to America/Los_Angeles (UTC-7 in September,
		// PDT).
		Functions\when( 'wp_kses' )->alias(
			// phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsTwoParameters -- shim for the real wp_kses(), not loaded in this WordPress-free unit test.
			static function ( string $value, array $allowed ): string {
				return trim( strip_tags( $value, '<' . implode( '><', array_keys( $allowed ) ) . '>' ) );
			}
		);
	}

	public function test_published_at_in_utc_maps_to_site_local_date(): void {
		// 00:30 UTC on the 20th is 17:30 the *previous* day in America/Los_Angeles (UTC-7 in
		// September) -- Clock::at()'s $tz parameter is silently ignored by PHP whenever the
		// datetime string itself carries an explicit offset (like this "+00:00"), so
		// parse_item() must explicitly convert to the site timezone before taking Y-m-d.
		$item = [
			'id'                  => 'utc-item',
			'title'               => 'Hope',
			'scripture_reference' => 'Psalm 33:20',
			'scripture_text'      => 'We wait in hope.',
			'published_at'        => '2026-09-20T00:30:00+00:00',
			'copyright_notice'    => 'Copyright.',
		];

		$mapped = Fetcher::parse_item( $item );

		$this->assertSame( '2026-09-19', $mapped['date'] );
	}

	public function test_published_at_already_in_site_timezone_is_unaffected(): void {
		$item = [
			'id'                  => 'local-item',
			'title'               => 'Hope',
			'scripture_reference' => 'Psalm 33:20',
			'scripture_text'      => 'We wait in hope.',
			'published_at'        => '2026-09-20T05:00:00-07:00',
			'copyright_notice'    => 'Copyright.',
		];

		$mapped = Fetcher::parse_item( $item );

		$this->assertSame( '2026-09-20', $mapped['date'] );
	}
}
