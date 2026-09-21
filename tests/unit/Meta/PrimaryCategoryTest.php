<?php
/**
 * Unit tests for TTM\Core\Meta\PrimaryCategory::resolve().
 *
 * @package TTM\Tests\Unit\Meta
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Meta;

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
}
