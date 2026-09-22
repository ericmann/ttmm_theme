<?php
/**
 * Unit tests for TTM\Core\Bindings\Values.
 *
 * @package TTM\Tests\Unit\Bindings
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Bindings;

use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Bindings\Values;
use TTM\Tests\Unit\TestCase;

class ValuesTest extends TestCase {

	private function date( string $s ): DateTimeImmutable {
		return new DateTimeImmutable( $s, new DateTimeZone( 'America/Los_Angeles' ) );
	}

	public function test_kicker_with_series_and_total(): void {
		$kicker = Values::kicker(
			[
				'section_name' => 'Technology',
				'series_name'  => 'Hardening WordPress',
				'part'         => 3,
				'total'        => 6,
			]
		);

		$this->assertSame( 'Technology · Series: Hardening WordPress, part 3 of 6', $kicker );
	}

	public function test_kicker_open_ended_series(): void {
		$kicker = Values::kicker(
			[
				'section_name' => 'Writing',
				'series_name'  => 'The Quiet Ledger',
				'part'         => 3,
				'total'        => null,
			]
		);

		$this->assertSame( 'Writing · Series: The Quiet Ledger, part 3', $kicker );
	}

	public function test_kicker_politics_suffix(): void {
		$kicker = Values::kicker(
			[
				'section_name' => 'Opinion',
				'is_politics'  => true,
			]
		);

		$this->assertSame( 'Opinion · Politics', $kicker );
	}

	/**
	 * Rule 26: kicker's empty value -- no section, not politics, no series -- is ''.
	 */
	public function test_kicker_empty_without_section(): void {
		$this->assertSame( '', Values::kicker( [] ) );
	}

	public function test_meta_line_joins_requested_parts_with_link(): void {
		$line = Values::meta_line(
			[ 'date', 'reading', 'prev-part' ],
			[
				'date'      => 'Sept 20',
				'reading'   => '5 min read',
				'prev_part' => [
					'part'  => 2,
					'title' => 'Two',
					'url'   => 'https://example.com/two',
				],
			]
		);

		$this->assertSame(
			'Sept 20 · 5 min read · <a href="https://example.com/two">Part 2: Two</a>',
			$line
		);
	}

	/**
	 * Rule 26: meta_line's empty value -- no requested parts, no politics flag -- is ''.
	 */
	public function test_meta_line_empty_without_parts(): void {
		$this->assertSame( '', Values::meta_line( [], [] ) );
	}

	public function test_short_date_adds_year_when_not_current(): void {
		$result = Values::short_date( $this->date( '2025-07-30' ), $this->date( '2026-09-20' ) );

		$this->assertSame( 'Jul 30, 2025', $result );
	}

	public function test_relative_date_today_yesterday_weekday_short_and_full(): void {
		$now = $this->date( '2026-09-20 12:00:00' );

		$this->assertSame( 'Today · Sept 20', Values::relative_date( $this->date( '2026-09-20' ), $now, 6, 30 ) );
		$this->assertSame( 'Yesterday · Sept 19', Values::relative_date( $this->date( '2026-09-19' ), $now, 6, 30 ) );
		$this->assertSame( 'Thursday · Sept 17', Values::relative_date( $this->date( '2026-09-17' ), $now, 6, 30 ) );
		$this->assertSame( 'Sept 3', Values::relative_date( $this->date( '2026-09-03' ), $now, 6, 30 ) );
		$this->assertSame( 'Aug 3, 2026', Values::relative_date( $this->date( '2026-08-03' ), $now, 6, 30 ) );
	}

	/**
	 * Rule 26: short_date()/relative_date() are pure formatters over an already-resolved
	 * DateTimeImmutable, so there is no "no date" input to hand them at this layer -- the
	 * genuine empty case (`ttm/short-date`/`ttm/relative-date` with no usable post date) is
	 * `Bindings\Sources::short_date()`/`relative_date()` returning '' *before* ever calling
	 * these, which needs `Clock::at()`/`get_post()` and so can't be unit-tested (rule 28: no
	 * WordPress here). What this layer can and does guarantee: both are total functions that
	 * never degrade to blank output, even at the most degenerate boundary they can be given --
	 * the reference date and "now" being the identical instant.
	 */
	public function test_short_and_relative_date_never_blank_for_a_valid_date(): void {
		$now = $this->date( '2026-09-20 12:00:00' );

		$this->assertNotSame( '', Values::short_date( $now, $now ) );
		$this->assertNotSame( '', Values::relative_date( $now, $now, 6, 30 ) );
	}

	public function test_category_count_zero_is_all_arrow(): void {
		$this->assertSame( 'All →', Values::category_count( 0, 'articles' ) );
	}

	public function test_category_count_articles_and_short_formats(): void {
		$this->assertSame( '1 article →', Values::category_count( 1, 'articles' ) );
		$this->assertSame( '5 articles →', Values::category_count( 5, 'articles' ) );
		$this->assertSame( '5 →', Values::category_count( 5, 'short' ) );
	}

	public function test_category_count_entries_format(): void {
		$this->assertSame( 'All 87 entries', Values::category_count( 87, 'entries' ) );
	}

	public function test_category_count_zero_reads_all_arrow_for_every_format(): void {
		$this->assertSame( 'All →', Values::category_count( 0, 'articles' ) );
		$this->assertSame( 'All →', Values::category_count( 0, 'short' ) );
		$this->assertSame( 'All →', Values::category_count( 0, 'entries' ) );
	}

	public function test_today_formats(): void {
		$now = $this->date( '2026-09-20' );

		$this->assertSame( 'Sunday, September 20, 2026', Values::today( $now, 'masthead' ) );
		$this->assertSame( 'Sun, Sept 20, 2026', Values::today( $now, 'compact' ) );
		$this->assertSame( '2026', Values::today( $now, 'year' ) );
	}

	public function test_footer_line_joins_site_year_author_and_platform(): void {
		$this->assertSame(
			'These Things Matter · © 2026 Eric Mann · Built on WordPress',
			Values::footer_line( 'These Things Matter', '2026' )
		);
	}
}
