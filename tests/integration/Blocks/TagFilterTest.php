<?php
/**
 * Integration tests for the ttm/tag-filter block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

class TagFilterTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function render(): string {
		return (string) do_blocks( '<!-- wp:ttm/tag-filter /-->' );
	}

	public function test_renders_top_tags_linking_within_category(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			]
		);

		$this->go_to( (string) get_category_link( $tech ) );

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-filter-row', $html );
		$this->assertStringContainsString( 'php', $html );
		$this->assertStringContainsString( 'tag=php', $html );
	}

	/**
	 * SPEC §6.6 "Filter row": an "All" chip leads the top tags, accent when no `?tag=` is
	 * active and neutral when one is (Decision "Filter row All chip").
	 */
	public function test_all_chip_is_accent_without_tag_and_neutral_with_tag(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			]
		);

		$this->go_to( (string) get_category_link( $tech ) );
		$html = $this->render();
		$this->assertMatchesRegularExpression( '/<a class="tag tag-accent" href="[^"]*">All<\/a>/', $html );

		$this->go_to( add_query_arg( 'tag', 'php', get_category_link( $tech ) ) );
		$html = $this->render();
		$this->assertMatchesRegularExpression( '/<a class="tag tag-neutral" href="[^"]*">All<\/a>/', $html );
	}

	public function test_active_tag_gets_accent_class(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			]
		);
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'wordpress' ],
			]
		);

		$this->go_to( add_query_arg( 'tag', 'php', get_category_link( $tech ) ) );

		$html = $this->render();

		$this->assertMatchesRegularExpression( '/class="tag tag-accent" href="[^"]*tag=php[^"]*"/', $html );
	}

	public function test_f16_no_tags_renders_nothing(): void {
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

	public function test_renders_nothing_outside_category_archive(): void {
		$this->go_to( '/' );

		$this->assertSame( '', trim( $this->render() ) );
	}

	/**
	 * P0-05: SPEC §4/§6.2 -- an importer that sets `post_status` before `tags_input` (WXR
	 * import order) must still produce the filter row once the tags land, not leave it
	 * permanently empty behind a stale cached-empty top_tags transient.
	 */
	public function test_import_order_terms_after_status_produces_the_row(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);

		$this->go_to( (string) get_category_link( $tech ) );
		$this->assertSame( '', trim( $this->render() ) );

		wp_set_post_tags( $post, [ 'imported-late' ] );

		$this->go_to( (string) get_category_link( $tech ) );
		$html = $this->render();
		$this->assertStringContainsString( 'ttm-filter-row', $html );
		$this->assertStringContainsString( 'imported-late', $html );
	}

	/**
	 * P1-05, rule 50: no queried category term, other content exists -> ''.
	 */
	public function test_rule_50_no_context_with_other_content(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'tags_input'    => [ 'php' ],
			]
		);
		wp_insert_term( 'A Series', 'series' );

		$GLOBALS['post'] = null;
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- rule 50 sweep: proving no-context behaviour.

		$this->assertSame( '', trim( $this->render() ) );
	}
}
