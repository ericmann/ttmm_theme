<?php
/**
 * Integration tests for the ttm/series-featured block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Config;
use TTM\Core\Query\SeriesIndex;

class SeriesFeaturedTest extends TTM_IntegrationTestCase {

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
	 * @param array<int, array{part:int, status?:string, date?:string}> $parts
	 * @return array{series_id:int, post_ids:array<int,int>}
	 */
	private function make_series( string $slug, string $name, int $total_parts, array $parts, array $term_meta = [] ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );
		foreach ( $term_meta as $key => $value ) {
			update_term_meta( $series_id, $key, $value );
		}

		$writing  = $this->category_id( 'writing', 'Writing' );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$args = [
				'post_status'   => $part['status'] ?? 'publish',
				'post_category' => [ $writing ],
				'post_title'    => "Chapter {$part['part']}",
				'post_date'     => $part['date'] ?? '2026-01-01 09:00:00',
			];

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

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/series-featured' . $json . ' /-->' );
	}

	/**
	 * get_term_field()'s default 'display' context runs descriptions through wpautop,
	 * wrapping them in a <p> that esc_html() would then render as literal text.
	 */
	public function test_dek_is_plain_text_not_wpautop_wrapped(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$made = $this->make_series( 'hardening-wp', 'Hardening WordPress', 1, [ [ 'part' => 1 ] ] );
		wp_update_term( $made['series_id'], 'series', [ 'description' => 'Six parts on hardening a WordPress install.' ] );

		$html = $this->render();

		$this->assertStringContainsString( '<p class="ttm-series-featured__dek">Six parts on hardening a WordPress install.</p>', $html );
		$this->assertStringNotContainsString( '&lt;p&gt;', $html );
	}

	public function test_features_ttm_featured_term_over_auto_pick(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			6,
			[
				[
					'part' => 1,
					'date' => '2026-09-19 09:00:00',
				],
			]
		);
		$this->make_series(
			'quiet-ledger',
			'The Quiet Ledger',
			0,
			[
				[
					'part' => 1,
					'date' => '2026-01-01 09:00:00',
				],
			],
			[ 'ttm_featured' => true ]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'The Quiet Ledger', $html );
		$this->assertStringNotContainsString( 'Hardening WordPress', $html );
	}

	public function test_auto_picks_in_progress_with_newest_part(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'older-series',
			'Older Series',
			2,
			[
				[
					'part' => 1,
					'date' => '2026-01-01 09:00:00',
				],
			]
		);
		$this->make_series(
			'newer-series',
			'Newer Series',
			2,
			[
				[
					'part' => 1,
					'date' => '2026-09-01 09:00:00',
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Newer Series', $html );
		$this->assertStringNotContainsString( 'Older Series', $html );
	}

	public function test_f5_no_in_progress_features_most_recently_completed_with_single_button(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'reading-cves',
			'Reading CVEs',
			4,
			[
				[
					'part' => 4,
					'date' => '2026-08-19 09:00:00',
				],
			],
			[ 'ttm_status' => 'complete' ]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Reading CVEs', $html );
		$this->assertStringContainsString( 'Start at part 1', $html );
		$this->assertStringNotContainsString( 'Follow this series', $html );
		$this->assertStringContainsString( 'Complete', $html );
	}

	public function test_part_list_caps_at_config_and_links_all(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		Config::reset();
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['series.hub_featured_parts'] = 2;
				return $config;
			}
		);

		$this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			3,
			[
				[
					'part' => 1,
					'date' => '2026-01-01 09:00:00',
				],
				[
					'part' => 2,
					'date' => '2026-02-01 09:00:00',
				],
				[
					'part' => 3,
					'date' => '2026-03-01 09:00:00',
				],
			]
		);

		$html = $this->render();

		$this->assertSame( 2, substr_count( $html, 'ttm-series-featured__part ' ) );
		$this->assertStringContainsString( 'All 3 →', $html );
	}

	public function test_kicker_joins_categories_with_middle_dots(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$tech     = $this->category_id( 'technology', 'Technology' );
		$security = $this->category_id( 'security', 'Security' );

		$term      = wp_insert_term( 'Cross Section Series', 'series' );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', 2 );

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
				'post_category' => [ $security ],
			]
		);
		update_post_meta( $part2, 'ttm_series_part', 2 );
		update_post_meta( $part2, 'ttm_primary_category', $security );
		wp_set_object_terms( $part2, [ $series_id ], 'series' );

		SeriesIndex::rebuild();

		$html = $this->render();

		$this->assertMatchesRegularExpression( '/ttm-series-featured__kicker is-style-kicker">In progress · Technology · Security<\/p>/', $html );
	}

	public function test_title_is_h1_on_series_archive_and_h2_elsewhere(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series( 'hardening-wp', 'Hardening WordPress', 1, [ [ 'part' => 1 ] ] );

		$html = $this->render();
		$this->assertStringContainsString( '<h2 class="ttm-series-featured__title">Hardening WordPress</h2>', $html );

		$term = get_term_by( 'slug', 'hardening-wp', 'series' );
		$this->go_to( (string) get_term_link( $term ) );

		$html = $this->render();
		$this->assertStringContainsString( '<h1 class="ttm-series-featured__title is-style-display-xl">Hardening WordPress</h1>', $html );
	}

	public function test_show_dek_renders_part_excerpts_for_published_parts_only(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			2,
			[
				[
					'part' => 1,
					'date' => '2026-01-01 09:00:00',
				],
				[
					'part'   => 2,
					'status' => 'future',
					'date'   => '2026-09-26 09:00:00',
				],
			]
		);

		$html = $this->render( [ 'showDek' => true ] );

		$this->assertSame( 1, substr_count( $html, 'ttm-series-featured__part-dek' ) );

		$html = $this->render();
		$this->assertStringNotContainsString( 'ttm-series-featured__part-dek', $html );
	}

	public function test_f24_scheduled_part_row_unlinked_with_date(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_series(
			'hardening-wp',
			'Hardening WordPress',
			2,
			[
				[
					'part' => 1,
					'date' => '2026-01-01 09:00:00',
				],
				[
					'part'   => 2,
					'status' => 'future',
					'date'   => '2026-09-26 09:00:00',
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'is-scheduled', $html );
		$this->assertMatchesRegularExpression( '/<span class="ttm-series-featured__part-title" title="Scheduled Sept 26">/', $html );

		preg_match( '/<li class="ttm-series-featured__part is-scheduled">(.*?)<\/li>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_zero_series_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}
}
