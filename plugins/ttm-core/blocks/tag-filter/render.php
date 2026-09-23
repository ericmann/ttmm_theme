<?php
/**
 * `ttm/tag-filter` render (01 §4.26, SPEC §6.6): top-tag filter row on a category archive
 * (F16), placed directly under the header. An "All" chip (accent when no `?tag=` is active,
 * neutral otherwise) leads the top tags.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
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

$ttm_limit = (int) ( $attributes['limit'] ?? 0 );
if ( $ttm_limit <= 0 ) {
	$ttm_limit = (int) Config::get( 'archive.tag_filter_limit', 5 );
}

$ttm_tags = array_slice( Stats::top_tags( $ttm_term->term_id ), 0, $ttm_limit );

if ( empty( $ttm_tags ) ) {
	return '';
}

$ttm_active        = (string) get_query_var( 'tag' );
$ttm_category_link = (string) get_category_link( $ttm_term );
$ttm_all_class     = '' === $ttm_active ? 'tag-accent' : 'tag-neutral';
?>
<div <?php echo Helpers::wrapper( 'filter-row' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<span class="ttm-filter-row__label"><?php esc_html_e( 'Filter', 'ttm-core' ); ?></span>
	<a class="tag <?php echo esc_attr( $ttm_all_class ); ?>" href="<?php echo esc_url( $ttm_category_link ); ?>"><?php esc_html_e( 'All', 'ttm-core' ); ?></a>
	<?php foreach ( $ttm_tags as $ttm_tag ) : ?>
		<?php $ttm_is_active = $ttm_tag['slug'] === $ttm_active; ?>
		<a class="tag <?php echo $ttm_is_active ? 'tag-accent' : 'tag-neutral'; ?>" href="<?php echo esc_url( add_query_arg( 'tag', $ttm_tag['slug'], $ttm_category_link ) ); ?>">
			<?php echo esc_html( $ttm_tag['name'] ); ?>
		</a>
	<?php endforeach; ?>
	<span class="ttm-filter-row__sort"><?php esc_html_e( 'Newest first', 'ttm-core' ); ?></span>
</div>
