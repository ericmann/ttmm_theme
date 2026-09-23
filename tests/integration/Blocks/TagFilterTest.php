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
}
