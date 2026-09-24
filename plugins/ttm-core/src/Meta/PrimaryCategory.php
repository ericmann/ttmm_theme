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
		add_filter( 'get_the_terms', [ self::class, 'order_terms' ], 10, 3 );
	}

	/**
	 * `get_the_terms` (front end, taxonomy `category` only): order a post's categories
	 * primary first, then by the `sections.order` index of each term's top-level ancestor,
	 * then by name -- so `core/post-terms` reads "Technology · Security" rather than
	 * alphabetically (Decision "Kicker term order").
	 *
	 * @param array<int, object>|\WP_Error $terms    Terms as core resolved them.
	 * @param int                          $post_id  Post ID.
	 * @param string                       $taxonomy Taxonomy.
	 * @return array<int, object>|\WP_Error
	 */
	public static function order_terms( $terms, $post_id, $taxonomy ) {
		if ( 'category' !== $taxonomy || ! is_array( $terms ) || count( $terms ) < 2 || is_admin() ) {
			return $terms;
		}

		$primary = self::id( (int) $post_id );
		$order   = array_values( (array) Config::get( 'sections.order', [] ) );

		$keyed = [];
		foreach ( $terms as $index => $term ) {
			if ( ! is_object( $term ) ) {
				return $terms;
			}
			$position = array_search( self::top_level_slug( $term ), $order, true );
			$keyed[]  = [
				'primary' => (int) $term->term_id === $primary ? 0 : 1,
				'order'   => false === $position ? PHP_INT_MAX : (int) $position,
				'name'    => (string) $term->name,
				'index'   => $index,
				'term'    => $term,
			];
		}

		usort(
			$keyed,
			static function ( array $a, array $b ): int {
				return [ $a['primary'], $a['order'], $a['name'], $a['index'] ] <=> [ $b['primary'], $b['order'], $b['name'], $b['index'] ];
			}
		);

		return array_map( static fn ( array $row ) => $row['term'], $keyed );
	}

	/**
	 * The slug of a term's top-level ancestor (the term itself when it has no parent).
	 *
	 * @param object $term Term object with `term_id`, `slug`, `parent`.
	 * @return string
	 */
	private static function top_level_slug( object $term ): string {
		$current = $term;
		$guard   = 0;
		while ( ! empty( $current->parent ) && $guard < 10 ) {
			$parent = get_term( (int) $current->parent, 'category' );
			if ( ! $parent || is_wp_error( $parent ) ) {
				break;
			}
			$current = $parent;
			++$guard;
		}

		return (string) $current->slug;
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
	 * Whether this save happens while an import is running -- the WordPress importer inserts
	 * the post (no categories, so the default category applies) before it assigns the real
	 * terms, so a `save_post_post` fired at insert time would otherwise stick a stale
	 * "Uncategorized" primary that a later `primary:assign --from-yoast` pass would then treat
	 * as already set (Decision mirrors Form::is_editor_save()). `true` only when `WP_IMPORTING`
	 * is defined and set; filterable so tests and other write paths can force either answer.
	 *
	 * @return bool
	 */
	public static function is_import_save(): bool {
		$default = ( defined( 'WP_IMPORTING' ) && WP_IMPORTING );

		return (bool) apply_filters( 'ttm_primary_on_import', $default );
	}

	/**
	 * Write ttm_primary_category only when empty or the stored term is no longer assigned.
	 * Never writes during an import (see is_import_save()) -- `primary:assign --from-yoast`
	 * or a plain `primary:assign` pass fills it in afterwards, once the real terms are set.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function on_save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}

		if ( self::is_import_save() ) {
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
	 * Read helper: the resolved primary category term id. Never writes. A stored term the
	 * post no longer carries (e.g. after re-categorising) is treated as empty and resolved
	 * fresh from the post's current terms, same as on_save().
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	public static function id( int $post_id ): int {
		$stored = (int) get_post_meta( $post_id, 'ttm_primary_category', true );
		$terms  = wp_get_post_categories( $post_id, [ 'fields' => 'ids' ] );

		if ( $stored && in_array( $stored, $terms, true ) ) {
			return $stored;
		}

		$slug = self::resolve_from_terms( $terms );
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
