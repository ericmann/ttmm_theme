<?php
/**
 * Integration tests for the ttm/most-read block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class MostReadTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function flagged_post( int $category_id, string $title, string $date ): int {
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $category_id ],
				'post_title'    => $title,
				'post_date'     => $date,
			]
		);
		update_post_meta( $post_id, 'ttm_primary_category', $category_id );
		update_post_meta( $post_id, 'ttm_featured_in_section', true );

		return $post_id;
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/most-read' . $json . ' /-->' );
	}

	public function test_lists_flagged_posts_in_primary_category_newest_first_and_limited(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$this->flagged_post( $tech, 'Oldest', '2026-09-01 09:00:00' );
		$this->flagged_post( $tech, 'Middle', '2026-09-10 09:00:00' );
		$this->flagged_post( $tech, 'Newest', '2026-09-20 09:00:00' );

		$this->go_to( (string) get_category_link( $tech ) );

		$html = $this->render( [ 'limit' => 2 ] );

		$this->assertStringContainsString( 'Newest', $html );
		$this->assertStringContainsString( 'Middle', $html );
		$this->assertStringNotContainsString( 'Oldest', $html );

		$pos_newest = strpos( $html, 'Newest' );
		$pos_middle = strpos( $html, 'Middle' );
		$this->assertLessThan( $pos_middle, $pos_newest );
	}

	public function test_fewer_than_limit_shows_what_exists(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->flagged_post( $tech, 'Only One', '2026-09-20 09:00:00' );

		$this->go_to( (string) get_category_link( $tech ) );

		$html = $this->render();

		$this->assertStringContainsString( 'Only One', $html );
		$this->assertSame( 1, substr_count( $html, 'ttm-numbered__row' ) );
	}

	/**
	 * SPEC §6.6 "Tag / date archive": aside = Most read only, so a tag archive (no category
	 * context) reads flagged posts site-wide rather than scoped to a category.
	 */
	public function test_renders_sitewide_flagged_posts_on_tag_archive(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$security = $this->category_id( 'security', 'Security' );

		$a = $this->flagged_post( $tech, 'From Technology', '2026-09-10 09:00:00' );
		$b = $this->flagged_post( $security, 'From Security', '2026-09-20 09:00:00' );
		wp_set_post_tags( $a, [ 'php' ] );
		wp_set_post_tags( $b, [ 'php' ] );

		$this->go_to( get_tag_link( get_term_by( 'slug', 'php', 'post_tag' ) ) );

		$html = $this->render();

		$this->assertStringContainsString( 'From Technology', $html );
		$this->assertStringContainsString( 'From Security', $html );
	}

	public function test_renders_nothing_outside_any_archive(): void {
		$this->go_to( '/' );

		$this->assertSame( '', trim( $this->render() ) );
	}

	public function test_zero_renders_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			] 
		);

		$this->go_to( (string) get_category_link( $tech ) );

		$this->assertSame( '', trim( $this->render() ) );
	}
}
