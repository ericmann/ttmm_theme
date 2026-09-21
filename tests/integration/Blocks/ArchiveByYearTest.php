<?php
/**
 * Integration tests for the ttm/archive-by-year block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Blocks\Helpers;

class ArchiveByYearTest extends TTM_IntegrationTestCase {

	private function wrapped_query(): string {
		return '<!-- wp:ttm/archive-by-year -->'
			. '<!-- wp:query {"query":{"perPage":10,"postType":"post","inherit":false,"order":"desc","orderBy":"date"}} -->'
			. '<div class="wp-block-query">'
			. '<!-- wp:post-template -->'
			. '<!-- wp:post-title /-->'
			. '<!-- /wp:post-template -->'
			. '</div>'
			. '<!-- /wp:query -->'
			. '<!-- /wp:ttm/archive-by-year -->';
	}

	private function plain_query(): string {
		return '<!-- wp:query {"query":{"perPage":10,"postType":"post","inherit":false,"order":"desc","orderBy":"date"}} -->'
			. '<div class="wp-block-query">'
			. '<!-- wp:post-template -->'
			. '<!-- wp:post-title /-->'
			. '<!-- /wp:post-template -->'
			. '</div>'
			. '<!-- /wp:query -->';
	}

	public function test_wraps_rows_in_year_groups_in_order(): void {
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2024-03-01 09:00:00',
			] 
		);
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2024-06-01 09:00:00',
			] 
		);
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2023-01-01 09:00:00',
			] 
		);

		$html = (string) do_blocks( $this->wrapped_query() );

		$this->assertSame( 2, substr_count( $html, 'ttm-archive-year__label' ) );

		$pos_2024 = strpos( $html, '>2024<' );
		$pos_2023 = strpos( $html, '>2023<' );

		$this->assertNotFalse( $pos_2024 );
		$this->assertNotFalse( $pos_2023 );
		$this->assertLessThan( $pos_2023, $pos_2024 );
	}

	public function test_f15_single_post_year_is_its_own_group(): void {
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2020-01-01 09:00:00',
			] 
		);
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2021-01-01 09:00:00',
			] 
		);
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2021-02-01 09:00:00',
			] 
		);

		$html = (string) do_blocks( $this->wrapped_query() );

		$this->assertSame( 2, substr_count( $html, 'ttm-archive-year__label' ) );
		$this->assertStringContainsString( '>2020<', $html );
	}

	public function test_post_templates_outside_the_wrapper_are_untouched(): void {
		self::factory()->post->create(
			[
				'post_status' => 'publish',
				'post_date'   => '2024-01-01 09:00:00',
			] 
		);

		$html = (string) do_blocks( $this->plain_query() );

		$this->assertStringNotContainsString( 'ttm-archive-year', $html );
		$this->assertStringContainsString( '<li', $html );
	}

	public function test_scope_resets_after_render(): void {
		self::factory()->post->create( [ 'post_status' => 'publish' ] );

		do_blocks( $this->wrapped_query() );

		$this->assertSame( 0, Helpers::$archive_scope );
	}
}
