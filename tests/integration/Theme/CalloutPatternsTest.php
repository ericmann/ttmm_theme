<?php
/**
 * Integration tests for the editor's-note and update callout patterns.
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

class CalloutPatternsTest extends TTM_IntegrationTestCase {

	public function test_both_callout_patterns_register_and_render_their_block_style(): void {
		$registry = WP_Block_Patterns_Registry::get_instance();

		foreach ( [
			'ttm/editors-note' => 'is-style-note',
			'ttm/update-note'  => 'is-style-update',
		] as $slug => $class ) {
			$pattern = $registry->get_registered( $slug );
			$this->assertNotNull( $pattern, $slug );
			$this->assertContains( 'post', $pattern['postTypes'] ?? [], $slug );

			$html = (string) do_blocks( $pattern['content'] );
			$this->assertStringContainsString( 'class="wp-block-group ' . $class, $html, $slug );
			$this->assertStringContainsString( 'is-style-kicker', $html, $slug );
		}
	}

	public function test_note_and_update_are_registered_group_styles(): void {
		$styles = WP_Block_Styles_Registry::get_instance()->get_registered_styles_for_block( 'core/group' );

		$this->assertArrayHasKey( 'note', $styles );
		$this->assertArrayHasKey( 'update', $styles );
	}
}
