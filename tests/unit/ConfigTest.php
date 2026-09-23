<?php
/**
 * Unit tests for TTM\Core\Config.
 *
 * @package TTM\Tests\Unit
 */

declare( strict_types=1 );

namespace TTM\Tests\Unit;

use Brain\Monkey\Functions;
use TTM\Core\Config;

class ConfigTest extends TestCase {

	protected function tearDown(): void {
		Config::reset();
		parent::tearDown();
	}

	public function test_defaults_contain_every_spec_key(): void {
		$expected = [
			'sections.order',
			'sections.nav_hub_slug',
			'sections.journal_slug',
			'sections.writing_slug',
			'sections.politics_slug',
			'nav.front_current',
			'sections.technology_slug',
			'lead.sticky_days',
			'lead.stale_days',
			'lead.cache_seconds',
			'cells.counts',
			'cells.stale_year_days',
			'cells.stale_count',
			'journal.rail_count',
			'journal.rail_window_days',
			'journal.excerpt_words',
			'journal.excerpt_max_words',
			'journal.stream_count',
			'journal.archive_per_page',
			'journal.relative_day_window',
			'series.strip_limit',
			'series.index_batch',
			'series.max_purchase_links',
			'series.hub_featured_parts',
			'series.related_limit',
			'writing.also_running_limit',
			'writing.chapters_recent',
			'writing.story_tiles',
			'writing.tile_columns',
			'writing.shelf_limit',
			'writing.plain_count',
			'books.max',
			'books.blank_rows',
			'books.link_rows',
			'cli.batch',
			'excerpt_length',
			'archive.per_page',
			'archive.tag_filter_limit',
			'archive.row_tags',
			'archive.most_read_limit',
			'article.more_in_section',
			'reading.words_per_minute',
			'verse.endpoint',
			'verse.item_url_pattern',
			'verse.fetch_hour',
			'verse.retry_delay_seconds',
			'verse.timeout_seconds',
			'verse.user_agent',
			'verse.history_size',
			'verse.log_size',
			'cache.verse_boundary_hour',
			'cache.max_age_cap_seconds',
			'cache.min_age_seconds',
			'cache.feed_seconds',
			'cache.cloudflare.zone_id',
			'cache.cloudflare.api_token',
			'cache.cloudflare.batch',
			'cache.cloudflare.timeout_seconds',
			'newsletter.provider',
			'newsletter.endpoint',
			'newsletter.list_id',
			'newsletter.fallback_email',
			'newsletter.token_ttl',
			'newsletter.rate_limit_per_ip',
			'newsletter.rate_limit_window',
			'newsletter.honeypot_field',
			'newsletter.api_key',
			'newsletter.timeout_seconds',
			'newsletter.dev_accept',
			'images.sizes',
			'stats.cache_seconds',
			'stats.tags_cache_seconds',
			'journal_in_main_feed',
			'comments_enabled',
			'seed.quiet_offset_days',
			'seed.image_band_angle',
		];

		$defaults = Config::defaults();

		foreach ( $expected as $key ) {
			$this->assertArrayHasKey( $key, $defaults, "Missing default for {$key}" );
		}

		$this->assertSame(
			[
				'technology' => 3,
				'business'   => 3,
				'security'   => 3,
				'faith'      => 2,
				'opinion'    => 2,
			],
			$defaults['cells.counts']
		);

		$this->assertSame(
			[
				'ttm-lead'  => [ 1600, 900, true ],
				'ttm-thumb' => [ 800, 533, true ],
				'ttm-cover' => [ 600, 900, true ],
				'ttm-tile'  => [ 800, 600, true ],
			],
			$defaults['images.sizes']
		);
	}

	public function test_get_returns_default_for_unknown_key(): void {
		$this->assertSame( 'fallback', Config::get( 'nope.does_not_exist', 'fallback' ) );
		$this->assertNull( Config::get( 'nope.does_not_exist' ) );
	}

	public function test_settings_option_overlays_only_allowed_keys(): void {
		Functions\when( 'get_option' )->justReturn(
			[
				'lead'  => [ 'sticky_days' => 45 ],
				'cells' => [ 'counts' => [ 'technology' => 99 ] ],
			]
		);

		$all = Config::all();

		$this->assertSame( 45, $all['lead.sticky_days'] );
		$this->assertSame(
			[
				'technology' => 3,
				'business'   => 3,
				'security'   => 3,
				'faith'      => 2,
				'opinion'    => 2,
			],
			$all['cells.counts']
		);
	}

	public function test_ttm_config_filter_applies(): void {
		Functions\when( 'apply_filters' )->alias(
			static function ( string $tag, $value ) {
				if ( 'ttm_config' === $tag ) {
					$value['lead.sticky_days'] = 1;
				}
				return $value;
			}
		);

		$this->assertSame( 1, Config::get( 'lead.sticky_days' ) );
	}

	public function test_verse_endpoint_default_is_the_dailymedtoday_api(): void {
		$this->assertSame( 'https://dailymedtoday.com/api/v1/meditations/', Config::get( 'verse.endpoint' ) );
	}

	public function test_series_related_limit_default_is_four(): void {
		$this->assertSame( 4, Config::get( 'series.related_limit' ) );
	}

	public function test_seed_image_band_angle_default_is_thirty(): void {
		$this->assertSame( 30, Config::get( 'seed.image_band_angle' ) );
	}
}
