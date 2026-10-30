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
}
