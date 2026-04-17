<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\PipelineModel;
use PressioCRM\Models\ActivityModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestDashboard extends Controller {

	protected $rest_base = 'dashboard';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stats',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'stats' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/funnel',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'funnel' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/activity',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'activity' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);
	}

	public function stats( WP_REST_Request $request ): WP_REST_Response {
		$cached = get_transient( 'pressio_crm_dashboard_stats' );
		if ( false !== $cached ) {
			return $this->ok( $cached );
		}

		global $wpdb;

		$contacts_table = $wpdb->prefix . 'pcrm_contacts';
		$deals_table    = $wpdb->prefix . 'pcrm_deals';
		$tasks_table    = $wpdb->prefix . 'pcrm_tasks';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, name is internally derived.
		$total_contacts = (int) $wpdb->get_var( "SELECT COUNT(id) FROM `{$contacts_table}` WHERE deleted_at IS NULL" );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table, name is internally derived.
		$open_deals_row = $wpdb->get_row(
			"SELECT COUNT(id) AS cnt, COALESCE(SUM(value), 0) AS total
			 FROM `{$deals_table}`
			 WHERE status = 'open' AND deleted_at IS NULL"
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$open_deals     = (int) ( $open_deals_row->cnt ?? 0 );
		$pipeline_value = (float) ( $open_deals_row->total ?? 0 );

		$today = gmdate( 'Y-m-d' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table with transient cache above.
		$tasks_due_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM `{$tasks_table}`
				 WHERE status != 'completed'
				 AND deleted_at IS NULL
				 AND DATE(due_date) = %s",
				$today
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$month_start = gmdate( 'Y-m-01 00:00:00' );
		$month_end   = gmdate( 'Y-m-t 23:59:59' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom table with transient cache above.
		$won_row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(id) AS cnt, SUM(value) AS total
				 FROM `{$deals_table}`
				 WHERE status = 'won'
				 AND deleted_at IS NULL
				 AND closed_at BETWEEN %s AND %s",
				$month_start,
				$month_end
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$stats = [
			'total_contacts'    => $total_contacts,
			'open_deals'        => $open_deals,
			'open_deals_value'  => round( $pipeline_value, 2 ),
			'tasks_due_today'   => $tasks_due_today,
			'won_this_month'    => (int) ( $won_row->cnt ?? 0 ),
			'won_this_month_value' => round( (float) ( $won_row->total ?? 0 ), 2 ),
		];

		set_transient( 'pressio_crm_dashboard_stats', $stats, 300 );

		return $this->ok( $stats );
	}

	public function funnel( WP_REST_Request $request ): WP_REST_Response {
		$cached = get_transient( 'pressio_crm_dashboard_funnel' );
		if ( false !== $cached ) {
			return $this->ok( $cached );
		}

		global $wpdb;

		$pipeline = PipelineModel::get_default();

		if ( null === $pipeline ) {
			return $this->ok( [] );
		}

		$pipeline_id  = (int) $pipeline->id;
		$stages_table = $wpdb->prefix . 'pcrm_pipeline_stages';
		$deals_table  = $wpdb->prefix . 'pcrm_deals';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom tables with transient cache above.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					s.id AS stage_id,
					s.name AS stage_name,
					COUNT(d.id) AS deal_count,
					COALESCE(SUM(d.value), 0) AS total_value
				 FROM `{$stages_table}` s
				 LEFT JOIN `{$deals_table}` d
				     ON d.stage_id = s.id
				     AND d.deleted_at IS NULL
				     AND d.status = 'open'
				 WHERE s.pipeline_id = %d
				 GROUP BY s.id, s.name
				 ORDER BY s.position ASC",
				$pipeline_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $rows ) {
			set_transient( 'pressio_crm_dashboard_funnel', [], 300 );
			return $this->ok( [] );
		}

		$data = array_map(
			static function ( $row ) {
				return [
					'stage_id'    => (int) $row->stage_id,
					'stage_name'  => (string) $row->stage_name,
					'deal_count'  => (int) $row->deal_count,
					'total_value' => round( (float) $row->total_value, 2 ),
				];
			},
			$rows
		);

		set_transient( 'pressio_crm_dashboard_funnel', $data, 300 );

		return $this->ok( $data );
	}

	public function activity( WP_REST_Request $request ): WP_REST_Response {
		$cached = get_transient( 'pressio_crm_dashboard_activity' );
		if ( false !== $cached ) {
			return $this->ok( $cached );
		}

		$activities = ActivityModel::get_recent( 20 );
		$data       = array_map( [ $this, 'format_activity' ], $activities );

		set_transient( 'pressio_crm_dashboard_activity', $data, 60 );

		return $this->ok( $data );
	}

	private function format_activity( object $activity ): array {
		$meta = $activity->meta;
		if ( is_string( $meta ) ) {
			$meta = json_decode( $meta, true ) ?: [];
		}
		if ( ! is_array( $meta ) ) {
			$meta = [];
		}

		return [
			'id'          => (int) $activity->id,
			'contact_id'  => (int) $activity->contact_id,
			'deal_id'     => (int) $activity->deal_id,
			'user_id'     => (int) $activity->user_id,
			'type'        => (string) $activity->type,
			'title'       => (string) $activity->title,
			'description' => (string) $activity->description,
			'meta'        => $meta,
			'created_at'  => (string) $activity->created_at,
		];
	}
}
