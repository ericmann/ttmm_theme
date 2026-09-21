<?php
/**
 * Integration tests for TTM\Core\Cache\Purge.
 *
 * @package TTM\Tests\Integration\Cache
 */

declare( strict_types=1 );

use TTM\Core\Cache\Purge;
use TTM\Core\Query\SeriesIndex;

class PurgeTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_actions( 'ttm_purge_urls' );
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

	public function test_for_post_includes_front_post_sections_feeds_and_series(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$term = wp_insert_term( 'Hardening WordPress', 'series' );
		$series_id = (int) $term['term_id'];

		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
			]
		);
		update_post_meta( $post_id, 'ttm_series_part', 1 );
		update_post_meta( $post_id, 'ttm_primary_category', $tech );
		wp_set_object_terms( $post_id, [ $series_id ], 'series' );
		SeriesIndex::rebuild();

		$urls = Purge::for_post( $post_id );

		$this->assertContains( home_url( '/' ), $urls );
		$this->assertContains( get_permalink( $post_id ), $urls );
		$this->assertContains( get_category_link( $tech ), $urls );
		$this->assertContains( get_category_feed_link( $tech ), $urls );
		$this->assertContains( home_url( '/series/hardening-wordpress/' ), $urls );
		$this->assertContains( home_url( '/series/' ), $urls );
		$this->assertContains( home_url( '/feed/' ), $urls );
		$this->assertContains( rest_url( 'ttm/v1/series' ), $urls );
		$this->assertContains( rest_url( 'ttm/v1/lead' ), $urls );
	}

	public function test_writing_post_includes_writing_page(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
			]
		);
		update_post_meta( $post_id, 'ttm_primary_category', $writing );

		$urls = Purge::for_post( $post_id );

		$this->assertContains( home_url( '/writing/' ), $urls );
	}

	public function test_publish_transition_fires_action_with_urls(): void {
		$purged = null;
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);

		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		$this->assertIsArray( $purged );
		$this->assertNotEmpty( $purged );
	}

	public function test_draft_to_draft_does_not_fire(): void {
		$fired = false;
		add_action(
			'ttm_purge_urls',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		self::factory()->post->create( [ 'post_status' => 'draft' ] );

		$this->assertFalse( $fired );
	}
}
