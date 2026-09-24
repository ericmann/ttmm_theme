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
		$security  = $this->category_id( 'security', 'Security' );
		$series_id = $this->make_series( 'hardening-wp', 'Hardening WordPress', 'in-progress', 'nonfiction', $tech );
		wp_update_term( $series_id, 'series', [ 'description' => 'Six parts on hardening a WordPress install.' ] );

		// R1-05: `categories_for()` collects one (primary) category per part, so a second
		// category on the row needs a second part in a different section -- and joins with
		// " · ", not just renders.
		$second_part = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $security ],
				'post_date'     => '2026-02-01 09:00:00',
			]
		);
		update_post_meta( $second_part, 'ttm_series_part', 2 );
		update_post_meta( $second_part, 'ttm_primary_category', $security );
		wp_set_object_terms( $second_part, [ $series_id ], 'series' );
		SeriesIndex::rebuild();

		$html = $this->render(
			[
				'status' => 'any',
				'layout' => 'grid-2',
			]
		);

		$this->assertStringContainsString( 'ttm-series-row__dek">Six parts on hardening a WordPress install.<', $html );
		$this->assertStringContainsString( 'ttm-series-row__categories">Technology · Security<', $html );
		$this->assertStringContainsString( 'ttm-series-row__count', $html );
		$this->assertStringContainsString( 'ttm-series-row__parts">2 parts<', $html );
		$this->assertStringContainsString( 'ttm-series-row__status">In progress<', $html );
	}

	/**
	 * SPEC §6.5 "Body": `list` layout's meta line is "{Form} · {genre} · {cadence}" for
	 * fiction (form labels Novel/Novella/Story cycle) and "{categories} · {cadence}" for
	 * nonfiction, empties omitted; the stored cadence stays lowercase (SPEC §6.5, R1-04) --
	 * capitalisation belongs only to `ttm/serial-hero`'s stat value (PLAN spec-issue #18).
	 */
	public function test_list_layout_form_line_for_fiction_and_nonfiction(): void {
		$tech = $this->category_id( 'technology', 'Technology' );

		$fiction_id = $this->make_series( 'quiet-ledger', 'The Quiet Ledger', 'in-progress', 'novel', $tech );
		update_term_meta( $fiction_id, 'ttm_genre', 'literary thriller' );
		update_term_meta( $fiction_id, 'ttm_cadence', 'monthly' );

		$nonfiction_id = $this->make_series( 'hardening-wp', 'Hardening WordPress', 'in-progress', 'nonfiction', $tech );
		update_term_meta( $nonfiction_id, 'ttm_cadence', 'weekly' );

		$html = $this->render( [ 'status' => 'any' ] );

		$this->assertStringContainsString( 'ttm-series-row__meta">Novel · literary thriller · monthly<', $html );
		$this->assertStringContainsString( 'ttm-series-row__meta">Technology · weekly<', $html );
		$this->assertStringNotContainsString( '· Monthly<', $html );
	}

	/**
	 * SPEC §6.5/§6.7 R4-02: a complete series' right cell reads "{N} chapters"
	 * (fiction) / "{N} parts" (nonfiction) rather than "{N} of {N}" once the
	 * planned total is reached.
	 */
	public function test_complete_fiction_series_count_reads_chapters(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$series_id = $this->make_series( 'failover', 'Failover', 'complete', 'novel', $tech );
		update_term_meta( $series_id, 'ttm_total_parts', 9 );

		for ( $i = 2; $i <= 9; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $tech ],
					'post_date'     => "2026-0{$i}-01 09:00:00",
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $i );
			update_post_meta( $post_id, 'ttm_primary_category', $tech );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
		}
		SeriesIndex::rebuild();

		$html = $this->render(
			[
				'status' => 'any',
				'layout' => 'list',
			]
		);

		$this->assertStringContainsString( 'ttm-series-row__parts">9 chapters<', $html );
		$this->assertStringNotContainsString( '9 of 9', $html );
	}

	public function test_complete_nonfiction_series_count_reads_parts(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$series_id = $this->make_series( 'hardening-wp', 'Hardening WordPress', 'complete', 'nonfiction', $tech );
		update_term_meta( $series_id, 'ttm_total_parts', 4 );

		for ( $i = 2; $i <= 4; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $tech ],
					'post_date'     => "2026-0{$i}-01 09:00:00",
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $i );
			update_post_meta( $post_id, 'ttm_primary_category', $tech );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
		}
		SeriesIndex::rebuild();

		$html = $this->render(
			[
				'status' => 'any',
				'layout' => 'grid-2',
			]
		);

		$this->assertStringContainsString( 'ttm-series-row__parts">4 parts<', $html );
		$this->assertStringNotContainsString( '4 of 4', $html );
	}

	/**
	 * An in-progress series with a planned total still reads "N of M" (unchanged).
	 */
	public function test_in_progress_series_still_reads_n_of_m(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$series_id = $this->make_series( 'quiet-ledger', 'The Quiet Ledger', 'in-progress', 'novel', $tech );
		update_term_meta( $series_id, 'ttm_total_parts', 31 );

		for ( $i = 2; $i <= 12; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $tech ],
					// All within January so every post stays published (not
					// auto-promoted to "future") relative to the real clock.
					'post_date'     => sprintf( '2026-01-%02d 09:00:00', $i ),
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $i );
			update_post_meta( $post_id, 'ttm_primary_category', $tech );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
		}
		SeriesIndex::rebuild();

		$html = $this->render( [ 'status' => 'any' ] );

		$this->assertStringContainsString( 'ttm-series-row__parts">12 of 31<', $html );
	}

	/**
	 * REVIEW round 3 finding 4 / SPEC §2: the strip/rail meta line's count word is
	 * untouched by the complete-series "chapters"/"parts" wording -- the front page's
	 * strip must not move.
	 */
	public function test_strip_meta_count_unchanged_for_complete_series(): void {
		$tech      = $this->category_id( 'technology', 'Technology' );
		$series_id = $this->make_series( 'failover', 'Failover', 'complete', 'novel', $tech );
		update_term_meta( $series_id, 'ttm_total_parts', 9 );

		$html = $this->render(
			[
				'status' => 'any',
				'layout' => 'strip',
			]
		);

		$this->assertStringContainsString( '1 of 9', $html );
		$this->assertStringNotContainsString( 'chapter', $html );
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

	/**
	 * Point the current query at a series term, the way visiting `/series/<slug>/` would.
	 */
	private function go_to_series( int $series_id ): void {
		$term = get_term( $series_id, 'series' );
		global $wp_query;
		$wp_query->queried_object         = $term;
		$wp_query->queried_object_id      = $series_id;
		$wp_query->query_vars['taxonomy'] = 'series';
		set_query_var( 'series', get_term_field( 'slug', $series_id, 'series' ) );
	}

	/**
	 * P1-03, SPEC §6.4, rule 51: relatedTo=current never crosses form (nonfiction current
	 * never lists a novel).
	 */
	public function test_related_to_current_lists_same_form_only(): void {
		$tech    = $this->category_id( 'technology', 'Technology' );
		$current = $this->make_series( 'current-nonfiction', 'Current Nonfiction', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'other-novel', 'Other Novel', 'in-progress', 'novel', $tech );
		$this->make_series( 'other-nonfiction', 'Other Nonfiction', 'in-progress', 'nonfiction', $tech );

		$this->go_to_series( $current );

		$html = $this->render( [ 'relatedTo' => 'current' ] );

		$this->assertStringNotContainsString( 'Other Novel', $html );
		$this->assertStringContainsString( 'Other Nonfiction', $html );
	}

	/**
	 * P1-03, rule 51: candidates rank by shared section count first, then last_update -- a
	 * same-section series outranks a more recently updated series from a different section.
	 */
	public function test_related_to_current_ranks_shared_sections_then_last_update(): void {
		$security = $this->category_id( 'security', 'Security' );
		$business = $this->category_id( 'business', 'Business' );

		$current = $this->make_series( 'current-security', 'Current Security', 'in-progress', 'nonfiction', $security, '2026-01-01 00:00:00' );
		$this->make_series( 'security-series', 'Security Series', 'in-progress', 'nonfiction', $security, '2026-01-05 00:00:00' );
		$this->make_series( 'business-series', 'Business Series', 'in-progress', 'nonfiction', $business, '2026-09-01 00:00:00' );

		$this->go_to_series( $current );

		$html = $this->render( [ 'relatedTo' => 'current' ] );

		$pos_security = strpos( $html, 'Security Series' );
		$pos_business = strpos( $html, 'Business Series' );
		$this->assertNotFalse( $pos_security );
		$this->assertNotFalse( $pos_business );
		$this->assertLessThan( $pos_business, $pos_security );
	}

	/**
	 * P1-03, F27: no other series shares the current series' form class -> the block returns
	 * '' (no heading, no empty list).
	 */
	public function test_related_to_current_renders_nothing_without_candidates(): void {
		$tech    = $this->category_id( 'technology', 'Technology' );
		$current = $this->make_series( 'only-nonfiction', 'Only Nonfiction', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'only-novel', 'Only Novel', 'in-progress', 'novel', $tech );

		$this->go_to_series( $current );

		$html = $this->render( [ 'relatedTo' => 'current' ] );

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * P1-03, SPEC §6.4: a non-empty `heading` attribute renders the same
	 * `.ttm-cell-heading.is-rail` markup `ttm/series-toc` uses, before the rows.
	 */
	public function test_related_to_current_renders_heading_when_set(): void {
		$tech    = $this->category_id( 'technology', 'Technology' );
		$current = $this->make_series( 'current', 'Current', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'other', 'Other', 'in-progress', 'nonfiction', $tech );

		$this->go_to_series( $current );

		$html = $this->render(
			[
				'relatedTo' => 'current',
				'heading'   => 'Other series',
			]
		);

		$this->assertStringContainsString( '<div class="ttm-cell-heading is-rail">', $html );
		$this->assertStringContainsString( '<span class="ttm-cell-heading__label">Other series</span>', $html );
	}

	/**
	 * P1-03, rule 50: relatedTo=current has no meaning outside a series page.
	 */
	public function test_related_to_current_outside_series_page_renders_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->make_series( 'a', 'A', 'in-progress', 'nonfiction', $tech );
		$this->make_series( 'b', 'B', 'in-progress', 'nonfiction', $tech );

		$html = $this->render( [ 'relatedTo' => 'current' ] );

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * P1-05, rule 50: `ttm/series-list`'s default (no `inCategory`/`excludeCurrent`/`relatedTo`)
	 * is a sanctioned site-wide-default listing -- it lists `SeriesIndex::all()` filtered by
	 * `status`/`form` attributes only, with no post/term context to read at all.
	 */
	public function test_rule_50_no_context_with_other_content(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$this->make_series( 'current-series', 'Current Series', 'in-progress', 'nonfiction', $tech );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );
		self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$GLOBALS['post'] = null;
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- rule 50 sweep: proving no-context behaviour.

		$html = $this->render();

		$this->assertStringContainsString( 'Current Series', $html );
	}
}
