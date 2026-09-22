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

	public function test_link_rows_turns_row_group_into_anchor(): void {
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/journal-post-1/' );

		$html  = '<div class="wp-block-group ttm-journal-row is-layout-flow"><h3 class="wp-block-post-title">Title</h3><div class="wp-block-post-excerpt"><p>Dek</p></div></div>';
		$block = [ 'attrs' => [ 'className' => 'ttm-journal-row' ] ];

		$this->assertSame(
			'<a href="https://example.test/journal-post-1/" class="wp-block-group ttm-journal-row is-layout-flow"><h3 class="wp-block-post-title">Title</h3><div class="wp-block-post-excerpt"><p>Dek</p></div></a>',
			Helpers::link_rows( $html, $block )
		);

		// The archive row class is covered too (P3-03 relies on it).
		$archive = '<div class="wp-block-group ttm-archive-row"><h3>Title</h3></div>';
		$this->assertSame(
			'<a href="https://example.test/journal-post-1/" class="wp-block-group ttm-archive-row"><h3>Title</h3></a>',
			Helpers::link_rows( $archive, [ 'attrs' => [ 'className' => 'ttm-archive-row' ] ] )
		);
	}

	public function test_link_rows_ignores_other_groups(): void {
		Functions\when( 'get_the_ID' )->justReturn( 12 );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.test/journal-post-1/' );

		$plain = '<div class="wp-block-group ttm-item"><h4>Title</h4></div>';
		$this->assertSame( $plain, Helpers::link_rows( $plain, [ 'attrs' => [ 'className' => 'ttm-item' ] ] ) );
		$this->assertSame( $plain, Helpers::link_rows( $plain, [] ) );

		// A row whose title still links (isLink: true) is left alone: no nested anchors.
		$linked = '<div class="wp-block-group ttm-archive-row"><h3><a href="/x/">Title</a></h3></div>';
		$this->assertSame( $linked, Helpers::link_rows( $linked, [ 'attrs' => [ 'className' => 'ttm-archive-row' ] ] ) );

		// A look-alike class ("ttm-journal-rows") is not a row.
		$lookalike = '<div class="wp-block-group ttm-journal-rows"><h4>Title</h4></div>';
		$this->assertSame( $lookalike, Helpers::link_rows( $lookalike, [ 'attrs' => [ 'className' => 'ttm-journal-rows' ] ] ) );
	}


	public function test_style_search_adds_input_and_button_classes(): void {
		$rendered = '<form role="search" method="get" action="/" class="wp-block-search__button-outside wp-block-search__text-button ttm-search wp-block-search"><label class="wp-block-search__label screen-reader-text">Search</label><div class="wp-block-search__inside-wrapper"><input class="wp-block-search__input" type="search" name="s" /><button aria-label="Search" class="wp-block-search__button wp-element-button" type="submit">Search</button></div></form>';

		$styled = Helpers::style_search( $rendered );

		$this->assertStringContainsString( 'class="wp-block-search__input input"', $styled );
		$this->assertStringContainsString( 'class="wp-block-search__button wp-element-button btn btn-secondary"', $styled );
		// The form's own class -- which also contains the substring "button" -- is untouched.
		$this->assertStringContainsString( 'class="wp-block-search__button-outside wp-block-search__text-button ttm-search wp-block-search"', $styled );
	}
}
