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
}
