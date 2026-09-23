<?php
/**
 * Integration tests for the ttm/serial-hero block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class SerialHeroTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param array<int, array{part:int, status?:string, date?:string, words?:int}> $parts
	 * @return array{series_id:int, post_ids:array<int,int>}
	 */
	private function make_serial( string $slug, string $name, int $total_parts, array $parts, array $term_meta = [] ): array {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_total_parts', $total_parts );
		update_term_meta( $series_id, 'ttm_form', 'novel' );
		wp_update_term( $series_id, 'series', [ 'description' => "Synopsis for {$name}." ] );
		foreach ( $term_meta as $key => $value ) {
			update_term_meta( $series_id, $key, $value );
		}

		$writing  = $this->category_id( 'writing', 'Writing' );
		$post_ids = [];

		foreach ( $parts as $part ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => $part['status'] ?? 'publish',
					'post_category' => [ $writing ],
					'post_title'    => "Chapter {$part['part']}",
					'post_date'     => $part['date'] ?? '2026-01-01 09:00:00',
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $part['part'] );
			update_post_meta( $post_id, 'ttm_primary_category', $writing );
			if ( isset( $part['words'] ) ) {
				update_post_meta( $post_id, 'ttm_word_count', $part['words'] );
			}
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

		return (string) do_blocks( '<!-- wp:ttm/serial-hero' . $json . ' /-->' );
	}

	public function test_renders_cover_kicker_title_synopsis_buttons_and_stats(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$attachment_id = self::factory()->attachment->create_object(
			[
				'file'           => 'cover.jpg',
				'post_parent'    => 0,
				'post_mime_type' => 'image/jpeg',
			]
		);
		$this->make_serial(
			'the-quiet-ledger',
			'The Quiet Ledger',
			31,
			[
				[
					'part'  => 1,
					'date'  => '2026-01-01 09:00:00',
					'words' => 3000,
				],
				[
					'part'  => 2,
					'date'  => '2026-09-01 09:00:00',
					'words' => 3200,
				],
			],
			[
				'ttm_cover_id'  => $attachment_id,
				'ttm_cadence'   => 'Monthly',
				'ttm_next_date' => '2026-10-17',
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-cover is-hero', $html );
		$this->assertStringContainsString( 'Writing · Serial in progress', $html );
		$this->assertStringContainsString( 'The Quiet Ledger', $html );
		$this->assertStringContainsString( 'Synopsis for The Quiet Ledger.', $html );
		$this->assertStringContainsString( 'Read chapter 1', $html );
		$this->assertStringContainsString( 'Latest: chapter 2', $html );
		$this->assertStringContainsString( 'Follow by email', $html );
		$this->assertStringContainsString( '2 / 31', $html );
		$this->assertStringContainsString( 'Monthly', $html );
		$this->assertStringContainsString( 'next: Oct 17', $html );
		$this->assertStringContainsString( '~13 min', $html );
	}

	public function test_f3_completed_serial_kicker_buttons_and_stats(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_serial(
			'salt-and-iron',
			'Salt and Iron',
			2,
			[
				[
					'part'  => 1,
					'date'  => '2026-01-01 09:00:00',
					'words' => 4000,
				],
				[
					'part'  => 2,
					'date'  => '2026-02-01 09:00:00',
					'words' => 4200,
				],
			],
			[
				'ttm_status'         => 'complete',
				'ttm_purchase_links' => [
					[
						'label' => 'Buy on Amazon',
						'url'   => 'https://example.com/buy',
					],
				],
			]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Writing · Complete · 2 chapters', $html );
		$this->assertStringContainsString( 'Read chapter 1', $html );
		$this->assertStringContainsString( 'Buy on Amazon', $html );
		$this->assertStringNotContainsString( 'Follow by email', $html );
		$this->assertStringContainsString( 'Complete', $html );
	}

	public function test_f3_picks_most_recently_completed_of_two(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$this->make_serial(
			'older-completed',
			'Older Completed',
			2,
			[
				[
					'part' => 1,
					'date' => '2025-01-01 09:00:00',
				],
				[
					'part' => 2,
					'date' => '2025-02-01 09:00:00',
				],
			],
			[ 'ttm_status' => 'complete' ]
		);
		$this->make_serial(
			'newer-completed',
			'Newer Completed',
			2,
			[
				[
					'part' => 1,
					'date' => '2026-06-01 09:00:00',
				],
				[
					'part' => 2,
					'date' => '2026-07-01 09:00:00',
				],
			],
			[ 'ttm_status' => 'complete' ]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'Newer Completed', $html );
		$this->assertStringNotContainsString( 'Older Completed', $html );
	}

	public function test_f21_no_cover_adds_is_nocover_and_no_figure(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_serial(
			'the-quiet-ledger',
			'The Quiet Ledger',
			31,
			[ [ 'part' => 1 ] ]
		);

		$html = $this->render();

		$this->assertStringContainsString( 'is-nocover', $html );
		$this->assertStringNotContainsString( '<figure', $html );
	}

	public function test_open_ended_total_shows_published_count(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->make_serial(
			'the-quiet-ledger',
			'The Quiet Ledger',
			0,
			[ [ 'part' => 1 ], [ 'part' => 2 ] ]
		);

		$html = $this->render();

		$this->assertStringContainsString( '2 / 2', $html );
	}

	public function test_no_fiction_renders_nothing(): void {
		$html = $this->render();

		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * Rule 32: the stored cadence ("monthly") is capitalised on its first letter only for
	 * display; the synopsis is the series term's description.
	 */
	public function test_cadence_is_capitalised_and_synopsis_is_term_description(): void {
		$this->make_serial(
			'the-quiet-ledger',
			'The Quiet Ledger',
			0,
			[ [ 'part' => 1 ] ],
			[ 'ttm_cadence' => 'monthly' ]
		);

		$html = $this->render();

		$this->assertStringContainsString( '<span class="ttm-stats__value">Monthly</span>', $html );
		$this->assertStringNotContainsString( 'MONTHLY', $html );
		$this->assertStringContainsString( '<p class="ttm-serial-hero__synopsis">Synopsis for The Quiet Ledger.</p>', $html );
		$this->assertStringNotContainsString( '&lt;p&gt;', $html );
	}

	/**
	 * P1-05, rule 50: `ttm/serial-hero` is one of the SPEC-named sanctioned blocks -- with no
	 * seriesId attribute it falls back to `Serials::active()`, a site-wide default, regardless
	 * of the current post/queried object.
	 */
	public function test_rule_50_no_context_with_other_content(): void {
		$this->make_serial( 'the-quiet-ledger', 'The Quiet Ledger', 0, [ [ 'part' => 1 ] ] );
		self::factory()->post->create( [ 'post_status' => 'publish' ] );
		self::factory()->term->create( [ 'taxonomy' => 'post_tag' ] );

		$GLOBALS['post'] = null;
		wp_reset_query(); // phpcs:ignore WordPress.WP.DiscouragedFunctions.wp_reset_query_wp_reset_query -- rule 50 sweep: proving no-context behaviour.

		$html = $this->render();

		$this->assertStringContainsString( 'The Quiet Ledger', $html );
	}
}
