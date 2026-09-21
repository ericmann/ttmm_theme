<?php
/**
 * These Things Matter theme bootstrap. Presentation only — see docs/SPEC.md §3.1.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		load_theme_textdomain( 'ttm-theme', get_template_directory() . '/languages' );
	}
);
