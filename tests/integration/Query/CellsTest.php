<?php
/**
 * Integration tests for TTM\Core\Query\Cells.
 *
 * @package TTM\Tests\Integration\Query
 */

declare( strict_types=1 );

class CellsTest extends TTM_IntegrationTestCase {

	private WP_REST_Server $server;

	public function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server = new WP_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );
	}

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

	/**
	 * A `core/post-template` block instance: the actual block `query_loop_block_query_vars`
	 * receives, since `core/query` only *provides* `query` as context (its own usesContext is
	 * templateSlug/postType), while `core/post-template` *uses* it.
	 */
	private function make_block( array $query_context ): WP_Block {
		$parsed = [
			'blockName' => 'core/post-template',
			'attrs'     => [],
			'innerHTML' => '',
		];

		return new WP_Block( $parsed, [ 'query' => $query_context ] );
	}

	/**
	 * A `core/query` block instance carrying its own `query` attribute, since the Query
	 * block's `render_block_core/query` filter reads the section from its own attributes,
	 * not from context.
	 */
	private function make_query_block( array $query_attrs ): WP_Block {
		$parsed = [
			'blockName' => 'core/query',
			'attrs'     => [ 'query' => $query_attrs ],
			'innerHTML' => '',
		];

		return new WP_Block( $parsed, [] );
	}

	public function test_section_query_adds_category_primary_meta_and_count(): void {
		$tech  = $this->category_id( 'technology', 'Technology' );
		$block = $this->make_block( [ 'ttmSection' => 'technology' ] );

		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertSame( 'technology', $query['category_name'] );
		$this->assertSame( 1, $query['ignore_sticky_posts'] );
		$this->assertSame( $tech, $query['meta_query'][0]['value'] );
		$this->assertSame( 'ttm_primary_category', $query['meta_query'][0]['key'] );

		$counts = (array) \TTM\Core\Config::get( 'cells.counts' );
		$this->assertSame( $counts['technology'], $query['posts_per_page'] );
	}

	public function test_exclude_lead_adds_post_not_in(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$lead = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $lead, 'ttm_primary_category', $tech );

		add_filter( 'ttm_lead_post_id', static fn () => $lead );

		$block = $this->make_block(
			[
				'ttmSection'     => 'technology',
				'ttmExcludeLead' => true,
			]
		);

		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertContains( $lead, $query['post__not_in'] );
	}

	public function test_non_journal_queries_exclude_journal(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$block   = $this->make_block( [ 'ttmSection' => 'technology' ] );

		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertContains( $journal, $query['category__not_in'] );
	}

	public function test_journal_section_does_not_exclude_itself(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$block   = $this->make_block( [ 'ttmSection' => 'journal' ] );

		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertArrayNotHasKey( 'category__not_in', $query );
	}

	public function test_f17_empty_section_query_gets_is_empty_class(): void {
		$block = $this->make_query_block( [ 'ttmSection' => 'technology' ] );

		$content = apply_filters( 'render_block_core/query', '<div class="wp-block-query"></div>', [], $block );

		$this->assertStringContainsString( 'is-empty', $content );
	}

	public function test_f17_non_empty_section_query_is_untouched(): void {
		$block = $this->make_query_block( [ 'ttmSection' => 'technology' ] );

		$content = apply_filters(
			'render_block_core/query',
			'<div class="wp-block-query"><div class="wp-block-post ">A</div></div>',
			[],
			$block
		);

		$this->assertStringNotContainsString( 'is-empty', $content );
	}

	public function test_lead_rest_returns_id_and_reason(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			] 
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$request  = new WP_REST_Request( 'GET', '/ttm/v1/lead' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();
		$this->assertSame( $post, $data['id'] );
		$this->assertSame( 'technology', $data['reason'] );
	}
}
