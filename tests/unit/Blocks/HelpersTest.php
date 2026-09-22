<?php
/**
 * Unit tests for TTM\Core\Blocks\Helpers.
 *
 * @package TTM\Tests\Unit\Blocks
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit\Blocks;

use Brain\Monkey\Functions;
use TTM\Core\Blocks\Helpers;
use TTM\Tests\Unit\TestCase;

class HelpersTest extends TestCase {

	public function test_status_word_maps_three_statuses(): void {
		$this->assertSame( 'In progress', Helpers::status_word( 'in-progress' ) );
		$this->assertSame( 'Complete', Helpers::status_word( 'complete' ) );
		$this->assertSame( 'On hiatus', Helpers::status_word( 'hiatus' ) );
	}

	public function test_wrapper_contains_block_name_and_state_classes(): void {
		Functions\when( 'get_block_wrapper_attributes' )->alias(
			static function ( array $attrs = [] ): string {
				$out = '';
				foreach ( $attrs as $key => $value ) {
					$out .= sprintf( ' %s="%s"', $key, $value );
				}
				return trim( $out );
			}
		);

		$html = Helpers::wrapper( 'verse', [ 'is-empty' ] );

		$this->assertStringContainsString( 'ttm-verse', $html );
		$this->assertStringContainsString( 'is-empty', $html );
		$this->assertStringContainsString( 'data-ttm-block="verse"', $html );
	}

	public function test_featured_caption_inserts_figcaption_before_closing_figure(): void {
		Functions\stubs( [ 'esc_html' ] );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 34 );
		Functions\when( 'wp_get_attachment_caption' )->justReturn( 'Photographs are grayscale only on the front page.' );

		$html = Helpers::featured_caption( '<figure class="wp-block-post-featured-image"><img src="x.png" alt=""></figure>' );

		$this->assertSame(
			'<figure class="wp-block-post-featured-image"><img src="x.png" alt=""><figcaption class="ttm-hero__caption">Photographs are grayscale only on the front page.</figcaption></figure>',
			$html
		);
	}

	public function test_featured_caption_leaves_figure_without_caption_alone(): void {
		Functions\stubs( [ 'esc_html' ] );
		Functions\when( 'is_singular' )->justReturn( true );
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_post_thumbnail_id' )->justReturn( 34 );
		Functions\when( 'wp_get_attachment_caption' )->justReturn( '' );

		$figure = '<figure class="wp-block-post-featured-image"><img src="x.png" alt=""></figure>';

		$this->assertSame( $figure, Helpers::featured_caption( $figure ) );
	}

	public function test_style_tag_terms_adds_tag_classes_and_drops_separators(): void {
		$html  = '<div class="taxonomy-post_tag is-style-tags wp-block-post-terms"><a href="/tag/wordpress/" rel="tag">wordpress</a><span class="wp-block-post-terms__separator">, </span><a href="/tag/php/" rel="tag">php</a></div>';
		$block = [ 'attrs' => [ 'className' => 'is-style-tags' ] ];

		$this->assertSame(
			'<div class="taxonomy-post_tag is-style-tags wp-block-post-terms"><a class="tag tag-neutral" href="/tag/wordpress/" rel="tag">wordpress</a><a class="tag tag-neutral" href="/tag/php/" rel="tag">php</a></div>',
			Helpers::style_tag_terms( $html, $block )
		);

		// Not the tags style: untouched (the kicker keeps its separator).
		$kicker = '<div class="is-style-kicker wp-block-post-terms"><a href="/category/technology/" rel="tag">Technology</a><span class="wp-block-post-terms__separator"> · </span><a href="/category/security/" rel="tag">Security</a></div>';
		$this->assertSame( $kicker, Helpers::style_tag_terms( $kicker, [ 'attrs' => [ 'className' => 'is-style-kicker' ] ] ) );
	}

	public function test_excerpt_markup_restores_inline_code_from_the_manual_excerpt(): void {
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_post_field' )->justReturn( 'Rewrite <code>wp_options</code> <b>quietly</b>.' );
		Functions\when( 'wp_kses' )->alias(
			static fn ( string $html ): string => strip_tags( $html, '<code><em><strong>' )
		);

		$rendered = '<div class="is-style-dek-l wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt">Rewrite wp_options quietly. </p></div>';

		$this->assertSame(
			'<div class="is-style-dek-l wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt">Rewrite <code>wp_options</code> quietly.</p></div>',
			Helpers::excerpt_markup( $rendered )
		);
	}

	public function test_excerpt_markup_leaves_plain_and_auto_excerpts_alone(): void {
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_post_field' )->justReturn( '' );

		$rendered = '<div class="wp-block-post-excerpt"><p class="wp-block-post-excerpt__excerpt">Auto excerpt…</p></div>';

		$this->assertSame( $rendered, Helpers::excerpt_markup( $rendered ) );
	}

	public function test_author_prefix_prepends_the_pattern_prefix(): void {
		$rendered = '<div class="wp-block-post-author-name"><a href="/author/eric/" target="_self" class="wp-block-post-author-name__link">Eric Mann</a></div>';

		$this->assertSame(
			'<div class="wp-block-post-author-name">By <a href="/author/eric/" target="_self" class="wp-block-post-author-name__link">Eric Mann</a></div>',
			Helpers::author_prefix( $rendered, [ 'attrs' => [ 'prefix' => 'By ' ] ] )
		);
		$this->assertSame( $rendered, Helpers::author_prefix( $rendered, [ 'attrs' => [] ] ) );
	}
}
