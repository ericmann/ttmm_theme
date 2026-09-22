<?php
/**
 * Unit tests for the P4-04 additions to TTM\Core\Bindings\Values.
 *
 * @package TTM\Tests\Unit\Bindings
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Bindings;

use DateTimeImmutable;
use DateTimeZone;
use TTM\Core\Bindings\Values;
use TTM\Tests\Unit\TestCase;

class ArticleValuesTest extends TestCase {

	private function date( string $s ): DateTimeImmutable {
		return new DateTimeImmutable( $s, new DateTimeZone( 'America/Los_Angeles' ) );
	}

	public function test_reading_time_long_and_short(): void {
		$this->assertSame( '14 min read', Values::reading_time( 3220, 230, 'long', false ) );
		$this->assertSame( '14 min', Values::reading_time( 3220, 230, 'short', false ) );
	}

	public function test_reading_time_empty_for_journal(): void {
		$this->assertSame( '', Values::reading_time( 3220, 230, 'long', true ) );
	}

	public function test_word_count_singular_and_plural(): void {
		$this->assertSame( '1 word', Values::word_count( 1 ) );
		$this->assertSame( '248 words', Values::word_count( 248 ) );
	}

	public function test_word_count_zero_is_empty(): void {
		$this->assertSame( '', Values::word_count( 0 ) );
	}

	public function test_word_count_when_unsyndicated_flag(): void {
		$this->assertSame( '', Values::word_count( 248, true ) );
		$this->assertSame( '248 words', Values::word_count( 248, false ) );
	}

	public function test_journal_subline_with_and_without_location(): void {
		$sunday = $this->date( '2026-09-20' );

		$this->assertSame( 'Sunday · Portland', Values::journal_subline( $sunday, 'Portland' ) );
		$this->assertSame( 'Sunday', Values::journal_subline( $sunday, '' ) );
	}

	public function test_series_name_and_part_empty_without_series(): void {
		$this->assertSame( '', Values::series_name( null ) );
		$this->assertSame( '', Values::series_part( null ) );
	}

	public function test_series_part_open_ended(): void {
		$this->assertSame(
			'Part 3 of 6',
			Values::series_part(
				[
					'part'  => 3,
					'total' => 6,
				]
			)
		);

		$this->assertSame(
			'Part 3',
			Values::series_part(
				[
					'part'  => 3,
					'total' => null,
				]
			)
		);

		$this->assertSame( 'The Quiet Ledger', Values::series_name( [ 'name' => 'The Quiet Ledger' ] ) );
	}
}
