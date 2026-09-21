<?php
/**
 * Integration tests for TTM\Core\Query\Lead.
 *
 * @package TTM\Tests\Integration\Query
 */

declare( strict_types=1 );

use TTM\Core\Query\Lead;

class LeadTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		delete_transient( 'ttm_lead_id' );
		delete_option( 'sticky_posts' );
		remove_all_filters( 'ttm_lead_post_id' );
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

	private function post_with_primary( int $category_id, string $date ): int {
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $category_id ],
				'post_date'     => $date,
			]
		);
		update_post_meta( $post_id, 'ttm_primary_category', $category_id );

		return $post_id;
	}

	public function test_sticky_within_window_wins(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech  = $this->category_id( 'technology', 'Technology' );
		$other = $this->category_id( 'business', 'Business' );
		$this->post_with_primary( $tech, '2026-09-19 09:00:00' );
		$sticky_id = $this->post_with_primary( $other, '2026-09-10 09:00:00' );
		stick_post( $sticky_id );

		$result = Lead::compute();

		$this->assertSame( $sticky_id, $result['id'] );
		$this->assertSame( 'sticky', $result['reason'] );
	}

	public function test_old_sticky_is_ignored(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech      = $this->category_id( 'technology', 'Technology' );
		$other     = $this->category_id( 'business', 'Business' );
		$tech_post = $this->post_with_primary( $tech, '2026-09-19 09:00:00' );
		$sticky_id = $this->post_with_primary( $other, '2026-01-01 09:00:00' );
		stick_post( $sticky_id );

		$result = Lead::compute();

		$this->assertSame( $tech_post, $result['id'] );
		$this->assertSame( 'technology', $result['reason'] );
	}

	public function test_newest_technology_post_is_lead(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech = $this->category_id( 'technology', 'Technology' );
		$this->post_with_primary( $tech, '2026-09-01 09:00:00' );
		$newest = $this->post_with_primary( $tech, '2026-09-15 09:00:00' );

		$result = Lead::compute();

		$this->assertSame( $newest, $result['id'] );
		$this->assertSame( 'technology', $result['reason'] );
	}

	public function test_technology_candidate_uses_sections_technology_slug(): void {
		// Rename the technology slug via the `ttm_config` filter (SPEC rule 24: the slug is a
		// Config key, not a hard-coded 'technology' literal) and confirm the technology
		// candidate lookup follows it.
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['sections.technology_slug'] = 'renamed-tech';
				return $config;
			}
		);
		\TTM\Core\Config::reset();

		$this->set_now( '2026-09-20 12:00:00' );

		$tech = $this->category_id( 'renamed-tech', 'Renamed Tech' );
		$post = $this->post_with_primary( $tech, '2026-09-19 09:00:00' );

		$result = Lead::compute();

		$this->assertSame( $post, $result['id'] );
		$this->assertSame( 'technology', $result['reason'] );
	}

	public function test_f22_stale_technology_falls_back_sitewide_excluding_journal(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech     = $this->category_id( 'technology', 'Technology' );
		$journal  = $this->category_id( 'journal', 'Journal' );
		$business = $this->category_id( 'business', 'Business' );

		$this->post_with_primary( $tech, '2026-01-01 09:00:00' );
		$this->post_with_primary( $journal, '2026-09-19 09:00:00' );
		$sitewide = $this->post_with_primary( $business, '2026-09-18 09:00:00' );

		$result = Lead::compute();

		$this->assertSame( $sitewide, $result['id'] );
		$this->assertSame( 'sitewide', $result['reason'] );
	}

	public function test_lead_is_cached_in_transient_and_flushed_on_publish(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech  = $this->category_id( 'technology', 'Technology' );
		$first = $this->post_with_primary( $tech, '2026-09-15 09:00:00' );

		$result = Lead::compute();
		$this->assertSame( $first, $result['id'] );

		$cached = get_transient( 'ttm_lead_id' );
		$this->assertSame( $first, $cached['id'] );

		$newer = $this->post_with_primary( $tech, '2026-09-19 09:00:00' );

		// Still cached: transition_post_status already fired for $newer's publish above,
		// so it should already be flushed. Re-assert the freshly computed value.
		$result_after = Lead::compute();
		$this->assertSame( $newer, $result_after['id'] );
	}

	public function test_ttm_lead_post_id_filter_overrides(): void {
		$this->set_now( '2026-09-20 12:00:00' );

		$tech = $this->category_id( 'technology', 'Technology' );
		$this->post_with_primary( $tech, '2026-09-15 09:00:00' );

		$override = self::factory()->post->create( [ 'post_status' => 'publish' ] );

		add_filter(
			'ttm_lead_post_id',
			static fn ( int $id ) => $override
		);

		$result = Lead::compute();

		$this->assertSame( $override, $result['id'] );
	}
}
