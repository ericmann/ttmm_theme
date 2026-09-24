<?php
/**
 * Integration tests for the ttm/series-toc block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SeriesTocTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, array{part:int, status?:string, date?:string, title?:string}> $parts
	 * @return array{series_id:int, post_ids:array<int,int>}
	 */
	private function make_series( string $slug, string $name, int $total_parts, array $parts ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );

		$writing  = $this->category_id( 'writing', 'Writing' );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$args = [
				'post_status'   => $part['status'] ?? 'publish',
				'post_category' => [ $writing ],
				'post_title'    => $part['title'] ?? "Chapter {$part['part']}",
				'post_excerpt'  => "Dek for chapter {$part['part']}.",
			];
			if ( isset( $part['date'] ) ) {
				$args['post_date'] = $part['date'];
			}

			$post_id = self::factory()->post->create( $args );
			update_post_meta( $post_id, 'ttm_series_part', $part['part'] );
			update_post_meta( $post_id, 'ttm_primary_category', $writing );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
			$post_ids[ $part['part'] ] = $post_id;
		}

		SeriesIndex::rebuild();

		return [
			'series_id' => $series_id,
			'post_ids'  => $post_ids,
		];
	}

	private function render( int $post_id, array $attributes = [] ): string {
		global $post;
		$post = $post_id ? get_post( $post_id ) : null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- test fixture mirrors a real single-post render context.
		if ( $post ) {
			setup_postdata( $post );
		}

		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );
		$html = (string) do_blocks( '<!-- wp:ttm/series-toc' . $json . ' /-->' );

		wp_reset_postdata();

		return $html;
	}

	public function test_lists_parts_in_order_with_current_highlighted_and_unlinked(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
			]
		);

		$html = $this->render( $series['post_ids'][2] );

		$this->assertSame( 3, substr_count( $html, 'ttm-series-toc__item' ) );
		$this->assertStringContainsString( 'is-current', $html );

		// The current part is not linked: its <li> contains no <a>.
		preg_match( '/<li class="ttm-series-toc__item is-current">(.*?)<\/li>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_f24_scheduled_part_unlinked_with_title_date(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[ 'part' => 1 ],
				[
					'part'   => 2,
					'status' => 'future',
					'date'   => '2026-09-26 09:00:00',
				],
			]
		);

		$html = $this->render( $series['post_ids'][1] );

		$this->assertStringContainsString( 'is-scheduled', $html );
		$this->assertStringContainsString( 'title="Scheduled Sept 26"', $html );

		// F24: the scheduled `title` sits on the <li> itself (SPEC §6.9 toc-scheduled).
		preg_match( '/<li class="ttm-series-toc__item is-scheduled"[^>]*>(.*?)<\/li>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_f23_open_ended_lists_published_only(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'quiet-ledger',
			'The Quiet Ledger',
			0,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[
					'part'   => 3,
					'status' => 'future',
					'date'   => '2026-10-01 09:00:00',
				],
			]
		);

		$html = $this->render( $series['post_ids'][2] );

		$this->assertSame( 2, substr_count( $html, 'ttm-series-toc__item' ) );
		$this->assertStringNotContainsString( 'is-scheduled', $html );
	}

	public function test_chapters_variant_newest_first_limited_with_dek(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
			]
		);

		$html = $this->render(
			$series['post_ids'][1],
			[
				'variant' => 'chapters',
				'order'   => 'desc',
				'limit'   => 2,
				'showDek' => true,
			]
		);

		$this->assertSame( 2, substr_count( $html, 'ttm-numbered__row' ) );
		$this->assertStringNotContainsString( 'ttm-series-toc__item', $html );
		$this->assertStringContainsString( 'Dek for chapter 3.', $html );
		$this->assertStringNotContainsString( 'Dek for chapter 1.', $html );

		$pos_3 = strpos( $html, '>03<' );
		$pos_2 = strpos( $html, '>02<' );
		$this->assertNotFalse( $pos_3 );
		$this->assertNotFalse( $pos_2 );
		$this->assertLessThan( $pos_2, $pos_3 );
	}

	/**
	 * REVIEW round 2, finding 1: the chapters variant must list published chapters only, even
	 * on a closed series (total_parts > 0) where the series variant still shows scheduled rows.
	 */
	public function test_chapters_variant_excludes_scheduled_parts(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			4,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[ 'part' => 3 ],
				[
					'part'   => 4,
					'status' => 'future',
					'date'   => '2026-09-26 09:00:00',
					'title'  => 'Chapter Four',
				],
			]
		);

		$html = $this->render(
			$series['post_ids'][1],
			[
				'variant' => 'chapters',
				'order'   => 'desc',
				'limit'   => 2,
			]
		);

		$this->assertStringContainsString( '>03<', $html );
		$this->assertStringContainsString( '>02<', $html );
		$this->assertStringNotContainsString( '>04<', $html );
		$this->assertStringNotContainsString( 'Chapter Four', $html );
	}

	/**
	 * R4-03, REVIEW round 3 finding 1: a series whose only parts are scheduled has no
	 * published rows for the chapters variant to list, so render.php's `return ''`
	 * guard (F23) must fire rather than emitting an empty `.ttm-series-toc` wrapper.
	 */
	public function test_chapters_variant_with_no_published_parts_renders_nothing(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			1,
			[
				[
					'part'   => 1,
					'status' => 'future',
					'date'   => '2026-09-26 09:00:00',
				],
			]
		);

		$html = $this->render(
			$series['post_ids'][1],
			[ 'variant' => 'chapters' ]
		);

		$this->assertSame( '', $html );
	}

	public function test_series_id_attribute_overrides_context(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series( 'hardening-wp', 'Hardening WordPress', 2, [ [ 'part' => 1 ], [ 'part' => 2 ] ] );

		$other_post = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$html = $this->render( $other_post, [ 'seriesId' => $series['series_id'] ] );

		$this->assertStringContainsString( 'Hardening WordPress', $html );
		$this->assertSame( 2, substr_count( $html, 'ttm-series-toc__item' ) );
	}

	/**
	 * Decision "ttm/series-toc": the rail heading carries the series name (desktop) and
	 * "Hub →" (phone), both linking to the series page; numbers and titles carry classes.
	 */
	public function test_heading_has_series_link_and_hub_link(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series( 'hardening-wp', 'Hardening WordPress', 2, [ [ 'part' => 1 ], [ 'part' => 2 ] ] );
		$link   = get_term_link( get_term( $series['series_id'], 'series' ) );
		if ( is_wp_error( $link ) ) {
			$this->fail( 'Series term link could not be resolved.' );
		}

		$html = $this->render( $series['post_ids'][2] );

		$this->assertStringContainsString( '<div class="ttm-cell-heading is-rail">', $html );
		$this->assertStringContainsString( '<span class="ttm-cell-heading__label">In this series</span>', $html );
		$this->assertStringContainsString( '<a class="ttm-series-toc__series" href="' . esc_url( $link ) . '">Hardening WordPress</a>', $html );
		$this->assertStringContainsString( '<a class="ttm-series-toc__hub" href="' . esc_url( $link ) . '">Hub →</a>', $html );
		$this->assertStringContainsString( '<span class="ttm-series-toc__num tnum">01</span>', $html );
		$this->assertStringContainsString( 'class="ttm-series-toc__title"', $html );
	}

	/**
	 * Chapters variant: "{Series name} — recent chapters" label, "All {published}" link to the
	 * series, and the shared numbered rows (styled in P4-05).
	 */
	public function test_chapters_variant_composes_heading_and_all_link(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series(
			'quiet-ledger',
			'The Quiet Ledger',
			0,
			[
				[ 'part' => 1 ],
				[ 'part' => 2 ],
				[
					'part'   => 3,
					'status' => 'future',
					'date'   => '2026-10-01 09:00:00',
				],
			]
		);

		$html = $this->render(
			$series['post_ids'][2],
			[
				'variant' => 'chapters',
				'order'   => 'desc',
			] 
		);

		$this->assertStringContainsString( '<span class="ttm-cell-heading__label">The Quiet Ledger — recent chapters</span>', $html );
		$this->assertMatchesRegularExpression( '/<a class="ttm-cell-heading__link" href="[^"]+">\s*All 2\s*<\/a>/', $html );
		$this->assertStringContainsString( '<ol class="ttm-numbered">', $html );
		$this->assertSame( 2, substr_count( $html, '<li class="ttm-numbered__row">' ) );
		$this->assertStringContainsString( '<span class="ttm-numbered__num tnum">02</span>', $html );
		$this->assertStringContainsString( 'class="ttm-numbered__title"', $html );
		$this->assertStringContainsString( 'class="ttm-numbered__date"', $html );
	}

	public function test_no_series_renders_nothing(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$html = $this->render( $post_id );

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * P1-02, SPEC §6.3: the `series` variant is scoped to an explicit seriesId or the current
	 * post's own series -- it must never fall back to Serials::active(), even when an
	 * in-progress fiction serial exists.
	 */
	public function test_post_without_series_renders_nothing_while_an_in_progress_serial_exists(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series( 'the-quiet-ledger', 'The Quiet Ledger', 0, [ [ 'part' => 1 ] ] );
		update_term_meta( $series['series_id'], 'ttm_form', 'novel' );
		SeriesIndex::rebuild();
		$this->assertNotNull( \TTM\Core\Fiction\Serials::active() );

		$tech    = $this->category_id( 'technology', 'Technology' );
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post_id, 'ttm_primary_category', $tech );

		$html = $this->render( $post_id );

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * P1-02, SPEC §6.3: unlike `series`, the `chapters` variant still falls back to the active
	 * serial when there is no seriesId/post series -- that's the Writing page's own use.
	 */
	public function test_chapters_variant_still_falls_back_to_the_active_serial(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$series = $this->make_series( 'the-quiet-ledger', 'The Quiet Ledger', 0, [ [ 'part' => 1 ] ] );
		update_term_meta( $series['series_id'], 'ttm_form', 'novel' );
		SeriesIndex::rebuild();

		$tech    = $this->category_id( 'technology', 'Technology' );
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post_id, 'ttm_primary_category', $tech );

		$html = $this->render( $post_id, [ 'variant' => 'chapters' ] );

		$this->assertStringContainsString( 'The Quiet Ledger', $html );
		$this->assertStringContainsString( 'ttm-numbered__row', $html );
	}

	/**
	 * P1-05, rule 50: the default `series` variant with no seriesId/postId/queried term, other
	 * content exists -> ''.
	 */
	public function test_rule_50_no_context_with_other_content(): void {
		$this->make_series( 'hardening-wp', 'Hardening WordPress', 6, [ [ 'part' => 1 ] ] );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );
		self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$html = $this->render( 0 );

		$this->assertSame( '', trim( $html ) );
	}
}
