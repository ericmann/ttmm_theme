<?php
/**
 * Unit tests for TTM\Core\Verse\Fetcher's pure parsing logic.
 *
 * @package TTM\Tests\Unit\Verse
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Verse;

use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Config;
use TTM\Core\Verse\Fetcher;
use TTM\Tests\Unit\TestCase;

class FetcherTest extends TestCase {

	protected function tearDown(): void {
		Config::reset();
		parent::tearDown();
	}

	private function now( string $datetime = '2026-09-20 12:00:00' ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'America/Los_Angeles' ) );
	}

	private function fixture(): array {
		$path = dirname( __DIR__, 3 ) . '/docs/fixtures/verse-sample.json';

		return json_decode( (string) file_get_contents( $path ), true );
	}

	protected function setUp(): void {
		parent::setUp();

		// phpcs:disable WordPress.WP.AlternativeFunctions.parse_url_parse_url -- shim for the real wp_parse_url(), not loaded in this WordPress-free unit test.
		Functions\when( 'wp_parse_url' )->alias(
			static fn ( string $url, ?int $component = -1 ) => -1 === $component ? parse_url( $url ) : parse_url( $url, $component )
		);
		// phpcs:enable WordPress.WP.AlternativeFunctions.parse_url_parse_url
		Functions\when( 'wp_kses' )->alias(
			// phpcs:ignore WordPressVIPMinimum.Functions.StripTags.StripTagsTwoParameters -- shim for the real wp_kses(), not loaded in this WordPress-free unit test.
			static function ( string $value, array $allowed ): string {
				return trim( strip_tags( $value, '<' . implode( '><', array_keys( $allowed ) ) . '>' ) );
			}
		);
	}

	public function test_parse_picks_todays_item_in_site_timezone(): void {
		$payload = $this->fixture();

		$verse = Fetcher::parse( $payload, $this->now( '2026-09-20 12:00:00' ) );

		$this->assertNotNull( $verse );
		$this->assertSame( '2026-09-20', $verse['date'] );
		$this->assertSame( 'Psalm 33:20-22', $verse['reference'] );
	}

	public function test_parse_falls_back_to_newest_item_not_after_today(): void {
		$payload = $this->fixture();

		$verse = Fetcher::parse( $payload, $this->now( '2026-09-25 12:00:00' ) );

		$this->assertNotNull( $verse );
		$this->assertSame( '2026-09-20', $verse['date'] );
	}

	public function test_parse_returns_null_when_all_items_are_future(): void {
		$payload = $this->fixture();

		$verse = Fetcher::parse( $payload, $this->now( '2020-01-01 00:00:00' ) );

		$this->assertNull( $verse );
	}

	public function test_parse_item_maps_fields_and_builds_url(): void {
		$item = [
			'id'                  => 'abc123',
			'title'               => 'Hope',
			'scripture_reference' => 'Psalm 33:20',
			'scripture_text'      => 'We wait in hope.',
			'published_at'        => '2026-09-20T05:00:00-07:00',
			'copyright_notice'    => 'Copyright.',
		];

		$mapped = Fetcher::parse_item( $item );

		$this->assertSame( '2026-09-20', $mapped['date'] );
		$this->assertSame( 'We wait in hope.', $mapped['text'] );
		$this->assertSame( 'Psalm 33:20', $mapped['reference'] );
		$this->assertSame( 'Hope', $mapped['title'] );
		$this->assertSame( 'https://dailymedtoday.com/meditation/abc123', $mapped['url'] );
		$this->assertSame( 'abc123', $mapped['source_id'] );
		$this->assertSame( 'Copyright.', $mapped['copyright'] );
	}

	public function test_parse_strips_disallowed_html_from_text(): void {
		$item = [
			'id'                  => 'abc123',
			'title'               => 'Hope',
			'scripture_reference' => 'Psalm 33:20',
			'scripture_text'      => '<script>alert(1)</script><em>We</em> <strong>wait</strong> <a href="#">in hope</a>.',
			'published_at'        => '2026-09-20T05:00:00-07:00',
			'copyright_notice'    => 'Copyright.',
		];

		$mapped = Fetcher::parse_item( $item );

		$this->assertStringNotContainsString( '<script>', $mapped['text'] );
		$this->assertStringNotContainsString( '<a ', $mapped['text'] );
		$this->assertStringContainsString( '<em>We</em>', $mapped['text'] );
		$this->assertStringContainsString( '<strong>wait</strong>', $mapped['text'] );
	}

	public function test_endpoint_constant_matches_config_default(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'apply_filters' )->returnArg( 2 );

		$this->assertSame( Fetcher::ENDPOINT, Fetcher::endpoint() );
	}

	public function test_endpoint_ignores_config_with_foreign_host(): void {
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				if ( 'ttm_config' === $tag ) {
					$value['verse.endpoint'] = 'https://evil.example.com/api/';
				}
				return $value;
			}
		);

		$this->assertSame( Fetcher::ENDPOINT, Fetcher::endpoint() );
	}
}
