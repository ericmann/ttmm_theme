<?php
/**
 * Integration tests for TTM\Core\Newsletter\Settings.
 *
 * @package TTM\Tests\Integration\Newsletter
 */

declare( strict_types=1 );

use TTM\Core\Config;
use TTM\Core\Newsletter\Settings;

class SettingsTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		remove_all_filters( 'ttm_config' );
		update_option( 'ttm_settings', [] );
		Config::reset();
		parent::tear_down();
	}

	public function test_newsletter_tab_masks_api_key(): void {
		Config::reset();
		add_filter(
			'ttm_config',
			static function ( array $config ): array {
				$config['newsletter.api_key'] = 'super-secret-value';
				return $config;
			}
		);

		ob_start();
		Settings::render();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '••••', $html );
		$this->assertStringNotContainsString( 'super-secret-value', $html );
	}

	public function test_save_stores_provider_and_endpoint(): void {
		$_POST['newsletter_provider']       = 'custom-url';
		$_POST['newsletter_endpoint']       = 'https://newsletter.example.com/subscribe';
		$_POST['newsletter_fallback_email'] = 'editor@example.com';
		$_POST['newsletter_list_id']        = 'list-1';

		Settings::save();

		$_POST = [];

		$settings = get_option( 'ttm_settings' );

		$this->assertSame( 'custom-url', $settings['newsletter']['provider'] );
		$this->assertSame( 'https://newsletter.example.com/subscribe', $settings['newsletter']['endpoint'] );
		$this->assertSame( 'editor@example.com', $settings['newsletter']['fallback_email'] );
		$this->assertSame( 'list-1', $settings['newsletter']['list_id'] );

		$this->assertSame( 'custom-url', Config::get( 'newsletter.provider' ) );
	}
}
