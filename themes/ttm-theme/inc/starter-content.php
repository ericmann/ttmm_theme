<?php
/**
 * One-time starter content on theme activation (04 §4.3). Idempotent by slug.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * The seven section slugs, in nav order.
 *
 * @return string[]
 */
function starter_sections(): array {
	return [ 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ];
}

/**
 * Create the seven categories, four pages and the Sections navigation, if missing.
 */
function create_starter_content(): void {
	foreach ( starter_sections() as $slug ) {
		if ( ! get_category_by_slug( $slug ) ) {
			wp_insert_term( ucfirst( $slug ), 'category', [ 'slug' => $slug ] );
		}
	}

	$pages = [
		'series'     => 'page-series',
		'writing'    => 'page-writing',
		'newsletter' => 'page',
		'about'      => 'page',
	];

	foreach ( $pages as $slug => $template ) {
		$existing = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $existing ) {
			update_post_meta( $existing->ID, '_wp_page_template', $template . '.html' );
			continue;
		}

		$post_id = wp_insert_post(
			[
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_name'   => $slug,
				'post_title'  => ucfirst( $slug ),
			]
		);

		if ( $post_id && ! is_wp_error( $post_id ) ) {
			update_post_meta( $post_id, '_wp_page_template', $template . '.html' );
		}
	}

	if ( ! get_page_by_path( 'ttm-sections', OBJECT, 'wp_navigation' ) ) {
		$links = '';
		foreach ( starter_sections() as $slug ) {
			$category = get_category_by_slug( $slug );
			if ( ! $category ) {
				continue;
			}
			$link   = get_category_link( $category );
			$links .= sprintf(
				'<!-- wp:navigation-link {"label":"%s","url":"%s","kind":"custom"} /-->',
				esc_attr( $category->name ),
				esc_url( $link )
			);
		}
		$links .= '<!-- wp:navigation-link {"label":"Series","url":"' . esc_url( home_url( '/series/' ) ) . '","kind":"custom"} /-->';

		wp_insert_post(
			[
				'post_type'    => 'wp_navigation',
				'post_status'  => 'publish',
				'post_name'    => 'ttm-sections',
				'post_title'   => 'Sections',
				'post_content' => $links,
			]
		);
	}//end if
}
add_action( 'after_switch_theme', __NAMESPACE__ . '\\create_starter_content' );
