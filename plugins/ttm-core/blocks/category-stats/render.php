<?php
/**
 * `ttm/category-stats` render: "36 articles · 2014–2026", "4 series touch this section",
 * "{Section} RSS" (01 §4.25). Not a category archive -> ''.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Query\Stats;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

if ( ! is_category() ) {
	return '';
}

$ttm_term = get_queried_object();
if ( ! ( $ttm_term instanceof WP_Term ) ) {
	return '';
}

$ttm_stats = Stats::category( $ttm_term->term_id );

/* translators: %d: article count. */
$ttm_count_label = sprintf( _n( '%d article', '%d articles', $ttm_stats['count'], 'ttm-core' ), $ttm_stats['count'] );

$ttm_year_range = '';
if ( $ttm_stats['first_year'] && $ttm_stats['last_year'] ) {
	$ttm_year_range = $ttm_stats['first_year'] === $ttm_stats['last_year']
		? (string) $ttm_stats['first_year']
		: $ttm_stats['first_year'] . '–' . $ttm_stats['last_year'];
}

$ttm_series_line = '';
if ( $ttm_stats['series_count'] > 0 ) {
	$ttm_series_line = sprintf(
		/* translators: %d: series count. */
		_n( '%d series touches this section', '%d series touch this section', $ttm_stats['series_count'], 'ttm-core' ),
		$ttm_stats['series_count']
	);
}

$ttm_feed_url = get_category_feed_link( $ttm_term->term_id );
?>
<div <?php echo Helpers::wrapper( 'category-stats' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<p class="tnum">
		<?php echo esc_html( $ttm_count_label ); ?>
		<?php if ( '' !== $ttm_year_range ) : ?>
			· <?php echo esc_html( $ttm_year_range ); ?>
		<?php endif; ?>
	</p>
	<?php if ( '' !== $ttm_series_line ) : ?>
	<p class="tnum"><?php echo esc_html( $ttm_series_line ); ?></p>
	<?php endif; ?>
	<p>
		<a href="<?php echo esc_url( $ttm_feed_url ); ?>">
			<?php
			printf(
				/* translators: %s: section name. */
				esc_html__( '%s RSS', 'ttm-core' ),
				esc_html( $ttm_term->name )
			);
			?>
		</a>
	</p>
</div>
