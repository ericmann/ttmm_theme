<?php
/**
 * Unit tests for TTM\Core\Meta\PostMeta pure sanitizers.
 *
 * @package TTM\Tests\Unit\Meta
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Meta;

use Brain\Monkey\Functions;
use TTM\Core\Meta\PostMeta;
use TTM\Tests\Unit\TestCase;

class PostMetaTest extends TestCase {

	public function test_sanitize_form_defaults_to_article(): void {
		$this->assertSame( 'chapter', PostMeta::sanitize_form( 'chapter' ) );
		$this->assertSame( 'article', PostMeta::sanitize_form( 'bogus' ) );
	}

	public function test_sanitize_syndication_keeps_only_https_known_networks(): void {
		Functions\when( 'esc_url_raw' )->alias(
			static function ( string $url ): string {
				return 0 === strpos( $url, 'https://' ) ? $url : '';
			}
		);

		$result = PostMeta::sanitize_syndication(
			[
				'x'        => 'javascript:alert(1)',
				'mastodon' => 'https://mastodon.social/@eric',
				'unknown'  => 'https://example.com',
				'bluesky'  => 'http://bsky.app/eric',
			]
		);

		$this->assertSame( [ 'mastodon' => 'https://mastodon.social/@eric' ], $result );
	}

	public function test_sanitize_part_rejects_zero_and_negatives(): void {
		$this->assertSame( 3, PostMeta::sanitize_part( 3 ) );
		$this->assertSame( 0, PostMeta::sanitize_part( 0 ) );
		$this->assertSame( 0, PostMeta::sanitize_part( -5 ) );
	}
}
