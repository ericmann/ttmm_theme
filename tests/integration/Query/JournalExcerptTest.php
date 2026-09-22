<?php
/**
 * Integration tests for TTM\Core\Query\JournalExcerpt.
 *
 * @package TTM\Tests\Integration\Query
 */

declare( strict_types=1 );

class JournalExcerptTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	public function test_journal_post_without_excerpt_gets_sentence_trimmed_excerpt_without_ellipsis(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$content = str_repeat( 'Word ', 60 ) . 'end.';
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_content'  => $content,
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );

		$excerpt = get_the_excerpt( $post );

		$this->assertStringNotContainsString( '[&hellip;]', $excerpt );
		$this->assertStringNotContainsString( '[…]', $excerpt );
		$this->assertNotSame( '', trim( $excerpt ) );
	}

	public function test_manual_excerpt_is_kept(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_content'  => str_repeat( 'Word ', 60 ),
				'post_excerpt'  => 'A manual excerpt.',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );

		$this->assertSame( 'A manual excerpt.', trim( get_the_excerpt( $post ) ) );
	}

	public function test_derived_excerpt_never_exceeds_excerpt_max_words(): void {
		$journal = $this->category_id( 'journal', 'Journal' );
		// No punctuation anywhere: neither the extend-forward nor the cut-back search inside
		// Text::sentence_excerpt() ever finds a boundary, forcing the hard-cut-at-max_words path.
		$content = str_repeat( 'word ', 200 );
		$post    = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $journal ],
				'post_content'  => $content,
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $journal );

		$excerpt    = trim( get_the_excerpt( $post ) );
		$word_count = count( preg_split( '/\s+/', rtrim( $excerpt, '…' ) ) );

		$this->assertLessThanOrEqual( 55, $word_count );
		$this->assertStringEndsWith( '…', $excerpt );
	}

	public function test_non_journal_posts_are_untouched(): void {
		$tech = $this->category_id( 'technology', 'Technology' );
		$post = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $tech ],
				'post_content'  => str_repeat( 'Word ', 60 ) . 'end.',
				'post_excerpt'  => '',
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $tech );

		$excerpt = get_the_excerpt( $post );

		// Untouched means core's own auto-excerpt logic (55-word default, "[&hellip;]") runs,
		// not the Journal sentence-trimmed one.
		$this->assertStringContainsString( '[&hellip;]', $excerpt );
	}
}
