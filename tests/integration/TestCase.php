<?php
/**
 * Base class for integration tests: real WordPress via the wp-env test suite.
 *
 * @package TTM\Tests\Integration
 */

declare( strict_types=1 );

use TTM\Core\Config;

abstract class TTM_IntegrationTestCase extends WP_UnitTestCase {

	/**
	 * Fix "now" for the duration of a test via the ttm_now filter.
	 *
	 * @param string $datetime Datetime string, site timezone.
	 */
	protected function set_now( string $datetime = '2026-09-20 12:00:00' ): void {
		add_filter(
			'ttm_now',
			static fn () => new DateTimeImmutable( $datetime, wp_timezone() )
		);
	}

	/**
	 * WP core's test suite unregisters every registered meta key in tear_down()
	 * (see WP_UnitTestCase_Base::tear_down() -> unregister_all_meta_keys()), but the
	 * plugin registers its taxonomies/meta only once, on the `init` hook fired during
	 * the single bootstrap request. Re-fire `init` at the start of every test so
	 * term/post meta registered by the plugin (still attached to the hook; only the
	 * $wp_meta_keys registry was wiped) comes back, matching what a real request does.
	 */
	public function set_up(): void {
		parent::set_up();
		do_action( 'init' );
	}

	/**
	 * Reset ttm_now and Config memoisation after each test.
	 */
	public function tear_down(): void {
		remove_all_filters( 'ttm_now' );
		Config::reset();
		parent::tear_down();
	}

	/**
	 * Render a block's markup through do_blocks().
	 *
	 * @param string               $markup  Serialized block markup.
	 * @param array<string, mixed> $context Unused placeholder for future block-context tests.
	 * @return string
	 */
	protected function render_block_html( string $markup, array $context = [] ): string {
		unset( $context );

		return (string) do_blocks( $markup );
	}

	/**
	 * Render a theme template part/file by slug and return the buffered output.
	 *
	 * @param string $slug Template slug, e.g. "front-page".
	 * @return string
	 */
	protected function render_template( string $slug ): string {
		$file = locate_template( [ "templates/{$slug}.html" ] );
		if ( '' === $file ) {
			return '';
		}

		$blocks = (string) file_get_contents( $file );

		return (string) do_blocks( $blocks );
	}
}
