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
		$this->assertStringContainsString( 'title="Scheduled Sept 26"', $html );

		preg_match( '/<li class="ttm-series-featured__part is-scheduled">(.*?)<\/li>/s', $html, $m );
		$this->assertNotEmpty( $m );
		$this->assertStringNotContainsString( '<a ', $m[1] );
	}

	public function test_zero_series_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}
}
