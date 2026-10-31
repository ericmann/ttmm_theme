<?php
/**
 * Unit tests for TTM\Theme\register_block_css(), block_css_last() and assets/css/blocks/. No
 * WordPress.
 *
 * @package TTM\Tests\Unit\Theme
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Theme;

use Brain\Monkey\Functions;
use TTM\Tests\Unit\TestCase;

class BlockCssTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_template_directory' )->justReturn( rtrim( TTM_THEME_DIR, '/' ) );
		Functions\when( 'get_template_directory_uri' )->justReturn( 'https://example.test/wp-content/themes/ttm-theme' );
		Functions\when( 'wp_get_theme' )->justReturn(
			new class() {
				public function get( string $key ): string {
					return '9.9.9';
				}
			}
		);
		require_once TTM_THEME_DIR . 'inc/block-css.php';
	}

	public function test_names_come_from_the_blocks_directory(): void {
		$names = \TTM\Theme\block_css_names();

		$this->assertContains( 'writing-cell', $names );
		$this->assertContains( 'series-toc', $names );
		$this->assertSame( $names, array_values( array_unique( $names ) ) );
		foreach ( $names as $name ) {
			$this->assertFileExists( dirname( TTM_THEME_DIR, 2 ) . '/plugins/ttm-core/blocks/' . $name . '/block.json', "assets/css/blocks/{$name}.css names no ttm-core block" );
		}
	}

	public function test_each_file_is_attached_to_its_block_on_the_front_end(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		$seen = [];
		Functions\expect( 'wp_enqueue_block_style' )
			->times( count( \TTM\Theme\block_css_names() ) )
			->andReturnUsing(
				static function ( string $block, array $args ) use ( &$seen ): void {
					$seen[ $block ] = $args;
				}
			);

		\TTM\Theme\register_block_css();

		$args = $seen['ttm/writing-cell'] ?? [];
		$this->assertSame( 'ttm-block-writing-cell', $args['handle'] ?? null );
		$this->assertStringEndsWith( '/assets/css/blocks/writing-cell.css', $args['src'] ?? '' );
		$this->assertFileExists( $args['path'] ?? '' );
		$this->assertStringStartsWith( '9.9.9.', $args['ver'] ?? '' );
	}

	public function test_the_editor_gets_the_files_as_editor_styles_instead(): void {
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\expect( 'wp_enqueue_block_style' )->never();

		\TTM\Theme\register_block_css();

		$this->assertTrue( true, 'no block-style registration in the editor; verified in tearDown()' );
	}

	public function test_queued_block_styles_move_behind_ttm_css(): void {
		Functions\when( 'wp_style_is' )->alias( static fn ( string $handle ): bool => 'ttm-block-series-toc' === $handle );
		Functions\expect( 'wp_dequeue_style' )->once()->with( 'ttm-block-series-toc' );
		Functions\expect( 'wp_enqueue_style' )->once()->with( 'ttm-block-series-toc' );

		\TTM\Theme\block_css_last();

		$this->assertTrue( true, 'dequeue/enqueue expectations verified in tearDown()' );
	}
}
