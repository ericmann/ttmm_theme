<?php
/**
 * Adds `current-section` to nav links, hides Series when the index is empty (SPEC §6.4, F18).
 *
 * @package TTM\Core\Nav
 */

declare( strict_types=1 );

namespace TTM\Core\Nav;

use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;
use WP_Block;

/**
 * Filters core/navigation-link rendering.
 */
class CurrentSection {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'render_block_core/navigation-link', [ self::class, 'filter' ], 10, 3 );
	}

	/**
	 * Filters a rendered navigation-link block.
	 *
	 * @param string               $block_content Rendered block HTML.
	 * @param array<string, mixed> $block         Parsed block.
	 * @param WP_Block             $instance      Block instance.
	 * @return string
	 */
	public static function filter( string $block_content, array $block, WP_Block $instance ): string {
		unset( $instance );

		$url = $block['attrs']['url'] ?? '';
		if ( '' === $url ) {
			return $block_content;
		}

		$raw_path = wp_parse_url( $url, PHP_URL_PATH );
		$path     = trailingslashit( is_string( $raw_path ) ? $raw_path : '' );

		if ( '/series/' === $path && empty( SeriesIndex::all() ) ) {
			return '';
		}

		if ( self::is_current_section( $path ) ) {
			$block_content = self::add_class( $block_content, 'current-section' );
		}

		return $block_content;
	}

	/**
	 * Whether a link path matches the current context's section.
	 *
	 * @param string $path Trailing-slashed URL path.
	 * @return bool
	 */
	private static function is_current_section( string $path ): bool {
		if ( is_singular( 'post' ) ) {
			$slug = PrimaryCategory::slug( get_the_ID() );
			if ( $slug ) {
				$category      = get_category_by_slug( $slug );
				$category_id   = $category ? $category->term_id : 0;
				$category_link = wp_parse_url( get_category_link( $category_id ), PHP_URL_PATH );
				if ( $category_link && trailingslashit( $category_link ) === $path ) {
					return true;
				}
			}
			if ( SeriesIndex::for_post( (int) get_the_ID() ) && '/series/' === $path ) {
				return true;
			}
		}

		if ( is_category() ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Term ) {
				$category_link = wp_parse_url( get_category_link( $queried ), PHP_URL_PATH );
				if ( $category_link && trailingslashit( $category_link ) === $path ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Add a CSS class to the first tag's class attribute in a block's HTML.
	 *
	 * @param string $html      Block HTML.
	 * @param string $classname Class to add.
	 * @return string
	 */
	private static function add_class( string $html, string $classname ): string {
		if ( preg_match( '/class="([^"]*)"/', $html, $m ) ) {
			return preg_replace( '/class="[^"]*"/', 'class="' . esc_attr( trim( $m[1] . ' ' . $classname ) ) . '"', $html, 1 );
		}

		return preg_replace( '/<a /', '<a class="' . esc_attr( $classname ) . '" ', $html, 1 );
	}
}
