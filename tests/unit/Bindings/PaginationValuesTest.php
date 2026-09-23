<?php
/**
 * Unit tests for TTM\Core\Bindings\Values::pagination_label().
 *
 * @package TTM\Tests\Unit\Bindings
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Bindings;

use TTM\Core\Bindings\Values;
use TTM\Tests\Unit\TestCase;

class PaginationValuesTest extends TestCase {

	public function test_older_with_range(): void {
		$this->assertSame( 'Older (2014–2022) →', Values::pagination_label( 'older', 2014, 2022 ) );
	}

	public function test_newer_with_single_year(): void {
		$this->assertSame( '← Newer (2023)', Values::pagination_label( 'newer', 2023, 2023 ) );
	}

	public function test_empty_when_no_years(): void {
		$this->assertSame( '', Values::pagination_label( 'older', null, null ) );
	}

	public function test_disabled_labels(): void {
		$this->assertSame( 'Older →', Values::pagination_disabled_label( 'older' ) );
		$this->assertSame( '← Newer', Values::pagination_disabled_label( 'newer' ) );
	}
}
