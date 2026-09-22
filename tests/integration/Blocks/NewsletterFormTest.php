<?php
/**
 * Integration tests for the ttm/newsletter-form block.
 *
 * @package TTM\Tests\Integration\Blocks
 */

declare( strict_types=1 );

use TTM\Core\Config;

class NewsletterFormTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		$_GET = [];
		update_option( 'ttm_settings', [] );
		Config::reset();
		\TTM\Core\Newsletter\Form::reset();
		if ( WP_Block_Type_Registry::get_instance()->is_registered( 'jetpack/subscriptions' ) ) {
			WP_Block_Type_Registry::get_instance()->unregister( 'jetpack/subscriptions' );
		}
		parent::tear_down();
	}

	private function configure_custom_url_dev_accept(): void {
		update_option(
			'ttm_settings',
			[
				'newsletter' => [
					'provider' => 'custom-url',
					'endpoint' => '',
				],
			]
		);
		Config::reset();
	}

	private function render( array $attributes = [] ): string {
		$json = empty( $attributes ) ? '' : ' ' . wp_json_encode( $attributes );

		return (string) do_blocks( '<!-- wp:ttm/newsletter-form' . $json . ' /-->' );
	}

	public function test_jetpack_provider_renders_shared_form_posting_to_admin_post(): void {
		register_block_type( 'jetpack/subscriptions', [] );

		$html = $this->render();

		$this->assertStringContainsString( 'data-provider="jetpack"', $html );
		$this->assertStringContainsString( 'class="ttm-newsletter-form__form"', $html );
		$this->assertMatchesRegularExpression( '#<form[^>]+action="[^"]*admin-post\.php"#', $html );
		$this->assertStringContainsString( 'name="action" value="ttm_subscribe"', $html );
		$this->assertMatchesRegularExpression( '/name="ttm_token" value="[^"]+"/', $html );
		$this->assertMatchesRegularExpression( '/name="redirect_to" value="[^"]*"/', $html );
		$this->assertStringContainsString( 'class="ttm-hp"', $html );
		$this->assertSame( 1, preg_match_all( '/id="ttm-nl-email-\d+"/', $html ), 'Exactly one Form::next_id() per render.' );
		$this->assertStringNotContainsString( '_wpnonce', $html );
		$this->assertStringNotContainsString( 'jetpack_subscriptions_widget', $html );
		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	public function test_jetpack_unavailable_falls_through_to_custom_url_dev_accept(): void {
		// jetpack/subscriptions is not registered, and Jetpack the class doesn't exist here:
		// available() is false, so the chain falls through past jetpack to custom-url's
		// dev-accept path (P1-06), not straight to mailto/none.
		$html = $this->render();

		$this->assertStringContainsString( 'data-provider="custom-url"', $html );
		$this->assertStringContainsString( '<form', $html );
	}

	public function test_mailto_provider_renders_mailto_link(): void {
		update_option(
			'ttm_settings',
			[
				'newsletter' => [
					'provider'       => 'mailto',
					'fallback_email' => 'editor@example.com',
				],
			]
		);
		Config::reset();

		$html = $this->render();

		$this->assertStringContainsString( 'href="mailto:editor@example.com?subject=Subscribe"', $html );
		$this->assertStringContainsString( 'data-provider="mailto"', $html );
	}

	public function test_f26_none_renders_statement_only_no_form(): void {
		// Neither jetpack/subscriptions is registered nor a fallback email configured, and
		// dev_accept is turned off here (P1-06: otherwise the default empty endpoint would
		// make custom-url available and the chain would resolve there instead of "none").
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['newsletter.dev_accept'] = false;
				return $config;
			}
		);
		Config::reset();

		$html = $this->render();

		$this->assertStringContainsString( 'data-provider="none"', $html );
		$this->assertStringContainsString( 'The weekly issue lands on Sundays.', $html );
		$this->assertStringNotContainsString( '<form', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}

	public function test_subscribed_query_sets_data_state_without_reflecting_input(): void {
		$_GET['subscribed'] = '<script>alert(1)</script>';

		$html = $this->render();

		$this->assertStringContainsString( 'data-state="subscribed"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
	}

	public function test_output_contains_no_nonce_field(): void {
		$html = $this->render();

		$this->assertStringNotContainsString( 'wpnonce', $html );
		$this->assertStringNotContainsString( 'wp_nonce', $html );
	}

	public function test_custom_url_renders_the_shared_form_markup(): void {
		$this->configure_custom_url_dev_accept();

		$html = $this->render();

		$this->assertStringContainsString( 'data-provider="custom-url"', $html );
		$this->assertStringContainsString( 'class="screen-reader-text"', $html );
		$this->assertStringContainsString( 'for="ttm-nl-email-1"', $html );
		$this->assertStringContainsString( 'id="ttm-nl-email-1"', $html );
		$this->assertStringContainsString( 'type="email"', $html );
		$this->assertStringContainsString( 'name="email"', $html );
		$this->assertStringContainsString( 'placeholder="you@example.com"', $html );
		$this->assertStringContainsString( 'autocomplete="email"', $html );
		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'class="btn btn-ghost"', $html );
		$this->assertStringContainsString( 'Check your inbox.', $html );
		$this->assertStringNotContainsString( 'mailto:', $html );
	}

	public function test_subscribe_success_query_sets_subscribed_state(): void {
		$_GET['subscribe'] = 'success';

		$html = $this->render();

		$this->assertStringContainsString( 'data-state="subscribed"', $html );
	}

	public function test_ids_are_deterministic_per_request(): void {
		$this->configure_custom_url_dev_accept();

		$first  = $this->render();
		$second = $this->render();

		$this->assertStringContainsString( 'id="ttm-nl-email-1"', $first );
		$this->assertStringContainsString( 'id="ttm-nl-email-2"', $second );

		\TTM\Core\Newsletter\Form::reset();

		$third = $this->render();
		$this->assertStringContainsString( 'id="ttm-nl-email-1"', $third );
	}
}
