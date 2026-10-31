<?php
/**
 * Integration tests for the editor copy of ttm.css (article rules retargeted to the editor's
 * post-content wrapper).
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

class EditorStylesTest extends TTM_IntegrationTestCase {

	public function test_editor_copy_of_ttm_css_targets_the_post_content_wrapper(): void {
		$settings = apply_filters(
			'block_editor_settings_all',
			[
				'styles' => [
					[
						'css'     => '.entry-content p{margin:0 0 22px}',
						'baseURL' => 'http://example.test/wp-content/themes/ttm-theme/assets/css/ttm.css',
					],
					[
						'css'     => '.entry-content p{color:red}',
						'baseURL' => 'http://example.test/wp-content/plugins/other/style.css',
					],
					[ 'css' => '.entry-content{}' ],
				],
			],
			new WP_Block_Editor_Context( [ 'name' => 'core/edit-post' ] )
		);

		$this->assertSame( '.wp-block-post-content p{margin:0 0 22px}', $settings['styles'][0]['css'] );
		// Other stylesheets, and entries without a baseURL, are left alone.
		$this->assertSame( '.entry-content p{color:red}', $settings['styles'][1]['css'] );
		$this->assertSame( '.entry-content{}', $settings['styles'][2]['css'] );
	}

	public function test_real_editor_settings_carry_no_entry_content_selector_in_ttm_css(): void {
		$settings = get_block_editor_settings( [], new WP_Block_Editor_Context( [ 'name' => 'core/edit-post' ] ) );
		$ttm      = array_values(
			array_filter(
				$settings['styles'],
				static fn ( array $style ): bool => str_ends_with( (string) ( $style['baseURL'] ?? '' ), '/assets/css/ttm.css' )
			)
		);

		$this->assertCount( 1, $ttm );
		$this->assertStringNotContainsString( '.entry-content', $ttm[0]['css'] );
		$this->assertStringContainsString( '.wp-block-post-content p', $ttm[0]['css'] );
	}

	public function test_editor_loads_every_block_stylesheet_after_ttm_css(): void {
		$settings = get_block_editor_settings( [], new WP_Block_Editor_Context( [ 'name' => 'core/edit-post' ] ) );
		$urls     = array_map( static fn ( array $style ): string => (string) ( $style['baseURL'] ?? '' ), $settings['styles'] );
		$ttm      = array_key_first( array_filter( $urls, static fn ( string $url ): bool => str_ends_with( $url, '/assets/css/ttm.css' ) ) );
		$blocks   = array_keys( array_filter( $urls, static fn ( string $url ): bool => str_contains( $url, '/assets/css/blocks/' ) ) );

		$this->assertNotNull( $ttm );
		$this->assertCount( count( \TTM\Theme\block_css_names() ), $blocks );
		$this->assertGreaterThan( $ttm, min( $blocks ) );
	}

	public function test_code_block_pre_is_keyboard_focusable(): void {
		$html = do_blocks( "<!-- wp:code -->\n<pre class=\"wp-block-code\"><code>echo 1;</code></pre>\n<!-- /wp:code -->" );

		$this->assertStringContainsString( '<pre tabindex="0" class="wp-block-code">', $html );
		$this->assertSame( '<pre tabindex="-1">x</pre>', \TTM\Theme\focusable_code_block( '<pre tabindex="-1">x</pre>' ) );
	}
}
