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

	public function test_stale_year_section_shows_two_posts_without_dek(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2024-01-01 09:00:00', // over cells.stale_year_days before "now".
			]
		);

		$this->assertTrue( \TTM\Core\Query\Cells::is_stale_year( 'security' ) );
		$this->assertFalse( \TTM\Core\Query\Cells::is_stale_year( 'technology' ) ); // no posts at all: not "stale", just empty.

		$block = $this->make_block( [ 'ttmSection' => 'security' ] );
		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertSame( 2, $query['posts_per_page'] );
	}

	public function test_stale_year_reads_cached_stats_and_runs_no_query_when_warm(): void {
		global $wpdb;

		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2024-01-01 09:00:00',
			]
		);

		$this->assertTrue( \TTM\Core\Query\Cells::is_stale_year( 'security' ) );

		$queries_before = $wpdb->num_queries;
		$this->assertTrue( \TTM\Core\Query\Cells::is_stale_year( 'security' ) );
		$this->assertSame( $queries_before, $wpdb->num_queries );

		// A publish transition flushes Query\Stats' transient, so the cached result changes.
		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2026-09-19 09:00:00',
			]
		);

		$this->assertFalse( \TTM\Core\Query\Cells::is_stale_year( 'security' ) );
	}

	public function test_stale_section_count_comes_from_config(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2024-01-01 09:00:00',
			]
		);

		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['cells.stale_count'] = 1;
				return $config;
			}
		);
		\TTM\Core\Config::reset();

		$block = $this->make_block( [ 'ttmSection' => 'security' ] );
		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertSame( 1, $query['posts_per_page'] );
	}

	public function test_stale_section_query_renders_no_excerpt_block_and_is_stale_class(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security = $this->category_id( 'security', 'Security' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_title'    => 'Old Security Post',
				'post_excerpt'  => 'A real excerpt that must not appear.',
				'post_date'     => '2024-01-01 09:00:00',
			]
		);

		$html = (string) do_blocks(
			'<!-- wp:query {"queryId":0,"query":{"perPage":2,"postType":"post","inherit":false,"ttmSection":"security"}} -->' .
			'<div class="wp-block-query">' .
			'<!-- wp:post-template -->' .
			'<!-- wp:post-title {"isLink":true} /-->' .
			'<!-- wp:post-excerpt {"className":"ttm-item__dek"} /-->' .
			'<!-- /wp:post-template -->' .
			'</div>' .
			'<!-- /wp:query -->'
		);

		$this->assertStringContainsString( 'is-stale', $html );
		$this->assertStringNotContainsString( 'ttm-item__dek', $html );
		$this->assertStringNotContainsString( 'A real excerpt', $html );
	}

	public function test_fresh_section_after_stale_section_keeps_its_dek(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$security   = $this->category_id( 'security', 'Security' );
		$technology = $this->category_id( 'technology', 'Technology' );

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_title'    => 'Old Security Post',
				'post_excerpt'  => 'A stale excerpt that must not appear.',
				'post_date'     => '2024-01-01 09:00:00',
			]
		);

		self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $technology ],
				'post_title'    => 'Fresh Technology Post',
				'post_excerpt'  => 'A fresh excerpt that must appear.',
				'post_date'     => '2026-09-19 09:00:00',
			]
		);

		$block = static function ( string $section ): string {
			return '<!-- wp:query {"queryId":0,"query":{"perPage":2,"postType":"post","inherit":false,"ttmSection":"' . $section . '"}} -->' .
				'<div class="wp-block-query">' .
				'<!-- wp:post-template -->' .
				'<!-- wp:post-title {"isLink":true} /-->' .
				'<!-- wp:post-excerpt {"className":"ttm-item__dek"} /-->' .
				'<!-- /wp:post-template -->' .
				'</div>' .
				'<!-- /wp:query -->';
		};

		$stale_html = (string) do_blocks( $block( 'security' ) );
		$fresh_html = (string) do_blocks( $block( 'technology' ) );

		$this->assertStringContainsString( 'is-stale', $stale_html );
		$this->assertStringNotContainsString( 'ttm-item__dek', $stale_html );

		$this->assertStringContainsString( 'ttm-item__dek', $fresh_html );
		$this->assertStringContainsString( 'A fresh excerpt that must appear.', $fresh_html );
	}

	public function test_journal_rail_posts_per_page_comes_from_config(): void {
		$this->category_id( 'journal', 'Journal' );

		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['journal.rail_count'] = 7;
				return $config;
			}
		);
		\TTM\Core\Config::reset();

		$block = $this->make_block( [ 'ttmSection' => 'journal' ] );
		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertSame( 7, $query['posts_per_page'] );
	}

	public function test_journal_stream_uses_config_slug(): void {
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['sections.journal_slug'] = 'renamed-journal';
				return $config;
			}
		);
		\TTM\Core\Config::reset();

		$block = $this->make_block(
			[
				'ttmSection'        => 'renamed-journal',
				'ttmExcludeCurrent' => true,
			]
		);

		$query = apply_filters( 'query_loop_block_query_vars', [], $block, 1 );

		$this->assertSame( (int) \TTM\Core\Config::get( 'journal.stream_count', 4 ), $query['posts_per_page'] );
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
