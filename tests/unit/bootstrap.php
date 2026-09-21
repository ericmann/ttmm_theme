<?php
/**
 * Unit test bootstrap: Brain\Monkey, no WordPress. Anything that needs WordPress goes in tests/integration.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/vendor/php-stubs/wordpress-stubs/' );
}
if ( ! defined( 'TTM_CORE_DIR' ) ) {
	define( 'TTM_CORE_DIR', dirname( __DIR__, 2 ) . '/plugins/ttm-core/' );
}
if ( ! defined( 'TTM_THEME_DIR' ) ) {
	define( 'TTM_THEME_DIR', dirname( __DIR__, 2 ) . '/themes/ttm-theme/' );
}
