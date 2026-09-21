<?php
/**
 * Posts list columns: Primary section, Series (part), Words (05 §8).
 *
 * @package TTM\Core\Editor
 */

declare( strict_types=1 );

namespace TTM\Core\Editor;

use TTM\Core\Meta\PrimaryCategory;
use WP_Query;

/**
 * Adds and renders the ttm_primary/ttm_series/ttm_words admin columns on Posts.
 */
class Columns {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_filter( 'manage_post_posts_columns', [ self::class, 'add_columns' ] );
		add_action( 'manage_post_posts_custom_column', [ self::class, 'render' ], 10, 2 );
		add_filter( 'manage_edit-post_sortable_columns', [ self::class, 'sortable' ] );
		add_action( 'pre_get_posts', [ self::class, 'sort_by_words' ] );
	}

	/**
	 * Insert columns after Title, before Date.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function add_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['ttm_primary'] = __( 'Primary section', 'ttm-core' );
				$new['ttm_series']  = __( 'Series', 'ttm-core' );
				$new['ttm_words']   = __( 'Words', 'ttm-core' );
			}
		}

		return $new;
	}

	/**
	 * Render column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'ttm_primary':
				$id = PrimaryCategory::id( $post_id );
				echo $id ? esc_html( get_cat_name( $id ) ) : '—';
				break;

			case 'ttm_series':
				$row = \TTM\Core\Query\SeriesIndex::for_post( $post_id );
				if ( ! $row ) {
					echo '—';
					break;
				}
				$part = (int) get_post_meta( $post_id, 'ttm_series_part', true );
				echo esc_html( sprintf( '%s (%d)', $row['name'], $part ) );
				break;

			case 'ttm_words':
				echo '<span class="tnum">' . esc_html( number_format_i18n( (int) get_post_meta( $post_id, 'ttm_word_count', true ) ) ) . '</span>';
				break;
		}
	}

	/**
	 * Mark the Words column sortable.
	 *
	 * @param array<string, string> $columns Existing sortable columns.
	 * @return array<string, string>
	 */
	public static function sortable( array $columns ): array {
		$columns['ttm_words'] = 'ttm_words';

		return $columns;
	}

	/**
	 * Order by ttm_word_count meta when requested, admin only.
	 *
	 * @param WP_Query $query The query.
	 */
	public static function sort_by_words( WP_Query $query ): void {
		if ( ! is_admin() ) {
			return;
		}
		if ( 'ttm_words' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', 'ttm_word_count' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}
