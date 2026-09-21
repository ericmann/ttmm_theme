<?php
/**
 * Primary category resolver: first assigned section in nav order (SPEC §5.2).
 *
 * @package TTM\Core\Meta
 */

declare( strict_types=1 );

namespace TTM\Core\Meta;

use TTM\Core\Config;
use WP_Post;

/**
 * Resolves and reads a post's primary category.
 */
class PrimaryCategory {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'save_post_post', [ self::class, 'on_save' ], 20, 2 );
	}

	/**
	 * Pure resolver: first slug in $order present in $category_slugs, else the
	 * first assigned slug, else null when uncategorised.
	 *
	 * @param string[] $category_slugs Slugs currently assigned to the post.
	 * @param string[] $order          Section slugs in nav order.
	 * @return string|null
	 */
	public static function resolve( array $category_slugs, array $order ): ?string {
		foreach ( $order as $slug ) {
			if ( in_array( $slug, $category_slugs, true ) ) {
				return $slug;
			}
		}

		return $category_slugs[0] ?? null;
	}

	/**
	 * Write ttm_primary_category only when empty or the stored term is no longer assigned.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		$stored = (int) get_post_meta( $post_id, 'ttm_primary_category', true );
		$terms  = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );

		if ( $stored && in_array( $stored, $terms, true ) ) {
			return;
		}

		$slug = self::resolve_from_terms( $terms );
		if ( null === $slug ) {
			return;
		}

		$term = get_term_by( 'slug', $slug, 'category' );
		if ( $term ) {
			update_post_meta( $post_id, 'ttm_primary_category', $term->term_id );
		}
	}

	/**
	 * Read helper: the resolved primary category term id. Never writes.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function id( int $post_id ): int {
		$stored = (int) get_post_meta( $post_id, 'ttm_primary_category', true );
		if ( $stored ) {
			return $stored;
		}

		$terms = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );
		$slug  = self::resolve_from_terms( $terms );
		if ( null === $slug ) {
			return 0;
		}

		$term = get_term_by( 'slug', $slug, 'category' );

		return $term ? $term->term_id : 0;
	}

	/**
	 * Read helper: the resolved primary category slug. Never writes.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function slug( int $post_id ): string {
		$id = self::id( $post_id );
		if ( ! $id ) {
			return '';
		}

		$term = get_term( $id, 'category' );

		return $term && ! is_wp_error( $term ) ? $term->slug : '';
	}

	/**
	 * Resolve a slug from assigned category term ids using Config's nav order.
	 *
	 * @param int[] $term_ids Assigned category term ids.
	 * @return string|null
	 */
	private static function resolve_from_terms( array $term_ids ): ?string {
		$slugs = [];
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$slugs[] = $term->slug;
			}
		}

		return self::resolve( $slugs, (array) Config::get( 'sections.order', [] ) );
	}
}
