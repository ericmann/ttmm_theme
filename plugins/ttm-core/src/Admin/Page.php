<?php
/**
 * Settings → These Things Matter: a tab registry, one form, one save handler.
 *
 * @package TTM\Core\Admin
 */

declare( strict_types=1 );

namespace TTM\Core\Admin;

/**
 * Tabs register themselves via Page::register_tab(); Admin/ imports Config, Support only.
 */
class Page {

	/**
	 * `[slug => ['label' => string, 'render' => callable, 'save' => ?callable]]`.
	 *
	 * @var array<string, array{label:string, render:callable, save:?callable}>
	 */
	private static array $tabs = [];

	/**
	 * Hook registration.
	 */
	public static function register(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_post_ttm_save_settings', [ self::class, 'handle_save' ] );
	}

	/**
	 * Register a settings tab. Test-only reset happens via reset_tabs().
	 *
	 * @param string        $slug   Tab slug.
	 * @param string        $label  Tab label.
	 * @param callable      $render Renders the tab's fields.
	 * @param callable|null $save   Saves the tab's fields from $_POST; null if read-only.
	 */
	public static function register_tab( string $slug, string $label, callable $render, ?callable $save = null ): void {
		self::$tabs[ $slug ] = [
			'label'  => $label,
			'render' => $render,
			'save'   => $save,
		];
	}

	/**
	 * Add the Settings submenu page.
	 */
	public static function add_menu(): void {
		add_options_page(
			__( 'These Things Matter', 'ttm-core' ),
			__( 'These Things Matter', 'ttm-core' ),
			'manage_options',
			'ttm-settings',
			[ self::class, 'render_page' ]
		);
	}

	/**
	 * Render the tabbed settings page.
	 */
	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : array_key_first( self::$tabs ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selector.

		echo '<div class="wrap"><h1>' . esc_html__( 'These Things Matter', 'ttm-core' ) . '</h1><h2 class="nav-tab-wrapper">';
		foreach ( self::$tabs as $slug => $tab ) {
			printf(
				'<a class="nav-tab%s" href="%s">%s</a>',
				$slug === $current ? ' nav-tab-active' : '',
				esc_url( add_query_arg( [ 'page' => 'ttm-settings', 'tab' => $slug ], admin_url( 'options-general.php' ) ) ),
				esc_html( $tab['label'] )
			);
		}
		echo '</h2>';

		if ( isset( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only success flag.
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'ttm-core' ) . '</p></div>';
		}

		if ( isset( self::$tabs[ $current ] ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'ttm_settings' );
			echo '<input type="hidden" name="action" value="ttm_save_settings">';
			echo '<input type="hidden" name="tab" value="' . esc_attr( $current ) . '">';
			( self::$tabs[ $current ]['render'] )();
			if ( self::$tabs[ $current ]['save'] ) {
				submit_button();
			}
			echo '</form>';
		}

		echo '</div>';
	}

	/**
	 * `admin_post_ttm_save_settings`: dispatch to the active tab's save callback.
	 */
	public static function handle_save(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'ttm-core' ) );
		}
		check_admin_referer( 'ttm_settings' );

		$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( $_POST['tab'] ) ) : '';

		if ( isset( self::$tabs[ $tab ]['save'] ) && is_callable( self::$tabs[ $tab ]['save'] ) ) {
			( self::$tabs[ $tab ]['save'] )();
		}

		if ( ! headers_sent() ) {
			wp_safe_redirect( add_query_arg( [ 'page' => 'ttm-settings', 'tab' => $tab, 'updated' => 1 ], admin_url( 'options-general.php' ) ) );
		}
	}

	/**
	 * Test-only: clear the tab registry between tests.
	 */
	public static function reset_tabs(): void {
		self::$tabs = [];
	}

	/**
	 * Test-only: registered tab slugs.
	 *
	 * @return string[]
	 */
	public static function tab_slugs(): array {
		return array_keys( self::$tabs );
	}
}
