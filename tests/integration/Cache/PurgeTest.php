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
		$tech      = $this->category_id( 'technology', 'Technology' );
		$term      = wp_insert_term( 'Hardening WordPress', 'series' );
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

	public function test_trashing_a_published_post_purges_the_url_it_was_served_at(): void {
		$post_id   = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$permalink = (string) get_permalink( $post_id );
		$purged    = null;
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);

		wp_trash_post( $post_id );

		$this->assertStringNotContainsString( '?p=', $permalink );
		$this->assertIsArray( $purged );
		$this->assertContains( $permalink, $purged, 'the pretty URL, not the ?p= one a trashed post reports' );
		$this->assertContains( home_url( '/' ), $purged );
	}

	public function test_unpublishing_to_draft_purges_the_url_it_was_served_at(): void {
		$post_id   = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$permalink = (string) get_permalink( $post_id );
		$purged    = null;
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);

		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'draft',
			]
		);

		$this->assertContains( $permalink, (array) $purged );
	}

	public function test_deleting_a_published_post_outright_purges_it(): void {
		$post_id   = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$permalink = (string) get_permalink( $post_id );
		$purged    = null;
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);

		wp_delete_post( $post_id, true );

		$this->assertContains( $permalink, (array) $purged );
	}

	public function test_block_editor_publish_purges_the_section_written_after_the_status(): void {
		$tech    = $this->category_id( 'technology', 'Technology' );
		$post_id = self::factory()->post->create( [ 'post_status' => 'draft' ] );
		$before  = get_post( $post_id );
		$purged  = null;
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);

		// The REST controller's order: save the status without the after-hooks, then terms and
		// meta, then `wp_after_insert_post()`.
		wp_update_post(
			[
				'ID'          => $post_id,
				'post_status' => 'publish',
			],
			false,
			false
		);
		$this->assertNull( $purged, 'nothing fires before the terms are written' );
		wp_set_post_categories( $post_id, [ $tech ] );
		update_post_meta( $post_id, 'ttm_primary_category', $tech );
		wp_after_insert_post( get_post( $post_id ), true, $before );

		$this->assertContains( get_category_link( $tech ), (array) $purged );
		$this->assertContains( get_category_feed_link( $tech ), (array) $purged );
	}

	public function test_a_published_comment_redirects_to_an_uncached_url(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$comment = get_comment(
			self::factory()->comment->create(
				[
					'comment_post_ID'  => $post_id,
					'comment_approved' => 1,
				]
			)
		);
		$held    = get_comment(
			self::factory()->comment->create(
				[
					'comment_post_ID'  => $post_id,
					'comment_approved' => 0,
				]
			)
		);

		$location = get_permalink( $post_id ) . '#comment-' . $comment->comment_ID;
		$this->assertSame( add_query_arg( 'c', $comment->comment_ID, $location ), Purge::comment_redirect( $location, $comment ) );
		$this->assertStringContainsString( '#comment-' . $comment->comment_ID, Purge::comment_redirect( $location, $comment ) );

		$held_location = add_query_arg(
			[
				'unapproved'      => $held->comment_ID,
				'moderation-hash' => 'x',
			],
			get_permalink( $post_id ) 
		);
		$this->assertSame( $held_location, Purge::comment_redirect( $held_location, $held ) );
	}

	public function test_approving_a_comment_purges_its_post(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$comment = self::factory()->comment->create(
			[
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
			]
		);
		$purged  = null;
		add_action(
			'ttm_purge_urls',
			static function ( array $urls ) use ( &$purged ): void {
				$purged = $urls;
			}
		);

		wp_set_comment_status( $comment, 'approve' );

		$this->assertSame( Purge::for_comments( $post_id ), $purged );
		$this->assertContains( (string) get_permalink( $post_id ), $purged );
	}

	public function test_a_held_comment_purges_nothing(): void {
		$post_id = self::factory()->post->create( [ 'post_status' => 'publish' ] );
		$fired   = false;
		add_action(
			'ttm_purge_urls',
			static function () use ( &$fired ): void {
				$fired = true;
			}
		);

		self::factory()->comment->create(
			[
				'comment_post_ID'  => $post_id,
				'comment_approved' => 0,
			]
		);

		$this->assertFalse( $fired );
	}
}
