<?php
/**
 * Integration tests for TTM\Core\Verse\Cron.
 *
 * @package TTM\Tests\Integration\Verse
 */

declare( strict_types=1 );

use TTM\Core\Config;
use TTM\Core\Support\Clock;
use TTM\Core\Verse\Cron;

class CronTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		wp_clear_scheduled_hook( 'ttm_verse_fetch' );
		wp_clear_scheduled_hook( 'ttm_verse_retry' );
		remove_all_filters( 'pre_http_request' );
		delete_option( 'ttm_verse' );
		delete_option( 'ttm_verse_log' );
		parent::tear_down();
	}

	public function test_init_schedules_daily_fetch_at_fetch_hour_site_time(): void {
		wp_clear_scheduled_hook( 'ttm_verse_fetch' );
		$this->set_now( '2026-09-20 12:00:00' );

		do_action( 'init' );

		$expected = Cron::next_run( Clock::now(), (int) Config::get( 'verse.fetch_hour' ) );

		$this->assertSame( $expected->getTimestamp(), wp_next_scheduled( 'ttm_verse_fetch' ) );
		$this->assertSame( '2026-09-21 05:00:00', $expected->format( 'Y-m-d H:i:s' ) );
	}

	public function test_failure_schedules_single_retry(): void {
		wp_clear_scheduled_hook( 'ttm_verse_retry' );
		$this->set_now( '2026-09-20 12:00:00' );

		add_filter(
			'pre_http_request',
			static fn () => new WP_Error( 'http_request_failed', 'Connection failed.' )
		);

		do_action( 'ttm_verse_fetch' );

		$scheduled = wp_next_scheduled( 'ttm_verse_retry' );
		$this->assertNotFalse( $scheduled );

		$expected = Clock::now()->getTimestamp() + (int) Config::get( 'verse.retry_delay_seconds' );
		$this->assertSame( $expected, $scheduled );
	}

	public function test_second_failure_does_not_schedule_again(): void {
		wp_clear_scheduled_hook( 'ttm_verse_retry' );

		add_filter(
			'pre_http_request',
			static fn () => new WP_Error( 'http_request_failed', 'Connection failed.' )
		);

		do_action( 'ttm_verse_retry' );

		$this->assertFalse( wp_next_scheduled( 'ttm_verse_retry' ) );
	}
}
