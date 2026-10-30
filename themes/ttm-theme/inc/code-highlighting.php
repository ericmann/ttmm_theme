<?php
/**
 * Syntax colours for code blocks. The Syntax-highlighting Code Block plugin (installed by the
 * site, not required) highlights core/code on the server; the theme replaces its highlight.js
 * stylesheet with assets/css/code.css, built from the palette. Without the plugin the filter is
 * never read and the stylesheet only matches hljs-* classes that aren't there.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * Turn off the plugin's stylesheet and load the theme's only where a code block renders.
 */
function register_code_highlighting(): void {
	add_filter( 'syntax_highlighting_code_block_styling', '__return_false' );

	$path = get_template_directory() . '/assets/css/code.css';
	wp_enqueue_block_style(
		'core/code',
		[
			'handle' => 'ttm-code',
			'src'    => get_template_directory_uri() . '/assets/css/code.css',
			'path'   => $path,
			'ver'    => wp_get_theme()->get( 'Version' ) . '.' . filemtime( $path ),
		]
	);
}

add_action( 'init', __NAMESPACE__ . '\\register_code_highlighting' );
