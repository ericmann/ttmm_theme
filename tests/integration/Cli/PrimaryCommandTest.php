<?php
/**
 * Integration tests for `wp ttm primary:assign --from-yoast` (P2-02, SPEC §6.7).
 *
 * @package TTM\Tests\Integration\Cli
 */

declare( strict_types=1 );

use TTM\Core\Cli\PrimaryCommand;

class PrimaryCommandTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_from_yoast_uses_a_valid_yoast_primary(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );
		$post     = self::factory()->post->create( [ 'post_category' => [ $tech, $business ] ] );
		delete_post_meta( $post, 'ttm_primary_category' );
		update_post_meta( $post, '_yoast_wpseo_primary_category', $business );

		$result = ( new PrimaryCommand() )->run( [], [ 'from-yoast' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $business, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertStringContainsString( 'Used 1 post(s) from Yoast, skipped 0.', $result['messages'][0] );
	}

	public function test_from_yoast_skips_a_yoast_category_the_post_is_not_in(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );
		$post     = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		delete_post_meta( $post, 'ttm_primary_category' );
		// Stale/renamed Yoast value: names a category this post doesn't actually carry.
		update_post_meta( $post, '_yoast_wpseo_primary_category', $business );

		$result = ( new PrimaryCommand() )->run( [], [ 'from-yoast' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 0, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertStringContainsString( 'Used 0 post(s) from Yoast, skipped 1.', $result['messages'][0] );
	}

	public function test_from_yoast_dry_run_writes_nothing(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		delete_post_meta( $post, 'ttm_primary_category' );
		update_post_meta( $post, '_yoast_wpseo_primary_category', $tech );

		$result = ( new PrimaryCommand() )->run(
			[],
			[
				'from-yoast' => true,
				'dry-run'    => true,
			]
		);

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 0, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertStringContainsString( 'Would use 1 post(s) from Yoast, skipped 0.', $result['messages'][0] );
	}

	/**
	 * R2-03, SPEC §6.7, §9 Q2: a WXR-imported post's `_yoast_wpseo_primary_category` names a
	 * term id on the *source* site, which doesn't exist (or means something else) on this one.
	 * `--term-map=<path>` translates it through the WXR's own term-id -> slug map (written by
	 * scripts/live/term-map.mjs) to a slug, then resolves *that* to this site's current term id.
	 */
	public function test_from_yoast_with_term_map_translates_source_ids(): void {
		$technology = $this->category_id( 'technology', 'Technology' );
		$security   = $this->category_id( 'security', 'Security' );
		$post       = self::factory()->post->create( [ 'post_category' => [ $technology, $security ] ] );
		delete_post_meta( $post, 'ttm_primary_category' );
		// A foreign id: not a term id on this site at all.
		update_post_meta( $post, '_yoast_wpseo_primary_category', 9999 );

		$map_path = wp_tempnam( 'ttm-term-map' );
		file_put_contents( $map_path, wp_json_encode( [ '9999' => 'security' ] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture file, not production code.

		$result = ( new PrimaryCommand() )->run( [], [ 'from-yoast' => true, 'term-map' => $map_path ] );

		unlink( $map_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $security, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertStringContainsString( 'Used 1 post(s) from Yoast, skipped 0.', $result['messages'][0] );
	}

	/**
	 * The mapped slug is a real category, but this post doesn't carry it -- same "skip, don't
	 * force" rule as an untranslated stale Yoast value.
	 */
	public function test_from_yoast_with_term_map_skips_a_category_the_post_lacks(): void {
		$technology = $this->category_id( 'technology', 'Technology' );
		$this->category_id( 'security', 'Security' );
		$post = self::factory()->post->create( [ 'post_category' => [ $technology ] ] );
		delete_post_meta( $post, 'ttm_primary_category' );
		update_post_meta( $post, '_yoast_wpseo_primary_category', 9999 );

		$map_path = wp_tempnam( 'ttm-term-map' );
		file_put_contents( $map_path, wp_json_encode( [ '9999' => 'security' ] ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- test fixture file, not production code.

		$result = ( new PrimaryCommand() )->run( [], [ 'from-yoast' => true, 'term-map' => $map_path ] );

		unlink( $map_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- test fixture cleanup.

		$this->assertTrue( $result['ok'] );
		$this->assertSame( 0, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
		$this->assertStringContainsString( 'Used 0 post(s) from Yoast, skipped 1.', $result['messages'][0] );
	}

	public function test_from_yoast_with_missing_term_map_file_fails(): void {
		$result = ( new PrimaryCommand() )->run(
			[],
			[
				'from-yoast' => true,
				'term-map'   => '/nonexistent/path/term-map.json',
			]
		);

		$this->assertFalse( $result['ok'] );
	}

	/**
	 * R1-01: the WordPress importer inserts the post (no categories -> default category),
	 * then assigns the real terms. `on_save` no longer stores a primary during that import
	 * save, so `--from-yoast` finds the post still missing a primary and uses Yoast's,
	 * without needing a manual delete_post_meta() first.
	 */
	public function test_from_yoast_after_importer_order_uses_yoast(): void {
		add_filter( 'ttm_primary_on_import', '__return_true' );

		$security   = $this->category_id( 'security', 'Security' );
		$technology = $this->category_id( 'technology', 'Technology' );

		$post_id = self::factory()->post->create();
		wp_set_post_terms( $post_id, [ $security, $technology ], 'category' );
		update_post_meta( $post_id, '_yoast_wpseo_primary_category', $security );

		remove_filter( 'ttm_primary_on_import', '__return_true' );

		$result = ( new PrimaryCommand() )->run( [], [ 'from-yoast' => true ] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $security, (int) get_post_meta( $post_id, 'ttm_primary_category', true ) );
		$this->assertStringContainsString( 'Used 1 post(s) from Yoast, skipped 0.', $result['messages'][0] );
	}

	/**
	 * R1-01: a plain `primary:assign` pass treats a stored primary the post no longer carries
	 * (e.g. after re-categorising) as missing, and replaces it by nav order.
	 */
	public function test_plain_assign_replaces_a_stale_stored_primary(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$post = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		update_post_meta( $post, 'ttm_primary_category', $business );

		$result = ( new PrimaryCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );
		$this->assertSame( $tech, (int) get_post_meta( $post, 'ttm_primary_category', true ) );
	}

	public function test_plain_assign_fills_the_rest_by_nav_order(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$empty_post = self::factory()->post->create( [ 'post_category' => [ $tech, $business ] ] );
		delete_post_meta( $empty_post, 'ttm_primary_category' );

		// $business must still be an assigned category, else it's a stale stored primary and
		// R1-01 replaces it -- this test is about an already-valid stored primary surviving.
		$set_post = self::factory()->post->create( [ 'post_category' => [ $tech, $business ] ] );
		update_post_meta( $set_post, 'ttm_primary_category', $business );

		$result = ( new PrimaryCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );
		// sections.order: technology precedes business.
		$this->assertSame( $tech, (int) get_post_meta( $empty_post, 'ttm_primary_category', true ) );
		$this->assertSame( $business, (int) get_post_meta( $set_post, 'ttm_primary_category', true ) );
	}
}
