<?php
/**
 * Integration tests for TTM\Core\Fiction\Serials.
 *
 * @package TTM\Tests\Integration\Fiction
 */

declare( strict_types=1 );

use TTM\Core\Fiction\Serials;
use TTM\Core\Query\SeriesIndex;

class SerialsTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	/**
	 * @param string $latest_chapter_date The last chapter's post_date (SeriesIndex's
	 *                                    `last_update` is the newest published part's
	 *                                    post_date, per R1-03); earlier chapters get a fixed
	 *                                    older date so it doesn't affect the result.
	 */
	private function make_serial( string $slug, string $name, string $form, string $status, int $writing_id, int $chapters, string $latest_chapter_date = '2026-01-01 09:00:00' ): int {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_form', $form );
		update_term_meta( $series_id, 'ttm_status', $status );

		for ( $i = 1; $i <= $chapters; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $writing_id ],
					'post_date'     => $i === $chapters ? $latest_chapter_date : '2020-01-01 09:00:00',
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $i );
			update_post_meta( $post_id, 'ttm_primary_category', $writing_id );
			update_post_meta( $post_id, 'ttm_form', 'chapter' );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
		}

		SeriesIndex::rebuild();

		return $series_id;
	}

	public function test_active_is_newest_in_progress_fiction(): void {
		$writing = $this->category_id( 'writing', 'Writing' );

		$this->make_serial( 'older-novel', 'Older Novel', 'novel', 'in-progress', $writing, 2, '2026-09-01 00:00:00' );
		$newer = $this->make_serial( 'newer-novel', 'Newer Novel', 'novel', 'in-progress', $writing, 2, '2026-09-20 00:00:00' );

		$active = Serials::active();

		$this->assertNotNull( $active );
		$this->assertSame( $newer, $active['id'] );
	}

	public function test_stats_compute_avg_minutes_from_chapter_word_counts(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$series  = $this->make_serial( 'wordy-novel', 'Wordy Novel', 'novel', 'in-progress', $writing, 2 );

		$row = SeriesIndex::by_slug( 'wordy-novel' );
		foreach ( $row['parts'] as $part ) {
			update_post_meta( $part['post_id'], 'ttm_word_count', 2300 );
		}

		$row   = SeriesIndex::by_slug( 'wordy-novel' );
		$stats = Serials::stats( $row );

		// 2300 words / 230 wpm = 10 minutes.
		$this->assertSame( 10, $stats['avg_minutes'] );
		$this->assertSame( 2, $stats['published'] );
	}

	public function test_stories_returns_only_story_form_posts_newest_first(): void {
		$writing = $this->category_id( 'writing', 'Writing' );

		$article = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
				'post_date'     => '2026-09-10 09:00:00',
			]
		);
		update_post_meta( $article, 'ttm_form', 'article' );

		$story_old = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
				'post_date'     => '2026-09-05 09:00:00',
			]
		);
		update_post_meta( $story_old, 'ttm_form', 'story' );

		$story_new = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
				'post_date'     => '2026-09-15 09:00:00',
			]
		);
		update_post_meta( $story_new, 'ttm_form', 'story' );

		$stories = Serials::stories( 10 );

		$this->assertSame( [ $story_new, $story_old ], $stories );
	}
}
