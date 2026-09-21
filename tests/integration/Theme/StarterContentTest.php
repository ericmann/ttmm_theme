<?php
/**
 * Integration tests for TTM\Theme\create_starter_content().
 *
 * @package TTM\Tests\Integration\Theme
 */

declare( strict_types=1 );

use function TTM\Theme\create_starter_content;

class StarterContentTest extends TTM_IntegrationTestCase {

	public function test_activation_creates_sections_pages_and_navigation(): void {
		create_starter_content();

		foreach ( [ 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ] as $slug ) {
			$this->assertNotFalse( term_exists( $slug, 'category' ), "Missing category {$slug}" );
		}

		$series = get_page_by_path( 'series', OBJECT, 'page' );
		$this->assertNotNull( $series );
		$this->assertSame( 'page-series.html', get_post_meta( $series->ID, '_wp_page_template', true ) );

		$nav = get_page_by_path( 'ttm-sections', OBJECT, 'wp_navigation' );
		$this->assertNotNull( $nav );
		$this->assertStringContainsString( 'Series', $nav->post_content );
	}

	public function test_activation_twice_creates_nothing_new(): void {
		create_starter_content();
		$first_page_count = wp_count_posts( 'page' )->publish;
		$first_nav_count  = wp_count_posts( 'wp_navigation' )->publish;
		$first_cat_count  = wp_count_terms( [ 'taxonomy' => 'category' ] );

		create_starter_content();

		$this->assertSame( $first_page_count, wp_count_posts( 'page' )->publish );
		$this->assertSame( $first_nav_count, wp_count_posts( 'wp_navigation' )->publish );
		$this->assertSame( $first_cat_count, wp_count_terms( [ 'taxonomy' => 'category' ] ) );
	}

	public function test_existing_page_slug_is_reused_and_template_assigned(): void {
		$existing_id = self::factory()->post->create(
			[
				'post_type'  => 'page',
				'post_name'  => 'about',
				'post_title' => 'About Me',
			]
		);

		create_starter_content();

		$page = get_page_by_path( 'about', OBJECT, 'page' );
		$this->assertSame( $existing_id, $page->ID );
		$this->assertSame( 'About Me', $page->post_title );
		$this->assertSame( 'page.html', get_post_meta( $existing_id, '_wp_page_template', true ) );
	}
}
