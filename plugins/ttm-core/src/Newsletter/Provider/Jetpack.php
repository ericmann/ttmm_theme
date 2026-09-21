<?php
/**
 * Jetpack Subscriptions provider (default): the §6.3 shared form markup with the widget's own
 * hidden fields (SPEC §6.3, amended by docs/spikes/P1-jetpack-form.md's Outcome B).
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
	 * Field list per the widget handler `Jetpack_Subscriptions_Widget
	 * ::render_widget_subscription_form()` (docs/spikes/P1-jetpack-form.md): `action=subscribe`,
	 * `source={current URL}`, `sub-type=widget`, `redirect_fragment=ttm-newsletter-{n}`. No
	 * nonce (rule 7).
	 *
	 * @param string $placement `poster` or `box`.
	 */
	public function render( string $placement ): string {
		$current_url = is_singular() ? (string) get_permalink() : home_url( '/' );

		return Form::render(
			$current_url,
			[
				'action'            => 'subscribe',
				'source'            => $current_url,
				'sub-type'          => 'widget',
				'redirect_fragment' => 'ttm-newsletter-' . Form::next_id(),
			],
			$placement,
			'jetpack_subscriptions_widget'
		);
	}
}
