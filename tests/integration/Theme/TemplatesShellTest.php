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
		$html = $this->render( '404' );

		$this->assertStringContainsString( 'Not here.', $html );
		$this->assertStringContainsString( 'wp-block-search', $html );
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
