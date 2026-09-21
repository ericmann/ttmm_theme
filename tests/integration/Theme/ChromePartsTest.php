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

	public function test_masthead_nav_uses_category_names(): void {
		$term    = term_exists( 'technology', 'category' );
		$term_id = $term ? (int) $term['term_id'] : (int) wp_insert_term( 'Technology', 'category', [ 'slug' => 'technology' ] )['term_id'];

		wp_update_term( $term_id, 'category', [ 'name' => 'Technology Renamed' ] );

		// WordPress caches theme patterns/*.php's scanned output in a transient keyed by file
		// mtimes, so going through the `ttm/masthead-front` pattern registry (as
		// parts/header-front.html's own `wp:pattern` reference does) would still see the name
		// from whenever that cache was last built, not this test's rename. Executing the
		// pattern file directly proves the same PHP logic without that unrelated cache layer.
		ob_start();
		require get_template_directory() . '/patterns/masthead-front.php';
		$raw = (string) ob_get_clean();

		$html = (string) do_blocks( $raw );

		$this->assertStringContainsString( 'Technology Renamed', $html );
	}

	public function test_header_inner_renders_menu_toggle_text(): void {
		$html = $this->render_template_part( 'header-inner' );

		$this->assertStringContainsString( 'Menu', $html );
	}

	public function test_footer_after_poster_variant_has_class(): void {
		$html = (string) do_blocks( '<!-- wp:template-part {"slug":"footer","theme":"ttm-theme","className":"is-after-poster"} /-->' );

		$this->assertStringContainsString( 'is-after-poster', $html );
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

	public function test_front_masthead_meta_links_are_plain_anchors_not_a_navigation_block(): void {
		ob_start();
		require get_template_directory() . '/patterns/masthead-front.php';
		$raw = (string) ob_get_clean();

		$html = (string) do_blocks( $raw );

		// `do_blocks()` strips the `<!-- wp:… -->` comment delimiters, so the meta group's
		// content runs from its own opening tag to the next sibling group's opening tag
		// (`ttm-masthead-front__title`, always immediately after it in the pattern).
		$start = strpos( $html, 'ttm-masthead-front__meta' );
		$end   = strpos( $html, 'ttm-masthead-front__title', $start );
		$this->assertNotFalse( $start, 'Could not locate .ttm-masthead-front__meta markup' );
		$this->assertNotFalse( $end, 'Could not locate .ttm-masthead-front__title markup' );

		$meta_html = substr( $html, $start, $end - $start );

		$this->assertSame( 3, substr_count( $meta_html, '<a ' ), 'Expected exactly three <a> links in the meta row' );
		$this->assertStringNotContainsString( 'wp-block-navigation', $meta_html );
	}

	public function test_both_mastheads_emit_ttm_nav_hub_class(): void {
		// Checks the pattern's own source markup (pre-`do_blocks()`), not the fully server-side
		// rendered nav -- core's Navigation block keeps request-scoped static caches that get
		// confused when the same pattern is rendered many times across a PHPUnit run (observed:
		// the last inner block silently disappears on a later render), unrelated to this
		// pattern's own correctness, which `wp eval` on a fresh request confirms is fine.
		foreach ( [ 'masthead-front', 'masthead-inner' ] as $slug ) {
			ob_start();
			require get_template_directory() . "/patterns/{$slug}.php";
			$raw = (string) ob_get_clean();

			$this->assertStringContainsString( 'ttm-nav__hub', $raw, "{$slug} should emit ttm-nav__hub" );
			$this->assertStringNotContainsString( 'ttm-nav-series', $raw, "{$slug} should not emit the old ttm-nav-series class" );
		}
	}

	private function render_template_part( string $slug ): string {
		$file = locate_template( "parts/{$slug}.html" );
		$this->assertNotSame( '', $file, "Missing part {$slug}" );

		return (string) do_blocks( (string) file_get_contents( $file ) );
	}
}
