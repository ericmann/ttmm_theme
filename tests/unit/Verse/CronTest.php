<?php
/**
 * Unit tests for TTM\Core\Verse\Cron::next_run()'s DST-safety.
 *
 * @package TTM\Tests\Unit\Verse
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Verse;

use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Verse\Cron;
use TTM\Tests\Unit\TestCase;

class CronTest extends TestCase {

	private function la( string $datetime ): DateTimeImmutable {
		return new DateTimeImmutable( $datetime, new DateTimeZone( 'America/Los_Angeles' ) );
	}

	/**
	 * US DST spring-forward in 2026 is 2026-03-08 (clocks jump 2am -> 3am, PST -08:00 becomes
	 * PDT -07:00). A naive "add DAY_IN_SECONDS" recurrence (what WP's own `wp_schedule_event(
	 * ..., 'daily', ...)` does, and what Cron used to rely on) would land the fetch at 04:00
	 * local instead of 05:00 once the clocks have shifted. next_run() must instead recompute
	 * the target hour fresh, in the site timezone, keeping 05:00 local on both sides of the
	 * transition.
	 */
	public function test_next_run_keeps_local_fetch_hour_across_dst_transition(): void {
		$before = $this->la( '2026-03-07 12:00:00' ); // the day before the transition, PST.
		$next   = Cron::next_run( $before, 5 );

		$this->assertSame( '2026-03-08 05:00:00 America/Los_Angeles', $next->format( 'Y-m-d H:i:s e' ) );

		// Real elapsed time from noon the day before to 05:00 the day of the transition is 16
		// hours (57600s), not 17 -- a wall-clock hour was skipped by the spring-forward. A
		// naive "+= DAY_IN_SECONDS" recurrence would have no way to reflect that.
		$this->assertSame( 57600, $next->getTimestamp() - $before->getTimestamp() );

		// One more cycle, now starting from the post-transition run: still 05:00 local, and
		// the elapsed wall-clock time is a full 24 hours again (no further DST change).
		$after = Cron::next_run( $next, 5 );

		$this->assertSame( '2026-03-09 05:00:00 America/Los_Angeles', $after->format( 'Y-m-d H:i:s e' ) );
		$this->assertSame( 86400, $after->getTimestamp() - $next->getTimestamp() );
	}

	/**
	 * US DST fall-back in 2026 is 2026-11-01 (clocks repeat 1am-2am, PDT -07:00 becomes PST
	 * -08:00). next_run() must still land on 05:00 local on both sides.
	 */
	public function test_next_run_keeps_local_fetch_hour_across_fall_back_transition(): void {
		$before = $this->la( '2026-10-31 12:00:00' ); // the day before the transition, PDT.
		$next   = Cron::next_run( $before, 5 );

		$this->assertSame( '2026-11-01 05:00:00 America/Los_Angeles', $next->format( 'Y-m-d H:i:s e' ) );

		$after = Cron::next_run( $next, 5 );
		$this->assertSame( '2026-11-02 05:00:00 America/Los_Angeles', $after->format( 'Y-m-d H:i:s e' ) );
	}
}
