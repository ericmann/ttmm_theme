<?php
/**
 * Plugin Name:       These Things Matter — Core
 * Plugin URI:        https://github.com/ericmann/ttmm_theme
 * Description:       Data, blocks, bindings, cron, CLI and migration tooling for eric.mann.blog. Owns everything that must survive a theme switch.
 * Version:           0.1.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Eric Mann
 * Author URI:        https://eric.mann.blog
 * License:           GPL-2.0-or-later
 * Text Domain:       ttm-core
 *
 * @package TTM\Core
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TTM_CORE_API', 1 );
define( 'TTM_CORE_VERSION', '0.1.0' );
define( 'TTM_CORE_FILE', __FILE__ );
define( 'TTM_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'TTM_CORE_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader when present (dev / monorepo); classmap fallback for plain installs.
if ( file_exists( TTM_CORE_DIR . 'vendor/autoload.php' ) ) {
	require_once TTM_CORE_DIR . 'vendor/autoload.php';
} elseif ( file_exists( dirname( TTM_CORE_DIR, 3 ) . '/vendor/autoload.php' ) ) {
	require_once dirname( TTM_CORE_DIR, 3 ) . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class_name ): void {
			if ( 0 !== strpos( $class_name, 'TTM\\Core\\' ) ) {
				return;
			}
			$relative = str_replace( '\\', '/', substr( $class_name, strlen( 'TTM\\Core\\' ) ) );
			$file     = TTM_CORE_DIR . 'src/' . $relative . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
			}
		}
	);
}

/**
 * Boot the plugin once all plugins are loaded.
 *
 * Phase 0 of docs/SPEC.md replaces this with TTM\Core\Plugin::boot().
 */
add_action(
	'plugins_loaded',
	static function (): void {
		if ( class_exists( \TTM\Core\Plugin::class ) ) {
			\TTM\Core\Plugin::boot();
		}
	}
);
