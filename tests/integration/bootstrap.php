<?php
/**
 * Integration bootstrap: loads the WordPress PHPUnit test suite that wp-env provides,
 * activates ttm-core as a mu-plugin and switches to ttm-theme.
 *
 * @package TTM\Tests\Integration
 */

declare( strict_types=1 );

$ttm_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $ttm_tests_dir ) {
	$ttm_tests_dir = '/wordpress-phpunit';
}
if ( ! file_exists( $ttm_tests_dir . '/includes/functions.php' ) ) {
	fwrite( STDERR, "WordPress test suite not found at {$ttm_tests_dir}. Run via: npm run test:integration\n" ); // phpcs:ignore
	exit( 1 );
}

require_once dirname( __DIR__ ) . '/../ttm-vendor/autoload.php';
require_once $ttm_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	static function (): void {
		require WP_CONTENT_DIR . '/plugins/ttm-core/ttm-core.php';
	}
);
tests_add_filter(
	'setup_theme',
	static function (): void {
		switch_theme( 'ttm-theme' );
	}
);

require $ttm_tests_dir . '/includes/bootstrap.php';

require_once __DIR__ . '/TestCase.php';
