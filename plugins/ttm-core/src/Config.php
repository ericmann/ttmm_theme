<?php
/**
 * Single configuration source. Imports nothing.
 *
 * @package TTM\Core
 */

declare( strict_types=1 );

namespace TTM\Core;

/**
 * Every tunable in the plugin is a Config key (SPEC §3.4 rule 24).
 */
class Config {

	/**
	 * Memoised merged config.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Keys `ttm_settings` is allowed to overlay onto the defaults.
	 *
	 * @var string[]
	 */
	private const OVERLAY_KEYS = [
		'newsletter.provider',
		'newsletter.endpoint',
		'newsletter.list_id',
		'newsletter.fallback_email',
		'lead.sticky_days',
		'lead.stale_days',
		'journal_in_main_feed',
		'comments_enabled',
	];

	/**
	 * Flat, dotted-key defaults, exactly as SPEC §5.4 lists them.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return [
			'sections.order'               => [ 'technology', 'business', 'faith', 'journal', 'writing', 'security', 'opinion' ],
			'sections.nav_hub_slug'        => 'series',
			'sections.journal_slug'        => 'journal',
			'sections.writing_slug'        => 'writing',
			'sections.politics_slug'       => 'politics',

			'lead.sticky_days'             => 30,
			'lead.stale_days'              => 30,
			'lead.cache_seconds'           => 300,

			'cells.counts'                 => [
				'technology' => 3,
				'business'   => 3,
				'security'   => 3,
				'faith'      => 2,
				'opinion'    => 2,
			],
			'cells.thin_days'              => 90,
			'cells.stale_year_days'        => 365,
			'journal.rail_count'           => 3,
			'journal.rail_window_days'     => 30,
			'journal.excerpt_words'        => 40,
			'journal.stream_count'         => 4,
			'journal.archive_per_page'     => 20,
			'journal.relative_day_window'  => 6,

			'series.strip_limit'           => 3,
			'series.index_batch'           => 500,
			'series.max_purchase_links'    => 6,
			'series.hub_featured_parts'    => 12,
			'writing.also_running_limit'   => 3,
			'writing.chapters_recent'      => 4,
			'writing.story_tiles'          => 4,
			'writing.shelf_limit'          => 4,

			'archive.per_page'             => 12,
			'archive.tag_filter_limit'     => 5,
			'archive.row_tags'             => 2,
			'archive.most_read_limit'      => 3,
			'article.more_in_section'      => 3,
			'reading.words_per_minute'     => 230,

			'verse.endpoint'               => 'https://dailymedtoday.com/api/v1/meditations/',
			'verse.item_url_pattern'       => 'https://dailymedtoday.com/meditation/%s',
			'verse.fetch_hour'             => 5,
			'verse.retry_delay_seconds'    => 7200,
			'verse.timeout_seconds'        => 8,
			'verse.user_agent'             => 'TTM-Core/{version} (+https://eric.mann.blog)',
			'verse.history_size'           => 30,
			'verse.log_size'               => 20,

			'cache.verse_boundary_hour'    => 6,
			'cache.max_age_cap_seconds'    => 86400,
			'cache.min_age_seconds'        => 60,
			'cache.cloudflare.zone_id'     => defined( 'TTM_CLOUDFLARE_ZONE_ID' ) ? TTM_CLOUDFLARE_ZONE_ID : '',
			'cache.cloudflare.api_token'   => defined( 'TTM_CLOUDFLARE_API_TOKEN' ) ? TTM_CLOUDFLARE_API_TOKEN : '',

			'newsletter.provider'          => 'jetpack',
			'newsletter.endpoint'          => '',
			'newsletter.list_id'           => '',
			'newsletter.fallback_email'    => '',
			'newsletter.token_ttl'         => 86400,
			'newsletter.rate_limit_per_ip' => 5,
			'newsletter.rate_limit_window' => 600,
			'newsletter.honeypot_field'    => 'ttm_website',

			'images.sizes'                 => [
				'ttm-lead'  => [ 1600, 900, true ],
				'ttm-thumb' => [ 800, 533, true ],
				'ttm-cover' => [ 600, 900, true ],
				'ttm-tile'  => [ 800, 600, true ],
			],
			'stats.cache_seconds'          => 3600,
			'stats.tags_cache_seconds'     => 43200,

			'journal_in_main_feed'         => true,
			'comments_enabled'             => false,
		];
	}

	/**
	 * Defaults overlaid with the `ttm_settings` option (allowed keys only) and `ttm_config` filter.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$config   = self::defaults();
		$settings = get_option( 'ttm_settings', [] );

		if ( is_array( $settings ) ) {
			$flat = self::flatten_settings( $settings );
			foreach ( self::OVERLAY_KEYS as $key ) {
				if ( array_key_exists( $key, $flat ) ) {
					$config[ $key ] = $flat[ $key ];
				}
			}
		}

		self::$cache = apply_filters( 'ttm_config', $config );

		return self::$cache;
	}

	/**
	 * Fetch a single key.
	 *
	 * @param string $key          Dotted config key.
	 * @param mixed  $fallback Value to return if the key is absent.
	 * @return mixed
	 */
	public static function get( string $key, mixed $fallback = null ): mixed {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Clear the memoised config (tests, or after a settings save).
	 */
	public static function reset(): void {
		self::$cache = null;
	}

	/**
	 * Flatten the nested `ttm_settings` shape (SPEC §5.3) to dotted keys.
	 *
	 * @param array<string, mixed> $settings Raw option value.
	 * @return array<string, mixed>
	 */
	private static function flatten_settings( array $settings ): array {
		$flat = [];

		foreach ( $settings as $key => $value ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $sub_key => $sub_value ) {
					$flat[ $key . '.' . $sub_key ] = $sub_value;
				}
				continue;
			}
			$flat[ $key ] = $value;
		}

		return $flat;
	}
}
