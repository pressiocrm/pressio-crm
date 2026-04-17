<?php
namespace PressioCRM;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Database\Migrator;

class Activator {

	/**
	 * @param bool $network_wide Whether the plugin is being activated network-wide (Multisite).
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			// Activate for every site in the network.
			$sites = get_sites( [ 'fields' => 'ids', 'number' => 0 ] );
			foreach ( $sites as $blog_id ) {
				switch_to_blog( $blog_id );
				self::run_for_site();
				restore_current_blog();
			}
		} else {
			self::run_for_site();
		}
	}

	private static function run_for_site(): void {
		self::run_migrations();
		self::seed_default_pipeline();
		self::add_capabilities();
		self::store_activation_meta();

		// Schedule a flush so that any REST routes we register are available.
		// We don't flush directly here; that would break on network activation.
		set_transient( 'pressio_crm_flush_rewrite_rules', true, 60 );
	}

	private static function run_migrations(): void {
		// Ensure the Migrator class is available (autoloader may not have run yet).
		$migrator_file = PRESSIO_CRM_PATH . 'includes/database/class-migrator.php';
		if ( ! class_exists( Migrator::class ) ) {
			require_once $migrator_file;
		}

		$migrations_dir = PRESSIO_CRM_PATH . 'includes/database/migrations/';
		$migrator       = new Migrator( $migrations_dir );
		$migrator->run();
	}

	/**
	 * Create the default pipeline and its 5 stages on first install.
	 * Does nothing if a pipeline already exists (safe to call on re-activation).
	 */
	private static function seed_default_pipeline(): void {
		global $wpdb;

		$pipeline_table = $wpdb->prefix . 'pcrm_pipelines';
		$stages_table   = $wpdb->prefix . 'pcrm_pipeline_stages';

		// Check whether any pipeline already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is a trusted internal constant
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$pipeline_table}`" );

		if ( $count > 0 ) {
			return; // Already seeded.
		}

		// Insert default pipeline.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$pipeline_table,
			[
				'name'       => __( 'Sales Pipeline', 'pressio-crm' ),
				'is_default' => 1,
				'position'   => 0,
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%d', '%d', '%s' ]
		);

		$pipeline_id = (int) $wpdb->insert_id;

		if ( ! $pipeline_id ) {
			return; // Shouldn't happen, but guard anyway.
		}

		// Default stages.
		$stages = [
			[
				'name'            => __( 'Lead', 'pressio-crm' ),
				'slug'            => 'lead',
				'type'            => 'open',
				'color'           => '#6366f1',
				'position'        => 1000.0,
				'win_probability' => 10,
			],
			[
				'name'            => __( 'Qualified', 'pressio-crm' ),
				'slug'            => 'qualified',
				'type'            => 'open',
				'color'           => '#3b82f6',
				'position'        => 2000.0,
				'win_probability' => 25,
			],
			[
				'name'            => __( 'Proposal', 'pressio-crm' ),
				'slug'            => 'proposal',
				'type'            => 'open',
				'color'           => '#f59e0b',
				'position'        => 3000.0,
				'win_probability' => 50,
			],
			[
				'name'            => __( 'Negotiation', 'pressio-crm' ),
				'slug'            => 'negotiation',
				'type'            => 'open',
				'color'           => '#f97316',
				'position'        => 4000.0,
				'win_probability' => 75,
			],
			[
				'name'            => __( 'Closed Won', 'pressio-crm' ),
				'slug'            => 'closed-won',
				'type'            => 'won',
				'color'           => '#22c55e',
				'position'        => 5000.0,
				'win_probability' => 100,
			],
		];

		foreach ( $stages as $stage ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->insert(
				$stages_table,
				array_merge( $stage, [ 'pipeline_id' => $pipeline_id ] ),
				[ '%s', '%s', '%s', '%s', '%f', '%d', '%d' ]
			);
		}
	}

	/**
	 * Add Pressio CRM capabilities to the Administrator role.
	 *
	 * Custom capabilities give site owners the option to delegate CRM access to
	 * custom roles (e.g. a Sales Manager role with only pressio_crm_manage_contacts).
	 */
	private static function add_capabilities(): void {
		$admin = get_role( 'administrator' );

		if ( ! $admin ) {
			return;
		}

		$caps = [
			'pressio_crm_manage_contacts', // Create, read, update, delete contacts / deals / tasks.
			'pressio_crm_manage_settings', // Access plugin settings page.
			'pressio_crm_export_data',     // Export contacts to CSV.
			'pressio_crm_delete_contacts', // Hard-delete contacts (higher privilege than soft-delete).
		];

		foreach ( $caps as $cap ) {
			$admin->add_cap( $cap );
		}
	}

	/**
	 * Record plugin version and first install date.
	 * pressio_crm_activated_at is set only once; version is updated on every activation.
	 */
	private static function store_activation_meta(): void {
		update_option( 'pressio_crm_version', PRESSIO_CRM_VERSION, false );

		if ( ! get_option( 'pressio_crm_activated_at' ) ) {
			add_option( 'pressio_crm_activated_at', current_time( 'mysql' ), '', false );
		}

		// Flag for the onboarding wizard — shown only on fresh install.
		if ( ! get_option( 'pressio_crm_onboarding_done' ) ) {
			update_option( 'pressio_crm_show_onboarding', true, false );
		}
	}
}
