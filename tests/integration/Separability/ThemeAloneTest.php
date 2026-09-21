<?php
/**
 * Proves the theme renders without PHP notices when no ttm/* block or binding source is
 * registered (SPEC §2 separability goal, rule 3, rule 25).
 *
 * @package TTM\Tests\Integration\Separability
 */

declare( strict_types=1 );

class ThemeAloneTest extends TTM_IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();

		$block_registry = WP_Block_Type_Registry::get_instance();
		foreach ( array_keys( $block_registry->get_all_registered() ) as $name ) {
			if ( 0 === strpos( $name, 'ttm/' ) ) {
				$block_registry->unregister( $name );
			}
		}

		if ( class_exists( 'WP_Block_Bindings_Registry' ) ) {
			$binding_registry = WP_Block_Bindings_Registry::get_instance();
			foreach ( array_keys( $binding_registry->get_all_registered() ) as $name ) {
				if ( 0 === strpos( $name, 'ttm/' ) ) {
					$binding_registry->unregister( $name );
				}
			}
		}
	}

	public function tear_down(): void {
		\TTM\Core\Blocks\Registrar::register_blocks();
		\TTM\Core\Bindings\Sources::register_sources();
		parent::tear_down();
	}

	/**
	 * Every template slug under themes/ttm-theme/templates.
	 *
	 * @return array<int, array<int, string>>
	 */
	public static function template_slugs(): array {
		return [
			[ '404' ],
			[ 'archive' ],
			[ 'category' ],
			[ 'category-journal' ],
			[ 'front-page' ],
			[ 'index' ],
			[ 'page' ],
			[ 'page-series' ],
			[ 'page-writing' ],
			[ 'search' ],
			[ 'single' ],
			[ 'single-journal' ],
			[ 'taxonomy-series' ],
		];
	}

	/**
	 * @dataProvider template_slugs
	 */
	public function test_every_template_renders_without_ttm_blocks_or_notices( string $slug ): void {
		// failOnWarning="true" in integration/phpunit.xml.dist fails this test on any PHP
		// notice/warning the render triggers; the assertion below just confirms a render
		// actually happened (an empty string is not itself a failure -- many patterns are
		// legitimately '' once their data source is gone).
		$html = $this->render_template( $slug );

		$this->assertIsString( $html );
	}

	public function test_no_ttm_wrapper_survives_without_the_plugin(): void {
		$html = $this->render_template( 'front-page' );

		$this->assertStringNotContainsString( 'data-ttm-block', $html );
		$this->assertStringNotContainsString( 'ttm-lead__inner', $html );
	}
}
