<?php
/**
 * `ttm/most-read` render: manually-flagged posts, scoped to the current category on a category
 * archive (01 §4.29), or site-wide on a tag/date archive (SPEC §6.6 "Tag / date archive": "aside
 * = Most read only"). `source=views` is not implemented (Q6) and is treated as `manual`. Outside
 * every archive kind above, or zero flagged posts -> ''.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_is_category = is_category();

if ( ! $ttm_is_category && ! is_tag() && ! is_month() && ! is_year() && ! is_day() ) {
	return '';
}

$ttm_term = $ttm_is_category ? get_queried_object() : null;
if ( $ttm_is_category && ! ( $ttm_term instanceof WP_Term ) ) {
	return '';
}

$ttm_limit = (int) ( $attributes['limit'] ?? 0 );
if ( $ttm_limit <= 0 ) {
	$ttm_limit = (int) Config::get( 'archive.most_read_limit', 3 );
}

$ttm_meta_query = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- bounded by posts_per_page.
	'relation' => 'AND',
	[
		'key'   => 'ttm_featured_in_section',
		'value' => '1',
	],
];

if ( $ttm_term instanceof WP_Term ) {
	$ttm_meta_query[] = [
		'key'   => 'ttm_primary_category',
		'value' => $ttm_term->term_id,
	];
}

$ttm_query = new WP_Query(
	[
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'meta_query'     => $ttm_meta_query,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'posts_per_page' => $ttm_limit,
	]
);

if ( empty( $ttm_query->posts ) ) {
	return '';
}
?>
<div <?php echo Helpers::wrapper( 'most-read' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<p class="ttm-cell-heading__label"><?php esc_html_e( 'Most read', 'ttm-core' ); ?></p>
	<ol class="ttm-most-read__list">
		<?php foreach ( $ttm_query->posts as $ttm_index => $ttm_post ) : ?>
		<li class="ttm-most-read__item">
			<span class="ttm-most-read__num tnum"><?php echo esc_html( sprintf( '%02d', $ttm_index + 1 ) ); ?></span>
			<a href="<?php echo esc_url( (string) get_permalink( $ttm_post ) ); ?>"><?php echo esc_html( get_the_title( $ttm_post ) ); ?></a>
		</li>
		<?php endforeach; ?>
	</ol>
</div>
