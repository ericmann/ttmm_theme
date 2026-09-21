<?php
/**
 * `ttm/series-featured` render: series hub featured slot (01 §4.38), also reused full-width
 * on `taxonomy-series.html` (02 §F single-series zone).
 * Resolution: `seriesId` attribute -> the queried `series` term (taxonomy-series.html) ->
 * auto-pick per 03 §3 (`ttm_featured` term meta if set, else the in-progress series with the
 * newest part, else the most recently completed - F5: single button, "Complete · {categories}"
 * kicker). F24: scheduled parts render unlinked with a "Scheduled {date}" title attribute.
 * Zero series (or an explicit `seriesId` that resolves to nothing) -> ''.
 * `partsLimit` explicitly set to `0` in the block markup (not merely absent/defaulted) means
 * "no cap" -- the full part list renders and no "All N" link is shown (taxonomy-series.html).
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block              $block      Block instance (parsed_block for the raw attrs).
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Query\SeriesIndex;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

/**
 * The date of a row's most recently added part (its highest part number), for "newest part"
 * comparisons across series. `parts` is sorted ascending by part number.
 *
 * @param array<string, mixed> $row Series index row.
 * @return string
 */
$ttm_latest_part_date = static function ( array $row ): string {
	$parts = $row['parts'];
	if ( empty( $parts ) ) {
		return '';
	}

	return (string) end( $parts )['date'];
};

$ttm_series_id = (int) ( $attributes['seriesId'] ?? 0 );
$ttm_queried   = get_queried_object();

if ( $ttm_series_id ) {
	$ttm_row = SeriesIndex::get( $ttm_series_id );
} elseif ( $ttm_queried instanceof WP_Term && 'series' === $ttm_queried->taxonomy ) {
	// taxonomy-series.html: feature the series being viewed, not an auto-pick.
	$ttm_row = SeriesIndex::get( $ttm_queried->term_id );
} else {
	$ttm_rows = SeriesIndex::all();

	if ( empty( $ttm_rows ) ) {
		return '';
	}

	$ttm_row = null;
	foreach ( $ttm_rows as $ttm_candidate ) {
		if ( get_term_meta( $ttm_candidate['id'], 'ttm_featured', true ) ) {
			$ttm_row = $ttm_candidate;
			break;
		}
	}

	if ( ! $ttm_row ) {
		$ttm_in_progress = array_values( array_filter( $ttm_rows, static fn ( array $r ): bool => 'in-progress' === $r['status'] ) );
		$ttm_pool        = ! empty( $ttm_in_progress )
			? $ttm_in_progress
			: array_values( array_filter( $ttm_rows, static fn ( array $r ): bool => 'complete' === $r['status'] ) );

		usort(
			$ttm_pool,
			static fn ( array $a, array $b ): int => strcmp( $ttm_latest_part_date( $b ), $ttm_latest_part_date( $a ) )
		);

		$ttm_row = $ttm_pool[0] ?? null;
	}
}//end if

if ( ! $ttm_row ) {
	return '';
}

$ttm_is_complete = 'complete' === $ttm_row['status'];

$ttm_categories = implode( ', ', array_map( 'get_cat_name', $ttm_row['categories'] ) );
$ttm_kicker     = trim( Helpers::status_word( $ttm_row['status'] ) . ( '' !== $ttm_categories ? ' · ' . $ttm_categories : '' ) );

$ttm_progress = render_block(
	[
		'blockName'    => 'ttm/series-progress',
		'attrs'        => [ 'seriesId' => (int) $ttm_row['id'] ],
		'innerBlocks'  => [],
		'innerHTML'    => '',
		'innerContent' => [],
	]
);

$ttm_raw_attrs      = $block->parsed_block['attrs'] ?? [];
$ttm_limit_explicit = array_key_exists( 'partsLimit', $ttm_raw_attrs );
$ttm_uncapped       = $ttm_limit_explicit && 0 === (int) $ttm_raw_attrs['partsLimit'];

