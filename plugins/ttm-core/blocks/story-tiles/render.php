<?php
/**
 * `ttm/story-tiles` render: short-fiction tiles (01 §4.35).
 * F19: a story with a featured image fills the tile with the image, text moves to `aria-label`.
 * Zero stories -> '' (F20: the theme omits the whole "Short fiction" section).
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Config;
use TTM\Core\Fiction\Serials;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_limit = (int) ( $attributes['limit'] ?? 0 );
if ( $ttm_limit <= 0 ) {
	$ttm_limit = (int) Config::get( 'writing.story_tiles', 4 );
}

$ttm_columns = (int) ( $attributes['columns'] ?? 0 );
if ( $ttm_columns <= 0 ) {
	$ttm_columns = (int) Config::get( 'writing.tile_columns', 2 );
}

$ttm_story_ids = Serials::stories( $ttm_limit );

if ( empty( $ttm_story_ids ) ) {
	return '';
}
?>
<div <?php echo Helpers::wrapper( 'story-tiles', [ 'is-cols-' . $ttm_columns ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php foreach ( $ttm_story_ids as $ttm_story_id ) : ?>
		<?php
		$ttm_title      = get_the_title( $ttm_story_id );
		$ttm_thumb_id   = (int) get_post_thumbnail_id( $ttm_story_id );
		$ttm_word_count = (int) get_post_meta( $ttm_story_id, 'ttm_word_count', true );
		$ttm_year       = get_post_time( 'Y', false, $ttm_story_id );
		$ttm_meta       = sprintf(
			/* translators: 1: word count, 2: publication year. */
			__( '%1$s words · %2$s', 'ttm-core' ),
			number_format_i18n( $ttm_word_count ),
			$ttm_year
		);
		?>
		<?php if ( $ttm_thumb_id ) : ?>
		<a class="ttm-tile is-cover" href="<?php echo esc_url( (string) get_permalink( $ttm_story_id ) ); ?>" aria-label="<?php echo esc_attr( $ttm_title ); ?>">
			<?php echo Helpers::image( $ttm_thumb_id, 'ttm-tile' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helpers::image() escapes via wp_get_attachment_image(). ?>
		</a>
		<?php else : ?>
		<a class="ttm-tile" href="<?php echo esc_url( (string) get_permalink( $ttm_story_id ) ); ?>">
			<span class="ttm-tile__title"><?php echo esc_html( $ttm_title ); ?></span>
			<span class="ttm-tile__meta tnum"><?php echo esc_html( $ttm_meta ); ?></span>
		</a>
		<?php endif; ?>
	<?php endforeach; ?>
</div>
