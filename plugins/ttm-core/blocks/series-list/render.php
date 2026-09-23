<?php
/**
 * `ttm/series-list` render: series rows (01 §4.16/§4.17), F4 in-progress fallback. `list`
 * (default) shows a dek, categories and count; `rail`/`strip` collapse those into one
 * `__meta` line (categories · count · cadence); `grid-2`/`grid-3` reuse `list`'s markup.
 * `relatedTo: "current"` (SPEC §6.4, rule 51, F27) replaces the status/form filters above with
 * a same-form-class, shared-section ranking against the queried series term.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Query\SeriesIndex;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_all = SeriesIndex::all();
if ( empty( $ttm_all ) ) {
	return '';
}

// Decision "Related series form class": fiction is novel/novella/story-cycle; everything else
// (including every nonfiction series) is the other class. Shared by both the normal `form`
// filter and the `relatedTo` ranking below.
$ttm_fiction_forms = [ 'novel', 'novella', 'story-cycle' ];

$ttm_layout     = (string) ( $attributes['layout'] ?? 'list' );
$ttm_show_dek   = ! isset( $attributes['showDek'] ) || $attributes['showDek'];
$ttm_show_cats  = ! isset( $attributes['showCategories'] ) || $attributes['showCategories'];
$ttm_show_count = ! isset( $attributes['showCount'] ) || $attributes['showCount'];
$ttm_heading    = (string) ( $attributes['heading'] ?? '' );
$ttm_related_to = (string) ( $attributes['relatedTo'] ?? '' );

$ttm_limit = (int) ( $attributes['limit'] ?? 0 );

if ( 'current' === $ttm_related_to ) {
	// Rule 50: this variant is scoped to the queried series term; no default elsewhere.
	$ttm_queried = get_queried_object();
	if ( ! ( $ttm_queried instanceof WP_Term && 'series' === $ttm_queried->taxonomy ) ) {
		return '';
	}

	$ttm_current_row = SeriesIndex::get( $ttm_queried->term_id );
	if ( ! $ttm_current_row ) {
		return '';
	}

	$ttm_current_is_fiction = in_array( $ttm_current_row['form'], $ttm_fiction_forms, true );

	$ttm_rows = array_values(
		array_filter(
			$ttm_all,
			static function ( array $row ) use ( $ttm_current_row, $ttm_current_is_fiction, $ttm_fiction_forms ): bool {
				if ( (int) $row['id'] === (int) $ttm_current_row['id'] ) {
					return false;
				}
				return in_array( $row['form'], $ttm_fiction_forms, true ) === $ttm_current_is_fiction;
			}
		)
	);

	if ( empty( $ttm_rows ) ) {
		return '';
	}

	usort(
		$ttm_rows,
		static function ( array $a, array $b ) use ( $ttm_current_row ): int {
			$ttm_shared_a = count( array_intersect( $a['categories'], $ttm_current_row['categories'] ) );
			$ttm_shared_b = count( array_intersect( $b['categories'], $ttm_current_row['categories'] ) );
			if ( $ttm_shared_a !== $ttm_shared_b ) {
				return $ttm_shared_b <=> $ttm_shared_a;
			}
			if ( $a['last_update'] !== $b['last_update'] ) {
				return strcmp( $b['last_update'], $a['last_update'] );
			}
			return strcmp( $a['slug'], $b['slug'] );
		}
	);

	if ( $ttm_limit <= 0 ) {
		$ttm_limit = (int) Config::get( 'series.related_limit', 4 );
	}
	$ttm_rows = array_slice( $ttm_rows, 0, $ttm_limit );

	$ttm_classes = [ 'is-' . $ttm_layout ];
	$ttm_extra   = [];
} else {
	$ttm_status          = (string) ( $attributes['status'] ?? 'in-progress' );
	$ttm_form            = (string) ( $attributes['form'] ?? 'any' );
	$ttm_in_category     = ! empty( $attributes['inCategory'] );
	$ttm_exclude_current = ! empty( $attributes['excludeCurrent'] );
	$ttm_orderby         = (string) ( $attributes['orderby'] ?? 'updated' );

	if ( $ttm_limit <= 0 ) {
		// SPEC §5: "Other series" (excludeCurrent, no explicit limit) falls back to
		// series.related_limit rather than the strip's own default.
		$ttm_limit = $ttm_exclude_current
			? (int) Config::get( 'series.related_limit', 4 )
			: (int) Config::get( 'series.strip_limit', 3 );
	}

	$ttm_queried_category_id = 0;
	if ( $ttm_in_category ) {
		$ttm_queried = get_queried_object();
		if ( $ttm_queried instanceof WP_Term && 'category' === $ttm_queried->taxonomy ) {
			$ttm_queried_category_id = $ttm_queried->term_id;
		}
	}

	$ttm_current_series_id = 0;
	if ( $ttm_exclude_current ) {
		$ttm_queried_series = get_queried_object();
		if ( $ttm_queried_series instanceof WP_Term && 'series' === $ttm_queried_series->taxonomy ) {
			$ttm_current_series_id = $ttm_queried_series->term_id;
		}
	}

	$ttm_filter = static function ( array $rows, string $status ) use ( $ttm_form, $ttm_in_category, $ttm_queried_category_id, $ttm_current_series_id ): array {
		return array_values(
			array_filter(
				$rows,
				static function ( array $row ) use ( $status, $ttm_form, $ttm_in_category, $ttm_queried_category_id, $ttm_current_series_id ): bool {
					if ( 'any' !== $status && $row['status'] !== $status ) {
						return false;
					}
					if ( 'fiction' === $ttm_form && 'nonfiction' === $row['form'] ) {
						return false;
					}
					if ( 'nonfiction' === $ttm_form && 'nonfiction' !== $row['form'] ) {
						return false;
					}
					if ( $ttm_in_category && ! in_array( $ttm_queried_category_id, $row['categories'], true ) ) {
						return false;
					}
					if ( $ttm_current_series_id && (int) $row['id'] === $ttm_current_series_id ) {
						return false;
					}
					return true;
				}
			)
		);
	};

	$ttm_rows        = $ttm_filter( $ttm_all, $ttm_status );
	$ttm_is_fallback = false;

	if ( empty( $ttm_rows ) && 'in-progress' === $ttm_status ) {
		$ttm_rows        = $ttm_filter( $ttm_all, 'complete' );
		$ttm_is_fallback = true;
	}

	if ( empty( $ttm_rows ) ) {
		return '';
	}

	usort(
		$ttm_rows,
		static function ( array $a, array $b ) use ( $ttm_orderby ): int {
			switch ( $ttm_orderby ) {
				case 'title':
					return strcasecmp( $a['name'], $b['name'] );
				case 'started':
					return strcmp( $a['parts'][0]['date'] ?? '', $b['parts'][0]['date'] ?? '' );
				case 'updated':
				default:
					return strcmp( $b['last_update'], $a['last_update'] );
			}
		}
	);

	$ttm_rows = array_slice( $ttm_rows, 0, $ttm_limit );

	$ttm_classes = [ 'is-' . $ttm_layout ];
	if ( $ttm_is_fallback ) {
		$ttm_classes[] = 'is-complete';
	}

	$ttm_extra = $ttm_is_fallback ? [ 'data-ttm-empty-heading' => __( 'Series', 'ttm-core' ) ] : [];
}//end if
?>
<div <?php echo Helpers::wrapper( 'series-list', $ttm_classes, $ttm_extra ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php if ( '' !== $ttm_heading ) : ?>
	<div class="ttm-cell-heading is-rail">
		<span class="ttm-cell-heading__label"><?php echo esc_html( $ttm_heading ); ?></span>
	</div>
	<?php endif; ?>
	<?php foreach ( $ttm_rows as $ttm_row ) : ?>
		<?php
		$ttm_term       = get_term( $ttm_row['id'], 'series' );
		$ttm_dek        = $ttm_term && ! is_wp_error( $ttm_term ) ? (string) $ttm_term->description : '';
		$ttm_raw_total  = (int) get_term_meta( $ttm_row['id'], 'ttm_total_parts', true );
		$ttm_count_word = $ttm_raw_total > 0
			? sprintf(
				/* translators: 1: published parts, 2: planned total parts. */
				__( '%1$d of %2$d', 'ttm-core' ),
				(int) $ttm_row['published'],
				$ttm_raw_total
			)
			: sprintf(
				/* translators: %d: published parts. */
				_n( '%d part', '%d parts', (int) $ttm_row['published'], 'ttm-core' ),
				(int) $ttm_row['published']
			);

		$ttm_category_names = [];
		foreach ( $ttm_row['categories'] as $ttm_category_id ) {
			$ttm_category = get_term( $ttm_category_id, 'category' );
			if ( $ttm_category && ! is_wp_error( $ttm_category ) ) {
				$ttm_category_names[] = $ttm_category->name;
			}
		}

		// R4-02 / SPEC §6.5, §6.7: a complete series' right cell (list/grid-2 only;
		// the strip/rail meta line keeps $ttm_count_word unchanged) reads "{N} chapters"
		// for fiction and "{N} parts" for nonfiction, not "{N} of {N}".
		$ttm_right_cell_word = $ttm_count_word;
		if ( in_array( $ttm_layout, [ 'list', 'grid-2' ], true ) && 'complete' === $ttm_row['status'] ) {
			$ttm_right_cell_word = in_array( $ttm_row['form'], $ttm_fiction_forms, true )
				? sprintf(
					/* translators: %d: published parts. */
					_n( '%d chapter', '%d chapters', (int) $ttm_row['published'], 'ttm-core' ),
					(int) $ttm_row['published']
				)
				: sprintf(
					/* translators: %d: published parts. */
					_n( '%d part', '%d parts', (int) $ttm_row['published'], 'ttm-core' ),
					(int) $ttm_row['published']
				);
		}
		?>
		<a class="ttm-series-row" href="<?php echo esc_url( home_url( '/series/' . $ttm_row['slug'] . '/' ) ); ?>">
			<span class="ttm-series-mark is-<?php echo esc_attr( $ttm_row['status'] ); ?>"></span>
			<span class="ttm-series-row__title"><?php echo esc_html( $ttm_row['name'] ); ?></span>
			<?php if ( in_array( $ttm_layout, [ 'strip', 'rail' ], true ) ) : ?>
				<?php
				$ttm_meta_parts   = $ttm_category_names;
				$ttm_meta_parts[] = $ttm_count_word;
				$ttm_cadence      = (string) get_term_meta( $ttm_row['id'], 'ttm_cadence', true );
				if ( '' !== $ttm_cadence ) {
					$ttm_meta_parts[] = $ttm_cadence;
				}
				?>
			<span class="ttm-series-row__meta"><?php echo esc_html( implode( ' · ', $ttm_meta_parts ) ); ?></span>
			<?php else : ?>
				<?php if ( $ttm_show_dek && '' !== $ttm_dek ) : ?>
			<span class="ttm-series-row__dek"><?php echo esc_html( $ttm_dek ); ?></span>
			<?php endif; ?>
				<?php if ( 'grid-2' === $ttm_layout ) : ?>
					<?php if ( $ttm_show_cats && ! empty( $ttm_category_names ) ) : ?>
			<span class="ttm-series-row__categories"><?php echo esc_html( implode( ' · ', $ttm_category_names ) ); ?></span>
					<?php endif; ?>
				<?php else : ?>
					<?php
					// list/grid-3 meta line: fiction "{Form} · {genre} · {cadence}"; nonfiction
					// categories joined " · " then cadence; empties omitted. SPEC §6.5: this line
					// is all lowercase ("monthly"); capitalisation belongs only to
					// `ttm/serial-hero`'s stat value (PLAN spec-issue #18).
					$ttm_cadence = (string) get_term_meta( $ttm_row['id'], 'ttm_cadence', true );
					if ( 'nonfiction' === $ttm_row['form'] ) {
						$ttm_meta_line_parts = [ implode( ' · ', $ttm_category_names ), $ttm_cadence ];
					} else {
						$ttm_form_labels     = [
							'novel'       => __( 'Novel', 'ttm-core' ),
							'novella'     => __( 'Novella', 'ttm-core' ),
							'story-cycle' => __( 'Story cycle', 'ttm-core' ),
						];
						$ttm_genre           = (string) get_term_meta( $ttm_row['id'], 'ttm_genre', true );
						$ttm_meta_line_parts = [ $ttm_form_labels[ $ttm_row['form'] ] ?? '', $ttm_genre, $ttm_cadence ];
					}
					$ttm_meta_line = implode( ' · ', array_filter( $ttm_meta_line_parts, static fn ( string $ttm_part ): bool => '' !== $ttm_part ) );
					?>
					<?php if ( $ttm_show_cats && '' !== $ttm_meta_line ) : ?>
			<span class="ttm-series-row__meta"><?php echo esc_html( $ttm_meta_line ); ?></span>
					<?php endif; ?>
				<?php endif; ?>
				<?php if ( $ttm_show_count ) : ?>
			<span class="ttm-series-row__count">
				<span class="ttm-series-row__parts"><?php echo esc_html( $ttm_right_cell_word ); ?></span>
				<span class="ttm-series-row__status"><?php echo esc_html( Helpers::status_word( $ttm_row['status'] ) ); ?></span>
			</span>
			<?php endif; ?>
			<?php endif; ?>
		</a>
	<?php endforeach; ?>
</div>
