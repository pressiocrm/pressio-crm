<?php
/**
 * Uninstall script.
 *
 * WordPress calls this file when the user deletes the plugin from the
 * Plugins → Delete action. It does NOT run on deactivation.
 *
 * What we remove:
 *  1. Transients created by the plugin (cached data, no business value).
 *
 * What we intentionally keep:
 *  - All CRM database tables and their data (contacts, deals, tasks, etc.)
 *  - All plugin options (pressio_crm_*)
 *
 * Uninstall in WordPress is a common action used for troubleshooting
 * (uninstall → reinstall). Deleting a user's contacts, deals, and pipeline
 * data on uninstall would cause irreversible data loss. Data removal is
 * available via Settings → Data → Export / Delete All Data.
 *
 * Multisite: runs for each site in the network.
 */

// WordPress sets this constant before calling uninstall.php.
// Bail if called directly (without going through WP uninstall flow).
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

function pressio_crm_uninstall_site(): void {
	delete_transient( 'pressio_crm_flush_rewrite_rules' );
	delete_transient( 'pressio_crm_dashboard_stats' );
	delete_transient( 'pressio_crm_dashboard_funnel' );
	delete_transient( 'pressio_crm_dashboard_activity' );

	// Remove custom capabilities from all roles.
	$caps = [
		'pressio_crm_manage_contacts',
		'pressio_crm_manage_settings',
		'pressio_crm_export_data',
		'pressio_crm_delete_contacts',
	];

	global $wp_roles;
	if ( ! isset( $wp_roles ) ) {
		$wp_roles = new WP_Roles();
	}
	foreach ( $wp_roles->roles as $role_name => $role_data ) {
		$role = get_role( $role_name );
		if ( $role ) {
			foreach ( $caps as $cap ) {
				$role->remove_cap( $cap );
			}
		}
	}
}

// Run uninstall — multisite-safe.
if ( is_multisite() ) {
	$pressio_crm_sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );

	foreach ( $pressio_crm_sites as $blog_id ) {
		switch_to_blog( $blog_id );
		pressio_crm_uninstall_site();
		restore_current_blog();
	}
} else {
	pressio_crm_uninstall_site();
}
