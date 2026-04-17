<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use WP_Screen;

class AdminModule extends Module {

	public function register(): void {
		// No container bindings — admin UI is purely hook-driven.
	}

	public function boot(): void {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		// current_screen fires after the screen object is set — get_current_screen()
		// is guaranteed non-null here, unlike admin_init where it can still be null.
		add_action( 'current_screen', [ $this, 'maybe_redirect_onboarding' ] );
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Pressio CRM', 'pressio-crm' ),
			__( 'Pressio CRM', 'pressio-crm' ),
			'pressio_crm_manage_contacts',
			'pressio-crm',
			[ $this, 'render_app' ],
			'dashicons-businessperson',
			30
		);

		add_submenu_page(
			'pressio-crm',
			__( 'Dashboard', 'pressio-crm' ),
			__( 'Dashboard', 'pressio-crm' ),
			'pressio_crm_manage_contacts',
			'pressio-crm',
			[ $this, 'render_app' ]
		);

		add_submenu_page(
			'pressio-crm',
			__( 'Contacts', 'pressio-crm' ),
			__( 'Contacts', 'pressio-crm' ),
			'pressio_crm_manage_contacts',
			'pressio-crm-contacts',
			[ $this, 'render_app' ]
		);

		add_submenu_page(
			'pressio-crm',
			__( 'Pipeline', 'pressio-crm' ),
			__( 'Pipeline', 'pressio-crm' ),
			'pressio_crm_manage_contacts',
			'pressio-crm-pipeline',
			[ $this, 'render_app' ]
		);

		add_submenu_page(
			'pressio-crm',
			__( 'Tasks', 'pressio-crm' ),
			__( 'Tasks', 'pressio-crm' ),
			'pressio_crm_manage_contacts',
			'pressio-crm-tasks',
			[ $this, 'render_app' ]
		);

		add_submenu_page(
			'pressio-crm',
			__( 'Settings', 'pressio-crm' ),
			__( 'Settings', 'pressio-crm' ),
			'pressio_crm_manage_settings',
			'pressio-crm-settings',
			[ $this, 'render_app' ]
		);
	}

	public function render_app(): void {
		echo '<div id="pressio-crm-root"></div>';
	}

	/**
	 * Enqueue the Vue SPA bundle only on Pressio CRM admin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		if ( ! $this->is_crm_page( $hook_suffix ) ) {
			return;
		}

		$js_path  = PRESSIO_CRM_PATH . 'build/index.js';
		$css_path = PRESSIO_CRM_PATH . 'build/index.css';

		if ( ! file_exists( $js_path ) ) {
			return;
		}

		wp_enqueue_script(
			'pressio-crm-admin',
			PRESSIO_CRM_URL . 'build/index.js',
			[ 'wp-tinymce', 'quicktags' ],
			PRESSIO_CRM_VERSION,
			true
		);

		// Vite produces an ESM bundle — WordPress must output type="module" on the tag.
		add_filter( 'script_loader_tag', [ $this, 'add_module_type' ], 10, 2 );

		// Read settings once to avoid two identical get_option() calls.
		$settings = get_option( 'pressio_crm_settings', [] );
		$settings = is_array( $settings ) ? $settings : [];

		wp_add_inline_script(
			'pressio-crm-admin',
			'window.pressioCrm = ' . wp_json_encode(
				[
					'restUrl'    => esc_url_raw( rest_url( 'pressio-crm/v1' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'userId'     => get_current_user_id(),
					'currency'   => sanitize_text_field( $settings['currency'] ?? 'USD' ),
					'dateFormat' => sanitize_text_field( $settings['date_format'] ?? 'Y-m-d' ),
					'version'    => PRESSIO_CRM_VERSION,
					'adminUrl'   => esc_url( admin_url( 'admin.php' ) ),
					'page'       => sanitize_key( wp_unslash( $_GET['page'] ?? 'pressio-crm' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				]
			),
			'before'
		);

		if ( file_exists( $css_path ) ) {
			wp_enqueue_style(
				'pressio-crm-admin',
				PRESSIO_CRM_URL . 'build/index.css',
				[],
				PRESSIO_CRM_VERSION
			);

			// Remove WP admin's .wrap margins so our full-width topnav sits flush
			// against the content column — matching the layout pattern used by
			// modern WP plugins (FluentCRM, FluentCart, etc.).
			wp_add_inline_style(
				'pressio-crm-admin',
				'#wpbody-content > .wrap { margin: 0; padding: 0; max-width: none; }'
			);
		}
	}

	/**
	 * Redirect to the onboarding flow on first activation.
	 * Runs once and self-clears the option so subsequent visits go straight to the dashboard.
	 *
	 * Hooked to current_screen so get_current_screen() is guaranteed non-null.
	 *
	 * @param WP_Screen $screen The current screen object.
	 */
	public function maybe_redirect_onboarding( WP_Screen $screen ): void {
		if ( ! get_option( 'pressio_crm_show_onboarding' ) ) {
			return;
		}

		// Only redirect when the user is landing on the top-level CRM page directly.
		if ( 'toplevel_page_pressio-crm' !== $screen->id ) {
			return;
		}

		delete_option( 'pressio_crm_show_onboarding' );

		wp_safe_redirect( admin_url( 'admin.php?page=pressio-crm#/onboarding' ) );
		exit;
	}

	/**
	 * Adds type="module" to the pressio-crm-admin script tag.
	 * Required so browsers treat the Vite ESM bundle as an ES module.
	 *
	 * @param string $tag    The full <script> HTML tag.
	 * @param string $handle Registered script handle.
	 */
	public function add_module_type( string $tag, string $handle ): string {
		if ( 'pressio-crm-admin' !== $handle ) {
			return $tag;
		}

		return str_replace( ' src=', ' type="module" src=', $tag );
	}

	/**
	 * Returns true when $hook_suffix belongs to one of our admin pages.
	 *
	 * @param string $hook_suffix Value passed by admin_enqueue_scripts.
	 */
	private function is_crm_page( string $hook_suffix ): bool {
		$crm_hooks = [
			'toplevel_page_pressio-crm',
			'pressio-crm_page_pressio-crm-contacts',
			'pressio-crm_page_pressio-crm-pipeline',
			'pressio-crm_page_pressio-crm-tasks',
			'pressio-crm_page_pressio-crm-settings',
		];

		return in_array( $hook_suffix, $crm_hooks, true );
	}
}
