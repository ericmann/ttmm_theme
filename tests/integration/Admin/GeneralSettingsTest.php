<?php
/**
 * Integration tests for TTM\Core\Admin\Page + General.
 *
 * @package TTM\Tests\Integration\Admin
 */

declare( strict_types=1 );

use TTM\Core\Admin\Page;
use TTM\Core\Config;

class GeneralSettingsTest extends TTM_IntegrationTestCase {

	public function tear_down(): void {
		$_POST    = [];
		$_REQUEST = [];
		parent::tear_down();
	}

	public function test_menu_page_is_registered_under_settings(): void {
		global $submenu;

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		Page::add_menu();

		$slugs = array_column( $submenu['options-general.php'] ?? [], 2 );
		$this->assertContains( 'ttm-settings', $slugs );
	}

	public function test_save_without_nonce_dies(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST = [ 'tab' => 'general' ];

		$this->expectException( WPDieException::class );
		Page::handle_save();
	}

	public function test_save_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$_POST = [ 'tab' => 'general' ];

		$this->expectException( WPDieException::class );
		Page::handle_save();
	}

	public function test_save_stores_nested_settings_and_config_overlays_them(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST = [
			'tab'              => 'general',
			'_wpnonce'         => wp_create_nonce( 'ttm_settings' ),
			'lead_sticky_days' => '45',
			'lead_stale_days'  => '20',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test fixture mirrors $_POST into $_REQUEST for check_admin_referer().

		Page::handle_save();

		$settings = get_option( 'ttm_settings' );
		$this->assertSame( 45, $settings['lead']['sticky_days'] );
		$this->assertSame( 20, $settings['lead']['stale_days'] );

		Config::reset();
		$this->assertSame( 45, Config::get( 'lead.sticky_days' ) );
	}

	public function test_empty_number_removes_override(): void {
		update_option( 'ttm_settings', [ 'lead' => [ 'sticky_days' => 99 ] ] );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$_POST = [
			'tab'              => 'general',
			'_wpnonce'         => wp_create_nonce( 'ttm_settings' ),
			'lead_sticky_days' => '',
			'lead_stale_days'  => '',
		];
		$_REQUEST = $_POST; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- test fixture mirrors $_POST into $_REQUEST for check_admin_referer().

		Page::handle_save();

		$settings = get_option( 'ttm_settings' );
		$this->assertArrayNotHasKey( 'lead', $settings );

		Config::reset();
		$this->assertSame( 30, Config::get( 'lead.sticky_days' ) );
	}
}
