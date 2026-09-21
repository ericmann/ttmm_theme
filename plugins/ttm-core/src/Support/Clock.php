<?php
/**
 * The only file in the plugin that constructs DateTimeImmutable/DateTime (SPEC §3.2 rule 9).
 *
 * @package TTM\Core\Support
 */

declare( strict_types=1 );

namespace TTM\Core\Support;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * All "now" reads go through Clock so tests and the verse/cache logic can control time.
 */
class Clock {

	/**
	 * The current moment in the site timezone, filterable for tests.
	 *
	 * @return DateTimeImmutable
	 */
	public static function now(): DateTimeImmutable {
		return apply_filters( 'ttm_now', new DateTimeImmutable( 'now', self::timezone() ) );
	}

	/**
	 * The site timezone.
	 *
	 * @return DateTimeZone
	 */
	public static function timezone(): DateTimeZone {
		return wp_timezone();
	}

	/**
	 * Today's date in the site timezone, `Y-m-d`.
	 *
	 * @return string
	 */
	public static function today(): string {
		return self::now()->format( 'Y-m-d' );
	}

	/**
	 * Parse a MySQL/ISO datetime string in the site (or given) timezone.
	 *
	 * @param string            $datetime Datetime string.
	 * @param DateTimeZone|null $tz      Timezone override.
	 * @return DateTimeImmutable|null Null on failure.
	 */
	public static function at( string $datetime, ?DateTimeZone $tz = null ): ?DateTimeImmutable {
		try {
			$parsed = new DateTimeImmutable( $datetime, $tz ?? self::timezone() );
		} catch ( Exception $e ) {
			return null;
		}

		return $parsed;
	}

	/**
	 * Build a DateTimeImmutable from a unix timestamp, in the site timezone.
	 *
	 * @param int $ts Unix timestamp.
	 * @return DateTimeImmutable
	 */
	public static function from_timestamp( int $ts ): DateTimeImmutable {
		return ( new DateTimeImmutable( '@' . $ts ) )->setTimezone( self::timezone() );
	}
}
