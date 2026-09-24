<?php
/**
 * Integration tests for TTM\Core\Cli\DemoImage.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\DemoImage;

class DemoImageTest extends TTM_IntegrationTestCase {

	private const FILE = 'demo-fixture-test.jpg';

	private string $tmp_dir;
	private string $images_dir;

	public function set_up(): void {
		parent::set_up();

		$this->tmp_dir    = sys_get_temp_dir() . '/ttm-demo-image-' . wp_generate_password( 8, false ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_tempnam -- test-only fixture tree, not a plugin runtime path.
		$this->images_dir = $this->tmp_dir . '/images';
		mkdir( $this->images_dir, 0777, true ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.directory_mkdir -- test-only fixture tree.

		$image = imagecreatetruecolor( 1600, 1000 );
		$grey  = imagecolorallocate( $image, 186, 182, 182 );
		imagefill( $image, 0, 0, $grey );
		imagejpeg( $image, $this->images_dir . '/' . self::FILE ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_imagejpeg -- test-only fixture tree.
		imagedestroy( $image );

		file_put_contents( // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents -- test-only fixture tree.
			$this->tmp_dir . '/CREDITS.json',
			wp_json_encode(
				[
					[
						'file'                => self::FILE,
						'openverse_id'        => 'fixture-id',
						'title'               => 'A Fixture Photo',
						'creator'             => 'Fixture Creator',
						'creator_url'         => 'https://example.test/fixture-creator',
						'source'              => 'example',
						'foreign_landing_url' => 'https://example.test/photos/fixture-id',
						'license'             => 'cc0',
						'license_url'         => 'https://creativecommons.org/publicdomain/zero/1.0/',
						'width'               => 1600,
						'height'              => 1000,
						'bytes'               => filesize( $this->images_dir . '/' . self::FILE ),
						'sha256'              => hash_file( 'sha256', $this->images_dir . '/' . self::FILE ),
						'attribution'         => 'A Fixture Photo by Fixture Creator',
					],
				]
			)
		);

		add_filter( 'ttm_demo_images_dir', [ $this, 'filter_images_dir' ] );
	}

	public function tear_down(): void {
		remove_filter( 'ttm_demo_images_dir', [ $this, 'filter_images_dir' ] );

		$files = glob( $this->images_dir . '/*' );
		foreach ( false === $files ? [] : $files as $file ) {
			wp_delete_file( $file );
		}
		if ( file_exists( $this->tmp_dir . '/CREDITS.json' ) ) {
			wp_delete_file( $this->tmp_dir . '/CREDITS.json' );
		}
		@rmdir( $this->images_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- test-only fixture tree.
		@rmdir( $this->tmp_dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPressVIPMinimum.Functions.RestrictedFunctions.directory_rmdir -- test-only fixture tree.

		parent::tear_down();
	}

	public function filter_images_dir(): string {
		return $this->images_dir;
	}

	public function test_attach_sideloads_a_present_file_with_alt_caption_and_credit(): void {
		$post_id = self::factory()->post->create();

		$attachment_id = DemoImage::attach( self::FILE, $post_id, 'A fixture photo alt text', 'A fixture caption.' );

		$this->assertGreaterThan( 0, $attachment_id );
		$this->assertSame( 'image/jpeg', get_post_mime_type( $attachment_id ) );
		$this->assertSame( self::FILE, basename( get_attached_file( $attachment_id ) ) );
		$this->assertSame( 'A fixture photo alt text', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

		$attachment = get_post( $attachment_id );
		$this->assertSame( 'A fixture caption.', $attachment->post_excerpt );
		$this->assertStringContainsString( 'Fixture Creator', $attachment->post_content );
		$this->assertStringContainsString( 'Openverse', $attachment->post_content );
		$this->assertSame( 1, (int) get_post_meta( $attachment_id, '_ttm_seed', true ) );

		// The DB row rolls back with the rest of the test, but the uploaded file on disk does
		// not -- force-delete it now so a later test's wp_unique_filename() doesn't see a
		// collision and suffix its own upload (found via test_reattach_after_delete_...
		// picking up a "-2" name instead of the bare one).
		wp_delete_attachment( $attachment_id, true );
	}

	public function test_attach_leaves_the_fixture_file_in_place(): void {
		$post_id = self::factory()->post->create();

		$attachment_id = DemoImage::attach( self::FILE, $post_id, 'Alt', 'Caption' );

		$this->assertFileExists( $this->images_dir . '/' . self::FILE );

		wp_delete_attachment( $attachment_id, true );
	}

	public function test_attach_returns_zero_for_a_missing_file(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame( 0, DemoImage::attach( 'demo-does-not-exist.jpg', $post_id, 'Alt', 'Caption' ) );
	}

	public function test_attach_returns_zero_for_an_invalid_name(): void {
		$post_id = self::factory()->post->create();

		$this->assertSame( 0, DemoImage::attach( '../demo-fixture-test.jpg', $post_id, 'Alt', 'Caption' ) );
		$this->assertSame( 0, DemoImage::attach( 'not-a-demo-file.jpg', $post_id, 'Alt', 'Caption' ) );
	}

	public function test_reattach_after_delete_reuses_the_file_name(): void {
		$post_id = self::factory()->post->create();

		$first = DemoImage::attach( self::FILE, $post_id, 'Alt', 'Caption' );
		$this->assertGreaterThan( 0, $first );
		wp_delete_attachment( $first, true );

		$second = DemoImage::attach( self::FILE, $post_id, 'Alt', 'Caption' );

		$this->assertGreaterThan( 0, $second );
		$this->assertSame( self::FILE, basename( get_attached_file( $second ) ) );

		wp_delete_attachment( $second, true );
	}
}
