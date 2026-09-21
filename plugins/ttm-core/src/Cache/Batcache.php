<?php
/**
 * Hands the computed max-age to Batcache when it is active.
 *
 * @package TTM\Core\Cache
 */

declare( strict_types=1 );

namespace TTM\Core\Cache;

use TTM\Core\Support\Clock;

/**
 * `init`: sets `$GLOBALS['batcache']['max_age']` when Batcache's global exists.
 */
class Batcache {

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'init', [ self::class, 'set_max_age' ] );
	}

	/**
	 * Sets the Batcache max_age, when Batcache is present.
	 */
	public static function set_max_age(): void {
		if ( isset( $GLOBALS['batcache'] ) && is_array( $GLOBALS['batcache'] ) ) {
			$GLOBALS['batcache']['max_age'] = Headers::max_age( Clock::now() );
		}
	}
}
