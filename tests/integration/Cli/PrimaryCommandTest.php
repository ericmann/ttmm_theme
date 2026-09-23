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

	public function test_plain_assign_fills_the_rest_by_nav_order(): void {
		$tech     = $this->category_id( 'technology', 'Technology' );
		$business = $this->category_id( 'business', 'Business' );

		$empty_post = self::factory()->post->create( [ 'post_category' => [ $tech, $business ] ] );
		delete_post_meta( $empty_post, 'ttm_primary_category' );

		$set_post = self::factory()->post->create( [ 'post_category' => [ $tech ] ] );
		update_post_meta( $set_post, 'ttm_primary_category', $business );

		$result = ( new PrimaryCommand() )->run( [], [] );

		$this->assertTrue( $result['ok'] );
		// sections.order: technology precedes business.
		$this->assertSame( $tech, (int) get_post_meta( $empty_post, 'ttm_primary_category', true ) );
		$this->assertSame( $business, (int) get_post_meta( $set_post, 'ttm_primary_category', true ) );
	}
}
