<?php
/**
 * Adds `current-section` to nav links, hides Series when the index is empty (SPEC §6.4, F18).
 *
 * @package TTM\Core\Nav
 */

declare( strict_types=1 );

namespace TTM\Core\Nav;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\Lead;
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
			$block_content = self::add_class( $block_content, 'current-section current-menu-item' );
		}

		return self::fill_label( $block_content, $path );
	}

	/**
	 * Replace a section link's anchor text with its category term's own (possibly
	 * owner-renamed) name, when the link is one of `sections.order`'s `/category/<slug>/`
	 * links (SPEC §6.1.8).
	 *
	 * @param string $html Block HTML.
	 * @param string $path Trailing-slashed URL path.
	 * @return string
	 */
	private static function fill_label( string $html, string $path ): string {
		if ( ! preg_match( '#^/category/([a-z0-9-]+)/$#', $path, $m ) ) {
			return $html;
		}

		$slug = $m[1];
		if ( ! in_array( $slug, (array) Config::get( 'sections.order', [] ), true ) ) {
			return $html;
		}

		$category = get_category_by_slug( $slug );
		if ( ! $category ) {
			return $html;
		}

		return (string) preg_replace_callback(
			'/(<a\b[^>]*>)(.*?)(<\/a>)/s',
			static function ( array $m ) use ( $category ): string {
				return $m[1] . esc_html( $category->name ) . $m[3];
			},
			$html,
			1
		);
	}

	/**
	 * Whether a link path matches the current context's section.
	 *
	 * @param string $path Trailing-slashed URL path.
	 * @return bool
	 */
	private static function is_current_section( string $path ): bool {
		if ( is_front_page() && 'lead' === Config::get( 'nav.front_current', 'lead' ) ) {
			$lead_id = Lead::id();
			if ( $lead_id ) {
				$category_id   = PrimaryCategory::id( $lead_id );
				$category_link = $category_id ? wp_parse_url( get_category_link( $category_id ), PHP_URL_PATH ) : false;
				if ( $category_link && trailingslashit( $category_link ) === $path ) {
					return true;
				}
			}
		}

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

		// The /series/ hub page itself, and any single series' own taxonomy archive
		// (/series/{slug}/) -- both are part of the Series section (PLAN P2-06).
		if ( '/series/' === $path ) {
			if ( is_tax( 'series' ) ) {
				return true;
			}

			if ( is_page() ) {
				$queried  = get_queried_object();
				$hub_slug = (string) Config::get( 'sections.nav_hub_slug', 'series' );
				if ( $queried instanceof \WP_Post && $hub_slug === $queried->post_name ) {
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
