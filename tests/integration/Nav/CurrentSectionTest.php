<?php
/**
 * Integration tests for TTM\Core\Nav\CurrentSection and Templates\Hierarchy.
 *
 * @package TTM\Tests\Integration\Nav
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;
use TTM\Core\Templates\Hierarchy;

class CurrentSectionTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function render_nav_link( string $url ): string {
		$markup = sprintf( '<!-- wp:navigation-link {"label":"Test","url":"%s","kind":"custom"} /-->', esc_url( $url ) );

		return (string) do_blocks( $markup );
	}

	public function test_current_section_class_on_single_post_primary_category(): void {
		$tech    = $this->category_id( 'technology', 'Technology' );
		$post_id = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		$this->go_to( get_permalink( $post_id ) );

		$link = get_category_link( $tech );
		$html = $this->render_nav_link( $link );

		$this->assertStringContainsString( 'current-section', $html );
	}

	public function test_current_section_class_on_category_archive(): void {
		$business = $this->category_id( 'business', 'Business' );
		$this->go_to( get_category_link( $business ) );

		$html = $this->render_nav_link( get_category_link( $business ) );

		$this->assertStringContainsString( 'current-section', $html );
	}

	public function test_series_item_hidden_when_index_empty(): void {
		update_option( 'ttm_series_index', [] );

		$html = $this->render_nav_link( home_url( '/series/' ) );

		$this->assertSame( '', $html );
	}

	public function test_series_item_current_on_hub_page_and_series_archive(): void {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		wp_set_object_terms( $post_id, [ $term_id ], 'series' );
		SeriesIndex::rebuild();

		$hub_page_id = self::factory()->post->create(
			[
				'post_type' => 'page',
				'post_name' => 'series',
			]
		);

		$this->go_to( get_permalink( $hub_page_id ) );
		$this->assertStringContainsString( 'current-section', $this->render_nav_link( home_url( '/series/' ) ) );

		$term = get_term( $term_id, 'series' );
		$this->go_to( (string) get_term_link( $term ) );
		$this->assertStringContainsString( 'current-section', $this->render_nav_link( home_url( '/series/' ) ) );
	}

	public function test_body_classes_on_single_series_post(): void {
		$term_id = self::factory()->term->create( [ 'taxonomy' => 'series' ] );
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		update_post_meta( $post_id, 'ttm_form', 'chapter' );
		wp_set_object_terms( $post_id, [ $term_id ], 'series' );
		SeriesIndex::rebuild();

		$this->go_to( get_permalink( $post_id ) );

		$classes = Hierarchy::body_classes( [] );

		$this->assertContains( 'ttm-in-series', $classes );
		$this->assertContains( 'ttm-form-chapter', $classes );
	}
}
