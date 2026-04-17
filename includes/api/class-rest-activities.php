<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\ActivityModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestActivities extends Controller {

	protected $rest_base = 'activities';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$contact_id = $this->get_int_param( $request, 'contact_id' );
		$deal_id    = $this->get_int_param( $request, 'deal_id' );
		$limit      = min( 100, max( 1, absint( $request->get_param( 'limit' ) ?: 20 ) ) );

		if ( $contact_id ) {
			$result = ActivityModel::get_for_contact( $contact_id, $limit, 1 );
			return $this->paginated_response( $result, [ $this, 'format_activity' ] );
		}

		if ( $deal_id ) {
			$result = ActivityModel::get_for_deal( $deal_id, $limit, 1 );
			return $this->paginated_response( $result, [ $this, 'format_activity' ] );
		}

		$activities = ActivityModel::get_recent( $limit );

		return $this->ok( array_map( [ $this, 'format_activity' ], $activities ) );
	}

	public function format_activity( object $activity ): array {
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
