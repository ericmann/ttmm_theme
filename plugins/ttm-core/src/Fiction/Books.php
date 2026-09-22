<?php
/**
 * `ttm_books` option: repeater settings tab and typed reader for ttm/book-grid.
 *
 * @package TTM\Core\Fiction
 */

declare( strict_types=1 );

namespace TTM\Core\Fiction;

use TTM\Core\Admin\Page;
use TTM\Core\Config;

/**
 * Books settings tab (Decisions: option repeater) + Books::all() reader.
 */
class Books {

	public const FORMS = [ 'novel', 'novella', 'story-cycle', 'collection', 'nonfiction' ];

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action(
			'init',
			static function (): void {
				Page::register_tab( 'books', __( 'Books', 'ttm-core' ), [ self::class, 'render' ], [ self::class, 'save' ] );
			}
		);
	}

	/**
	 * Sanitized `ttm_books` rows, sanitized again on read for safety.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function all(): array {
		return self::sanitize( (array) get_option( 'ttm_books', [] ) );
	}

	/**
	 * Pure: drop blank rows, cast types, validate links/formats, cap at books.max.
	 *
	 * @param array<int, mixed> $rows Raw rows.
	 * @return array<int, array<string, mixed>>
	 */
	public static function sanitize( array $rows ): array {
		$max   = (int) Config::get( 'books.max', 12 );
		$clean = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row['title'] ) ) {
				continue;
			}

			$form   = in_array( $row['form'] ?? '', self::FORMS, true ) ? $row['form'] : 'novel';
			$year   = isset( $row['year'] ) ? (int) $row['year'] : 0;
			$cover  = isset( $row['cover_id'] ) ? absint( $row['cover_id'] ) : 0;
			$series = isset( $row['series_id'] ) ? absint( $row['series_id'] ) : 0;

			$formats = [];
			foreach ( (array) ( $row['formats'] ?? [] ) as $format ) {
				$format = sanitize_text_field( (string) $format );
				if ( '' !== $format ) {
					$formats[] = $format;
				}
			}

			$links = [];
			foreach ( (array) ( $row['links'] ?? [] ) as $link ) {
				if ( ! is_array( $link ) || empty( $link['url'] ) ) {
					continue;
				}
				$url = esc_url_raw( (string) $link['url'] );
				if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
					continue;
				}
				$links[] = [
					'label' => sanitize_text_field( (string) ( $link['label'] ?? '' ) ),
					'url'   => $url,
				];
			}

			$clean[] = [
				'title'     => sanitize_text_field( (string) $row['title'] ),
				'form'      => $form,
				'year'      => $year,
				'cover_id'  => $cover,
				'formats'   => $formats,
				'links'     => $links,
				'series_id' => $series,
			];

			if ( count( $clean ) >= $max ) {
				break;
			}
		}//end foreach

		return $clean;
	}

	/**
	 * Render the repeater: existing rows plus `books.blank_rows` blank rows.
	 */
	public static function render(): void {
		$books      = (array) get_option( 'ttm_books', [] );
		$max        = (int) Config::get( 'books.max', 12 );
		$blank_rows = (int) Config::get( 'books.blank_rows', 3 );
		$slots      = min( $max, count( $books ) + $blank_rows );

		for ( $i = 0; $i < $slots; $i++ ) {
			$book = $books[ $i ] ?? [];
			self::render_row( $i, $book );
		}
	}

	/**
	 * Render one repeater row.
	 *
	 * @param int                  $index Row index.
	 * @param array<string, mixed> $book  Existing values, or [].
	 */
	private static function render_row( int $index, array $book ): void {
		$name = "ttm_books[{$index}]";

		echo '<fieldset class="book-admin-row"><legend>' . esc_html(
			sprintf(
				/* translators: %d: repeater row number (1-based). */
				__( 'Book %d', 'ttm-core' ),
				$index + 1
			)
		) . '</legend>';
		printf( '<p><input type="text" placeholder="%s" name="%s[title]" value="%s"></p>', esc_attr__( 'Title', 'ttm-core' ), esc_attr( $name ), esc_attr( $book['title'] ?? '' ) );

		echo '<select name="' . esc_attr( $name ) . '[form]">';
		foreach ( self::FORMS as $form ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $form ), selected( $book['form'] ?? '', $form, false ), esc_html( $form ) );
		}
		echo '</select>';

		printf( '<input type="number" placeholder="%s" name="%s[year]" value="%s">', esc_attr__( 'Year', 'ttm-core' ), esc_attr( $name ), esc_attr( (string) ( $book['year'] ?? '' ) ) );
		printf( '<input type="number" placeholder="%s" name="%s[cover_id]" value="%s">', esc_attr__( 'Cover attachment ID', 'ttm-core' ), esc_attr( $name ), esc_attr( (string) ( $book['cover_id'] ?? '' ) ) );
		printf( '<input type="text" placeholder="%s" name="%s[formats]" value="%s">', esc_attr__( 'Formats, comma separated', 'ttm-core' ), esc_attr( $name ), esc_attr( implode( ', ', (array) ( $book['formats'] ?? [] ) ) ) );
		printf( '<input type="number" placeholder="%s" name="%s[series_id]" value="%s">', esc_attr__( 'Series term ID', 'ttm-core' ), esc_attr( $name ), esc_attr( (string) ( $book['series_id'] ?? '' ) ) );

		$link_rows = (int) Config::get( 'books.link_rows', 2 );

		for ( $l = 0; $l < $link_rows; $l++ ) {
			$link = $book['links'][ $l ] ?? [];
			printf(
				'<p><input type="text" placeholder="%s" name="%s[links][%d][label]" value="%s"> <input type="url" placeholder="%s" name="%s[links][%d][url]" value="%s"></p>',
				esc_attr__( 'Link label', 'ttm-core' ),
				esc_attr( $name ),
				(int) $l,
				esc_attr( $link['label'] ?? '' ),
				esc_attr__( 'Link URL', 'ttm-core' ),
				esc_attr( $name ),
				(int) $l,
				esc_attr( $link['url'] ?? '' )
			);
		}

		echo '</fieldset>';
	}

	/**
	 * Save handler: reads $_POST['ttm_books'], splits the formats CSV, sanitizes, stores.
	 */
	public static function save(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce + capability already checked by Admin\Page::handle_save() before dispatching here.
		$raw = isset( $_POST['ttm_books'] ) ? map_deep( wp_unslash( $_POST['ttm_books'] ), 'sanitize_text_field' ) : [];

		$rows = [];
		foreach ( (array) $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( isset( $row['formats'] ) && is_string( $row['formats'] ) ) {
				$row['formats'] = array_filter( array_map( 'trim', explode( ',', $row['formats'] ) ) );
			}
			$rows[] = $row;
		}

		update_option( 'ttm_books', self::sanitize( $rows ) );

		do_action( 'ttm_purge_urls', [ home_url( '/writing/' ) ] );
	}
}
