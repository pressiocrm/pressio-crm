<?php
/**
 * Plugin Name:       Pressio CRM
 * Plugin URI:        https://wordpress.org/plugins/pressio-crm/
 * Description:       A lightweight, pipeline-first CRM for small businesses. Manage contacts, track deals, and stay on top of follow-ups — all inside WordPress.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Tested up to:      6.9
 * Stable tag:        1.0.0
 * Requires PHP:      7.4
 * Author:            Pressio CRM
 * Author URI:        https://pressiocrm.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pressio-crm
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants.
define( 'PRESSIO_CRM_VERSION',  '1.0.0' );
define( 'PRESSIO_CRM_PATH',     plugin_dir_path( __FILE__ ) );
define( 'PRESSIO_CRM_URL',      plugin_dir_url( __FILE__ ) );
define( 'PRESSIO_CRM_BASENAME', plugin_basename( __FILE__ ) );
define( 'PRESSIO_CRM_MIN_PHP',  '7.4' );
define( 'PRESSIO_CRM_MIN_WP',   '6.2' );

// Guard: minimum PHP version check before loading anything.
if ( version_compare( PHP_VERSION, PRESSIO_CRM_MIN_PHP, '<' ) ) {
	add_action( 'admin_notices', function () {
		printf(
			'<div class="notice notice-error"><p>%s</p></div>',
			sprintf(
				/* translators: 1: required PHP version, 2: current PHP version */
				esc_html__( 'Pressio CRM requires PHP %1$s or higher. You are running PHP %2$s. Please upgrade PHP or contact your host.', 'pressio-crm' ),
				esc_html( PRESSIO_CRM_MIN_PHP ),
				esc_html( PHP_VERSION )
			)
		);
	} );
	return;
}

// Namespace-based autoloader.
spl_autoload_register( function ( $class ) {
	$prefix = 'PressioCRM\\';
	if ( strpos( $class, $prefix ) !== 0 ) {
		return;
	}

	$relative   = substr( $class, strlen( $prefix ) );
	$parts      = explode( '\\', $relative );
	$class_name = array_pop( $parts );
	$namespace  = implode( '\\', $parts );

	$dir_map = [
		''                   => 'includes',
		'Framework'          => 'includes/framework',
		'Database'           => 'includes/database',
		'Database\\Migrations' => 'includes/database/migrations',
		'Models'             => 'includes/models',
		'API'                => 'includes/api',
		'Modules'            => 'includes/modules',
		'Mail'               => 'includes/mail',
		'Integrations'       => 'includes/integrations',
	];

	if ( ! array_key_exists( $namespace, $dir_map ) ) {
		return;
	}

	// PascalCase → kebab-case  e.g. ContactModel → contact-model
	$file_name = 'class-' . strtolower( preg_replace( '/(?<!^)[A-Z]/', '-$0', $class_name ) ) . '.php';
	$file      = PRESSIO_CRM_PATH . $dir_map[ $namespace ] . '/' . $file_name;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

use PressioCRM\Plugin;
use PressioCRM\Activator;
use PressioCRM\Deactivator;

// Activation / deactivation hooks — registered before the plugin boots.
register_activation_hook( __FILE__, [ Activator::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Deactivator::class, 'deactivate' ] );

/**
 * Global helper — returns the main plugin instance (the service container).
 *
 * Usage anywhere in the plugin:
 *   pressio_crm()->make( 'model.contact' )
 */
function pressio_crm(): Plugin {
	return Plugin::instance();
}

// Boot the plugin.
pressio_crm();
