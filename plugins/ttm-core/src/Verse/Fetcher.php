<?php
/**
 * Fetches, parses, sanitises and stores the daily verse (SPEC Appendix A, §5.3, §6.7).
 *
 * @package TTM\Core\Verse
 */

declare( strict_types=1 );

namespace TTM\Core\Verse;

use DateTimeImmutable;
use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * The only file allowed to call wp_safe_remote_get for the verse endpoint (SPEC §3.3 rule 16).
 */
class Fetcher {

	/**
	 * The one allowed verse endpoint. Config may only override its path, never its host.
	 */
	public const ENDPOINT = 'https://dailymedtoday.com/api/v1/meditations/';

	/**
	 * The endpoint to fetch: the configured `verse.endpoint` when it shares ENDPOINT's host,
	 * else ENDPOINT itself (SPEC §3.3 rule 16).
	 *
	 * @return string
	 */
	public static function endpoint(): string {
		$configured = (string) Config::get( 'verse.endpoint', self::ENDPOINT );

		if ( wp_parse_url( $configured, PHP_URL_HOST ) !== wp_parse_url( self::ENDPOINT, PHP_URL_HOST ) ) {
			return self::ENDPOINT;
		}

		return $configured;
	}

	/**
	 * Pick today's item (site tz), else the newest item with date <= today.
	 *
	 * @param array<string, mixed> $payload Decoded JSON body: `{data: [...]}`.
	 * @param DateTimeImmutable    $now     Reference "now" (site timezone).
	 * @return array<string, mixed>|null
	 */
	public static function parse( array $payload, DateTimeImmutable $now ): ?array {
		$items = $payload['data'] ?? [];

		if ( ! is_array( $items ) || empty( $items ) ) {
			return null;
		}

		$today = $now->format( 'Y-m-d' );

		$todays_item   = null;
		$todays_parsed = null;
		$best_item     = null;
		$best_parsed   = null;

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$parsed = self::parse_item( $item );

			if ( '' === $parsed['date'] ) {
				continue;
			}

			if ( $parsed['date'] === $today ) {
				$todays_item   = $item;
				$todays_parsed = $parsed;
				continue;
			}

			if ( $parsed['date'] < $today && ( null === $best_parsed || $parsed['date'] > $best_parsed['date'] ) ) {
				$best_item   = $item;
				$best_parsed = $parsed;
			}
		}//end foreach

		if ( null !== $todays_parsed ) {
			$chosen_item   = $todays_item;
			$chosen_parsed = $todays_parsed;
		} elseif ( null !== $best_parsed ) {
			$chosen_item   = $best_item;
			$chosen_parsed = $best_parsed;
		} else {
			return null;
		}

