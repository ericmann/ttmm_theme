<?php
/**
 * The one shared newsletter form markup shape every provider (except `none`) renders through
 * (SPEC §6.3).
 *
 * @package TTM\Core\Newsletter
 */

declare( strict_types=1 );

namespace TTM\Core\Newsletter;

use TTM\Core\Config;
use TTM\Core\Support\Clock;

/**
 * `<form class="ttm-newsletter-form__form">`: a screen-reader label, the email input, each
 * provider's own hidden fields (the configured honeypot field name gets the special
 * `.ttm-hp` treatment instead of a plain hidden input), and the submit button.
 */
class Form {

	/**
	 * Per-request counter behind `ttm-nl-email-{n}` (rule 29: deterministic, not random).
	 *
	 * @var int
	 */
	private static int $counter = 0;

	/**
	 * Test-only: restart the counter at 0.
	 */
	public static function reset(): void {
		self::$counter = 0;
	}

	/**
	 * The next `{n}`.
	 *
	 * @return int
	 */
	public static function next_id(): int {
		return ++self::$counter;
	}

	/**
	 * The shared `admin-post.php` handler fields every provider that submits through
	 * `Handler::handle()` (`custom-url` and `jetpack`) posts: the same HMAC token, redirect
	 * target and honeypot field, built once so both providers' forms are identical apart from
	 * the wrapper's `data-provider` (R1-01).
	 *
	 * @param string $current_url The page the form is rendered on, used as the redirect target.
	 * @return array<string, string>
	 */
	public static function handler_fields( string $current_url ): array {
		$honeypot_field = (string) Config::get( 'newsletter.honeypot_field', 'ttm_website' );
		$token          = Handler::token( intdiv( Clock::now()->getTimestamp(), (int) Config::get( 'newsletter.token_ttl', 86400 ) ) );

		return [
			'action'        => 'ttm_subscribe',
			'ttm_token'     => $token,
			'redirect_to'   => $current_url,
			$honeypot_field => '',
		];
	}

	/**
	 * Render the shared form markup (everything but the surrounding `.ttm-newsletter-form`
	 * wrapper and the `.ttm-newsletter-form__done` paragraph, both added by the block itself).
	 *
	 * @param string                $action      Form `action` URL.
	 * @param array<string, string> $hidden      `name => value` hidden fields; the configured
	 *                                            honeypot field name renders as the special
	 *                                            `.ttm-hp` input instead of a plain hidden one.
	 * @param string                $placement   `poster` or `box` (`box` uses `.btn.btn-primary`).
	 * @param string                $submit_name Optional `name` attribute on the submit button.
	 * @return string
	 */
	public static function render( string $action, array $hidden, string $placement, string $submit_name = '' ): string {
		$n              = self::next_id();
		$btn_class      = 'box' === $placement ? 'btn btn-primary' : 'btn btn-ghost';
		$honeypot_field = (string) Config::get( 'newsletter.honeypot_field', 'ttm_website' );

		ob_start();
		?>
		<form class="ttm-newsletter-form__form" method="post" action="<?php echo esc_url( $action ); ?>" novalidate>
			<label class="screen-reader-text" for="ttm-nl-email-<?php echo (int) $n; ?>"><?php esc_html_e( 'Email address', 'ttm-core' ); ?></label>
			<input class="input" type="email" name="email" id="ttm-nl-email-<?php echo (int) $n; ?>" placeholder="<?php esc_attr_e( 'you@example.com', 'ttm-core' ); ?>" autocomplete="email" required>
			<?php foreach ( $hidden as $name => $value ) : ?>
				<?php if ( $name === $honeypot_field ) : ?>
					<input class="ttm-hp" type="text" name="<?php echo esc_attr( $name ); ?>" value="" tabindex="-1" autocomplete="off" aria-hidden="true">
				<?php else : ?>
					<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>
			<button class="<?php echo esc_attr( $btn_class ); ?>" type="submit"<?php echo '' !== $submit_name ? ' name="' . esc_attr( $submit_name ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_attr()'d above. ?>><?php esc_html_e( 'Subscribe', 'ttm-core' ); ?></button>
		</form>
		<?php
		return (string) ob_get_clean();
	}
}
