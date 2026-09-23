<?php
/**
 * Unit tests for TTM\Core\Support\Html.
 *
 * @package TTM\Tests\Unit\Support
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Support;

use TTM\Core\Support\Html;
use TTM\Tests\Unit\TestCase;

class HtmlTest extends TestCase {

	public function test_el_escapes_attributes_and_urls(): void {
		$html = Html::el(
			'a',
			[
				'class' => 'ttm-link',
				'href'  => 'https://example.com/?a=b',
			],
			'Inner'
		);

		$this->assertSame( '<a class="ttm-link" href="https://example.com/?a=b">Inner</a>', $html );
	}

	public function test_link_builds_escaped_anchor(): void {
		$html = Html::link( 'https://example.com', 'Label', [ 'class' => 'ttm-cta' ] );

		$this->assertSame( '<a class="ttm-cta" href="https://example.com">Label</a>', $html );
	}

	public function test_classes_joins_truthy_flags(): void {
		$this->assertSame(
			'ttm-a ttm-c',
			Html::classes(
				[
					'ttm-a' => true,
					'ttm-b' => false,
					'ttm-c' => 1,
					'ttm-d' => 0,
				]
			)
		);
	}

	public function test_image_srcs_lists_every_img_src(): void {
		$html = '<p><img src="https://example.com/a.png" alt=""></p>'
			. '<figure><img class="x" src=\'https://example.com/b.png\' /></figure>';

		$this->assertSame(
			[ 'https://example.com/a.png', 'https://example.com/b.png' ],
			Html::image_srcs( $html )
		);
	}

	public function test_image_srcs_returns_empty_array_when_none(): void {
		$this->assertSame( [], Html::image_srcs( '<p>No images here.</p>' ) );
	}

	public function test_photon_origin_url_rewrites_matching_host_only(): void {
		$this->assertSame(
			'https://eric.mann.blog/wp-content/uploads/2020/01/photo.jpg?resize=800',
			Html::photon_origin_url( 'https://i0.wp.com/eric.mann.blog/wp-content/uploads/2020/01/photo.jpg?resize=800', 'eric.mann.blog' )
		);

		$this->assertNull(
			Html::photon_origin_url( 'https://i0.wp.com/example.com/photo.jpg', 'eric.mann.blog' )
		);

		$this->assertNull(
			Html::photon_origin_url( 'https://eric.mann.blog/wp-content/uploads/photo.jpg', 'eric.mann.blog' )
		);
	}

	public function test_replace_url_rewrites_src_and_wrapping_href(): void {
		$html = '<a href="https://example.com/old.jpg"><img src="https://example.com/old.jpg" alt=""></a>';

		$this->assertSame(
			'<a href="https://example.com/new.jpg"><img src="https://example.com/new.jpg" alt=""></a>',
			Html::replace_url( $html, 'https://example.com/old.jpg', 'https://example.com/new.jpg' )
		);
	}
}
