<?php
/**
 * Series term admin: read-only part list and the "Parts" list-table column (05 §8).
 *
 * Split out of `Taxonomy\SeriesAdmin` (SPEC §4.2: `Taxonomy/` may not import `Query/`, but
 * `Editor/` may). Hooks its own `series_edit_form_fields`/column callbacks alongside
 * `Taxonomy\SeriesAdmin`'s rather than being called from it, so the two modules stay
 * independent.
 *
 * @package TTM\Core\Editor
 */

declare( strict_types=1 );

namespace TTM\Core\Editor;

use TTM\Core\Query\SeriesIndex;
use WP_Term;

/**
 * Read-only series part list and list-table column.
 */
class SeriesPartList {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'series_edit_form_fields', [ self::class, 'render' ] );
		add_filter( 'manage_edit-series_columns', [ self::class, 'columns' ] );
		add_filter( 'manage_series_custom_column', [ self::class, 'column_content' ], 10, 3 );
	}

	/**
	 * Read-only ordered part list, appended to the "edit series" screen.
	 *
	 * @param WP_Term $term Series term.
	 */
	public static function render( WP_Term $term ): void {
		$row = SeriesIndex::get( $term->term_id );

		echo '<tr class="form-field"><th>' . esc_html__( 'Parts', 'ttm-core' ) . '</th><td>';
		echo '<table class="widefat"><thead><tr><th>#</th><th>' . esc_html__( 'Title', 'ttm-core' ) . '</th><th>' . esc_html__( 'Status', 'ttm-core' ) . '</th><th>' . esc_html__( 'Date', 'ttm-core' ) . '</th><th></th></tr></thead><tbody>';

		foreach ( ( $row['parts'] ?? [] ) as $part ) {
			printf(
				'<tr><td>%d</td><td>%s</td><td>%s</td><td>%s</td><td><a href="%s">%s</a></td></tr>',
				(int) $part['part'],
				esc_html( $part['title'] ),
				esc_html( $part['status'] ),
				esc_html( $part['date'] ),
				esc_url( (string) get_edit_post_link( $part['post_id'], '' ) ),
				esc_html__( 'Edit', 'ttm-core' )
			);
		}

		echo '</tbody></table></td></tr>';
	}

	/**
	 * Add the Parts column to the series term list.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$columns['ttm_parts'] = __( 'Parts', 'ttm-core' );

		return $columns;
	}

	/**
	 * Render the Parts column content: "N of M".
	 *
	 * @param string $content     Existing content (empty for custom columns).
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term id.
	 * @return string
	 */
	public static function column_content( string $content, string $column_name, int $term_id ): string {
		if ( 'ttm_parts' !== $column_name ) {
			return $content;
		}

		$row = SeriesIndex::get( $term_id );
		if ( ! $row ) {
			return '';
		}

		return esc_html( sprintf( '%d of %d', $row['published'], $row['total'] ) );
	}
}
