<?php
/**
 * `ttm/series-toc` render: "In this series" (01 §4.20, SPEC §6.2) / chapters list (02 §D, P4-05).
 * Series variant: rail heading with the series name (>= 721) and "Hub →" (<= 720), then
 * `li.ttm-series-toc__item.is-current|is-published|is-scheduled` rows. Chapters variant: the
 * shared numbered list (`ol.ttm-numbered`) under "{Series} — recent chapters" + "All {n}".
 * F11 no series -> ''; F23 open-ended series and the chapters variant list published parts only
 * (chapters -> '' if none are published); F24 scheduled parts unlinked (series variant only).
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
$ttm_variant   = (string) ( $attributes['variant'] ?? 'series' );

// SPEC §6.3, rule 50: the `series` variant is scoped to an explicit seriesId or the current
// post's own series -- it never falls back to the active serial (that fallback is the
// `chapters` variant's alone, for the Writing page).
if ( $ttm_series_id ) {
	$ttm_row = SeriesIndex::get( $ttm_series_id );
} elseif ( $ttm_post_id && SeriesIndex::for_post( $ttm_post_id ) ) {
	$ttm_row = SeriesIndex::for_post( $ttm_post_id );
} elseif ( 'chapters' === $ttm_variant ) {
	$ttm_row = Serials::active();
} else {
	$ttm_row = null;
}

if ( ! $ttm_row ) {
	return '';
}

$ttm_order    = 'desc' === ( $attributes['order'] ?? 'asc' ) ? 'desc' : 'asc';
$ttm_limit    = (int) ( $attributes['limit'] ?? 0 );
$ttm_show_dek = ! empty( $attributes['showDek'] );
$ttm_heading  = '' !== ( $attributes['heading'] ?? '' ) ? (string) $attributes['heading'] : __( 'In this series', 'ttm-core' );

$ttm_open_ended  = (int) get_term_meta( $ttm_row['id'], 'ttm_total_parts', true ) <= 0;
$ttm_is_chapters = 'chapters' === $ttm_variant;

$ttm_rows = $ttm_row['parts'];
if ( $ttm_open_ended || $ttm_is_chapters ) {
	$ttm_rows = array_values( array_filter( $ttm_rows, static fn ( array $part ): bool => 'publish' === $part['status'] ) );
}

usort(
	$ttm_rows,
	static fn ( array $a, array $b ): int => 'desc' === $ttm_order ? $b['part'] <=> $a['part'] : $a['part'] <=> $b['part']
);

if ( $ttm_limit > 0 ) {
	$ttm_rows = array_slice( $ttm_rows, 0, $ttm_limit );
}

if ( $ttm_is_chapters && ! $ttm_rows ) {
	return '';
}

$ttm_term      = get_term( $ttm_row['id'], 'series' );
$ttm_term_link = $ttm_term && ! is_wp_error( $ttm_term ) ? get_term_link( $ttm_term ) : '';
$ttm_term_link = is_string( $ttm_term_link ) ? $ttm_term_link : '';

$ttm_published = count( array_filter( $ttm_row['parts'], static fn ( array $part ): bool => 'publish' === $part['status'] ) );

if ( $ttm_is_chapters && '' === ( $attributes['heading'] ?? '' ) ) {
	$ttm_heading = sprintf(
		/* translators: %s: series name. */
		__( '%s — recent chapters', 'ttm-core' ),
		$ttm_row['name']
	);
}

/**
 * F24: the scheduled date for a part's `title` attribute ("Scheduled Sept 26"), or ''.
 *
 * @param array<string, mixed> $ttm_part Series index part row.
 * @return string
 */
