<?php
/**
 * Serialized fiction: active serial, chapters, stats, stories (03 §4).
 *
 * @package TTM\Core\Fiction
 */

declare( strict_types=1 );

namespace TTM\Core\Fiction;

use TTM\Core\Config;
use TTM\Core\Query\SeriesIndex;
use TTM\Core\Query\Stats;
use WP_Query;

/**
 * Static readers over SeriesIndex rows (form !== nonfiction) and `ttm_form=story` posts. No
 * hooks: nothing to register.
 */
class Serials {

	/**
	 * The in-progress fiction row with the newest `last_update`, or null.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function active(): ?array {
		$best = null;

		foreach ( self::fiction_rows() as $row ) {
			if ( 'in-progress' !== $row['status'] ) {
				continue;
			}
			if ( null === $best || $row['last_update'] > $best['last_update'] ) {
				$best = $row;
			}
		}

		return $best;
	}

	/**
	 * Every completed fiction row.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function completed(): array {
		return array_values(
			array_filter(
				self::fiction_rows(),
				static fn ( array $row ): bool => 'complete' === $row['status']
			)
		);
	}

	/**
	 * Whether any serialized fiction or story exists at all (F2 gate).
	 *
	 * @return bool
	 */
	public static function has_any_fiction(): bool {
		if ( ! empty( self::fiction_rows() ) ) {
			return true;
		}

		return Stats::story_count() > 0;
	}

	/**
	 * The published part with the highest part number, or null.
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return array<string, mixed>|null
	 */
	public static function latest_chapter( array $row ): ?array {
		$best = null;

		foreach ( $row['parts'] as $part ) {
			if ( 'publish' !== $part['status'] ) {
				continue;
			}
			if ( null === $best || (int) $part['part'] > (int) $best['part'] ) {
				$best = $part;
			}
		}

		return $best;
	}

	/**
	 * The permalink of chapter 1, or the earliest published chapter when part 1 is missing.
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return string
	 */
	public static function first_chapter_url( array $row ): string {
		foreach ( $row['parts'] as $part ) {
			if ( 1 === (int) $part['part'] && 'publish' === $part['status'] ) {
				return (string) get_permalink( $part['post_id'] );
			}
		}

		$published = array_values(
			array_filter( $row['parts'], static fn ( array $part ): bool => 'publish' === $part['status'] )
		);

		if ( empty( $published ) ) {
			return '';
		}

		usort( $published, static fn ( array $a, array $b ): int => $a['part'] <=> $b['part'] );

		return (string) get_permalink( $published[0]['post_id'] );
	}

	/**
	 * `{published, total, cadence, next_date, avg_minutes}` for a fiction row.
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return array{published:int, total:int, cadence:string, next_date:string, avg_minutes:int}
	 */
	public static function stats( array $row ): array {
		$published_parts = array_values(
			array_filter( $row['parts'], static fn ( array $part ): bool => 'publish' === $part['status'] )
		);
		$published       = count( $published_parts );
		$raw_total       = (int) get_term_meta( $row['id'], 'ttm_total_parts', true );

		$words   = 0;
		$counted = 0;
		foreach ( $published_parts as $part ) {
			$word_count = (int) get_post_meta( $part['post_id'], 'ttm_word_count', true );
			if ( $word_count > 0 ) {
				$words += $word_count;
				++$counted;
			}
		}

		$avg_words   = $counted > 0 ? $words / $counted : 0;
		$avg_minutes = $avg_words > 0 ? (int) round( $avg_words / (int) Config::get( 'reading.words_per_minute', 230 ) ) : 0;

		return [
			'published'   => $published,
			'total'       => $raw_total > 0 ? $raw_total : $published,
			'cadence'     => (string) get_term_meta( $row['id'], 'ttm_cadence', true ),
			'next_date'   => (string) get_term_meta( $row['id'], 'ttm_next_date', true ),
			'avg_minutes' => $avg_minutes,
		];
	}

	/**
	 * Post ids with `ttm_form = story`, newest first, bounded.
	 *
	 * @param int $limit Max results.
	 * @return int[]
	 */
	public static function stories( int $limit ): array {
		$query = new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_key'       => 'ttm_form', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded by posts_per_page.
				'meta_value'     => 'story', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
			]
		);

		return $query->posts;
	}

	/**
	 * Sanitized `ttm_purchase_links` for a series row (already sanitized on save; re-cast here
	 * for callers that only have the raw row array).
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return array<int, array{label:string, url:string}>
	 */
	public static function purchase_links( array $row ): array {
		$links = get_term_meta( $row['id'], 'ttm_purchase_links', true );

		return is_array( $links ) ? $links : [];
	}

	/**
	 * `ttm_cover_id` attachment id for a series row, or 0 when none is set.
	 *
	 * @param array<string, mixed> $row Series index row.
	 * @return int
	 */
	public static function cover_id( array $row ): int {
		return (int) get_term_meta( $row['id'], 'ttm_cover_id', true );
	}

	/**
	 * Series index rows that are fiction (`form !== nonfiction`).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private static function fiction_rows(): array {
		return array_values(
			array_filter(
				SeriesIndex::all(),
				static fn ( array $row ): bool => 'nonfiction' !== $row['form']
			)
		);
	}
}