$ttm_limit = (int) ( $attributes['partsLimit'] ?? 0 );
if ( ! $ttm_uncapped && $ttm_limit <= 0 ) {
	$ttm_limit = (int) Config::get( 'series.hub_featured_parts', 12 );
}

$ttm_parts    = $ttm_row['parts'];
$ttm_all_link = get_term_link( (int) $ttm_row['id'], 'series' );
$ttm_all_link = is_string( $ttm_all_link ) ? $ttm_all_link : '';
$ttm_has_more = ! $ttm_uncapped && count( $ttm_parts ) > $ttm_limit;
$ttm_visible  = $ttm_uncapped ? $ttm_parts : array_slice( $ttm_parts, 0, $ttm_limit );
?>
<div <?php echo Helpers::wrapper( 'series-featured' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<div class="ttm-series-featured__main">
		<p class="ttm-series-featured__kicker is-style-kicker"><?php echo esc_html( $ttm_kicker ); ?></p>
		<h2 class="ttm-series-featured__title is-style-featured-series"><?php echo esc_html( $ttm_row['name'] ); ?></h2>
		<?php
		$ttm_dek = (string) get_term_field( 'description', (int) $ttm_row['id'], 'series' );
		if ( '' !== $ttm_dek ) :
			?>
		<p class="ttm-series-featured__dek"><?php echo esc_html( $ttm_dek ); ?></p>
		<?php endif; ?>
		<?php echo $ttm_progress; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_block() output is already escaped by the inner block. ?>
		<p class="ttm-series-featured__buttons">
			<a class="btn btn-primary" href="<?php echo esc_url( (string) get_permalink( $ttm_row['first_post_id'] ) ); ?>"><?php esc_html_e( 'Start at part 1', 'ttm-core' ); ?></a>
			<?php if ( ! $ttm_is_complete ) : ?>
			<a class="btn btn-secondary" href="#newsletter"><?php esc_html_e( 'Follow this series', 'ttm-core' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
	<ol class="ttm-series-featured__parts">
		<?php foreach ( $ttm_visible as $ttm_part ) : ?>
			<?php
			$ttm_is_published = 'publish' === $ttm_part['status'];
			$ttm_title        = '' !== $ttm_part['title'] ? $ttm_part['title'] : get_the_title( $ttm_part['post_id'] );
			?>
			<li class="ttm-series-featured__part <?php echo $ttm_is_published ? 'is-published' : 'is-scheduled'; ?>">
				<span class="ttm-series-featured__num tnum"><?php echo esc_html( sprintf( '%02d', (int) $ttm_part['part'] ) ); ?></span>
				<?php if ( $ttm_is_published ) : ?>
				<a href="<?php echo esc_url( (string) get_permalink( $ttm_part['post_id'] ) ); ?>"><?php echo esc_html( $ttm_title ); ?></a>
				<?php else : ?>
					<?php
					$ttm_date       = \TTM\Core\Support\Clock::at( $ttm_part['date'] );
					$ttm_title_attr = $ttm_date
						? sprintf(
							/* translators: %s: scheduled date (e.g. "Sept 26"). */
							__( 'Scheduled %s', 'ttm-core' ),
							\TTM\Core\Support\Dates::short_month( $ttm_date ) . ' ' . $ttm_date->format( 'j' )
						)
						: '';
					?>
				<span<?php echo $ttm_title_attr ? ' title="' . esc_attr( $ttm_title_attr ) . '"' : ''; ?>><?php echo esc_html( $ttm_title ); ?></span>
				<?php endif; ?>
				<span class="ttm-series-featured__date"><?php echo esc_html( Helpers::date_short( $ttm_part['date'] ) ); ?></span>
			</li>
		<?php endforeach; ?>
	</ol>
	<?php if ( $ttm_has_more && '' !== $ttm_all_link ) : ?>
	<p class="ttm-series-featured__all">
		<a href="<?php echo esc_url( $ttm_all_link ); ?>">
			<?php
			printf(
				/* translators: %d: total part count. */
				esc_html__( 'All %d →', 'ttm-core' ),
				(int) count( $ttm_parts )
			);
			?>
		</a>
	</p>
	<?php endif; ?>
</div>
