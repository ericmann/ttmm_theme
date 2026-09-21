<?php
/**
 * Integration tests for the ttm/lead-story block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class LeadStoryTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_transient( 'ttm_lead_id' );
		parent::tear_down();
	}

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function attachment(): int {
		return self::factory()->attachment->create_object(
			[
				'file'           => 'test.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			]
		);
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/lead-story' . $json . ' /-->' );
	}

	public function test_renders_lead_with_image_kicker_title_dek_meta(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_excerpt'  => 'A short dek.',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		update_post_meta( $post, 'ttm_word_count', 460 );
		set_post_thumbnail( $post, $this->attachment() );

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-lead__inner', $html );
		$this->assertStringContainsString( 'Technology', $html );
		$this->assertStringContainsString( get_the_title( $post ), $html );
		$this->assertStringContainsString( 'A short dek.', $html );
		$this->assertStringContainsString( '2 min read', $html );
		$this->assertStringContainsString( 'ttm-lead__media', $html );
	}

	public function test_f8_no_image_adds_is_textonly_and_no_figure(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$html = $this->render();

		$this->assertStringContainsString( 'is-textonly', $html );
		$this->assertStringNotContainsString( '<figure', $html );
	}

	public function test_f22_kicker_shows_real_section_of_sitewide_lead(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$stale = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-01-01 09:00:00',
			]
		);
		update_post_meta( $stale, 'ttm_primary_category', $tech );

		$sitewide = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $business ],
				'post_date'     => '2026-09-19 09:00:00',
			]
		);
		update_post_meta( $sitewide, 'ttm_primary_category', $business );

		$html = $this->render();

		$this->assertStringContainsString( get_the_title( $sitewide ), $html );
		$this->assertStringContainsString( 'Business', $html );
		$this->assertStringNotContainsString( 'Technology', $html );
	}

	public function test_series_lead_meta_links_previous_part(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech      = $this->category_id( 'technology', 'Technology' );
		$term      = wp_insert_term( 'Hardening WordPress', 'series' );
		$series_id = (int) $term['term_id'];

		$part1 = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-18 09:00:00',
				'post_title'    => 'Part One Title',
			]
		);
		update_post_meta( $part1, 'ttm_primary_category', $tech );
		update_post_meta( $part1, 'ttm_series_part', 1 );
		wp_set_object_terms( $part1, [ $series_id ], 'series' );

		$part2 = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_date'     => '2026-09-19 09:00:00',
				'post_title'    => 'Part Two Title',
			]
		);
		update_post_meta( $part2, 'ttm_primary_category', $tech );
		update_post_meta( $part2, 'ttm_series_part', 2 );
		wp_set_object_terms( $part2, [ $series_id ], 'series' );

		\TTM\Core\Query\SeriesIndex::rebuild();

		$html = $this->render();

		$this->assertStringContainsString( get_the_title( $part2 ), $html );
		$this->assertStringContainsString( 'Part 1: Part One Title', $html );
		$this->assertStringContainsString( (string) get_permalink( $part1 ), $html );
	}

	public function test_renders_nothing_when_no_posts(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	public function test_image_has_fetchpriority_high_and_no_lazy(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );
		set_post_thumbnail( $post, $this->attachment() );

		$html = $this->render();

		$this->assertStringContainsString( 'fetchpriority="high"', $html );
		$this->assertStringNotContainsString( 'loading="lazy"', $html );
	}
}
