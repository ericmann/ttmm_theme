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

	public function test_featured_caption_inserts_figcaption_before_closing_figure(): void {
		Functions\stubs( [ 'esc_html' ] );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 34 );
		Functions\when( 'wp_get_attachment_caption' )->justReturn( 'Photographs are grayscale only on the front page.' );

		$html = Helpers::featured_caption( '<figure class="wp-block-post-featured-image"><img src="x.png" alt=""></figure>' );

		$this->assertSame(
			'<figure class="wp-block-post-featured-image"><img src="x.png" alt=""><figcaption class="ttm-hero__caption">Photographs are grayscale only on the front page.</figcaption></figure>',
			$html
		);
	}

	public function test_featured_caption_leaves_figure_without_caption_alone(): void {
		Functions\stubs( [ 'esc_html' ] );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 34 );
		Functions\when( 'wp_get_attachment_caption' )->justReturn( '' );

		$figure = '<figure class="wp-block-post-featured-image"><img src="x.png" alt=""></figure>';

		$this->assertSame( $figure, Helpers::featured_caption( $figure ) );
	}
}
