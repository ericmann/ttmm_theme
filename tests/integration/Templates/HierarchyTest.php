<?php
/**
 * Integration tests for TTM\Core\Templates\Hierarchy's template routing (P4-05).
 *
 * @package TTM\Tests\Integration\Templates
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class HierarchyTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * A fiction series with one published part in Writing, so `is_f28()` is false.
	 */
	private function make_fiction_series(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$term    = wp_insert_term( 'A Novel', 'series', [ 'slug' => 'a-novel' ] );
		update_term_meta( (int) $term['term_id'], 'ttm_form', 'novel' );

		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $writing );
		update_post_meta( $post, 'ttm_series_part', 1 );
		wp_set_object_terms( $post, [ (int) $term['term_id'] ], 'series' );

		SeriesIndex::rebuild();
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

	public function test_writing_category_prepends_page_writing_when_fiction_exists(): void {
		$this->make_fiction_series();

		$writing = $this->category_id( 'writing', 'Writing' );
		$this->go_to( (string) get_category_link( $writing ) );

		$hierarchy = apply_filters( 'category_template_hierarchy', [ 'category.php' ] );

		$this->assertSame( 'page-writing', $hierarchy[0] );
	}

	/**
	 * P1-04, F28: `/writing/` (a real Page, template page-writing) rewrites into the Writing
	 * category archive while there is no fiction series and no story at all.
	 */
	public function test_writing_page_becomes_the_writing_category_archive_when_no_fiction_exists(): void {
		$this->category_id( 'writing', 'Writing' );

		$page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_name'   => 'writing',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $page, '_wp_page_template', 'page-writing' );

		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( home_url( '/writing/' ) );

		$this->assertTrue( is_category() );
		$queried = get_queried_object();
		$this->assertInstanceOf( \WP_Term::class, $queried );
		$this->assertSame( 'writing', $queried->slug );

		$hierarchy = apply_filters( 'category_template_hierarchy', [ 'category.php' ] );
		$this->assertSame( 'category.php', $hierarchy[0] );
	}

	/**
	 * R1-06, PLAN Decision "F28 routing": `/writing/` in the F28 state paginates exactly like
	 * `/category/writing/` -- `archive.per_page`, not the site's own `posts_per_page` option.
	 * `Query\Archive::shape()` also runs on `pre_get_posts` but is registered (and so runs)
	 * before `Hierarchy`, so at the point it ran this was still a page query and it never set
	 * `posts_per_page`; `route_writing_page()` must set it itself.
	 */
	public function test_writing_page_in_f28_state_uses_archive_per_page(): void {
		$this->category_id( 'writing', 'Writing' );

		$page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_name'   => 'writing',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $page, '_wp_page_template', 'page-writing' );

		update_option( 'posts_per_page', 10 );

		$this->set_permalink_structure( '/%postname%/' );
		$this->go_to( home_url( '/writing/' ) );

		global $wp_query;
		$this->assertSame(
			(int) \TTM\Core\Config::get( 'archive.per_page', 12 ),
			(int) $wp_query->get( 'posts_per_page' )
		);
	}

	/**
	 * P1-04, F28: `/category/writing/` stays the plain section-archive layout (no page-writing
	 * prepended) while there is no fiction.
	 */
	public function test_writing_category_stays_section_archive_when_no_fiction_exists(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$this->go_to( (string) get_category_link( $writing ) );

		$hierarchy = apply_filters( 'category_template_hierarchy', [ 'category.php' ] );

		$this->assertSame( 'category.php', $hierarchy[0] );
	}

	/**
	 * P1-04, F28: once an in-progress/complete series of a fiction form exists, both Writing
	 * routes return to the serial hub template.
	 */
	public function test_writing_routes_return_to_page_writing_once_a_serial_exists(): void {
		$this->make_fiction_series();
		$writing = $this->category_id( 'writing', 'Writing' );

		$page = self::factory()->post->create(
			[
				'post_type'   => 'page',
				'post_name'   => 'writing-page',
				'post_status' => 'publish',
			]
		);
		update_post_meta( $page, '_wp_page_template', 'page-writing' );

		$this->go_to( (string) get_category_link( $writing ) );
		$hierarchy = apply_filters( 'category_template_hierarchy', [ 'category.php' ] );
		$this->assertSame( 'page-writing', $hierarchy[0] );
	}

	/**
	 * P1-04, F28: a standalone `ttm_form = story` post (no series at all) also clears F28.
	 */
	public function test_writing_routes_return_to_page_writing_once_a_story_exists(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$story   = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
			]
		);
		update_post_meta( $story, 'ttm_primary_category', $writing );
		update_post_meta( $story, 'ttm_form', 'story' );

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
