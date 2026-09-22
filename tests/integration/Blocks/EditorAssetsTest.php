<?php
/**
 * Integration tests for SPEC §6.7: every `ttm/*` block registers in every editor context.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class EditorAssetsTest extends TTM_IntegrationTestCase {

	/**
	 * Every `ttm/<slug>` block name that has a built `index.asset.php`.
	 *
	 * @return array<string, array{name: string, deps: string[]}>
	 */
	private function built_blocks(): array {
		$files = glob( TTM_CORE_DIR . 'build/blocks/*/index.asset.php' );
		$this->assertNotEmpty( $files, 'Run `npm run build` first.' );

		$blocks = [];
		foreach ( $files as $file ) {
			$slug              = basename( dirname( $file ) );
			$asset             = include $file;
			$blocks[ $slug ]   = [
				'name' => 'ttm/' . $slug,
				'deps' => (array) ( $asset['dependencies'] ?? [] ),
			];
		}

		return $blocks;
	}

	public function test_every_asset_dependency_is_a_registered_script_handle(): void {
		// Simulate both editor contexts firing their own enqueue hooks, the way each real
		// admin screen would, then check every block's built dependency handle exists.
		do_action( 'wp_default_scripts', wp_scripts() );
		do_action( 'enqueue_block_editor_assets' );
		do_action( 'customize_controls_enqueue_scripts' );

		foreach ( $this->built_blocks() as $slug => $block ) {
			foreach ( $block['deps'] as $handle ) {
				$this->assertTrue(
					wp_script_is( $handle, 'registered' ),
					"Block {$slug}'s dependency '{$handle}' is not a registered script handle"
				);
			}
		}
	}

	public function test_server_block_settings_include_every_ttm_block_with_an_editor_script(): void {
		if ( ! function_exists( 'get_block_editor_server_block_settings' ) ) {
			require_once ABSPATH . 'wp-admin/includes/post.php';
		}

		$settings = get_block_editor_server_block_settings();
		$registry = WP_Block_Type_Registry::get_instance();

		foreach ( $this->built_blocks() as $slug => $block ) {
			$this->assertArrayHasKey( $block['name'], $settings, "{$block['name']} missing from get_block_editor_server_block_settings()" );

			$type = $registry->get_registered( $block['name'] );
			$this->assertNotNull( $type, "{$block['name']} is not registered" );
			$this->assertNotEmpty( $type->editor_script_handles, "{$block['name']} has no editor_script_handles" );
		}
	}

	public function test_lead_story_has_editor_script_handles(): void {
		$type = WP_Block_Type_Registry::get_instance()->get_registered( 'ttm/lead-story' );

		$this->assertNotNull( $type );
		$this->assertNotEmpty( $type->editor_script_handles );
	}

	public function test_fallback_script_is_never_enqueued_on_the_front_end(): void {
		$this->go_to( '/' );
		do_action( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_script_is( 'ttm-core-editor-fallback', 'enqueued' ) );
	}
}
