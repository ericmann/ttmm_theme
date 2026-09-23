<?php
/**
 * Integration tests for the ttm/story-tiles block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Config;

class StoryTilesTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		update_option( 'ttm_settings', [] );
		Config::reset();
		parent::tear_down();
	}

	private function attachment(): int {
		return self::factory()->attachment->create_object(
			[
				'file'           => 'story-cover.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			]
		);
	}

	private function story( string $title, string $date = '2026-05-01 09:00:00', int $words = 3100 ): int {
		$post_id = self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_title'  => $title,
				'post_date'   => $date,
			]
		);
		update_post_meta( $post_id, 'ttm_form', 'story' );
		update_post_meta( $post_id, 'ttm_word_count', $words );

		return $post_id;
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/story-tiles' . $json . ' /-->' );
	}

	public function test_renders_typographic_tiles_with_word_count_and_year(): void {
		$this->story( 'A Quiet Field', '2026-05-01 09:00:00', 3100 );

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-tile', $html );
		$this->assertStringContainsString( 'A Quiet Field', $html );
		$this->assertStringContainsString( '3,100 words · 2026', $html );
	}

	public function test_f19_cover_story_renders_image_with_aria_label_and_no_text(): void {
		$post_id = $this->story( 'Cover Story' );
		set_post_thumbnail( $post_id, $this->attachment() );

		$html = $this->render();

		$this->assertStringContainsString( 'aria-label="Cover Story"', $html );
		$this->assertStringContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'ttm-tile__title', $html );
	}

	public function test_limit_and_columns_class(): void {
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['writing.story_tiles'] = 4;
				return $config;
			}
		);

		for ( $i = 1; $i <= 3; $i++ ) {
			$this->story( "Story {$i}" );
		}

		$html = $this->render(
			[
				'limit'   => 2,
				'columns' => 3,
			] 
		);

		$this->assertSame( 2, substr_count( $html, 'class="ttm-tile"' ) );
		$this->assertStringContainsString( 'is-cols-3', $html );
	}

	public function test_default_columns_come_from_config(): void {
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['writing.tile_columns'] = 3;
				return $config;
			}
		);
		Config::reset();

		$this->story( 'A Quiet Field' );

		$html = $this->render();

		$this->assertStringContainsString( 'is-cols-3', $html );
	}

	public function test_zero_stories_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * P1-05, rule 50: `ttm/story-tiles` is one of the SPEC-named sanctioned blocks --
	 * `Serials::stories()` has no post/term context to read at all.
	 */
	public function test_rule_50_no_context_with_other_content(): void {
		$this->story( 'A Quiet Field' );
		wp_insert_term( 'A Series', 'series' );
		self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$GLOBALS['post'] = null;
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- rule 50 sweep: proving no-context behaviour.

		$html = $this->render();

		$this->assertStringContainsString( 'A Quiet Field', $html );
	}
}
