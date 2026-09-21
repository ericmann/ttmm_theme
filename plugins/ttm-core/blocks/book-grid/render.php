<?php
/**
 * `ttm/book-grid` render: print book grid (01 §4.36) from `Fiction\Books::all()`.
 * Zero books -> '' (F20: the theme omits the whole "In print" section).
 *
 * @package TTM\Core\Blocks
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;
use TTM\Core\Fiction\Books;

if ( 'empty' === Helpers::preview_state( $attributes ) ) {
	return '';
}

$ttm_books = Books::all();

if ( empty( $ttm_books ) ) {
	return '';
}
?>
<div <?php echo Helpers::wrapper( 'book-grid' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() output is already escaped. ?>>
	<?php foreach ( $ttm_books as $ttm_book ) : ?>
		<?php
		// Translatable captions for Fiction\Books::FORMS; an unrecognised value (shouldn't
		// happen -- Books::sanitize() already constrains it) falls back to a plain transform.
		$ttm_form_labels = [
			'novel'       => __( 'Novel', 'ttm-core' ),
			'novella'     => __( 'Novella', 'ttm-core' ),
			'story-cycle' => __( 'Story cycle', 'ttm-core' ),
			'collection'  => __( 'Collection', 'ttm-core' ),
			'nonfiction'  => __( 'Nonfiction', 'ttm-core' ),
		];
		$ttm_form_label  = $ttm_form_labels[ $ttm_book['form'] ] ?? ucfirst( str_replace( '-', ' ', (string) $ttm_book['form'] ) );
		$ttm_meta_parts  = array_filter(
			[
				$ttm_form_label,
				$ttm_book['year'] > 0 ? (string) $ttm_book['year'] : '',
				! empty( $ttm_book['formats'] ) ? implode( ', ', $ttm_book['formats'] ) : '',
			]
		);
		?>
		<div class="ttm-book">
			<?php if ( ! empty( $ttm_book['cover_id'] ) ) : ?>
			<figure class="ttm-cover">
				<?php echo Helpers::image( (int) $ttm_book['cover_id'], 'ttm-cover' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helpers::image() escapes via wp_get_attachment_image(). ?>
			</figure>
			<?php endif; ?>
			<p class="ttm-book__title"><?php echo esc_html( $ttm_book['title'] ); ?></p>
			<p class="ttm-book__meta tnum"><?php echo esc_html( implode( ' · ', $ttm_meta_parts ) ); ?></p>
			<?php if ( ! empty( $ttm_book['links'] ) ) : ?>
			<p class="ttm-book__links">
				<?php foreach ( $ttm_book['links'] as $ttm_link ) : ?>
				<a class="btn btn-ghost" href="<?php echo esc_url( $ttm_link['url'] ); ?>" rel="noopener" target="_blank">
					<?php echo esc_html( '' !== $ttm_link['label'] ? $ttm_link['label'] : __( 'Buy', 'ttm-core' ) ); ?>
				</a>
				<?php endforeach; ?>
			</p>
			<?php endif; ?>
		</div>
	<?php endforeach; ?>
</div>
