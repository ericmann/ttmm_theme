<?php
/**
 * Integration tests for the theme's gallery filter: frames carry their photograph's ratio.
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

class GalleriesTest extends TTM_IntegrationTestCase {

	private function attachment( int $width, int $height ): int {
		$id = self::factory()->attachment->create( [ 'post_mime_type' => 'image/jpeg' ] );
		update_attached_file( $id, '2026/08/photo-' . $id . '.jpg' );
		wp_update_attachment_metadata(
			$id,
			[
				'width'  => $width,
				'height' => $height,
				'file'   => '2026/08/photo-' . $id . '.jpg',
			]
		);

		return $id;
	}

	private function image_block( int $id ): string {
		return "<!-- wp:image {\"id\":{$id},\"sizeSlug\":\"large\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"x.jpg\" alt=\"\" class=\"wp-image-{$id}\"/></figure>\n<!-- /wp:image -->\n";
	}

	public function test_each_gallery_frame_carries_its_photo_ratio(): void {
		$wide = $this->attachment( 2048, 1542 );
		$tall = $this->attachment( 1542, 2048 );

		$html = do_blocks( "<!-- wp:gallery {\"linkTo\":\"none\"} -->\n<figure class=\"wp-block-gallery has-nested-images columns-default is-cropped\">" . $this->image_block( $wide ) . $this->image_block( $tall ) . "</figure>\n<!-- /wp:gallery -->" );

		$this->assertStringContainsString( '<figure style="--ttm-ar:1.3281" class="wp-block-image size-large">', $html );
		$this->assertStringContainsString( '<figure style="--ttm-ar:0.7529" class="wp-block-image size-large">', $html );
	}

	public function test_frames_without_an_attachment_and_lone_images_are_left_alone(): void {
		$lone = $this->image_block( $this->attachment( 800, 600 ) );

		$this->assertStringNotContainsString( '--ttm-ar', do_blocks( $lone ) );
		$this->assertSame(
			'<figure class="wp-block-gallery"><figure class="wp-block-image"><img src="x.jpg"></figure></figure>',
			\TTM\Theme\gallery_aspect_ratios( '<figure class="wp-block-gallery"><figure class="wp-block-image"><img src="x.jpg"></figure></figure>' )
		);
	}

	public function test_an_existing_frame_style_is_kept(): void {
		$id = $this->attachment( 1000, 500 );

		$this->assertSame(
			'<figure style="margin:0;--ttm-ar:2" class="wp-block-image"><img data-id="' . $id . '"></figure>',
			\TTM\Theme\gallery_aspect_ratios( '<figure style="margin:0;" class="wp-block-image"><img data-id="' . $id . '"></figure>' )
		);
	}
}
