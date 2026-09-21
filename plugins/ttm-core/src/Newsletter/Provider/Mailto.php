<?php
/**
 * `mailto:` fallback provider.
 *
 * @package TTM\Core\Newsletter\Provider
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter\Provider;

use TTM\Core\Config;

/**
 * A plain "Subscribe by email" mailto link, available when a fallback email is configured.
 */
class Mailto implements Provider {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'mailto';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return '' !== (string) Config::get( 'newsletter.fallback_email', '' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $placement Unused: the link looks the same everywhere.
	 */
	public function render( string $placement ): string {
		unset( $placement );

		$email = (string) Config::get( 'newsletter.fallback_email', '' );
		$href  = 'mailto:' . $email . '?subject=Subscribe';

		return sprintf(
			'<a class="btn btn-primary" href="%s">%s</a>',
			esc_url( $href ),
			esc_html__( 'Subscribe by email', 'ttm-core' )
		);
	}
}
