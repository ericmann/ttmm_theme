<?php
/**
 * Jetpack Subscriptions provider (default): the §6.3 shared form markup, posted to the site's
 * own `admin-post.php` handler like `custom-url` (SPEC §6.3, amended by
 * docs/spikes/P1-jetpack-form.md's "Handler (review R1-01)" finding: the widget's own POST
 * requires a per-visitor nonce, which rule 7 forbids on cacheable output, so this provider never
 * emulates that widget markup).
 *
 * @package TTM\Core\Newsletter\Provider
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter\Provider;

use TTM\Core\Newsletter\Form;

/**
 * Available only when Jetpack is actually connected (or, for tests, when
 * `jetpack/subscriptions` is registered) -- an installed-but-unconnected Jetpack falls through
 * to the rest of the provider chain (P1-06).
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
		if ( class_exists( '\Jetpack' ) && method_exists( '\Jetpack', 'is_connection_ready' ) && \Jetpack::is_connection_ready() ) {
			return true;
		}

		return \WP_Block_Type_Registry::get_instance()->is_registered( 'jetpack/subscriptions' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Posts through the site's own `admin_post_ttm_subscribe` handler, exactly like
	 * `custom-url` (`Form::handler_fields()`): no nonce (rule 7), no per-visitor markup (rule 8).
	 * `Handler::handle()` routes the actual subscribe call to Jetpack's own API when the
	 * configured provider resolves to `jetpack`.
	 *
	 * @param string $placement `poster` or `box`.
	 */
	public function render( string $placement ): string {
		$current_url = is_singular() ? (string) get_permalink() : home_url( '/' );

		return Form::render(
			admin_url( 'admin-post.php' ),
			Form::handler_fields( $current_url ),
			$placement
		);
	}
}
