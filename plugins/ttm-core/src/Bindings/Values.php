<?php
/**
 * Pure value functions for the front-page block-binding sources (SPEC §6.3, 03 §13).
 *
 * @package TTM\Core\Bindings
 */

declare( strict_types=1 );

namespace TTM\Core\Bindings;

use DateTimeImmutable;
use TTM\Core\Support\Dates;
use TTM\Core\Support\Html;

/**
 * No WordPress data reads: every input is already-resolved arrays/DateTimeImmutable so these
 * are directly unit-testable (SPEC rule 9 compliant: no clock reads here, callers pass `$now`).
 */
class Values {

	/**
	 * "Technology · Series: Hardening WordPress, part 3 of 6"; open-ended → "part 3";
	 * `is_politics` → "Opinion · Politics".
	 *
	 * @param array{section_name?: string, series_name?: string, part?: int, total?: int|null, is_politics?: bool} $ctx Context.
	 * @return string
	 */
	public static function kicker( array $ctx ): string {
		$section = (string) ( $ctx['section_name'] ?? '' );

		if ( ! empty( $ctx['is_politics'] ) ) {
			return sprintf( '%1$s · %2$s', $section, __( 'Politics', 'ttm-core' ) );
		}

		if ( ! empty( $ctx['series_name'] ) ) {
			$part  = (int) ( $ctx['part'] ?? 0 );
			$total = $ctx['total'] ?? null;

			$part_label = null !== $total
				? sprintf(
					/* translators: 1: part number, 2: total parts. */
					__( 'part %1$d of %2$d', 'ttm-core' ),
					$part,
					(int) $total
				)
				: sprintf(
					/* translators: %d: part number. */
					__( 'part %d', 'ttm-core' ),
					$part
				);

			return sprintf(
				/* translators: 1: section name, 2: series name, 3: "part N [of M]". */
				__( '%1$s · Series: %2$s, %3$s', 'ttm-core' ),
				$section,
				(string) $ctx['series_name'],
				$part_label
			);
		}//end if

		return $section;
	}

	/**
	 * Join the requested parts (`date`, `reading`, `prev-part`) with " · "; `prev-part` renders
	 * as a link "Part 2: {title}" (the only meta-line part that may carry HTML).
	 *
	 * @param string[]                                                                                      $parts Requested parts, in order.
	 * @param array{date?: string, reading?: string, prev_part?: array{part:int, title:string, url:string}} $ctx Context.
	 * @return string
	 */
	public static function meta_line( array $parts, array $ctx ): string {
		$pieces = [];

		foreach ( $parts as $part ) {
			switch ( $part ) {
				case 'date':
					if ( ! empty( $ctx['date'] ) ) {
						$pieces[] = (string) $ctx['date'];
					}
					break;
				case 'reading':
					if ( ! empty( $ctx['reading'] ) ) {
						$pieces[] = (string) $ctx['reading'];
					}
					break;
				case 'prev-part':
					if ( ! empty( $ctx['prev_part']['url'] ) ) {
						$prev  = $ctx['prev_part'];
						$label = sprintf(
							/* translators: 1: previous part number, 2: previous part's title. */
							__( 'Part %1$d: %2$s', 'ttm-core' ),
							(int) ( $prev['part'] ?? 0 ),
							(string) ( $prev['title'] ?? '' )
						);
						$pieces[] = Html::link( (string) $prev['url'], $label );
					}
					break;
			}//end switch
		}//end foreach

		return implode( ' · ', $pieces );
	}

	/**
	 * "Sept 20", or "Jul 30, 2025" when the year differs from `$now`.
	 *
	 * @param DateTimeImmutable $d   Date.
	 * @param DateTimeImmutable $now Reference "now".
	 * @return string
	 */
	public static function short_date( DateTimeImmutable $d, DateTimeImmutable $now ): string {
		return Dates::short( $d, $now );
	}

	/**
	 * "Today · Sept 20" / "Yesterday · Sept 19" / "Thursday · Sept 17" (within `$window` days) /
	 * "Sept 3" (within `$rail_window` days) / "Aug 3, 2026" (older — always with year, even the
	 * current year; Decisions, P3-05).
	 *
	 * @param DateTimeImmutable $d           Date.
	 * @param DateTimeImmutable $now         Reference "now".
	 * @param int               $window      Days that still get a weekday name (journal.relative_day_window).
	 * @param int               $rail_window Days that still get a bare short date (journal.rail_window_days).
	 * @return string
	 */
	public static function relative_date( DateTimeImmutable $d, DateTimeImmutable $now, int $window, int $rail_window ): string {
		$label = Dates::relative_day( $d, $now, $window );

		if ( null !== $label ) {
			return $label . ' · ' . Dates::short( $d, $now );
		}

		if ( Dates::days_between( $d, $now ) <= $rail_window ) {
			return Dates::short( $d, $now );
		}

		return Dates::short_with_year( $d );
	}

	/**
	 * "431 articles →" / "431 →"; 0 → "All →" (F25).
	 *
	 * @param int    $count  Post count.
	 * @param string $format `articles` or `short`.
	 * @return string
	 */
	public static function category_count( int $count, string $format ): string {
		if ( 0 === $count ) {
			return __( 'All →', 'ttm-core' );
		}

		if ( 'short' === $format ) {
			/* translators: %d: post count. */
			return sprintf( __( '%d →', 'ttm-core' ), $count );
		}

		return sprintf(
			/* translators: %d: post count. */
			_n( '%d article →', '%d articles →', $count, 'ttm-core' ),
			$count
		);
	}

	/**
	 * `masthead` → "Sunday, September 20, 2026"; `compact` → "Sun, Sept 20, 2026"; `year` → "2026".
	 *
	 * @param DateTimeImmutable $now    Reference "now".
	 * @param string            $format `masthead`, `compact`, or `year`.
	 * @return string
	 */
	public static function today( DateTimeImmutable $now, string $format ): string {
		switch ( $format ) {
			case 'compact':
				return Dates::compact( $now );
			case 'year':
				return $now->format( 'Y' );
			case 'masthead':
			default:
				return Dates::masthead( $now );
		}
	}
}
