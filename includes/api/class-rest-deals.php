<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\DealModel;
use PressioCRM\Models\PipelineStageModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestDeals extends Controller {

	protected $rest_base = 'deals';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/move',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'move' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$pagination  = $this->get_pagination_params( $request );
		$pipeline_id = $this->get_int_param( $request, 'pipeline_id' );
		$stage_id    = $this->get_int_param( $request, 'stage_id' );
		$status      = $this->get_string_param( $request, 'status' );
		$contact_id  = $this->get_int_param( $request, 'contact_id' );
		$owner_id    = $this->get_int_param( $request, 'owner_id' );

		$result = DealModel::query()
			->not_deleted()
			->where_if( $pipeline_id ?: null, 'pipeline_id', '%d' )
			->where_if( $stage_id ?: null, 'stage_id', '%d' )
			->where_if( $status, 'status' )
			->where_if( $contact_id ?: null, 'contact_id', '%d' )
			->where_if( $owner_id ?: null, 'owner_id', '%d' )
			->order_by( 'position', 'ASC' )
			->paginate( $pagination['per_page'], $pagination['page'] );

		return $this->paginated_response( $result, [ $this, 'format_deal' ] );
	}

	public function create_item( $request ) {
		$pipeline_id = absint( $request->get_param( 'pipeline_id' ) );
		$stage_id    = absint( $request->get_param( 'stage_id' ) );
		$title       = $this->get_string_param( $request, 'title' );

		$errors = [];
		if ( ! $pipeline_id ) {
			$errors['pipeline_id'] = __( 'pipeline_id is required.', 'pressio-crm' );
		}
		if ( ! $stage_id ) {
			$errors['stage_id'] = __( 'stage_id is required.', 'pressio-crm' );
		}
		if ( empty( $title ) ) {
			$errors['title'] = __( 'title is required.', 'pressio-crm' );
		}
		if ( $errors ) {
			return $this->validation_error( $errors );
		}

		$raw_value    = $request->get_param( 'value' );
		$raw_position = $request->get_param( 'position' );

		if ( null !== $raw_value && (float) $raw_value < 0 ) {
			return $this->bad_request( 'invalid_value', __( 'value must be 0 or greater.', 'pressio-crm' ) );
		}
		if ( null !== $raw_position && (float) $raw_position < 0 ) {
			return $this->bad_request( 'invalid_position', __( 'position must be 0 or greater.', 'pressio-crm' ) );
		}

		$expected_close = $request->get_param( 'expected_close' );
		if ( null !== $expected_close ) {
			$clean = sanitize_text_field( wp_unslash( $expected_close ) );
			if ( $clean !== '' && ! strtotime( $clean ) ) {
				return $this->validation_error( [ 'expected_close' => __( 'expected_close must be a valid date.', 'pressio-crm' ) ] );
			}
		}

		$data = $this->sanitise_deal_fields( $request );

		try {
			$deal = DealModel::create( $data );
		} catch ( \RuntimeException $e ) {
			return $this->bad_request( 'pressio_crm_create_failed', $e->getMessage() );
		}

		return $this->created( $this->format_deal( $deal ) );
	}

	public function get_item( $request ) {
		$deal = DealModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $deal ) {
			return $this->not_found();
		}

		return $this->ok( $this->format_deal( $deal ) );
	}

	public function update_item( $request ) {
		$deal = DealModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $deal ) {
			return $this->not_found();
		}

		// Validate that the new stage_id belongs to the deal's pipeline.
		$new_stage_id = $request->get_param( 'stage_id' );
		if ( null !== $new_stage_id ) {
			$stage = PipelineStageModel::find( absint( $new_stage_id ) );
			if ( null === $stage || (int) $stage->pipeline_id !== (int) $deal->pipeline_id ) {
				return $this->validation_error( [ 'stage_id' => __( 'Stage does not belong to this deal\'s pipeline.', 'pressio-crm' ) ] );
			}
		}

		$raw_value    = $request->get_param( 'value' );
		$raw_position = $request->get_param( 'position' );

		if ( null !== $raw_value && (float) $raw_value < 0 ) {
			return $this->bad_request( 'invalid_value', __( 'value must be 0 or greater.', 'pressio-crm' ) );
		}
		if ( null !== $raw_position && (float) $raw_position < 0 ) {
			return $this->bad_request( 'invalid_position', __( 'position must be 0 or greater.', 'pressio-crm' ) );
		}

		$expected_close = $request->get_param( 'expected_close' );
		if ( null !== $expected_close ) {
			$clean = sanitize_text_field( wp_unslash( $expected_close ) );
			if ( $clean !== '' && ! strtotime( $clean ) ) {
				return $this->validation_error( [ 'expected_close' => __( 'expected_close must be a valid date.', 'pressio-crm' ) ] );
			}
		}

		$data = $this->sanitise_deal_fields( $request );
		$deal->update( $data );

		return $this->ok( $this->format_deal( $deal ) );
	}

	public function delete_item( $request ) {
		$deal = DealModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $deal ) {
			return $this->not_found();
		}

		$deal->delete();

		return $this->no_content();
	}

	public function move( WP_REST_Request $request ) {
		$deal = DealModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $deal ) {
			return $this->not_found();
		}

		$stage_id = absint( $request->get_param( 'stage_id' ) );
		$position = (float) $request->get_param( 'position' );

		if ( ! $stage_id ) {
			return $this->validation_error( [ 'stage_id' => __( 'stage_id is required.', 'pressio-crm' ) ] );
		}

		if ( ! $request->get_param( 'position' ) && $request->get_param( 'position' ) !== 0 ) {
			return $this->validation_error( [ 'position' => __( 'position is required.', 'pressio-crm' ) ] );
		}

		$moved = $deal->move_stage( $stage_id, $position );

		if ( ! $moved ) {
			return $this->bad_request( 'pressio_crm_move_failed', __( 'Could not move deal — stage not found.', 'pressio-crm' ) );
		}

		return $this->ok( $this->format_deal( $deal ) );
	}

	public function format_deal( object $deal ): array {
		return [
			'id'             => (int) $deal->id,
			'contact_id'     => (int) $deal->contact_id,
			'pipeline_id'    => (int) $deal->pipeline_id,
			'stage_id'       => (int) $deal->stage_id,
			'owner_id'       => (int) $deal->owner_id,
			'title'          => (string) $deal->title,
			'value'          => (float) $deal->value,
			'currency'       => (string) $deal->currency,
			'expected_close' => (string) $deal->expected_close,
			'closed_at'      => (string) $deal->closed_at,
			'status'         => (string) $deal->status,
			'position'       => (float) $deal->position,
			'notes'          => (string) $deal->notes,
			'created_at'     => (string) $deal->created_at,
			'updated_at'     => (string) $deal->updated_at,
		];
	}

	private function sanitise_deal_fields( WP_REST_Request $request ): array {
		$data = [];

		$string_fields = [ 'title', 'currency' ];
		foreach ( $string_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$allowed = [ 'open', 'won', 'lost' ];
			$clean   = sanitize_text_field( wp_unslash( $status ) );
			if ( in_array( $clean, $allowed, true ) ) {
				$data['status'] = $clean;
			}
		}

		$notes = $request->get_param( 'notes' );
		if ( null !== $notes ) {
			$data['notes'] = wp_kses_post( wp_unslash( $notes ) );
		}

		$int_fields = [ 'contact_id', 'pipeline_id', 'stage_id', 'owner_id' ];
		foreach ( $int_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = absint( $value );
			}
		}

		$value = $request->get_param( 'value' );
		if ( null !== $value ) {
			$data['value'] = (float) $value;
		}

		$position = $request->get_param( 'position' );
		if ( null !== $position ) {
			$data['position'] = (float) $position;
		}

		$expected_close = $request->get_param( 'expected_close' );
		if ( null !== $expected_close ) {
			$clean                  = sanitize_text_field( wp_unslash( $expected_close ) );
			$data['expected_close'] = $clean ?: null;
		}

		return $data;
	}
}
