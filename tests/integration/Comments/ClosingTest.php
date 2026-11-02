<?php
/**
 * Integration tests for TTM\Core\Comments\Closing: comments close by kind, not core's setting.
 *
 * @package TTM\Tests\Integration\Comments
 */

declare( strict_types=1 );

class ClosingTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		return (int) wp_insert_term( ucfirst( $slug ), 'category', [ 'slug' => $slug ] )['term_id'];
	}

	private function post_days_ago( string $section, int $days, string $form = '' ): int {
		$category = $this->category_id( $section );
		$post     = self::factory()->post->create(
			[
				'post_status'    => 'publish',
				'post_category'  => [ $category ],
				'comment_status' => 'open',
				'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ),
				'post_date'      => gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) ),
			]
		);
		update_post_meta( $post, 'ttm_primary_category', $category );
		if ( '' !== $form ) {
			update_post_meta( $post, 'ttm_form', $form );
		}

		return $post;
	}

	public function test_journal_closes_after_two_weeks(): void {
		$this->assertTrue( comments_open( $this->post_days_ago( 'journal', 13 ) ) );
		$this->assertFalse( comments_open( $this->post_days_ago( 'journal', 15 ) ) );
	}

	public function test_fiction_closes_after_thirty_days(): void {
		$this->assertTrue( comments_open( $this->post_days_ago( 'writing', 29, 'chapter' ) ) );
		$this->assertFalse( comments_open( $this->post_days_ago( 'writing', 31, 'chapter' ) ) );
	}

	public function test_everything_else_stays_open_a_year(): void {
		$this->assertTrue( comments_open( $this->post_days_ago( 'technology', 300 ) ) );
		$this->assertFalse( comments_open( $this->post_days_ago( 'technology', 370 ) ) );
	}

	public function test_core_setting_no_longer_closes_anything(): void {
		update_option( 'close_comments_for_old_posts', 1 );
		update_option( 'close_comments_days_old', 1 );

		$this->assertTrue( comments_open( $this->post_days_ago( 'technology', 30 ) ) );
	}

	public function test_a_closed_post_stays_closed(): void {
		$post = $this->post_days_ago( 'technology', 1 );
		wp_update_post(
			[
				'ID'             => $post,
				'comment_status' => 'closed',
			]
		);

		$this->assertFalse( comments_open( $post ) );
	}
}