		return apply_filters( 'ttm_verse_parsed', $chosen_parsed, $chosen_item );
	}

	/**
	 * Map one raw API item to the `ttm_verse` shape (without `fetched_at`/`etag`), sanitising
	 * text fields (SPEC §3.3 rule 21): `text`/`reference` allow only `em`/`strong`; `copyright`
	 * and `title` are plain text; `meditation_content` is never read.
	 *
	 * @param array<string, mixed> $item Raw API item.
	 * @return array<string, mixed>
	 */
	public static function parse_item( array $item ): array {
		$published    = isset( $item['published_at'] ) ? Clock::at( (string) $item['published_at'] ) : null;
		$item_pattern = (string) Config::get( 'verse.item_url_pattern', 'https://dailymedtoday.com/meditation/%s' );

		return [
			'date'      => $published ? $published->format( 'Y-m-d' ) : '',
			'text'      => wp_kses( (string) ( $item['scripture_text'] ?? '' ), self::allowed_html() ),
			'reference' => wp_kses( (string) ( $item['scripture_reference'] ?? '' ), self::allowed_html() ),
			'title'     => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
			'url'       => sprintf( $item_pattern, (string) ( $item['id'] ?? '' ) ),
			'source_id' => (string) ( $item['id'] ?? '' ),
			'copyright' => sanitize_text_field( (string) ( $item['copyright_notice'] ?? '' ) ),
		];
	}

	/**
	 * Fetch the endpoint (unless today's verse is already stored and not forced), parse it,
	 * and store the result.
	 *
	 * @param bool $force Fetch even when today's verse is already stored.
	 * @return array{ok: bool, message: string}
	 */
	public static function fetch( bool $force = false ): array {
		$current = get_option( 'ttm_verse', [] );

		if ( ! $force && is_array( $current ) && ( $current['date'] ?? '' ) === Clock::today() ) {
			$message = __( "Already have today's verse.", 'ttm-core' );
			self::log( true, $message );
			return [
				'ok'      => true,
				'message' => $message,
			];
		}

		$user_agent = str_replace(
			'{version}',
			defined( 'TTM_CORE_VERSION' ) ? TTM_CORE_VERSION : '0.0.0',
			(string) Config::get( 'verse.user_agent', 'TTM-Core/{version}' )
		);

		$args = [
			'timeout'    => (int) Config::get( 'verse.timeout_seconds', 8 ),
			'user-agent' => $user_agent,
			'headers'    => [],
		];

		$etag = is_array( $current ) ? (string) ( $current['etag'] ?? '' ) : '';
		if ( '' !== $etag ) {
			$args['headers']['If-None-Match'] = $etag;
		}

		$response = wp_safe_remote_get( self::endpoint(), $args );

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();
			self::log( false, $message );
			return [
				'ok'      => false,
				'message' => $message,
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 304 === $code ) {
			$message = __( 'Verse unchanged (304).', 'ttm-core' );
			self::log( true, $message );
			return [
				'ok'      => true,
				'message' => $message,
			];
		}

		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code. */
			$message = sprintf( __( 'Unexpected HTTP status %d.', 'ttm-core' ), $code );
			self::log( false, $message );
			return [
				'ok'      => false,
				'message' => $message,
			];
		}

		$payload = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $payload ) ) {
			$message = __( 'Invalid JSON payload.', 'ttm-core' );
			self::log( false, $message );
			return [
				'ok'      => false,
				'message' => $message,
			];
		}

		$parsed = self::parse( $payload, Clock::now() );

		if ( null === $parsed ) {
			$message = __( 'No usable verse in the payload.', 'ttm-core' );
			self::log( false, $message );
			return [
				'ok'      => false,
				'message' => $message,
			];
		}

		$parsed['fetched_at'] = Clock::now()->format( 'Y-m-d H:i:s' );
		$parsed['etag']       = (string) wp_remote_retrieve_header( $response, 'etag' );

		self::store( $parsed );

		$message = __( 'Verse fetched.', 'ttm-core' );
		self::log( true, $message );

		return [
			'ok'      => true,
			'message' => $message,
		];
	}

	/**
	 * Store a parsed verse as `ttm_verse`, pushing the previous value onto `ttm_verse_history`
	 * (unless it shares the new one's `source_id`), and fire a purge.
	 *
	 * @param array<string, mixed> $verse Parsed verse, with `fetched_at`/`etag`.
	 */
	public static function store( array $verse ): void {
		$previous = get_option( 'ttm_verse', [] );

		if ( is_array( $previous ) && ! empty( $previous ) && ( $previous['source_id'] ?? null ) !== ( $verse['source_id'] ?? null ) ) {
			$history = get_option( 'ttm_verse_history', [] );
			$history = is_array( $history ) ? $history : [];

			array_unshift( $history, $previous );

			update_option( 'ttm_verse_history', array_slice( $history, 0, (int) Config::get( 'verse.history_size', 30 ) ) );
		}

		update_option( 'ttm_verse', $verse );

		do_action( 'ttm_purge_urls', [ home_url( '/' ), rest_url( 'ttm/v1/verse' ) ] );
	}

	/**
	 * Append a log entry, newest first, capped at `verse.log_size`.
	 *
	 * @param bool   $ok      Whether the fetch succeeded.
	 * @param string $message Human-readable message.
	 */
	public static function log( bool $ok, string $message ): void {
		$log = get_option( 'ttm_verse_log', [] );
		$log = is_array( $log ) ? $log : [];

		array_unshift(
			$log,
			[
				'at'      => Clock::now()->format( 'Y-m-d H:i:s' ),
				'ok'      => $ok,
				'message' => $message,
			]
		);

		update_option( 'ttm_verse_log', array_slice( $log, 0, (int) Config::get( 'verse.log_size', 20 ) ) );
	}

	/**
	 * The wp_kses allow-list for verse text fields (SPEC §3.3 rule 21).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private static function allowed_html(): array {
		return [
			'em'     => [],
			'strong' => [],
		];
	}
}
