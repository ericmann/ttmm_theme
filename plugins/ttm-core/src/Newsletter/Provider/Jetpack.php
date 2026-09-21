<?php
/**
 * Jetpack Subscriptions provider (default).
 *
 * @package TTM\Core\Newsletter\Provider
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter\Provider;

/**
 * Renders `jetpack/subscriptions` when it is registered.
 */
class Jetpack implements Provider {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'jetpack';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return \WP_Block_Type_Registry::get_instance()->is_registered( 'jetpack/subscriptions' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $placement Unused: Jetpack's own block handles its own layout.
	 */
	public function render( string $placement ): string {
		unset( $placement );

		return (string) do_blocks( '<!-- wp:jetpack/subscriptions {"buttonText":"Subscribe","showSubscribersTotal":false} /-->' );
	}
}
