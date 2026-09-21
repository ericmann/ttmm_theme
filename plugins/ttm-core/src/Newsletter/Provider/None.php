<?php
/**
 * No provider configured or available: a plain statement (F26).
 *
 * @package TTM\Core\Newsletter\Provider
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter\Provider;

/**
 * The guaranteed last-resort provider: always available.
 */
class None implements Provider {

	/**
	 * {@inheritDoc}
	 */
	public function slug(): string {
		return 'none';
	}

	/**
	 * {@inheritDoc}
	 */
	public function available(): bool {
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $placement Unused: the statement looks the same everywhere.
	 */
	public function render( string $placement ): string {
		unset( $placement );

		return sprintf(
			'<p class="ttm-newsletter-form__statement">%s</p>',
			esc_html__( 'The weekly issue lands on Sundays.', 'ttm-core' )
		);
	}
}
