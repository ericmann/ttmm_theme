<?php
/**
 * Unit tests for TTM\Core\Taxonomy\Series pure sanitizers.
 *
 * @package TTM\Tests\Unit\Taxonomy
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Taxonomy;

use Brain\Monkey\Functions;
use TTM\Core\Taxonomy\Series;
use TTM\Tests\Unit\TestCase;

class SeriesTest extends TestCase {

	public function test_sanitize_status_falls_back_to_in_progress(): void {
		$this->assertSame( 'complete', Series::sanitize_status( 'complete' ) );
		$this->assertSame( 'in-progress', Series::sanitize_status( 'bogus' ) );
	}

	public function test_sanitize_form_falls_back_to_nonfiction(): void {
		$this->assertSame( 'novel', Series::sanitize_form( 'novel' ) );
		$this->assertSame( 'nonfiction', Series::sanitize_form( 'bogus' ) );
	}

	public function test_sanitize_next_date_rejects_invalid_dates(): void {
		$this->assertSame( '2026-09-20', Series::sanitize_next_date( '2026-09-20' ) );
		$this->assertSame( '', Series::sanitize_next_date( '2026-02-30' ) );
		$this->assertSame( '', Series::sanitize_next_date( 'not-a-date' ) );
		$this->assertSame( '', Series::sanitize_next_date( '' ) );
	}

	public function test_sanitize_purchase_links_drops_javascript_urls_and_caps_count(): void {
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );

		$links = [];
		for ( $i = 0; $i < 10; $i++ ) {
			$links[] = [
				'label' => "Store {$i}",
				'url'   => "https://example.com/{$i}",
			];
		}
		$links[] = [
			'label' => 'Bad',
			'url'   => 'javascript:alert(1)',
		];

		$result = Series::sanitize_purchase_links( $links );

		$this->assertCount( 6, $result );
		foreach ( $result as $entry ) {
			$this->assertStringStartsWith( 'https://', $entry['url'] );
		}
	}
}
