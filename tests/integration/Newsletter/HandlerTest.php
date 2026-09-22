<?php
/**
 * Integration tests for the custom-url newsletter Handler + Provider\CustomUrl.
 *
 * @package TTM\Tests\Integration\Newsletter
 */

declare( strict_types=1 );

use TTM\Core\Config;
use TTM\Core\Newsletter\Handler;

class HandlerTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_filters( 'pre_http_request' );
		remove_all_filters( 'ttm_config' );
		remove_all_actions( 'ttm_newsletter_subscribed' );
		update_option( 'ttm_settings', [] );
		Config::reset();
		Handler::set_forwarder( null );
		parent::tear_down();
	}

	/**
	 * wp_http_validate_url() does a real gethostbyname() lookup for any host that doesn't match
	 * the site's own home URL host, which fails in this sandboxed test environment for a fake
	 * external domain. Using the site's own host with a forced https scheme makes validate_url's
	 * $same_host branch skip the DNS/IP-range check entirely.
	 *
	 * @return string
	 */
	private function same_host_https_endpoint(): string {
		return preg_replace( '#^http://#', 'https://', home_url( '/subscribe' ) );
	}

	private function configure( string $endpoint, string $api_key = 'secret-key' ): void {
		Config::reset();
		add_filter(
			'ttm_config',
			static function ( array $config ) use ( $endpoint, $api_key ): array {
				$config['newsletter.endpoint'] = $endpoint;
				$config['newsletter.api_key']  = $api_key;
				return $config;
			}
		);
	}

	private function mock_http( array &$captured ): void {
		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( &$captured ) {
				$captured[] = [
					'url'  => $url,
					'args' => $args,
				];
				return [
					'headers'  => [],
					'body'     => '{"ok":true}',
					'response' => [
						'code'    => 200,
						'message' => 'OK',
					],
					'cookies'  => [],
					'filename' => null,
				];
			},
			10,
			3
		);
	}

	public function test_valid_submission_forwards_to_endpoint_with_bearer_and_fires_hashed_action(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$endpoint = $this->same_host_https_endpoint();
		$this->configure( $endpoint );

		$requests = [];
		$this->mock_http( $requests );

		$fired = null;
		add_action(
			'ttm_newsletter_subscribed',
			static function ( string $hash, string $provider ) use ( &$fired ): void {
				$fired = [
					'hash'     => $hash,
					'provider' => $provider,
				];
			},
			10,
			2
		);

		$now    = new DateTimeImmutable( '2026-09-20 12:00:00', wp_timezone() );
		$window = intdiv( $now->getTimestamp(), (int) Config::get( 'newsletter.token_ttl', 86400 ) );

		Handler::handle(
			[
				'email'     => 'reader@example.com',
				'ttm_token' => Handler::token( $window ),
			],
			'10.0.0.1',
			$now
		);

		$this->assertCount( 1, $requests );
		$this->assertSame( $endpoint, $requests[0]['url'] );
		$this->assertSame( 'Bearer secret-key', $requests[0]['args']['headers']['Authorization'] );

		$body = json_decode( $requests[0]['args']['body'], true );
		$this->assertSame( 'reader@example.com', $body['email'] );

		$this->assertNotNull( $fired );
		$this->assertSame( hash( 'sha256', 'reader@example.com' ), $fired['hash'] );
		$this->assertSame( 'custom-url', $fired['provider'] );
	}

	public function test_form_markup_has_token_honeypot_and_no_nonce(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->configure( $this->same_host_https_endpoint() );
		update_option(
			'ttm_settings',
			[ 'newsletter' => [ 'provider' => 'custom-url' ] ]
		);
		Config::reset();

		$html = (string) do_blocks( '<!-- wp:ttm/newsletter-form /-->' );

		$this->assertStringContainsString( 'data-provider="custom-url"', $html );
		$this->assertStringContainsString( 'class="ttm-newsletter-form__form"', $html );
		$this->assertStringContainsString( 'id="ttm-nl-email-', $html );
		$this->assertStringContainsString( 'name="ttm_token"', $html );
		$this->assertStringContainsString( 'class="ttm-hp"', $html );
		$this->assertStringContainsString( 'tabindex="-1"', $html );
		$this->assertStringContainsString( 'name="redirect_to"', $html );
		$this->assertStringNotContainsString( 'wpnonce', $html );
		$this->assertStringNotContainsString( 'wp_nonce', $html );
	}

	public function test_endpoint_on_http_is_never_called(): void {
		$this->set_now( '2026-09-20 12:00:00' );
		$this->configure( 'http://insecure.example.com/subscribe' );

		$requests = [];
		$this->mock_http( $requests );

		$now    = new DateTimeImmutable( '2026-09-20 12:00:00', wp_timezone() );
		$window = intdiv( $now->getTimestamp(), (int) Config::get( 'newsletter.token_ttl', 86400 ) );

		Handler::handle(
			[
				'email'     => 'reader@example.com',
				'ttm_token' => Handler::token( $window ),
			],
			'10.0.0.2',
			$now
		);

		$this->assertEmpty( $requests );
	}
}
