<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\TaskModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestTasks extends Controller {

	protected $rest_base = 'tasks';

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
			'/' . $this->rest_base . '/(?P<id>\d+)/complete',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'complete' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$pagination = $this->get_pagination_params( $request );
		$status     = $this->get_string_param( $request, 'status' );
		$owner_id   = $this->get_int_param( $request, 'owner_id' );
		$contact_id = $this->get_int_param( $request, 'contact_id' );
		$deal_id    = $this->get_int_param( $request, 'deal_id' );
		$due_before = $this->get_string_param( $request, 'due_before' );
		if ( $due_before && ! strtotime( $due_before ) ) {
			return $this->bad_request( 'invalid_date', __( 'Invalid due_before date format.', 'pressio-crm' ) );
		}

		$due_after = $this->get_string_param( $request, 'due_after' );
		if ( $due_after && ! strtotime( $due_after ) ) {
			return $this->bad_request( 'invalid_date', __( 'Invalid due_after date format.', 'pressio-crm' ) );
		}

		$query = TaskModel::query()
			->not_deleted()
			->where_if( $status, 'status' )
			->where_if( $owner_id ?: null, 'owner_id', '%d' )
			->where_if( $contact_id ?: null, 'contact_id', '%d' )
			->where_if( $deal_id ?: null, 'deal_id', '%d' )
			->order_by( 'due_date', 'ASC' );

		if ( $due_before ) {
			$query->where_raw( '`due_date` <= %s', [ $due_before ] );
		}

		if ( $due_after ) {
			$query->where_raw( '`due_date` >= %s', [ $due_after ] );
		}

		$result = $query->paginate( $pagination['per_page'], $pagination['page'] );

		return $this->paginated_response( $result, [ $this, 'format_task' ] );
	}

	public function create_item( $request ) {
		$title = $this->get_string_param( $request, 'title' );

		if ( empty( $title ) ) {
			return $this->validation_error( [ 'title' => __( 'title is required.', 'pressio-crm' ) ] );
		}

		$due_date = $request->get_param( 'due_date' );
		if ( null !== $due_date ) {
			$clean = sanitize_text_field( wp_unslash( $due_date ) );
			if ( $clean !== '' && ! strtotime( $clean ) ) {
				return $this->validation_error( [ 'due_date' => __( 'due_date must be a valid date.', 'pressio-crm' ) ] );
			}
		}

		$data = $this->sanitise_task_fields( $request );

		try {
			$task = TaskModel::create( $data );
		} catch ( \RuntimeException $e ) {
			return $this->bad_request( 'pressio_crm_create_failed', $e->getMessage() );
		}

		return $this->created( $this->format_task( $task ) );
	}

	public function update_item( $request ) {
		$task = TaskModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $task ) {
			return $this->not_found();
		}

		$due_date = $request->get_param( 'due_date' );
		if ( null !== $due_date ) {
			$clean = sanitize_text_field( wp_unslash( $due_date ) );
			if ( $clean !== '' && ! strtotime( $clean ) ) {
				return $this->validation_error( [ 'due_date' => __( 'due_date must be a valid date.', 'pressio-crm' ) ] );
			}
		}

		$data = $this->sanitise_task_fields( $request );
		$task->update( $data );

		return $this->ok( $this->format_task( $task ) );
	}

	public function delete_item( $request ) {
		$task = TaskModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $task ) {
			return $this->not_found();
		}

		$task->delete();

		return $this->no_content();
	}

	public function complete( WP_REST_Request $request ) {
		$task = TaskModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $task ) {
			return $this->not_found();
		}

		$task->complete();

		return $this->ok( $this->format_task( $task ) );
	}

	public function format_task( object $task ): array {
		return [
			'id'           => (int) $task->id,
			'contact_id'   => (int) $task->contact_id,
			'deal_id'      => (int) $task->deal_id,
			'owner_id'     => (int) $task->owner_id,
			'title'        => (string) $task->title,
			'description'  => (string) $task->description,
			'type'         => (string) $task->type,
			'status'       => (string) $task->status,
			'priority'     => (string) $task->priority,
			'due_date'     => (string) $task->due_date,
			'completed_at' => (string) $task->completed_at,
			'created_at'   => (string) $task->created_at,
			'updated_at'   => (string) $task->updated_at,
		];
	}

	private function sanitise_task_fields( WP_REST_Request $request ): array {
		$data = [];

		$string_fields = [ 'title', 'description', 'type' ];
		foreach ( $string_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$allowed = [ 'pending', 'in_progress', 'completed' ];
			$clean   = sanitize_text_field( wp_unslash( $status ) );
			if ( in_array( $clean, $allowed, true ) ) {
				$data['status'] = $clean;
			}
		}

		$priority = $request->get_param( 'priority' );
		if ( null !== $priority ) {
			$allowed  = [ 'low', 'medium', 'high' ];
			$clean    = sanitize_text_field( wp_unslash( $priority ) );
			if ( in_array( $clean, $allowed, true ) ) {
				$data['priority'] = $clean;
			}
		}

		$int_fields = [ 'contact_id', 'deal_id', 'owner_id' ];
		foreach ( $int_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = absint( $value );
			}
		}

		$due_date = $request->get_param( 'due_date' );
		if ( null !== $due_date ) {
			$clean            = sanitize_text_field( wp_unslash( $due_date ) );
			$data['due_date'] = $clean ?: null;
		}

		return $data;
	}
}
