<?php
/**
 * `ttm/series-prev-next` render (01 §4.21): series parts, or chronological (F11) fallback.
 * A missing side renders an empty cell (keeps the grid); `mode=auto` prefers series.
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 * @var WP_Block              $block      Block instance (postId context).
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Core\Query\SeriesIndex;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_post_id = (int) ( $block->context['postId'] ?? get_the_ID() );
if ( ! $ttm_post_id ) {
	return '';
}

$ttm_mode = (string) ( $attributes['mode'] ?? 'auto' );
$ttm_row  = SeriesIndex::for_post( $ttm_post_id );

$ttm_use_series = 'series' === $ttm_mode || ( 'auto' === $ttm_mode && $ttm_row );

$ttm_prev = null;
$ttm_next = null;

if ( $ttm_use_series && $ttm_row ) {
	$ttm_current_part = 0;
	foreach ( $ttm_row['parts'] as $ttm_entry ) {
		if ( (int) $ttm_entry['post_id'] === $ttm_post_id ) {
			$ttm_current_part = (int) $ttm_entry['part'];
			break;
		}
	}

	foreach ( $ttm_row['parts'] as $ttm_entry ) {
		if ( 'publish' !== $ttm_entry['status'] ) {
			continue;
		}
		$ttm_title = '' !== $ttm_entry['title'] ? $ttm_entry['title'] : get_the_title( $ttm_entry['post_id'] );

		if ( (int) $ttm_entry['part'] === $ttm_current_part - 1 ) {
			$ttm_prev = [
				'url'   => (string) get_permalink( $ttm_entry['post_id'] ),
				/* translators: %d: part number. */
				'label' => sprintf( __( '← Part %d', 'ttm-core' ), $ttm_entry['part'] ),
				'title' => $ttm_title,
			];
		}
		if ( (int) $ttm_entry['part'] === $ttm_current_part + 1 ) {
			$ttm_next = [
				'url'   => (string) get_permalink( $ttm_entry['post_id'] ),
				/* translators: %d: part number. */
				'label' => sprintf( __( 'Part %d →', 'ttm-core' ), $ttm_entry['part'] ),
				'title' => $ttm_title,
			];
		}
	}//end foreach
} else {
	$ttm_category_id = PrimaryCategory::id( $ttm_post_id );
	$ttm_post        = get_post( $ttm_post_id );

	if ( $ttm_category_id && $ttm_post ) {
		$ttm_category      = get_term( $ttm_category_id, 'category' );
		$ttm_category_name = $ttm_category && ! is_wp_error( $ttm_category ) ? $ttm_category->name : '';

		$ttm_prev_posts = ( new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'meta_key'       => 'ttm_primary_category', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded by posts_per_page.
				'meta_value'     => $ttm_category_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'date_query'     => [
			[
			'before' => $ttm_post->post_date,
			'column' => 'post_date',
				],
				], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
				'orderby'        => 'date',
				'order'          => 'DESC',
				'posts_per_page' => 1,
				'post__not_in'   => [ $ttm_post_id ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			]
		) )->posts;

		if ( $ttm_prev_posts ) {
			$ttm_prev = [
				'url'   => (string) get_permalink( $ttm_prev_posts[0] ),
				/* translators: %s: category name. */
				'label' => sprintf( __( '← Previously in %s', 'ttm-core' ), $ttm_category_name ),
				'title' => get_the_title( $ttm_prev_posts[0] ),
			];
		}

		$ttm_next_posts = ( new WP_Query(
			[
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'meta_key'       => 'ttm_primary_category', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- bounded by posts_per_page.
				'meta_value'     => $ttm_category_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'date_query'     => [
			[
			'after'  => $ttm_post->post_date,
			'column' => 'post_date',
				],
				], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_date_query
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => 1,
				'post__not_in'   => [ $ttm_post_id ], // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
			]
		) )->posts;

		if ( $ttm_next_posts ) {
			$ttm_next = [
				'url'   => (string) get_permalink( $ttm_next_posts[0] ),
				'label' => __( 'Next →', 'ttm-core' ),
				'title' => get_the_title( $ttm_next_posts[0] ),
			];
		}
	}//end if
}//end if
?>
<div <?php echo Helpers::wrapper( 'prevnext' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<div class="ttm-prevnext__prev">
		<?php if ( $ttm_prev ) : ?>
		<a href="<?php echo esc_url( $ttm_prev['url'] ); ?>">
			<span class="ttm-prevnext__label"><?php echo esc_html( $ttm_prev['label'] ); ?></span>
			<span class="ttm-prevnext__title"><?php echo esc_html( $ttm_prev['title'] ); ?></span>
		</a>
		<?php endif; ?>
	</div>
	<div class="ttm-prevnext__next">
		<?php if ( $ttm_next ) : ?>
		<a href="<?php echo esc_url( $ttm_next['url'] ); ?>">
			<span class="ttm-prevnext__label"><?php echo esc_html( $ttm_next['label'] ); ?></span>
			<span class="ttm-prevnext__title"><?php echo esc_html( $ttm_next['title'] ); ?></span>
		</a>
		<?php endif; ?>
	</div>
</div>
