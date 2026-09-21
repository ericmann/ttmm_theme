<?php
/**
 * Unit tests for TTM\Core\Fiction\Books::sanitize().
 *
 * @package TTM\Tests\Unit\Fiction
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Fiction;

use Brain\Monkey\Functions;
use TTM\Core\Fiction\Books;
use TTM\Tests\Unit\TestCase;

class BooksTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
	}

	public function test_sanitize_drops_blank_rows(): void {
		$rows = Books::sanitize(
			[
				[ 'title' => 'Real Book' ],
				[ 'title' => '' ],
				[],
			]
		);

		$this->assertCount( 1, $rows );
		$this->assertSame( 'Real Book', $rows[0]['title'] );
	}

	public function test_sanitize_casts_year_and_cover_id(): void {
		$rows = Books::sanitize(
			[
				[
					'title'    => 'Book',
					'year'     => '2020',
					'cover_id' => '5',
				],
			]
		);

		$this->assertSame( 2020, $rows[0]['year'] );
		$this->assertSame( 5, $rows[0]['cover_id'] );
	}

	public function test_sanitize_rejects_javascript_links(): void {
		$rows = Books::sanitize(
			[
				[
					'title' => 'Book',
					'links' => [
						[
							'label' => 'Bad',
							'url'   => 'javascript:alert(1)',
						],
						[
							'label' => 'Good',
							'url'   => 'https://example.com',
						],
					],
				],
			]
		);

		$this->assertCount( 1, $rows[0]['links'] );
		$this->assertSame( 'https://example.com', $rows[0]['links'][0]['url'] );
	}

	public function test_sanitize_caps_rows_at_config_max(): void {
		$rows = [];
		for ( $i = 0; $i < 20; $i++ ) {
			$rows[] = [ 'title' => "Book {$i}" ];
		}

		$result = Books::sanitize( $rows );

		$this->assertCount( 12, $result );
	}
}
