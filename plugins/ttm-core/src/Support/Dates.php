<?php
/**
 * Pure date formatting helpers. Every method takes DateTimeImmutable arguments (no clock reads, SPEC rule 9).
 *
 * @package TTM\Core\Support
 */

declare( strict_types=1 );

namespace TTM\Core\Support;

use DateTimeImmutable;

/**
 * Pure date formatting helpers.
 */
class Dates {

	/**
	 * Three-letter month, except September which is "Sept".
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return string
	 */
	public static function short_month( DateTimeImmutable $d ): string {
		return '9' === $d->format( 'n' ) ? 'Sept' : $d->format( 'M' );
	}

	/**
	 * "Sept 19", or "Jul 30, 2025" when the year differs from $now.
	 *
	 * @param DateTimeImmutable $d   Date.
	 * @param DateTimeImmutable $now Reference "now".
	 * @return string
	 */
	public static function short( DateTimeImmutable $d, DateTimeImmutable $now ): string {
		$base = self::short_month( $d ) . ' ' . $d->format( 'j' );

		if ( $d->format( 'Y' ) !== $now->format( 'Y' ) ) {
			return $base . ', ' . $d->format( 'Y' );
		}

		return $base;
	}

	/**
	 * "Aug 3, 2026" — always includes the year.
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return string
	 */
	public static function short_with_year( DateTimeImmutable $d ): string {
		return self::short_month( $d ) . ' ' . $d->format( 'j, Y' );
	}

	/**
	 * "September 19, 2026".
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return string
	 */
	public static function full( DateTimeImmutable $d ): string {
		return $d->format( 'F j, Y' );
	}

	/**
	 * "Sun, Sept 20, 2026".
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return string
	 */
	public static function compact( DateTimeImmutable $d ): string {
		return $d->format( 'D' ) . ', ' . self::short_month( $d ) . ' ' . $d->format( 'j, Y' );
	}

	/**
	 * "Sunday, September 20, 2026".
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return string
	 */
	public static function masthead( DateTimeImmutable $d ): string {
		return $d->format( 'l' ) . ', ' . $d->format( 'F j, Y' );
	}

	/**
	 * "Sunday".
	 *
	 * @param DateTimeImmutable $d Date.
	 * @return string
	 */
	public static function weekday( DateTimeImmutable $d ): string {
		return $d->format( 'l' );
	}

	/**
	 * "Today"/"Yesterday"/a weekday name within $window days, else null.
	 *
	 * @param DateTimeImmutable $d      Date to describe.
	 * @param DateTimeImmutable $now    Reference "now".
	 * @param int               $window Days before $now that still get a weekday name.
	 * @return string|null
	 */
	public static function relative_day( DateTimeImmutable $d, DateTimeImmutable $now, int $window ): ?string {
		$diff = self::days_between( $d, $now );

		if ( 0 === $diff ) {
			return __( 'Today', 'ttm-core' );
		}

		if ( 1 === $diff ) {
			return __( 'Yesterday', 'ttm-core' );
		}

		if ( $diff > 1 && $diff <= $window ) {
			return self::weekday( $d );
		}

		return null;
	}

	/**
	 * Calendar days between two dates (positive when $b is after $a), ignoring time of day.
	 *
	 * @param DateTimeImmutable $a Earlier (or later) date.
	 * @param DateTimeImmutable $b Later (or earlier) date.
	 * @return int
	 */
	public static function days_between( DateTimeImmutable $a, DateTimeImmutable $b ): int {
		$a_midnight = $a->setTime( 0, 0, 0 );
		$b_midnight = $b->setTime( 0, 0, 0 );

		return (int) $a_midnight->diff( $b_midnight )->format( '%r%a' );
	}
}
