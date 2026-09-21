<?php
/**
 * Series term admin: add/edit form fields, save handler, part list, list columns.
 *
 * @package TTM\Core\Taxonomy
 */

declare( strict_types=1 );

namespace TTM\Core\Taxonomy;

use TTM\Core\Config;
use WP_Term;

/**
 * Owner-facing series term admin (05 §8).
 */
class SeriesAdmin {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'series_add_form_fields', [ self::class, 'add_form_fields' ] );
		add_action( 'series_edit_form_fields', [ self::class, 'edit_form_fields' ] );
		add_action( 'created_series', [ self::class, 'save' ] );
		add_action( 'edited_series', [ self::class, 'save' ] );

		add_filter( 'manage_edit-series_columns', [ self::class, 'columns' ] );
		add_filter( 'manage_series_custom_column', [ self::class, 'column_content' ], 10, 3 );
	}

	/**
	 * Fields on the "add series" screen.
	 */
	public static function add_form_fields(): void {
		self::render_fields( 0 );
	}

	/**
	 * Fields on the "edit series" screen. The read-only part list is a separate
	 * `series_edit_form_fields` callback in `Editor\SeriesPartList` (SPEC §4.2: `Taxonomy/` may
	 * not import `Editor/`, so it cannot call that renderer directly).
	 *
	 * @param WP_Term $term Series term.
	 */
	public static function edit_form_fields( WP_Term $term ): void {
		self::render_fields( $term->term_id );
	}

	/**
	 * Save handler for both add and edit. Verifies capability + the core term nonce
	 * before reading $_POST, then writes through the P1-01 sanitizers.
	 *
	 * @param int $term_id Series term id.
	 */
	public static function save( int $term_id ): void {
		if ( ! current_user_can( 'manage_categories' ) ) {
			return;
		}

		$is_edit = isset( $_POST['_wpnonce'] ) && check_admin_referer( 'update-tag_' . $term_id );
		$is_add  = ! $is_edit && isset( $_POST['_wpnonce_add-tag'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce_add-tag'] ) ), 'add-tag' );

		if ( ! $is_edit && ! $is_add ) {
			return;
		}

		if ( isset( $_POST['ttm_status'] ) ) {
			update_term_meta( $term_id, 'ttm_status', Series::sanitize_status( sanitize_text_field( wp_unslash( $_POST['ttm_status'] ) ) ) );
		}
		if ( isset( $_POST['ttm_total_parts'] ) ) {
			update_term_meta( $term_id, 'ttm_total_parts', absint( wp_unslash( $_POST['ttm_total_parts'] ) ) );
		}
		if ( isset( $_POST['ttm_form'] ) ) {
			update_term_meta( $term_id, 'ttm_form', Series::sanitize_form( sanitize_text_field( wp_unslash( $_POST['ttm_form'] ) ) ) );
		}
		if ( isset( $_POST['ttm_genre'] ) ) {
			update_term_meta( $term_id, 'ttm_genre', sanitize_text_field( wp_unslash( $_POST['ttm_genre'] ) ) );
		}
		if ( isset( $_POST['ttm_cadence'] ) ) {
			update_term_meta( $term_id, 'ttm_cadence', sanitize_text_field( wp_unslash( $_POST['ttm_cadence'] ) ) );
		}
		if ( isset( $_POST['ttm_next_date'] ) ) {
			update_term_meta( $term_id, 'ttm_next_date', Series::sanitize_next_date( sanitize_text_field( wp_unslash( $_POST['ttm_next_date'] ) ) ) );
		}
		if ( isset( $_POST['ttm_cover_id'] ) ) {
			$cover_id = absint( wp_unslash( $_POST['ttm_cover_id'] ) );
			update_term_meta( $term_id, 'ttm_cover_id', Series::sanitize_cover_id_checked( $cover_id ) );
		}
		update_term_meta( $term_id, 'ttm_featured', ! empty( $_POST['ttm_featured'] ) );

		if ( isset( $_POST['ttm_purchase_links'] ) && is_array( $_POST['ttm_purchase_links'] ) ) {
			$posted_links = map_deep( wp_unslash( $_POST['ttm_purchase_links'] ), 'sanitize_text_field' );
			$raw          = array_map(
				static fn ( array $row ): array => [
					'label' => $row['label'] ?? '',
					'url'   => $row['url'] ?? '',
				],
				$posted_links
			);
			update_term_meta( $term_id, 'ttm_purchase_links', Series::sanitize_purchase_links( $raw ) );
		}
	}

	/**
	 * Render every editable term-meta field.
	 *
	 * @param int $term_id 0 on the add-form.
	 */
	private static function render_fields( int $term_id ): void {
		$status   = $term_id ? get_term_meta( $term_id, 'ttm_status', true ) : 'in-progress';
		$total    = $term_id ? (int) get_term_meta( $term_id, 'ttm_total_parts', true ) : 0;
		$form     = $term_id ? get_term_meta( $term_id, 'ttm_form', true ) : 'nonfiction';
		$genre    = $term_id ? get_term_meta( $term_id, 'ttm_genre', true ) : '';
		$cadence  = $term_id ? get_term_meta( $term_id, 'ttm_cadence', true ) : '';
		$next     = $term_id ? get_term_meta( $term_id, 'ttm_next_date', true ) : '';
		$cover    = $term_id ? (int) get_term_meta( $term_id, 'ttm_cover_id', true ) : 0;
		$featured = $term_id ? (bool) get_term_meta( $term_id, 'ttm_featured', true ) : false;
		$links    = $term_id ? (array) get_term_meta( $term_id, 'ttm_purchase_links', true ) : [];
		$max      = (int) Config::get( 'series.max_purchase_links', 6 );

		echo '<tr class="form-field"><th><label for="ttm_status">' . esc_html__( 'Status', 'ttm-core' ) . '</label></th><td>';
		echo '<select name="ttm_status" id="ttm_status">';
		foreach ( Series::STATUSES as $option ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), selected( $status, $option, false ), esc_html( $option ) );
		}
		echo '</select></td></tr>';

		echo '<tr class="form-field"><th><label for="ttm_total_parts">' . esc_html__( 'Total parts', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="number" min="0" name="ttm_total_parts" id="ttm_total_parts" value="%d"></td></tr>', (int) $total );

		echo '<tr class="form-field"><th><label for="ttm_form">' . esc_html__( 'Form', 'ttm-core' ) . '</label></th><td>';
		echo '<select name="ttm_form" id="ttm_form">';
		foreach ( Series::FORMS as $option ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), selected( $form, $option, false ), esc_html( $option ) );
		}
		echo '</select></td></tr>';

		echo '<tr class="form-field"><th><label for="ttm_genre">' . esc_html__( 'Genre', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="text" name="ttm_genre" id="ttm_genre" value="%s"></td></tr>', esc_attr( $genre ) );

		echo '<tr class="form-field"><th><label for="ttm_cadence">' . esc_html__( 'Cadence', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="text" name="ttm_cadence" id="ttm_cadence" value="%s"></td></tr>', esc_attr( $cadence ) );

		echo '<tr class="form-field"><th><label for="ttm_next_date">' . esc_html__( 'Next date', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="date" name="ttm_next_date" id="ttm_next_date" value="%s"></td></tr>', esc_attr( $next ) );

		echo '<tr class="form-field"><th><label for="ttm_cover_id">' . esc_html__( 'Cover attachment ID', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="number" min="0" name="ttm_cover_id" id="ttm_cover_id" value="%d"></td></tr>', (int) $cover );

		echo '<tr class="form-field"><th><label for="ttm_featured">' . esc_html__( 'Featured', 'ttm-core' ) . '</label></th><td>';
		printf( '<input type="checkbox" name="ttm_featured" id="ttm_featured" value="1"%s></td></tr>', checked( $featured, true, false ) );

		echo '<tr class="form-field"><th>' . esc_html__( 'Purchase links', 'ttm-core' ) . '</th><td>';
		for ( $i = 0; $i < $max; $i++ ) {
			$label = $links[ $i ]['label'] ?? '';
			$url   = $links[ $i ]['url'] ?? '';
			printf(
				'<p><input type="text" placeholder="%s" name="ttm_purchase_links[%d][label]" value="%s"> <input type="url" placeholder="%s" name="ttm_purchase_links[%d][url]" value="%s"></p>',
				esc_attr__( 'Label', 'ttm-core' ),
				(int) $i,
				esc_attr( $label ),
				esc_attr__( 'URL', 'ttm-core' ),
				(int) $i,
				esc_attr( $url )
			);
		}
		echo '</td></tr>';
	}

	/**
	 * Add Status/Form columns to the series term list. `Editor\SeriesPartList` adds the Parts
	 * column separately (SPEC §4.2: `Taxonomy/` may not import `Editor/`/`Query/`).
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$columns['ttm_status'] = __( 'Status', 'ttm-core' );
		$columns['ttm_form']   = __( 'Form', 'ttm-core' );

		return $columns;
	}

	/**
	 * Render column content.
	 *
	 * @param string $content     Existing content (empty for custom columns).
	 * @param string $column_name Column key.
	 * @param int    $term_id     Term id.
	 * @return string
	 */
	public static function column_content( string $content, string $column_name, int $term_id ): string {
		switch ( $column_name ) {
			case 'ttm_status':
				return esc_html( (string) get_term_meta( $term_id, 'ttm_status', true ) );
			case 'ttm_form':
				return esc_html( (string) get_term_meta( $term_id, 'ttm_form', true ) );
			default:
				return $content;
		}
	}
}
