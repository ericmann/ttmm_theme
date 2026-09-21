<?php
/**
 * `ttm/series-progress` render: segmented bar + meta line (01 §4.19).
 * F23 open-ended: published-count equal done segments + one trailing neutral segment, meta "N parts".
 * No series resolvable -> ''.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block              $block      Block instance (postId context).
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Query\SeriesIndex;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_series_id = (int) ( $attributes['seriesId'] ?? 0 );

if ( $ttm_series_id ) {
	$ttm_row = SeriesIndex::get( $ttm_series_id );
} else {
	$ttm_queried = get_queried_object();
	if ( is_tax( 'series' ) && $ttm_queried instanceof WP_Term ) {
		$ttm_row = SeriesIndex::get( $ttm_queried->term_id );
	} else {
		$ttm_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
		$ttm_row     = $ttm_post_id ? SeriesIndex::for_post( $ttm_post_id ) : null;
	}
}

if ( ! $ttm_row ) {
	return '';
}

$ttm_raw_total  = (int) get_term_meta( $ttm_row['id'], 'ttm_total_parts', true );
$ttm_open_ended = $ttm_raw_total <= 0;
$ttm_published  = (int) $ttm_row['published'];

$ttm_segments = [];
if ( $ttm_open_ended ) {
	foreach ( $ttm_row['parts'] as $ttm_entry ) {
		if ( 'publish' === $ttm_entry['status'] ) {
			$ttm_segments[] = 'is-done';
		}
	}
	$ttm_segments[] = 'is-todo';

	$ttm_meta = sprintf(
		/* translators: %d: published part count. */
		_n( '%d part', '%d parts', $ttm_published, 'ttm-core' ),
		$ttm_published
	);
} else {
	for ( $ttm_i = 1; $ttm_i <= $ttm_raw_total; $ttm_i++ ) {
		$ttm_segments[] = $ttm_i <= $ttm_published ? 'is-done' : 'is-todo';
	}

	$ttm_meta = sprintf(
		/* translators: 1: published count, 2: total parts. */
		__( '%1$d of %2$d published', 'ttm-core' ),
		$ttm_published,
		$ttm_raw_total
	);

	if ( 'complete' === $ttm_row['status'] ) {
		$ttm_last_published = null;
		foreach ( array_reverse( $ttm_row['parts'] ) as $ttm_entry ) {
			if ( 'publish' === $ttm_entry['status'] ) {
				$ttm_last_published = $ttm_entry;
				break;
			}
		}
		if ( $ttm_last_published ) {
			$ttm_meta .= ' · ' . sprintf(
				/* translators: %s: date the series finished. */
				__( 'finished %s', 'ttm-core' ),
				Helpers::date_short( $ttm_last_published['date'] )
			);
		}
	} elseif ( '' !== $ttm_row['next_date'] ) {
		$ttm_meta .= ' · ' . sprintf(
			/* translators: %s: date the next part is expected. */
			__( 'next part %s', 'ttm-core' ),
			Helpers::date_short( $ttm_row['next_date'] )
		);
	}//end if
}//end if
?>
<div <?php echo Helpers::wrapper( 'series-progress' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<span class="ttm-series-progress__segments">
		<?php foreach ( $ttm_segments as $ttm_state ) : ?>
		<span class="ttm-series-progress__seg <?php echo esc_attr( $ttm_state ); ?>"></span>
		<?php endforeach; ?>
	</span>
	<p class="ttm-series-progress__meta"><?php echo esc_html( $ttm_meta ); ?></p>
</div>
