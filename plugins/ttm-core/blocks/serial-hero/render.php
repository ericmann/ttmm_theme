<?php
/**
 * `ttm/serial-hero` render: Writing page hero (02 §D zone 2, 01 §4.36/§4.37).
 * F3: no in-progress serial -> most recently completed (single purchase-link button, no Follow).
 * F21: no cover -> class `is-nocover`, no figure.
 * No fiction at all -> ''.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Fiction\Serials;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_series_id = (int) ( $attributes['seriesId'] ?? 0 );
$ttm_fallback  = false;

if ( $ttm_series_id ) {
	$ttm_row = \TTM\Core\Query\SeriesIndex::get( $ttm_series_id );
} else {
	$ttm_row = Serials::active();

	if ( ! $ttm_row ) {
		$ttm_completed = Serials::completed();
		usort( $ttm_completed, static fn ( array $a, array $b ): int => strcmp( $b['last_update'], $a['last_update'] ) );
		$ttm_row      = $ttm_completed[0] ?? null;
		$ttm_fallback = null !== $ttm_row;
	}
}

if ( ! $ttm_row ) {
	return '';
}

$ttm_is_complete = $ttm_fallback || 'complete' === $ttm_row['status'];
$ttm_stats       = Serials::stats( $ttm_row );
$ttm_cover_id    = Serials::cover_id( $ttm_row );
$ttm_synopsis    = (string) get_term_field( 'description', (int) $ttm_row['id'], 'series' );

$ttm_kicker = $ttm_is_complete
	? sprintf(
		/* translators: %d: total chapter count. */
		__( 'Writing · Complete · %d chapters', 'ttm-core' ),
		$ttm_stats['total']
	)
	: __( 'Writing · Serial in progress', 'ttm-core' );

// page-writing.html serves both the /writing/ Page (is_page()) and, via
// Templates\Hierarchy's category_template prepend, the Writing category archive
// (is_category()) -- the hero is that page's one h1 either way.
$ttm_title_tag = ( is_page() || is_category() ) ? 'h1' : 'h2';

$ttm_purchase_links = $ttm_is_complete ? Serials::purchase_links( $ttm_row ) : [];
$ttm_purchase_link  = $ttm_purchase_links[0] ?? null;

$ttm_latest = Serials::latest_chapter( $ttm_row );
?>
<div <?php echo Helpers::wrapper( 'serial-hero', $ttm_cover_id ? [] : [ 'is-nocover' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php if ( $ttm_cover_id ) : ?>
	<figure class="ttm-cover is-hero">
		<?php echo Helpers::image( $ttm_cover_id, 'ttm-cover' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helpers::image() escapes via wp_get_attachment_image(). ?>
	</figure>
	<?php endif; ?>
	<div class="ttm-serial-hero__body">
		<p class="ttm-serial-hero__kicker is-style-kicker"><?php echo esc_html( $ttm_kicker ); ?></p>
		<<?php echo esc_html( $ttm_title_tag ); ?> class="ttm-serial-hero__title is-style-display-m"><?php echo esc_html( $ttm_row['name'] ); ?></<?php echo esc_html( $ttm_title_tag ); ?>>
		<?php if ( '' !== $ttm_synopsis ) : ?>
		<p class="ttm-serial-hero__synopsis"><?php echo esc_html( $ttm_synopsis ); ?></p>
		<?php endif; ?>
		<p class="ttm-serial-hero__buttons">
			<a class="btn btn-primary" href="<?php echo esc_url( Serials::first_chapter_url( $ttm_row ) ); ?>"><?php esc_html_e( 'Read chapter 1', 'ttm-core' ); ?></a>
			<?php if ( $ttm_is_complete ) : ?>
				<?php if ( $ttm_purchase_link ) : ?>
				<a class="btn btn-secondary" href="<?php echo esc_url( $ttm_purchase_link['url'] ); ?>">
					<?php echo esc_html( '' !== $ttm_purchase_link['label'] ? $ttm_purchase_link['label'] : __( 'Buy the book', 'ttm-core' ) ); ?>
				</a>
				<?php endif; ?>
			<?php else : ?>
				<?php if ( $ttm_latest ) : ?>
				<a class="btn btn-secondary" href="<?php echo esc_url( (string) get_permalink( $ttm_latest['post_id'] ) ); ?>">
					<?php
					printf(
						/* translators: %d: latest published chapter number. */
						esc_html__( 'Latest: chapter %d', 'ttm-core' ),
						(int) $ttm_latest['part']
					);
					?>
				</a>
				<?php endif; ?>
				<a class="btn btn-ghost" href="#newsletter"><?php esc_html_e( 'Follow by email', 'ttm-core' ); ?></a>
			<?php endif; ?>
		</p>
		<div class="ttm-stats">
			<?php if ( $ttm_is_complete ) : ?>
			<p><span class="ttm-stats__value tnum"><?php echo esc_html( (string) $ttm_stats['total'] ); ?></span><span class="ttm-stats__label"><?php esc_html_e( 'chapters', 'ttm-core' ); ?></span></p>
			<p><span class="ttm-stats__value"><?php esc_html_e( 'Complete', 'ttm-core' ); ?></span></p>
			<?php else : ?>
			<p>
				<span class="ttm-stats__value tnum"><?php echo esc_html( sprintf( '%d / %d', $ttm_stats['published'], $ttm_stats['total'] ) ); ?></span>
				<span class="ttm-stats__label"><?php esc_html_e( 'chapters published', 'ttm-core' ); ?></span>
			</p>
				<?php if ( '' !== $ttm_stats['cadence'] ) : ?>
			<p>
				<span class="ttm-stats__value"><?php echo esc_html( $ttm_stats['cadence'] ); ?></span>
					<?php if ( '' !== $ttm_stats['next_date'] ) : ?>
				<span class="ttm-stats__label">
						<?php
						printf(
						/* translators: %s: next chapter's expected date. */
							esc_html__( 'next: %s', 'ttm-core' ),
							esc_html( Helpers::date_short( $ttm_stats['next_date'] ) )
						);
						?>
				</span>
				<?php endif; ?>
			</p>
			<?php endif; ?>
			<?php endif; ?>
			<?php if ( $ttm_stats['avg_minutes'] > 0 ) : ?>
			<p>
				<span class="ttm-stats__value tnum">
					<?php
					printf(
						/* translators: %d: average reading minutes per chapter. */
						esc_html__( '~%d min', 'ttm-core' ),
						(int) $ttm_stats['avg_minutes']
					);
					?>
				</span>
				<span class="ttm-stats__label"><?php esc_html_e( 'per chapter', 'ttm-core' ); ?></span>
			</p>
			<?php endif; ?>
		</div>
	</div>
</div>
