<?php
/**
 * Base class for unit tests: boots Brain\Monkey around every test.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use DateTimeZone;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		$this->stub_wordpress_functions();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Minimal WordPress function stubs shared by every unit test. No WordPress is loaded (rule 28).
	 */
	private function stub_wordpress_functions(): void {
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( '_x' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'esc_url' )->returnArg( 1 );
		Functions\when( 'esc_url_raw' )->returnArg( 1 );
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_strip_all_tags' )->alias(
			static fn ( string $value ): string => trim( strip_tags( $value ) )
		);
		Functions\when( 'sanitize_text_field' )->alias(
			static fn ( string $value ): string => trim( strip_tags( $value ) )
		);
		Functions\when( 'absint' )->alias( static fn ( $value ): int => abs( (int) $value ) );
		Functions\when( 'wp_timezone' )->justReturn( new DateTimeZone( 'America/Los_Angeles' ) );
		Functions\when( 'get_option' )->justReturn( [] );
		Functions\when( '_n' )->alias(
			static fn ( string $single, string $plural, int $number ): string => 1 === $number ? $single : $plural
		);
		// Default pass-through; individual tests override with a more specific when()/expect() for the tag they care about.
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}
}
