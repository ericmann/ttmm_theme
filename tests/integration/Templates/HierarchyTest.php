<?php
/**
 * Integration tests for TTM\Core\Templates\Hierarchy's template routing (P4-05).
 *
 * @package TTM\Tests\Integration\Templates
 */

declare( strict_types=1 );

class HierarchyTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_journal_primary_post_uses_single_journal_template(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );

		$this->go_to( (string) get_permalink( $post ) );

		$hierarchy = apply_filters( 'single_template_hierarchy', [ 'single.php' ] );

		$this->assertSame( 'single-journal', $hierarchy[0] );
	}

	public function test_technology_post_uses_single_template(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$this->go_to( (string) get_permalink( $post ) );

		$hierarchy = apply_filters( 'single_template_hierarchy', [ 'single.php' ] );

		$this->assertSame( [ 'single.php' ], $hierarchy );
	}

	public function test_writing_category_prepends_page_writing_when_file_exists(): void {
		$writing_template_exists = file_exists( get_stylesheet_directory() . '/templates/page-writing.html' );
		if ( ! $writing_template_exists ) {
			$this->markTestSkipped( 'page-writing.html does not exist yet (P6-05).' );
		}

		$writing = $this->category_id( 'writing', 'Writing' );
		$this->go_to( (string) get_category_link( $writing ) );

		$hierarchy = apply_filters( 'category_template_hierarchy', [ 'category.php' ] );

		$this->assertSame( 'page-writing', $hierarchy[0] );
	}

	public function test_hierarchy_unchanged_for_pages(): void {
		$page = self::factory()->post->create( [ 'post_type' => 'page' ] );
		$this->go_to( (string) get_permalink( $page ) );

		$hierarchy = apply_filters( 'single_template_hierarchy', [ 'page.php' ] );

		$this->assertSame( [ 'page.php' ], $hierarchy );
	}
}
