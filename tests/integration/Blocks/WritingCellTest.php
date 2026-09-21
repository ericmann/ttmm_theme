<?php
/**
 * Integration tests for the ttm/writing-cell block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Query\SeriesIndex;

class WritingCellTest extends TTM_IntegrationTestCase {

	private function category_id( string $slug, string $name ): int {
		$term = term_exists( $slug, 'category' );
		if ( $term ) {
			return (int) $term['term_id'];
		}

		$created = wp_insert_term( $name, 'category', [ 'slug' => $slug ] );

		return (int) $created['term_id'];
	}

	private function make_serial( string $slug, string $name, string $form, string $status, int $writing_id, int $chapters ): int {
		$term      = wp_insert_term( $name, 'series', [ 'slug' => $slug ] );
		$series_id = (int) $term['term_id'];
		update_term_meta( $series_id, 'ttm_form', $form );
		update_term_meta( $series_id, 'ttm_status', $status );
		update_term_meta( $series_id, 'ttm_cadence', 'monthly' );

		for ( $i = 1; $i <= $chapters; $i++ ) {
			$post_id = self::factory()->post->create(
				[
					'post_status'   => 'publish',
					'post_category' => [ $writing_id ],
					'post_title'    => "Chapter {$i} Title",
					'post_excerpt'  => "Chapter {$i} dek.",
				]
			);
			update_post_meta( $post_id, 'ttm_series_part', $i );
			update_post_meta( $post_id, 'ttm_primary_category', $writing_id );
			update_post_meta( $post_id, 'ttm_form', 'chapter' );
			wp_set_object_terms( $post_id, [ $series_id ], 'series' );
		}

		SeriesIndex::rebuild();

		return $series_id;
	}

	private function make_story( int $writing_id, string $title ): int {
		$post_id = self::factory()->post->create(
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing_id ],
				'post_title'    => $title,
			]
		);
		update_post_meta( $post_id, 'ttm_form', 'story' );
		update_post_meta( $post_id, 'ttm_primary_category', $writing_id );

		return $post_id;
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/writing-cell' . $json . ' /-->' );
	}

	public function test_renders_active_serial_with_latest_chapter_and_buttons(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$this->make_serial( 'active-novel', 'Active Novel', 'novel', 'in-progress', $writing, 2 );

		$html = $this->render();

		$this->assertStringContainsString( 'Active Novel', $html );
		$this->assertStringContainsString( 'Ch. 2: Chapter 2 Title', $html );
		$this->assertStringContainsString( 'Read chapter 2', $html );
		$this->assertStringContainsString( 'From chapter 1', $html );
		$this->assertStringContainsString( 'btn-primary', $html );
	}

	public function test_also_running_lists_other_serials_and_stories_limited(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$this->make_serial( 'active-novel', 'Active Novel', 'novel', 'in-progress', $writing, 2 );
		$this->make_serial( 'done-novella', 'Done Novella', 'novella', 'complete', $writing, 3 );
		$this->make_story( $writing, 'Story One' );
		$this->make_story( $writing, 'Story Two' );

		$html = $this->render( [ 'alsoRunningLimit' => 2 ] );

		$this->assertSame( 2, substr_count( $html, 'ttm-writing-cell__also-row' ) );
		$this->assertStringContainsString( 'Done Novella', $html );
	}

	public function test_f1_no_active_serial_renders_shelf_without_buttons(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$this->make_serial( 'done-novel', 'Done Novel', 'novel', 'complete', $writing, 4 );

		$html = $this->render();

		$this->assertStringContainsString( 'From the shelf', $html );
		$this->assertStringContainsString( 'Done Novel', $html );
		$this->assertStringNotContainsString( 'btn-primary', $html );
		$this->assertStringContainsString( 'Short fiction and the full index', $html );
	}

	public function test_f2_no_fiction_renders_plain_section_cell(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		// Plain Writing posts about writing (not fiction): Meta\Form::on_save() would
		// otherwise auto-derive `ttm_form=story` for any Writing post with no series, so lock
		// these as `article` to reproduce a genuine F2 (no serial terms, no story posts).
		$ids = self::factory()->post->create_many(
			3,
			[
				'post_status'   => 'publish',
				'post_category' => [ $writing ],
			]
		);
		foreach ( $ids as $id ) {
			update_post_meta( $id, 'ttm_form', 'article' );
			update_post_meta( $id, 'ttm_form_locked', true );
		}

		$html = $this->render();

		$this->assertStringContainsString( 'ttm-item', $html );
		$this->assertStringNotContainsString( 'From the shelf', $html );
		$this->assertStringNotContainsString( 'Also running', $html );
	}

	public function test_preview_state_thin_forces_shelf(): void {
		$writing = $this->category_id( 'writing', 'Writing' );
		$this->make_serial( 'active-novel', 'Active Novel', 'novel', 'in-progress', $writing, 2 );
		$this->make_serial( 'done-novella', 'Done Novella', 'novella', 'complete', $writing, 3 );

		set_current_screen( 'post' );

		$html = $this->render( [ 'previewState' => 'thin' ] );

		$this->assertStringContainsString( 'From the shelf', $html );
		$this->assertStringNotContainsString( 'Ch. 2:', $html );
	}
}
