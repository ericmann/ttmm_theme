<?php
/**
 * `ttm/series-toc` render: "In this series" (01 §4.20) / chapters list (02 §D).
 * F11 no series -> ''; F23 open-ended lists published parts only; F24 scheduled parts unlinked.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block              $block      Block instance (postId context).
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Fiction\Serials;
use TTM\Core\Query\SeriesIndex;
use TTM\Core\Support\Clock;
use TTM\Core\Support\Dates;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_post_id   = (int) ( $block->context['postId'] ?? get_the_ID() );
$ttm_series_id = (int) ( $attributes['seriesId'] ?? 0 );

if ( $ttm_series_id ) {
	$ttm_row = SeriesIndex::get( $ttm_series_id );
} elseif ( $ttm_post_id && SeriesIndex::for_post( $ttm_post_id ) ) {
	$ttm_row = SeriesIndex::for_post( $ttm_post_id );
} else {
	$ttm_row = Serials::active();
}

if ( ! $ttm_row ) {
	return '';
}

$ttm_variant  = (string) ( $attributes['variant'] ?? 'series' );
$ttm_order    = 'desc' === ( $attributes['order'] ?? 'asc' ) ? 'desc' : 'asc';
$ttm_limit    = (int) ( $attributes['limit'] ?? 0 );
$ttm_show_dek = ! empty( $attributes['showDek'] );
$ttm_heading  = '' !== ( $attributes['heading'] ?? '' ) ? (string) $attributes['heading'] : __( 'In this series', 'ttm-core' );

$ttm_open_ended = (int) get_term_meta( $ttm_row['id'], 'ttm_total_parts', true ) <= 0;

$ttm_rows = $ttm_row['parts'];
if ( $ttm_open_ended ) {
	$ttm_rows = array_values( array_filter( $ttm_rows, static fn ( array $part ): bool => 'publish' === $part['status'] ) );
}

usort(
	$ttm_rows,
	static fn ( array $a, array $b ): int => 'desc' === $ttm_order ? $b['part'] <=> $a['part'] : $a['part'] <=> $b['part']
);

if ( $ttm_limit > 0 ) {
	$ttm_rows = array_slice( $ttm_rows, 0, $ttm_limit );
}

$ttm_term      = get_term( $ttm_row['id'], 'series' );
$ttm_term_link = $ttm_term && ! is_wp_error( $ttm_term ) ? get_term_link( $ttm_term ) : '';
$ttm_term_link = is_string( $ttm_term_link ) ? $ttm_term_link : '';
?>
<div <?php echo Helpers::wrapper( 'series-toc', [ 'is-' . $ttm_variant ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<div class="ttm-cell-heading">
		<span class="ttm-cell-heading__label"><?php echo esc_html( $ttm_heading ); ?></span>
		<a class="ttm-series-toc__hub" href="<?php echo esc_url( $ttm_term_link ); ?>"><?php echo esc_html( $ttm_row['name'] ); ?></a>
	</div>
	<ol class="<?php echo 'chapters' === $ttm_variant ? 'ttm-numbered' : ''; ?>">
		<?php foreach ( $ttm_rows as $ttm_part ) : ?>
			<?php
			$ttm_is_current   = (int) $ttm_part['post_id'] === $ttm_post_id;
			$ttm_is_published = 'publish' === $ttm_part['status'];
			$ttm_state        = $ttm_is_current ? 'is-current' : ( $ttm_is_published ? 'is-published' : 'is-scheduled' );
			$ttm_title        = '' !== $ttm_part['title'] ? $ttm_part['title'] : get_the_title( $ttm_part['post_id'] );
			$ttm_number       = sprintf( '%02d', (int) $ttm_part['part'] );
			?>
			<li class="ttm-series-toc__item <?php echo esc_attr( $ttm_state ); ?>">
				<span class="tnum"><?php echo esc_html( $ttm_number ); ?></span>
				<?php if ( $ttm_is_current || ! $ttm_is_published ) : ?>
					<?php
					$ttm_title_attr = '';
					if ( ! $ttm_is_published ) {
						$ttm_date       = Clock::at( $ttm_part['date'] );
						$ttm_title_attr = $ttm_date
							? sprintf(
								/* translators: %s: scheduled date (e.g. "Sept 26"). */
								__( 'Scheduled %s', 'ttm-core' ),
								Dates::short_month( $ttm_date ) . ' ' . $ttm_date->format( 'j' )
							)
							: '';
					}
					?>
					<span<?php echo $ttm_title_attr ? ' title="' . esc_attr( $ttm_title_attr ) . '"' : ''; ?>><?php echo esc_html( $ttm_title ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( (string) get_permalink( $ttm_part['post_id'] ) ); ?>"><?php echo esc_html( $ttm_title ); ?></a>
				<?php endif; ?>
				<?php if ( $ttm_show_dek && $ttm_is_published ) : ?>
					<span class="ttm-series-toc__dek"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $ttm_part['post_id'] ) ) ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</div>
