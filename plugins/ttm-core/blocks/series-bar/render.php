<?php
/**
 * `ttm/series-bar` render (01 §4.18): F11 no series -> ''; F23 open-ended text + trailing segment.
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

$ttm_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
$ttm_row     = $ttm_post_id ? SeriesIndex::for_post( $ttm_post_id ) : null;

if ( ! $ttm_row ) {
	return '';
}

$ttm_current_part = 0;
foreach ( $ttm_row['parts'] as $ttm_entry ) {
	if ( (int) $ttm_entry['post_id'] === $ttm_post_id ) {
		$ttm_current_part = (int) $ttm_entry['part'];
		break;
	}
}

$ttm_raw_total  = (int) get_term_meta( $ttm_row['id'], 'ttm_total_parts', true );
$ttm_open_ended = $ttm_raw_total <= 0;

$ttm_part_label = $ttm_open_ended
	? sprintf(
		/* translators: %d: part number. */
		__( 'Part %d', 'ttm-core' ),
		$ttm_current_part
	)
	: sprintf(
		/* translators: 1: part number, 2: total parts. */
		__( 'Part %1$d of %2$d', 'ttm-core' ),
		$ttm_current_part,
		$ttm_raw_total
	);

$ttm_segments = [];
if ( $ttm_open_ended ) {
	foreach ( $ttm_row['parts'] as $ttm_entry ) {
		if ( 'publish' !== $ttm_entry['status'] ) {
			continue;
		}
		$ttm_segments[] = (int) $ttm_entry['part'] === $ttm_current_part ? 'is-current' : 'is-done';
	}
	$ttm_segments[] = 'is-todo';
} else {
	for ( $ttm_i = 1; $ttm_i <= $ttm_raw_total; $ttm_i++ ) {
		if ( $ttm_i === $ttm_current_part ) {
			$ttm_segments[] = 'is-current';
		} elseif ( $ttm_i < $ttm_current_part ) {
			$ttm_segments[] = 'is-done';
		} else {
			$ttm_segments[] = 'is-todo';
		}
	}
}

$ttm_term      = get_term( $ttm_row['id'], 'series' );
$ttm_term_link = $ttm_term && ! is_wp_error( $ttm_term ) ? get_term_link( $ttm_term ) : '';
$ttm_term_link = is_string( $ttm_term_link ) ? $ttm_term_link : '';
?>
<div <?php echo Helpers::wrapper( 'series-bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<span class="ttm-series-mark is-<?php echo esc_attr( $ttm_row['status'] ); ?>"></span>
	<span class="ttm-series-bar__label"><?php esc_html_e( 'Series', 'ttm-core' ); ?></span>
	<a class="ttm-series-bar__name" href="<?php echo esc_url( $ttm_term_link ); ?>"><?php echo esc_html( $ttm_row['name'] ); ?></a>
	<span class="ttm-series-bar__part tnum"><?php echo esc_html( $ttm_part_label ); ?></span>
	<span class="ttm-series-bar__segments">
		<?php foreach ( $ttm_segments as $ttm_state ) : ?>
		<span class="ttm-series-bar__seg <?php echo esc_attr( $ttm_state ); ?>"></span>
		<?php endforeach; ?>
	</span>
	<a class="ttm-series-bar__view" href="<?php echo esc_url( $ttm_term_link ); ?>"><?php esc_html_e( 'View series', 'ttm-core' ); ?></a>
</div>
