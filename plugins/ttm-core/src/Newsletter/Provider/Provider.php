<?php
/**
 * Newsletter subscription provider contract (SPEC §6.1).
 *
 * @package TTM\Core\Newsletter\Provider
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter\Provider;

/**
 * Every provider renders escaped, cache-safe markup: no nonces, no per-visitor branching.
 */
interface Provider {

	/**
	 * The provider's `data-provider` slug.
	 *
	 * @return string
	 */
	public function slug(): string;

	/**
	 * Whether this provider can render right now.
	 *
	 * @return bool
	 */
	public function available(): bool;

	/**
	 * The provider's inner markup (already escaped).
	 *
	 * @param string $placement `poster` or `box`.
	 * @return string
	 */
	public function render( string $placement ): string;
}
