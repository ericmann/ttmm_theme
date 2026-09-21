<?php
/**
 * `ttm/lead-story` render: the front-page lead (01 §4.7), text-only when there is no image (F8).
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Bindings\Values;
use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\Lead;
use TTM\Core\Query\SeriesIndex;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_lead = Lead::compute();
$ttm_post = $ttm_lead['id'] ? get_post( $ttm_lead['id'] ) : null;

if ( ! $ttm_post ) {
	return '';
}

$ttm_category_id  = PrimaryCategory::id( $ttm_post->ID );
$ttm_category     = $ttm_category_id ? get_term( $ttm_category_id, 'category' ) : null;
$ttm_has_category = $ttm_category && ! is_wp_error( $ttm_category );

$ttm_ctx = [
	'section_name' => $ttm_has_category ? $ttm_category->name : '',
	'is_politics'  => $ttm_has_category && (string) Config::get( 'sections.politics_slug', 'politics' ) === $ttm_category->slug,
];

$ttm_prev_part = null;
$ttm_series    = Helpers::series_position( $ttm_post->ID );

if ( $ttm_series ) {
	$ttm_ctx['series_name'] = $ttm_series['name'];
	$ttm_ctx['part']        = $ttm_series['part'];

	$ttm_series_term  = get_term_by( 'slug', $ttm_series['slug'], 'series' );
	$ttm_raw_total    = $ttm_series_term ? (int) get_term_meta( $ttm_series_term->term_id, 'ttm_total_parts', true ) : 0;
	$ttm_ctx['total'] = $ttm_raw_total > 0 ? $ttm_raw_total : null;

	if ( $ttm_series['part'] > 1 ) {
		$ttm_row = SeriesIndex::for_post( $ttm_post->ID );
		if ( $ttm_row ) {
			foreach ( $ttm_row['parts'] as $ttm_entry ) {
				if ( (int) $ttm_entry['part'] === $ttm_series['part'] - 1 && 'publish' === $ttm_entry['status'] ) {
					$ttm_prev_part = [
						'part'  => $ttm_series['part'] - 1,
						'title' => (string) $ttm_entry['title'],
						'url'   => (string) get_permalink( $ttm_entry['post_id'] ),
					];
					break;
				}
			}
		}
	}
}//end if

$ttm_kicker = Values::kicker( $ttm_ctx );

$ttm_meta_ctx = [
	'date'    => Helpers::date_short( $ttm_post->post_date ),
	/* translators: %d: minutes to read. */
	'reading' => Helpers::reading_time( $ttm_post->ID, __( '%d min read', 'ttm-core' ) ),
];
if ( $ttm_prev_part ) {
	$ttm_meta_ctx['prev_part'] = $ttm_prev_part;
}

$ttm_meta_line = Values::meta_line( [ 'date', 'reading', 'prev-part' ], $ttm_meta_ctx );

$ttm_thumbnail_id = get_post_thumbnail_id( $ttm_post->ID );
$ttm_text_only    = ! $ttm_thumbnail_id || 'thin' === Helpers::preview_state( $attributes );

$ttm_excerpt = trim( wp_strip_all_tags( get_the_excerpt( $ttm_post ) ) );
$ttm_ratio   = '4-3' === ( $attributes['imageRatio'] ?? '16-9' ) ? '4/3' : '16/9';

$ttm_classes = [];
if ( $ttm_text_only ) {
	$ttm_classes[] = 'is-textonly';
}
?>
<div <?php echo Helpers::wrapper( 'lead', $ttm_classes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<article class="ttm-lead__inner">
		<?php if ( ! $ttm_text_only ) : ?>
		<figure class="ttm-lead__media is-style-grayscale" style="aspect-ratio:<?php echo esc_attr( $ttm_ratio ); ?>">
			<?php echo Helpers::image( $ttm_thumbnail_id, 'ttm-lead', [ 'fetchpriority' => 'high' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output is already escaped. ?>
		</figure>
		<?php endif; ?>
		<p class="ttm-lead__kicker"><?php echo esc_html( $ttm_kicker ); ?></p>
		<h2 class="ttm-lead__title"><a href="<?php echo esc_url( (string) get_permalink( $ttm_post ) ); ?>"><?php echo esc_html( get_the_title( $ttm_post ) ); ?></a></h2>
		<?php if ( '' !== $ttm_excerpt ) : ?>
		<p class="ttm-lead__dek"><?php echo esc_html( $ttm_excerpt ); ?></p>
		<?php endif; ?>
		<p class="ttm-lead__meta"><?php echo wp_kses( $ttm_meta_line, [ 'a' => [ 'href' => true ] ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_kses()'d above. ?></p>
	</article>
</div>
