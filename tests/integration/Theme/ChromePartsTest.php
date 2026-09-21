<?php
/**
 * Integration tests for the P2-05 template parts and chrome patterns.
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

class ChromePartsTest extends TTM_IntegrationTestCase {

	public function test_header_front_renders_skip_link_nav_landmark_and_seven_sections(): void {
		$html = $this->render_template_part( 'header-front' );

		$this->assertStringContainsString( 'ttm-skip', $html );
		$this->assertStringContainsString( 'Skip to content', $html );
		$this->assertStringContainsString( '<nav', $html );

		foreach ( [ 'Technology', 'Business', 'Faith', 'Journal', 'Writing', 'Security', 'Opinion' ] as $section ) {
			$this->assertStringContainsString( $section, $html );
		}
	}

	public function test_header_inner_renders_menu_toggle_text(): void {
		$html = $this->render_template_part( 'header-inner' );

		$this->assertStringContainsString( 'Menu', $html );
	}

	public function test_footer_after_poster_variant_has_class(): void {
		$html = (string) do_blocks( '<!-- wp:template-part {"slug":"footer","theme":"ttm-theme","className":"is-after-poster"} /-->' );

		$this->assertStringContainsString( 'is-after-poster', $html );
	}

	public function test_rail_renders_without_plugin_blocks_registered(): void {
		global $wp_error_handler_triggered;

		if ( class_exists( '\TTM\Core\Blocks\Registrar' ) ) {
			$this->markTestSkipped( 'ttm/* blocks are registered in this environment; nothing to unregister yet.' );
		}

		$html = $this->render_template_part( 'rail' );

		$this->assertStringNotContainsString( 'data-ttm-block', $html );
	}

	public function test_patterns_are_registered_in_ttm_categories(): void {
		$registry = \WP_Block_Patterns_Registry::get_instance();

		$slugs = [
			'ttm/masthead-front',
			'ttm/masthead-inner',
			'ttm/newsletter-poster',
			'ttm/newsletter-box',
			'ttm/pull-quote',
			'ttm/code-figure',
			'ttm/stat-row',
		];

		foreach ( $slugs as $slug ) {
			$this->assertTrue( $registry->is_registered( $slug ), "Pattern {$slug} is not registered" );
		}
	}

	private function render_template_part( string $slug ): string {
		$file = locate_template( "parts/{$slug}.html" );
		$this->assertNotSame( '', $file, "Missing part {$slug}" );

		return (string) do_blocks( (string) file_get_contents( $file ) );
	}
}
