<?php
/**
 * Schedules the daily verse fetch, with a single retry on failure.
 *
 * @package TTM\Core\Verse
 */

declare( strict_types=1 );

namespace TTM\Core\Verse;

use DateInterval;
use DateTimeImmutable;
use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * `ttm_verse_fetch` daily at `verse.fetch_hour`:00 site time; `ttm_verse_retry` once after a
 * failure, `verse.retry_delay_seconds` later.
 */
class Cron {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'schedule' ] );
		add_action( 'ttm_verse_fetch', [ self::class, 'run_fetch' ] );
		add_action( 'ttm_verse_retry', [ self::class, 'run_retry' ] );
	}

	/**
	 * Schedule the daily fetch event if it isn't already scheduled.
	 */
	public static function schedule(): void {
		if ( false !== wp_next_scheduled( 'ttm_verse_fetch' ) ) {
			return;
		}

		$hour = (int) Config::get( 'verse.fetch_hour', 5 );
		$next = self::next_run( Clock::now(), $hour );

		wp_schedule_event( $next->getTimestamp(), 'daily', 'ttm_verse_fetch' );
	}

	/**
	 * The next occurrence of `$hour`:00 site time, at or after `$now`.
	 *
	 * @param DateTimeImmutable $now  Reference "now" (site timezone).
	 * @param int               $hour Hour of day (0-23), site time.
	 * @return DateTimeImmutable
	 */
	public static function next_run( DateTimeImmutable $now, int $hour ): DateTimeImmutable {
		$candidate = $now->setTime( $hour, 0, 0 );

		if ( $candidate <= $now ) {
			$candidate = $candidate->add( new DateInterval( 'P1D' ) );
		}

		return $candidate;
	}

	/**
	 * `ttm_verse_fetch`: fetch, and schedule a retry on failure.
	 */
	public static function run_fetch(): void {
		$result = Fetcher::fetch();

		if ( ! $result['ok'] ) {
			self::maybe_schedule_retry();
		}
	}

	/**
	 * `ttm_verse_retry`: one retry attempt; a second failure only logs (Fetcher::fetch() already
	 * logs it), it does not schedule another retry.
	 */
	public static function run_retry(): void {
		Fetcher::fetch();
	}

	/**
	 * Schedule a single retry, `verse.retry_delay_seconds` from now, unless one is pending.
	 */
	private static function maybe_schedule_retry(): void {
		if ( false !== wp_next_scheduled( 'ttm_verse_retry' ) ) {
			return;
		}

		$delay = (int) Config::get( 'verse.retry_delay_seconds', 7200 );

		wp_schedule_single_event( Clock::now()->getTimestamp() + $delay, 'ttm_verse_retry' );
	}
}
