<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestSettings extends Controller {

	protected $rest_base = 'settings';

	private const DEFAULTS = [
		// General
		'currency'              => 'USD',
		'date_format'           => 'd/m/Y',
		'company_name'          => '',
		'cf7_enabled'           => true,
		// Email
		'email_from_name'       => '',
		'email_from_email'      => '',
		'email_reply_to'        => '',
		'email_header_type'     => 'none',   // 'none' | 'logo' | 'custom'
		'email_header_logo_url' => '',
		'email_header_logo_id'  => 0,
		'email_header_custom'   => '',
		'email_accent_color'    => '#2271b1',
		'email_footer'          => '',
	];

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'require_settings_cap' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'require_settings_cap' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/install-fluent-smtp',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'install_fluent_smtp' ],
				'permission_callback' => [ $this, 'require_settings_cap' ],
			]
		);
	}

	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$saved    = get_option( 'pressio_crm_settings', [] );
		$settings = array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : [] );

		$settings['fluentsmtp_info'] = $this->get_fluent_smtp_info();

		return $this->ok( $settings );
	}

	public function install_fluent_smtp( WP_REST_Request $request ) {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return new \WP_Error(
				'pressio_crm_forbidden',
				__( 'You do not have permission to install plugins.', 'pressio-crm' ),
				[ 'status' => 403 ]
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		WP_Filesystem();

		// If already installed, just activate and return.
		$installed_plugins = get_plugins();
		if ( isset( $installed_plugins['fluent-smtp/fluent-smtp.php'] ) ) {
			if ( ! is_plugin_active( 'fluent-smtp/fluent-smtp.php' ) ) {
				activate_plugin( 'fluent-smtp/fluent-smtp.php' );
			}
			return $this->ok( [
				'installed'  => true,
				'config_url' => admin_url( 'options-general.php?page=fluent-mail#/connections' ),
				'message'    => __( 'FluentSMTP has been activated.', 'pressio-crm' ),
			] );
		}

		$api = plugins_api( 'plugin_information', [
			'slug'   => 'fluent-smtp',
			'fields' => [
				'short_description' => false,
				'sections'          => false,
				'requires'          => false,
				'ratings'           => false,
				'downloaded'        => false,
				'last_updated'      => false,
				'added'             => false,
				'tags'              => false,
				'homepage'          => false,
				'donate_link'       => false,
				'author_profile'    => false,
				'author'            => false,
			],
		] );

		if ( is_wp_error( $api ) ) {
			return $this->bad_request( 'pressio_crm_install_failed', $api->get_error_message() );
		}

		ob_start();

		try {
			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \WP_Upgrader( $skin );

			$download = $upgrader->download_package( $api->download_link );
			if ( is_wp_error( $download ) ) {
				throw new \RuntimeException( $download->get_error_message() );
			}

			$working_dir = $upgrader->unpack_package( $download, true );
			if ( is_wp_error( $working_dir ) ) {
				throw new \RuntimeException( $working_dir->get_error_message() );
			}

			$result = $upgrader->install_package( [
				'source'                      => $working_dir,
				'destination'                 => WP_PLUGIN_DIR,
				'clear_destination'           => false,
				'abort_if_destination_exists' => false,
				'clear_working'               => true,
				'hook_extra'                  => [ 'type' => 'plugin', 'action' => 'install' ],
			] );
			if ( is_wp_error( $result ) ) {
				throw new \RuntimeException( $result->get_error_message() );
			}
		} catch ( \RuntimeException $e ) {
			ob_end_clean();
			return $this->bad_request( 'pressio_crm_install_failed', $e->getMessage() );
		}

		ob_end_clean();

		wp_clean_plugins_cache();
		activate_plugin( 'fluent-smtp/fluent-smtp.php' );

		return $this->ok( [
			'installed'  => true,
			'config_url' => admin_url( 'options-general.php?page=fluent-mail#/connections' ),
			'message'    => __( 'FluentSMTP has been installed and activated.', 'pressio-crm' ),
		] );
	}

	private function get_fluent_smtp_info(): array {
		if ( ! defined( 'FLUENTMAIL' ) ) {
			return [
				'installed'  => false,
				'configured' => false,
			];
		}

		$smtp_settings = get_option( 'fluentmail-settings', [] );
		$configured    = ! empty( $smtp_settings['connections'] );

		return [
			'installed'        => true,
			'configured'       => $configured,
			'config_url'       => admin_url( 'options-general.php?page=fluent-mail#/connections' ),
			'verified_senders' => $configured ? array_keys( $smtp_settings['mappings'] ?? [] ) : [],
		];
	}

	public function update_settings( WP_REST_Request $request ) {
		$raw = $request->get_params();

		if ( isset( $raw['currency'] ) ) {
			$currency = strtoupper( sanitize_text_field( wp_unslash( $raw['currency'] ) ) );
			if ( strlen( $currency ) !== 3 || ! ctype_alpha( $currency ) ) {
				return $this->bad_request( 'invalid_currency', __( 'Currency must be a 3-letter ISO 4217 code.', 'pressio-crm' ) );
			}
		}

		if ( isset( $raw['date_format'] ) ) {
			$fmt = sanitize_text_field( wp_unslash( $raw['date_format'] ) );
			if ( empty( $fmt ) || strlen( $fmt ) > 20 ) {
				return $this->bad_request( 'invalid_date_format', __( 'Invalid date format.', 'pressio-crm' ) );
			}
		}

		$saved     = get_option( 'pressio_crm_settings', [] );
		$current   = array_merge( self::DEFAULTS, is_array( $saved ) ? $saved : [] );
		$sanitised = $this->sanitise_settings( $request, $current );

		update_option( 'pressio_crm_settings', $sanitised );

		$sanitised['fluentsmtp_info'] = $this->get_fluent_smtp_info();

		return $this->ok( $sanitised );
	}

	private function sanitise_settings( WP_REST_Request $request, array $current ): array {
		$out = $current;
		$raw = $request->get_params();

		// ── General ───────────────────────────────────────────────────────────
		if ( isset( $raw['currency'] ) ) {
			$out['currency'] = strtoupper( sanitize_text_field( wp_unslash( $raw['currency'] ) ) );
		}

		if ( isset( $raw['date_format'] ) ) {
			$out['date_format'] = sanitize_text_field( wp_unslash( $raw['date_format'] ) );
		}

		$company_name = $request->get_param( 'company_name' );
		if ( null !== $company_name ) {
			$out['company_name'] = sanitize_text_field( wp_unslash( $company_name ) );
		}

		$cf7_enabled = $request->get_param( 'cf7_enabled' );
		if ( null !== $cf7_enabled ) {
			$out['cf7_enabled'] = (bool) $cf7_enabled;
		}

		// ── Email ─────────────────────────────────────────────────────────────
		foreach ( [ 'email_from_name' ] as $field ) {
			$val = $request->get_param( $field );
			if ( null !== $val ) {
				$out[ $field ] = sanitize_text_field( wp_unslash( $val ) );
			}
		}

		foreach ( [ 'email_from_email', 'email_reply_to' ] as $field ) {
			$val = $request->get_param( $field );
			if ( null !== $val ) {
				$out[ $field ] = sanitize_email( wp_unslash( $val ) );
			}
		}

		$header_type = $request->get_param( 'email_header_type' );
		if ( null !== $header_type ) {
			$allowed             = [ 'none', 'logo', 'custom' ];
			$out['email_header_type'] = in_array( $header_type, $allowed, true ) ? $header_type : 'none';
		}

		$logo_url = $request->get_param( 'email_header_logo_url' );
		if ( null !== $logo_url ) {
			$out['email_header_logo_url'] = esc_url_raw( wp_unslash( $logo_url ) );
		}

		$logo_id = $request->get_param( 'email_header_logo_id' );
		if ( null !== $logo_id ) {
			$out['email_header_logo_id'] = absint( $logo_id );
		}

		$header_custom = $request->get_param( 'email_header_custom' );
		if ( null !== $header_custom ) {
			$out['email_header_custom'] = wp_kses_post( wp_unslash( $header_custom ) );
		}

		$accent = $request->get_param( 'email_accent_color' );
		if ( null !== $accent ) {
			$out['email_accent_color'] = $this->sanitise_hex_color( sanitize_text_field( wp_unslash( $accent ) ) );
		}

		$footer = $request->get_param( 'email_footer' );
		if ( null !== $footer ) {
			$out['email_footer'] = wp_kses_post( wp_unslash( $footer ) );
		}

		return $out;
	}

	private function sanitise_hex_color( string $color ): string {
		return preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ? $color : '#2271b1';
	}
}
