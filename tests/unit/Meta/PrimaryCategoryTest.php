<?php
/**
 * Unit tests for TTM\Core\Meta\PrimaryCategory::resolve().
 *
 * @package TTM\Tests\Unit\Meta
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Meta;

use Brain\Monkey\Functions;
use TTM\Core\Meta\PrimaryCategory;
use TTM\Tests\Unit\TestCase;

class PrimaryCategoryTest extends TestCase {

	private const ORDER = [ 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ];

	public function test_resolve_picks_first_in_nav_order(): void {
		$this->assertSame(
			'technology',
			PrimaryCategory::resolve( [ 'security', 'technology' ], self::ORDER )
		);
	}

	public function test_resolve_falls_back_to_first_assigned_when_none_is_a_section(): void {
		$this->assertSame(
			'uncategorized',
			PrimaryCategory::resolve( [ 'uncategorized', 'random' ], self::ORDER )
		);
	}

	public function test_resolve_null_when_uncategorised(): void {
		$this->assertNull( PrimaryCategory::resolve( [], self::ORDER ) );
	}

	/**
	 * @param int    $id     Term id.
	 * @param string $slug   Slug.
	 * @param string $name   Name.
	 * @param int    $parent Parent id.
	 * @return object
	 */
	private function term( int $id, string $slug, string $name, int $parent = 0 ): object {
		return (object) [
			'term_id' => $id,
			'slug'    => $slug,
			'name'    => $name,
			'parent'  => $parent,
		];
	}

	public function test_order_terms_puts_primary_first_then_nav_order(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		// Primary category meta -> security (id 6), so it leads even though technology is
		// first in nav order; then technology (index 0) before politics, whose top-level
		// ancestor opinion (index 6) is last; get_term() resolves politics' parent.
		Functions\when( 'get_post_meta' )->justReturn( 6 );
		Functions\when( 'wp_get_post_categories' )->justReturn( [ 1, 6, 8 ] );
		$opinion = $this->term( 7, 'opinion', 'Opinion' );
		Functions\when( 'get_term' )->alias(
			static function ( int $id ) use ( $opinion ) {
				return 7 === $id ? $opinion : null;
			}
		);
		Functions\when( 'is_wp_error' )->justReturn( false );

		$politics   = $this->term( 8, 'politics', 'Politics', 7 );
		$technology = $this->term( 1, 'technology', 'Technology' );
		$security   = $this->term( 6, 'security', 'Security' );

		$ordered = PrimaryCategory::order_terms( [ $politics, $security, $technology ], 42, 'category' );

		$this->assertSame( [ 'security', 'technology', 'politics' ], array_column( $ordered, 'slug' ) );
	}

	public function test_order_terms_leaves_other_taxonomies_alone(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		$terms = [ $this->term( 3, 'wordpress', 'wordpress' ), $this->term( 2, 'php', 'php' ) ];

		$this->assertSame( $terms, PrimaryCategory::order_terms( $terms, 42, 'post_tag' ) );
	}

	/**
	 * R1-01: a stored primary the post no longer carries (e.g. after re-categorising, or a
	 * stale value left by the importer's insert-then-set-terms order) is treated as empty and
	 * resolved fresh from the post's current terms and nav order -- id() never trusts a
	 * stored value that isn't in wp_get_post_categories().
	 */
	public function test_id_falls_back_to_nav_order_when_stored_term_is_stale(): void {
		Functions\when( 'get_post_meta' )->justReturn( 99 );
		Functions\when( 'wp_get_post_categories' )->justReturn( [ 6 ] );
		Functions\when( 'is_wp_error' )->justReturn( false );
		$security = $this->term( 6, 'security', 'Security' );
		Functions\when( 'get_term' )->justReturn( $security );
		Functions\when( 'get_term_by' )->justReturn( $security );

		$this->assertSame( 6, PrimaryCategory::id( 42 ) );
	}

	public function test_id_returns_stored_when_still_assigned(): void {
		Functions\when( 'get_post_meta' )->justReturn( 6 );
		Functions\when( 'wp_get_post_categories' )->justReturn( [ 1, 6 ] );

		$this->assertSame( 6, PrimaryCategory::id( 42 ) );
	}

	public function test_is_import_save_is_true_when_wp_importing_defined(): void {
		if ( ! defined( 'WP_IMPORTING' ) ) {
			define( 'WP_IMPORTING', true );
		}

		$this->assertTrue( PrimaryCategory::is_import_save() );
	}
}
