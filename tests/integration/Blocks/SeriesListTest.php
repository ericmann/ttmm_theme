<?php
/**
 * Integration tests for the ttm/series-list block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Config;
use TTM\Core\Query\SeriesIndex;

class SeriesListTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		update_option( 'ttm_settings', [] );
		Config::reset();
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
	 * Create a series term with one published part dated `$part_date` (SeriesIndex's
	 * `last_update` is now the newest published part's post_date, per R1-03 -- a distinct
	 * date per series is what gives each row a distinct, stable `last_update`), and rebuild
	 * the index.
	 *
	 * @return int Series term id.
	 */
	private function make_series( string $slug, string $name, string $status, string $form, int $category_id, string $part_date = '2026-01-01 09:00:00' ): int {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_status', $status );
		update_term_meta( $series_id, 'ttm_form', $form );

		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $category_id ],
				'post_date'     => $part_date,
			]
		);
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		update_post_meta( $post_id, 'ttm_primary_category', $category_id );
		wp_set_object_terms( $post_id, [ $series_id ], 'series' );

		SeriesIndex::rebuild();

		return $series_id;
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/series-list' . $json . ' /-->' );
	}

	public function test_lists_in_progress_series_limited_and_sorted_by_update(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$this->make_series( 'series-a', 'Series A', 'in-progress', 'nonfiction', $tech, '2026-09-01 00:00:00' );
		$this->make_series( 'series-b', 'Series B', 'in-progress', 'nonfiction', $tech, '2026-09-20 00:00:00' );
		$this->make_series( 'series-c', 'Series C', 'in-progress', 'nonfiction', $tech, '2026-09-10 00:00:00' );

		$html = $this->render( [ 'limit' => 2 ] );

		$pos_b = strpos( $html, 'Series B' );
		$pos_c = strpos( $html, 'Series C' );

		$this->assertNotFalse( $pos_b );
		$this->assertNotFalse( $pos_c );
		$this->assertLessThan( $pos_c, $pos_b );
		$this->assertStringNotContainsString( 'Series A', $html );
	}

	public function test_fiction_filter_excludes_nonfiction(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$this->make_series( 'nonfiction-one', 'Nonfiction One', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'novel-one', 'Novel One', 'in-progress', 'novel', $tech );

		$html = $this->render( [ 'form' => 'fiction' ] );

		$this->assertStringContainsString( 'Novel One', $html );
		$this->assertStringNotContainsString( 'Nonfiction One', $html );
	}

	public function test_in_category_keeps_only_series_touching_the_queried_category(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$this->make_series( 'tech-series', 'Tech Series', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'business-series', 'Business Series', 'in-progress', 'nonfiction', $business );

		global $wp_query;
		$wp_query->queried_object    = get_term( $tech, 'category' );
		$wp_query->queried_object_id = $tech;

		$html = $this->render( [ 'inCategory' => true ] );

		$this->assertStringContainsString( 'Tech Series', $html );
		$this->assertStringNotContainsString( 'Business Series', $html );
	}

	public function test_f4_no_in_progress_falls_back_to_complete_with_empty_heading_attr(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->make_series( 'done-series', 'Done Series', 'complete', 'nonfiction', $tech );

		$html = $this->render();

		$this->assertStringContainsString( 'Done Series', $html );
		$this->assertStringContainsString( 'is-complete', $html );
		$this->assertStringContainsString( 'data-ttm-empty-heading="Series"', $html );
	}

	public function test_zero_series_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	public function test_strip_layout_renders_single_meta_line_with_categories_count_and_cadence(): void {
		$this->seed( 'normal' );

		$html = $this->render(
			[
				'status' => 'in-progress',
				'limit'  => 10,
				'layout' => 'strip',
			]
		);

		$row_start = strpos( $html, 'Ordinary Time' );
		$this->assertIsInt( $row_start );
		$row_html = substr( $html, $row_start, 400 );

		$this->assertStringContainsString( 'Faith · 9 of 12 · Sundays', $row_html );
	}

	public function test_strip_layout_has_no_dek_or_count_column(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$series_id = $this->make_series( 'strip-series', 'Strip Series', 'in-progress', 'nonfiction', $tech );
		wp_update_term( $series_id, 'series', [ 'description' => 'A dek that must not appear.' ] );

		$html = $this->render( [ 'layout' => 'strip' ] );

		$this->assertStringNotContainsString( 'ttm-series-row__dek', $html );
		$this->assertStringNotContainsString( 'ttm-series-row__count', $html );
		$this->assertStringNotContainsString( 'A dek that must not appear.', $html );
		$this->assertStringContainsString( 'ttm-series-row__meta', $html );
	}

	public function test_categories_are_joined_with_middle_dots_in_list_layout(): void {
		// SeriesIndex::categories_for() collects each *chapter's* primary category, not a
		// single post's multiple categories, so two categories on one series needs two parts
		// with different primary categories.
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$term      = wp_insert_term( 'Two Category Series', 'series' );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_status', 'in-progress' );
		update_term_meta( $series_id, 'ttm_form', 'nonfiction' );

		$part1 = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $part1, 'ttm_series_part', 1 );
		update_post_meta( $part1, 'ttm_primary_category', $tech );
		wp_set_object_terms( $part1, [ $series_id ], 'series' );

		$part2 = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $business ],
			]
		);
		update_post_meta( $part2, 'ttm_series_part', 2 );
		update_post_meta( $part2, 'ttm_primary_category', $business );
		wp_set_object_terms( $part2, [ $series_id ], 'series' );

		SeriesIndex::rebuild();

		$html = $this->render();

		$this->assertMatchesRegularExpression( '/Technology\s*·\s*Business/', $html );
	}

	public function test_row_is_single_anchor_with_title_as_name(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->make_series( 'solo-series', 'Solo Series', 'in-progress', 'nonfiction', $tech );

		$html = $this->render();

		$this->assertSame( 1, substr_count( $html, '<a ' ) );

		preg_match( '/<a[^>]*>(.*?)<\/a>/s', $html, $matches );
		$this->assertNotEmpty( $matches );
		$this->assertStringStartsWith( 'Solo Series', trim( wp_strip_all_tags( $matches[1] ) ) );
	}

	public function test_rail_layout_renders_title_and_meta_only(): void {
		$this->seed( 'normal' );

		$html = $this->render(
			[
				'status' => 'in-progress',
				'limit'  => 10,
				'layout' => 'rail',
			]
		);

		$row_start = strpos( $html, 'Ordinary Time' );
		$this->assertIsInt( $row_start );
		$row_html = substr( $html, $row_start, 400 );

		$this->assertStringContainsString( 'Faith · 9 of 12 · Sundays', $row_html );
		$this->assertStringNotContainsString( 'ttm-series-row__dek', $row_html );
		$this->assertStringNotContainsString( 'ttm-series-row__count', $row_html );
	}

	public function test_layout_rows_is_no_longer_accepted(): void {
		$schema = json_decode(
			(string) file_get_contents( TTM_CORE_DIR . '/blocks/series-list/block.json' ),
			true
		);

		$enum = $schema['attributes']['layout']['enum'];

		$this->assertNotContains( 'rows', $enum );
		$this->assertContains( 'list', $enum );
		$this->assertContains( 'rail', $enum );
		$this->assertSame( 'list', $schema['attributes']['layout']['default'] );
	}

	/**
	 * SPEC §6.7 "All series": grid-2 rows are mark, title, dek, categories and count
	 * (`__parts` over `__status`), the same markup `list` uses -- only the CSS differs.
	 */
	public function test_grid_2_layout_renders_dek_categories_and_count(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$series_id = $this->make_series( 'hardening-wp', 'Hardening WordPress', 'in-progress', 'nonfiction', $tech );
		wp_update_term( $series_id, 'series', [ 'description' => 'Six parts on hardening a WordPress install.' ] );

		$html = $this->render(
			[
				'status' => 'any',
				'layout' => 'grid-2',
			] 
		);

		$this->assertStringContainsString( 'ttm-series-row__dek">Six parts on hardening a WordPress install.<', $html );
		$this->assertStringContainsString( 'ttm-series-row__categories">Technology<', $html );
		$this->assertStringContainsString( 'ttm-series-row__count', $html );
		$this->assertStringContainsString( 'ttm-series-row__parts">1 part<', $html );
		$this->assertStringContainsString( 'ttm-series-row__status">In progress<', $html );
	}

	/**
	 * SPEC §5: `excludeCurrent` with no explicit `limit` reads `series.related_limit`
	 * (default 4), not the strip's own default of 3.
	 */
	public function test_exclude_current_without_limit_uses_related_limit(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$current = $this->make_series( 'current-series', 'Current Series', 'in-progress', 'nonfiction', $tech );
		foreach ( range( 1, 5 ) as $i ) {
			$this->make_series( "other-series-{$i}", "Other Series {$i}", 'in-progress', 'nonfiction', $tech );
		}

		$term = get_term( $current, 'series' );
		global $wp_query;
		$wp_query->queried_object         = $term;
		$wp_query->queried_object_id      = $current;
		$wp_query->query_vars['taxonomy'] = 'series';
		set_query_var( 'series', get_term_field( 'slug', $current, 'series' ) );

		$html = $this->render(
			[
				'status'         => 'any',
				'excludeCurrent' => true,
			] 
		);

		$this->assertStringNotContainsString( 'Current Series', $html );
		$this->assertSame( 4, substr_count( $html, 'class="ttm-series-row"' ) );

		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['series.related_limit'] = 2;
				return $config;
			}
		);
		Config::reset();

		$html = $this->render(
			[
				'status'         => 'any',
				'excludeCurrent' => true,
			] 
		);

		$this->assertSame( 2, substr_count( $html, 'class="ttm-series-row"' ) );
	}
}