$ttm_scheduled_label = static function ( array $ttm_part ): string {
	$ttm_date = Clock::at( (string) $ttm_part['date'] );
	if ( ! $ttm_date ) {
		return '';
	}

	return sprintf(
		/* translators: %s: scheduled date (e.g. "Sept 26"). */
		__( 'Scheduled %s', 'ttm-core' ),
		Dates::short_month( $ttm_date ) . ' ' . $ttm_date->format( 'j' )
	);
};
?>
<div <?php echo Helpers::wrapper( 'series-toc', [ 'is-' . $ttm_variant ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<div class="ttm-cell-heading is-rail">
		<span class="ttm-cell-heading__label"><?php echo esc_html( $ttm_heading ); ?></span>
		<?php if ( $ttm_is_chapters ) : ?>
			<a class="ttm-cell-heading__link" href="<?php echo esc_url( $ttm_term_link ); ?>">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of published chapters. */
						__( 'All %d', 'ttm-core' ),
						$ttm_published
					)
				);
				?>
			</a>
		<?php else : ?>
			<a class="ttm-series-toc__series" href="<?php echo esc_url( $ttm_term_link ); ?>"><?php echo esc_html( $ttm_row['name'] ); ?></a>
			<a class="ttm-series-toc__hub" href="<?php echo esc_url( $ttm_term_link ); ?>"><?php esc_html_e( 'Hub →', 'ttm-core' ); ?></a>
		<?php endif; ?>
	</div>
	<?php if ( $ttm_is_chapters ) : ?>
	<ol class="ttm-numbered">
		<?php foreach ( $ttm_rows as $ttm_part ) : ?>
			<?php
			// $ttm_rows is already filtered to 'publish' status above (chapters is always
			// in the $ttm_open_ended || $ttm_is_chapters branch), so every row here is
			// published; no is-published branch is needed (R4-03, finding 1).
			$ttm_title = '' !== $ttm_part['title'] ? $ttm_part['title'] : get_the_title( $ttm_part['post_id'] );
			?>
			<li class="ttm-numbered__row">
				<span class="ttm-numbered__num tnum"><?php echo esc_html( sprintf( '%02d', (int) $ttm_part['part'] ) ); ?></span>
				<a class="ttm-numbered__title" href="<?php echo esc_url( (string) get_permalink( $ttm_part['post_id'] ) ); ?>"><?php echo esc_html( $ttm_title ); ?></a>
				<?php if ( $ttm_show_dek ) : ?>
					<span class="ttm-numbered__dek"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $ttm_part['post_id'] ) ) ); ?></span>
				<?php endif; ?>
				<span class="ttm-numbered__date"><?php echo esc_html( Helpers::date_short( (string) $ttm_part['date'] ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php else : ?>
	<ol class="ttm-series-toc__list">
		<?php foreach ( $ttm_rows as $ttm_part ) : ?>
			<?php
			$ttm_is_current   = (int) $ttm_part['post_id'] === $ttm_post_id;
			$ttm_is_published = 'publish' === $ttm_part['status'];
			$ttm_state        = $ttm_is_current ? 'is-current' : ( $ttm_is_published ? 'is-published' : 'is-scheduled' );
			$ttm_title        = '' !== $ttm_part['title'] ? $ttm_part['title'] : get_the_title( $ttm_part['post_id'] );
			$ttm_title_attr   = $ttm_is_published ? '' : $ttm_scheduled_label( $ttm_part );
			?>
			<li class="ttm-series-toc__item <?php echo esc_attr( $ttm_state ); ?>"<?php echo '' !== $ttm_title_attr ? ' title="' . esc_attr( $ttm_title_attr ) . '"' : ''; ?>>
				<span class="ttm-series-toc__num tnum"><?php echo esc_html( sprintf( '%02d', (int) $ttm_part['part'] ) ); ?></span>
				<?php if ( $ttm_is_current || ! $ttm_is_published ) : ?>
					<span class="ttm-series-toc__title"><?php echo esc_html( $ttm_title ); ?></span>
				<?php else : ?>
					<a class="ttm-series-toc__title" href="<?php echo esc_url( (string) get_permalink( $ttm_part['post_id'] ) ); ?>"><?php echo esc_html( $ttm_title ); ?></a>
				<?php endif; ?>
				<?php if ( $ttm_show_dek && $ttm_is_published ) : ?>
					<span class="ttm-series-toc__dek"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt( $ttm_part['post_id'] ) ) ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php endif; ?>
</div>
