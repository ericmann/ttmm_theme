<?php
/**
 * Body classes for the current section/series/form (SPEC §6.4). Template routing
 * (single-journal, page-writing) lands in P4-05.
 *
 * @package TTM\Core\Templates
 */

declare( strict_types=1 );

namespace TTM\Core\Templates;

use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;

/**
 * Adds ttm-section-{slug}, ttm-in-series, ttm-form-{form} to body_class.
 */
class Hierarchy {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'body_class', [ self::class, 'body_classes' ] );
	}

	/**
	 * @param string[] $classes Existing body classes.
	 * @return string[]
	 */
	public static function body_classes( array $classes ): array {
		if ( is_singular( 'post' ) ) {
			$post_id = get_the_ID();
			$slug    = PrimaryCategory::slug( (int) $post_id );
			if ( $slug ) {
				$classes[] = 'ttm-section-' . $slug;
			}
			if ( SeriesIndex::for_post( (int) $post_id ) ) {
				$classes[] = 'ttm-in-series';
			}
			$form = get_post_meta( $post_id, 'ttm_form', true );
			if ( $form ) {
				$classes[] = 'ttm-form-' . $form;
			}
		} elseif ( is_category() ) {
			$queried = get_queried_object();
			if ( $queried instanceof \WP_Term ) {
				$classes[] = 'ttm-section-' . $queried->slug;
			}
		}

		return $classes;
	}
}
