<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\API\RestDashboard;

class DashboardModule extends Module {

	public function register(): void {
		// No new bindings — dashboard queries models already bound by other modules.
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );

		// Bust cached stats whenever contacts or deals change.
		$bust_stats = [ $this, 'bust_stats' ];
		add_action( 'pressio_crm_contact_created', $bust_stats );
		add_action( 'pressio_crm_contact_deleted',  $bust_stats );

		$bust_all = [ $this, 'bust_all' ];
		add_action( 'pressio_crm_deal_created',       $bust_all );
		add_action( 'pressio_crm_deal_stage_changed', $bust_all );
		add_action( 'pressio_crm_deal_won',            $bust_all );
		add_action( 'pressio_crm_deal_lost',           $bust_all );
		add_action( 'pressio_crm_deal_deleted',        $bust_all );

		// Bust the activity feed cache whenever a new activity is logged.
		add_action( 'pressio_crm_activity_logged', [ $this, 'bust_activity' ] );
	}

	public function bust_stats(): void {
		delete_transient( 'pressio_crm_dashboard_stats' );
	}

	public function bust_all(): void {
		delete_transient( 'pressio_crm_dashboard_stats' );
		delete_transient( 'pressio_crm_dashboard_funnel' );
	}

	public function bust_activity(): void {
		delete_transient( 'pressio_crm_dashboard_activity' );
	}

	public function register_routes(): void {
		( new RestDashboard() )->register_routes();
	}
}
