<?php
/**
 * Integration tests for the P2-06 template shells.
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

class TemplatesShellTest extends TTM_IntegrationTestCase {

	private function render( string $slug ): string {
		$file = locate_template( "templates/{$slug}.html" );
		$this->assertNotSame( '', $file, "Missing template {$slug}" );

		return (string) do_blocks( (string) file_get_contents( $file ) );
	}

	public function test_page_template_has_main_landmark_and_aside(): void {
		$html = $this->render( 'page' );

		$this->assertStringContainsString( 'id="main"', $html );
		$this->assertStringContainsString( '<aside', $html );
	}

	public function test_404_template_renders_not_here_and_search(): void {
		self::factory()->post->create_many( 5, [ 'post_status' => 'publish' ] );

		$html = $this->render( '404' );

		$this->assertStringContainsString( 'Not here.', $html );
		$this->assertStringContainsString( 'wp-block-search', $html );
		$this->assertStringContainsString( 'ttm-series-strip', $html );
		$this->assertSame( 4, substr_count( $html, 'ttm-numbered__row' ) );
		$this->assertStringContainsString( 'ttm-cell-heading is-rail', $html );
	}

	/**
	 * Rule 42: the page container is `.wp-site-blocks`, the wrapper core's template loader
	 * emits around every block template -- so it must be there for `page`, `404` and `index`
	 * (resolved the way the loader does, not via a bare do_blocks() which never wraps).
	 */
	public function test_site_blocks_wrapper_is_present_on_every_template(): void {
		foreach ( [ 'page', '404', 'index' ] as $slug ) {
			$canvas = locate_block_template( '', $slug, [ $slug ] );
			$this->assertNotSame( '', $canvas, "Block template {$slug} did not resolve" );

			$html = get_the_block_template_html();

			$this->assertStringContainsString( 'class="wp-site-blocks"', $html, "{$slug} lacks the site-blocks wrapper" );
		}
	}

	public function test_index_template_renders_posts(): void {
		self::factory()->post->create( [ 'post_title' => 'Hello Index' ] );
		$this->go_to( '/' );

		$html = $this->render( 'index' );

		$this->assertStringContainsString( 'Hello Index', $html );
	}

	public function test_page_404_index_render_exactly_one_header_and_footer_landmark(): void {
		// Regression: 404.html/index.html/page.html used to wrap the header-inner/footer
		// template-part references with their own `"tagName":"header"/"footer"`, nesting a
		// second landmark around the part's own <header>/<footer> element.
		foreach ( [ 'page', '404', 'index' ] as $slug ) {
			$html = $this->render( $slug );

			$this->assertSame( 1, substr_count( $html, '<header' ), "{$slug}.html renders more than one <header landmark" );
			$this->assertSame( 1, substr_count( $html, '<footer' ), "{$slug}.html renders more than one <footer landmark" );
		}
	}
}
