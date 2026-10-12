<?php
/**
 * Base class for integration tests: real WordPress via the wp-env test suite.
 *
 * @package TTM\Tests\Integration
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;
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
		add_filter( 'wp_insert_post_data', [ $this, 'keep_requested_future_status' ], 10, 2 );
	}

	/**
	 * Keep a fixture's requested `future` status. wp_insert_post() flips `future` to
	 * `publish` when post_date_gmt is behind the real wall clock, which ttm_now cannot
	 * pin -- so scheduled fixtures dated after the pinned "now" silently became
	 * published once the real calendar passed them.
	 *
	 * @param array<string, mixed> $data    Sanitized post data about to be saved.
	 * @param array<string, mixed> $postarr Post data as passed to wp_insert_post().
	 * @return array<string, mixed>
	 */
	public function keep_requested_future_status( array $data, array $postarr ): array {
		if ( 'future' === ( $postarr['post_status'] ?? '' ) && 'publish' === $data['post_status'] ) {
			$data['post_status'] = 'future';
		}

		return $data;
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
	 * Run the full seed and return the Seeder so tests can inspect/reuse it.
	 *
	 * @param string $state       Seed state (e.g. "normal").
	 * @param bool   $demo_images Whether to sideload real demo photographs (P1-02); default
	 *                            `false` so most integration tests never touch the demo
	 *                            fixtures directory or a mounted `ttm_demo_images_dir`.
	 * @return Seeder
	 */
	protected function seed( string $state = 'normal', bool $demo_images = false ): Seeder {
		$seeder = new Seeder( $demo_images );
		$seeder->run( $state );

		return $seeder;
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
