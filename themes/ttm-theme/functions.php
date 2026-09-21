<?php
/**
 * These Things Matter theme bootstrap. Presentation only — see docs/SPEC.md §3.1.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

require_once __DIR__ . '/inc/bindings-compat.php';
require_once __DIR__ . '/inc/block-styles.php';
require_once __DIR__ . '/inc/patterns.php';
require_once __DIR__ . '/inc/image-sizes.php';
require_once __DIR__ . '/inc/starter-content.php';

add_action(
	'after_setup_theme',
	static function (): void {
		add_theme_support( 'wp-block-styles' );
		add_theme_support( 'editor-styles' );
		load_theme_textdomain( 'ttm-theme', get_template_directory() . '/languages' );
		add_editor_style( [ 'assets/css/ttm.css', 'assets/css/editor.css' ] );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function (): void {
		$css = get_template_directory() . '/assets/css/ttm.css';
		wp_enqueue_style( 'ttm-theme', get_template_directory_uri() . '/assets/css/ttm.css', [], (string) filemtime( $css ) );

		$js = get_template_directory() . '/assets/js/nav.js';
		wp_enqueue_script(
			'ttm-nav',
			get_template_directory_uri() . '/assets/js/nav.js',
			[],
			(string) filemtime( $js ),
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			] 
		);
	}
);

add_action(
	'enqueue_block_editor_assets',
	static function (): void {
		$js = get_template_directory() . '/assets/js/variations.js';
		wp_enqueue_script(
			'ttm-variations',
			get_template_directory_uri() . '/assets/js/variations.js',
			[ 'wp-blocks', 'wp-i18n', 'wp-dom-ready' ],
			(string) filemtime( $js ),
			true
		);
	}
);

add_action(
	'wp_head',
	static function (): void {
		$uri = get_template_directory_uri() . '/assets/fonts/';
		foreach ( [ '400', '800' ] as $weight ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( $uri . 'archivo-' . $weight . '-latin.woff2' )
			);
		}
	},
	1
);
