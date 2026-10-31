<?php
/**
 * Styles owned by a single ttm-core block. assets/css/blocks/<name>.css styles ttm/<name> and
 * loads only on pages that render that block, so ttm.css carries only the chrome, grids and
 * shared pieces every page needs. The editor loads every file, after ttm.css (functions.php).
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * Block names that have a stylesheet, from the file names in assets/css/blocks/.
 *
 * @return string[]
 */
function block_css_names(): array {
	$paths = glob( get_template_directory() . '/assets/css/blocks/*.css' );
	$names = array_map( static fn( string $path ): string => basename( $path, '.css' ), false === $paths ? [] : $paths );
	sort( $names );

	return $names;
}

/**
 * Attach each file to the block its name names, on the front end only.
 */
function register_block_css(): void {
	if ( is_admin() ) {
		return;
	}

	$ver = wp_get_theme()->get( 'Version' ) . '.';
	foreach ( block_css_names() as $name ) {
		$path = get_template_directory() . '/assets/css/blocks/' . $name . '.css';
		wp_enqueue_block_style(
			'ttm/' . $name,
			[
				'handle' => 'ttm-block-' . $name,
				'src'    => get_template_directory_uri() . '/assets/css/blocks/' . $name . '.css',
				'path'   => $path,
				'ver'    => $ver . filemtime( $path ),
			]
		);
	}
}

add_action( 'init', __NAMESPACE__ . '\\register_block_css' );

/**
 * Print block styles after ttm.css. A block template renders before wp_head, so its block
 * styles join the queue ahead of global styles and ttm.css; these rules were written to follow
 * both (they win ties), so each queued one is moved to the end.
 */
function block_css_last(): void {
	foreach ( block_css_names() as $name ) {
		$handle = 'ttm-block-' . $name;
		if ( wp_style_is( $handle ) ) {
			wp_dequeue_style( $handle );
			wp_enqueue_style( $handle );
		}
	}
}

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\\block_css_last', PHP_INT_MAX );
