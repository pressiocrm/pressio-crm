<?php
namespace PressioCRM;

defined( 'ABSPATH' ) || exit;

class Deactivator {

	public static function deactivate(): void {
		self::clear_scheduled_events();
		self::clear_transients();
		flush_rewrite_rules();
	}

	/**
	 * Remove any WP cron events registered by the plugin.
	 * Add more as we introduce scheduled tasks (e.g. sequence emails in Pro).
	 */
	private static function clear_scheduled_events(): void {
		$hooks = [
			'pressio_crm_daily_maintenance',
		];

		foreach ( $hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	private static function clear_transients(): void {
		delete_transient( 'pressio_crm_flush_rewrite_rules' );
		delete_transient( 'pressio_crm_dashboard_stats' );
		delete_transient( 'pressio_crm_dashboard_funnel' );
		delete_transient( 'pressio_crm_dashboard_activity' );
	}
}
