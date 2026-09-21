<?php
/**
 * Unit tests for TTM\Core\Blocks\Helpers.
 *
 * @package TTM\Tests\Unit\Blocks
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use TTM\Core\Blocks\Helpers;
use TTM\Tests\Unit\TestCase;

class HelpersTest extends TestCase {

	public function test_status_word_maps_three_statuses(): void {
		$this->assertSame( 'In progress', Helpers::status_word( 'in-progress' ) );
		$this->assertSame( 'Complete', Helpers::status_word( 'complete' ) );
		$this->assertSame( 'On hiatus', Helpers::status_word( 'hiatus' ) );
	}

	public function test_wrapper_contains_block_name_and_state_classes(): void {
		Functions\when( 'get_block_wrapper_attributes' )->alias(
			static function ( array $attrs = [] ): string {
				$out = '';
				foreach ( $attrs as $key => $value ) {
					$out .= sprintf( ' %s="%s"', $key, $value );
				}
				return trim( $out );
			}
		);

		$html = Helpers::wrapper( 'verse', [ 'is-empty' ] );

		$this->assertStringContainsString( 'ttm-verse', $html );
		$this->assertStringContainsString( 'is-empty', $html );
		$this->assertStringContainsString( 'data-ttm-block="verse"', $html );
	}
}
