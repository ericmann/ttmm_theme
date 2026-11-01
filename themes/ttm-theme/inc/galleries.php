<?php
/**
 * Gallery frames carry their photograph's aspect ratio as `--ttm-ar`, so a journal gallery
 * (assets/css/blocks/core-gallery.css) can lay frames out in justified rows: equal height
 * within a row, nothing cropped, order kept.
 *
 * @package TTM\Theme
 */

declare( strict_types=1 );

namespace TTM\Theme;

/**
 * Add `--ttm-ar: <width / height>` to each image frame in a gallery. Frames whose image has no
 * attachment are left alone.
 *
 * @param string $content Rendered core/gallery markup.
 */
function gallery_aspect_ratios( string $content ): string {
	$tags  = new \WP_HTML_Tag_Processor( $content );
	$frame = false;

	while ( $tags->next_tag() ) {
		if ( 'FIGURE' === $tags->get_tag() && $tags->has_class( 'wp-block-image' ) ) {
			$tags->set_bookmark( 'frame' );
			$frame = true;
		} elseif ( $frame && 'IMG' === $tags->get_tag() ) {
			$frame = false;
			$ratio = image_ratio( $tags );
			if ( null !== $ratio ) {
				$tags->seek( 'frame' );
				$style = (string) $tags->get_attribute( 'style' );
				$tags->set_attribute( 'style', ( '' === $style ? '' : rtrim( $style, '; ' ) . ';' ) . '--ttm-ar:' . $ratio );
			}
		}
	}

	return $tags->get_updated_html();
}

/**
 * Width over height of the attachment an `img` shows, from its full size: saved gallery images
 * carry no width or height. Null when the image names no attachment.
 *
 * @param \WP_HTML_Tag_Processor $img Processor stopped on the `img`.
 */
function image_ratio( \WP_HTML_Tag_Processor $img ): ?float {
	$id = (int) $img->get_attribute( 'data-id' );
	if ( 0 === $id && preg_match( '/\bwp-image-(\d+)\b/', (string) $img->get_attribute( 'class' ), $match ) ) {
		$id = (int) $match[1];
	}

	$image = 0 === $id ? false : wp_get_attachment_image_src( $id, 'full' );

	return $image && ! empty( $image[1] ) && ! empty( $image[2] ) ? round( $image[1] / $image[2], 4 ) : null;
}

add_filter( 'render_block_core/gallery', __NAMESPACE__ . '\\gallery_aspect_ratios' );
