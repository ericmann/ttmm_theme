<?php
/**
 * Unit tests for TTM\Core\Newsletter\Providers.
 *
 * @package TTM\Tests\Unit\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Newsletter;

use TTM\Core\Newsletter\Providers;
use TTM\Tests\Unit\TestCase;

class ProvidersTest extends TestCase {

	public function test_jetpack_when_block_registered(): void {
		$registry = [
			'jetpack' => new StubProvider( 'jetpack', true ),
			'mailto'  => new StubProvider( 'mailto', true ),
			'none'    => new StubProvider( 'none', true ),
		];

		$this->assertSame( 'jetpack', Providers::resolve( 'jetpack', $registry )->slug() );
	}

	public function test_falls_back_to_mailto_when_email_set(): void {
		$registry = [
			'jetpack' => new StubProvider( 'jetpack', false ),
			'mailto'  => new StubProvider( 'mailto', true ),
			'none'    => new StubProvider( 'none', true ),
		];

		$this->assertSame( 'mailto', Providers::resolve( 'jetpack', $registry )->slug() );
	}

	public function test_falls_back_to_none_otherwise(): void {
		$registry = [
			'jetpack' => new StubProvider( 'jetpack', false ),
			'mailto'  => new StubProvider( 'mailto', false ),
			'none'    => new StubProvider( 'none', true ),
		];

		$this->assertSame( 'none', Providers::resolve( 'jetpack', $registry )->slug() );
	}

	public function test_unknown_slug_uses_fallback_chain(): void {
		$registry = [
			'mailto' => new StubProvider( 'mailto', true ),
			'none'   => new StubProvider( 'none', true ),
		];

		$this->assertSame( 'mailto', Providers::resolve( 'custom-url', $registry )->slug() );
	}
}
