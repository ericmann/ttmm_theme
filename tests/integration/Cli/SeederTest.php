<?php
/**
 * Integration tests for TTM\Core\Cli\Seeder.
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\Seeder;

class SeederTest extends TTM_IntegrationTestCase {

	public function test_seed_creates_seven_sections_and_politics_child(): void {
		$seeder = new Seeder();
		$seeder->seed_categories();

		$sections = [ 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ];
		foreach ( $sections as $slug ) {
			$this->assertNotFalse( term_exists( $slug, 'category' ), "Missing section {$slug}" );
		}

		$politics = get_term_by( 'slug', 'politics', 'category' );
		$opinion  = get_term_by( 'slug', 'opinion', 'category' );
		$this->assertNotFalse( $politics );
		$this->assertSame( $opinion->term_id, $politics->parent );
	}

	public function test_seed_creates_pages_with_templates(): void {
		$seeder = new Seeder();
		$seeder->seed_pages();

		$series = get_page_by_path( 'series', OBJECT, 'page' );
		$this->assertNotNull( $series );
		$this->assertSame( 'page-series.html', get_post_meta( $series->ID, '_wp_page_template', true ) );

		$writing = get_page_by_path( 'writing', OBJECT, 'page' );
		$this->assertSame( 'page-writing.html', get_post_meta( $writing->ID, '_wp_page_template', true ) );
	}

	public function test_seed_is_idempotent(): void {
		$seeder = new Seeder();
		$seeder->seed_categories();
		$first  = $seeder->seed_posts();
		$second = $seeder->seed_posts();

		$this->assertSame( $first, $second );

		$count = wp_count_posts( 'post' )->publish;
		$this->assertGreaterThanOrEqual( 60, (int) $count );
	}

	public function test_seed_posts_have_primary_category_and_word_count(): void {
		$seeder = new Seeder();
		$seeder->seed_categories();
		$ids = $seeder->seed_posts();

		$post_id = $ids[0];
		$this->assertGreaterThan( 0, (int) get_post_meta( $post_id, 'ttm_primary_category', true ) );
		$this->assertGreaterThan( 0, (int) get_post_meta( $post_id, 'ttm_word_count', true ) );
	}

	public function test_reset_removes_only_seeded_content(): void {
		$manual_post = self::factory()->post->create( [ 'post_title' => 'Not seeded' ] );

		$seeder = new Seeder();
		$seeder->run( 'normal' );
		$seeder->reset();

		$this->assertNotNull( get_post( $manual_post ) );

		$count = wp_count_posts( 'post' )->publish;
		$this->assertSame( 1, (int) $count );
	}

	public function test_generated_image_is_an_attachment_with_alt(): void {
		if ( ! function_exists( 'imagecreatetruecolor' ) ) {
			$this->markTestSkipped( 'GD is not available.' );
		}

		$seeder        = new Seeder();
		$attachment_id = $seeder->image( 'Test Image', 'ttm-tile' );

		$this->assertGreaterThan( 0, $attachment_id );
		$this->assertSame( 'attachment', get_post_type( $attachment_id ) );
		$this->assertSame( 'Test Image', get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
	}
}
