<?php
/**
 * Resolves the configured newsletter provider through the fallback chain (SPEC §6.1).
 *
 * @package TTM\Core\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter;

use TTM\Core\Config;
use TTM\Core\Newsletter\Provider\CustomUrl;
use TTM\Core\Newsletter\Provider\Jetpack;
use TTM\Core\Newsletter\Provider\Mailto;
use TTM\Core\Newsletter\Provider\None;
use TTM\Core\Newsletter\Provider\Provider;

/**
 * `resolve()` is pure over an injected registry so the fallback chain is unit-testable without
 * WordPress; `current()` wires it to the real config and providers.
 */
class Providers {

	/**
	 * The real provider registry.
	 *
	 * @return array<string, Provider>
	 */
	public static function default_registry(): array {
		return [
			'jetpack'    => new Jetpack(),
			'custom-url' => new CustomUrl(),
			'mailto'     => new Mailto(),
			'none'       => new None(),
		];
	}

	/**
	 * The provider for the currently configured `newsletter.provider`.
	 *
	 * @return Provider
	 */
	public static function current(): Provider {
		return self::resolve(
			(string) Config::get( 'newsletter.provider', 'jetpack' ),
			self::default_registry(),
			CustomUrl::dev_accept_applies()
		);
	}

	/**
	 * The configured provider if available, else `custom-url` if `$dev_accept` and available,
	 * else `mailto` if available, else `none`.
	 *
	 * @param string                  $configured Configured `newsletter.provider` slug.
	 * @param array<string, Provider> $registry   Slug => Provider map.
	 * @param bool                    $dev_accept Whether `custom-url`'s dev-accept applies.
	 * @return Provider
	 */
	public static function resolve( string $configured, array $registry, bool $dev_accept = false ): Provider {
		if ( isset( $registry[ $configured ] ) && $registry[ $configured ]->available() ) {
			return $registry[ $configured ];
		}

		if ( $dev_accept && isset( $registry['custom-url'] ) && $registry['custom-url']->available() ) {
			return $registry['custom-url'];
		}

		if ( isset( $registry['mailto'] ) && $registry['mailto']->available() ) {
			return $registry['mailto'];
		}

		return $registry['none'];
	}
}
