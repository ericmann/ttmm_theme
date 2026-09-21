<?php
/**
 * Body classes for the current section/series/form, and template routing
 * (single-journal, page-writing), per SPEC §6.4.
 *
 * @package TTM\Core\Templates
 */

declare( strict_types=1 );

namespace TTM\Core\Templates;

use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;

/**
 * Adds ttm-section-{slug}, ttm-in-series, ttm-form-{form} to body_class; prepends
 * single-journal/page-writing to the template hierarchy (rule 3: only prepends candidates,
 * missing template files fall through on their own).
 */
class Hierarchy {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'body_class', [ self::class, 'body_classes' ] );
		add_filter( 'single_template_hierarchy', [ self::class, 'single_hierarchy' ] );
		add_filter( 'category_template_hierarchy', [ self::class, 'category_hierarchy' ] );
	}

	/**
	 * Prepend `single-journal` when the post's primary category is Journal.
	 *
	 * @param string[] $templates Candidate template slugs, most specific first.
	 * @return string[]
	 */
	public static function single_hierarchy( array $templates ): array {
		if ( ! is_singular( 'post' ) ) {
			return $templates;
		}

		$slug = PrimaryCategory::slug( (int) get_the_ID() );

		if ( $slug && (string) Config::get( 'sections.journal_slug', 'journal' ) === $slug ) {
			array_unshift( $templates, 'single-journal' );
		}

		return $templates;
	}

	/**
	 * Prepend `page-writing` when the queried category is Writing.
	 *
	 * @param string[] $templates Candidate template slugs, most specific first.
	 * @return string[]
	 */
	public static function category_hierarchy( array $templates ): array {
		$queried = get_queried_object();

		if ( $queried instanceof \WP_Term && (string) Config::get( 'sections.writing_slug', 'writing' ) === $queried->slug ) {
			array_unshift( $templates, 'page-writing' );
		}

		return $templates;
	}

	/**
	 * Adds ttm-section-{slug}, ttm-in-series, ttm-form-{form}.
	 *
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
