<?php
/**
 * `ttm/series-stats` render: series hub header stats (02 §F).
 * "11 series · 3 in progress" + "Spanning Technology, Security, Faith, Business, Writing".
 * Zero series -> ''.
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

$ttm_rows = SeriesIndex::all();

if ( empty( $ttm_rows ) ) {
	return '';
}

$ttm_in_progress  = 0;
$ttm_category_ids = [];

foreach ( $ttm_rows as $ttm_row ) {
	if ( 'in-progress' === $ttm_row['status'] ) {
		++$ttm_in_progress;
	}
	foreach ( $ttm_row['categories'] as $ttm_cat_id ) {
		$ttm_category_ids[ $ttm_cat_id ] = true;
	}
}

$ttm_order   = (array) Config::get( 'sections.order', [] );
$ttm_by_slug = [];

foreach ( array_keys( $ttm_category_ids ) as $ttm_cat_id ) {
	$ttm_term = get_category( $ttm_cat_id );
	if ( $ttm_term && ! is_wp_error( $ttm_term ) ) {
		$ttm_by_slug[ $ttm_term->slug ] = $ttm_cat_id;
	}
}

$ttm_sorted = [];
foreach ( $ttm_order as $ttm_slug ) {
	if ( isset( $ttm_by_slug[ $ttm_slug ] ) ) {
		$ttm_sorted[] = $ttm_by_slug[ $ttm_slug ];
		unset( $ttm_by_slug[ $ttm_slug ] );
	}
}
$ttm_sorted = array_merge( $ttm_sorted, array_values( $ttm_by_slug ) );

$ttm_names = array_map( 'get_cat_name', $ttm_sorted );

$ttm_count_label = sprintf(
	/* translators: %d: series count. */
	_n( '%d series', '%d series', count( $ttm_rows ), 'ttm-core' ),
	count( $ttm_rows )
);

$ttm_progress_label = sprintf(
	/* translators: %d: in-progress series count. */
	_n( '%d in progress', '%d in progress', $ttm_in_progress, 'ttm-core' ),
	$ttm_in_progress
);
?>
<div <?php echo Helpers::wrapper( 'series-stats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<p class="tnum"><?php echo esc_html( $ttm_count_label . ' · ' . $ttm_progress_label ); ?></p>
	<?php if ( ! empty( $ttm_names ) ) : ?>
	<p>
		<?php
		printf(
			/* translators: %s: comma-separated category names. */
			esc_html__( 'Spanning %s', 'ttm-core' ),
			esc_html( implode( ', ', $ttm_names ) )
		);
		?>
	</p>
	<?php endif; ?>
</div>
