<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\ContactModel;
use PressioCRM\Models\ActivityModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestContacts extends Controller {

	protected $rest_base = 'contacts';

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
			'/' . $this->rest_base . '/bulk',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'bulk' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
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
			'/' . $this->rest_base . '/(?P<id>\d+)/timeline',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'timeline' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/notes',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_note' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/export',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'export_csv' ],
				'permission_callback' => [ $this, 'require_export_cap' ],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/import',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import_csv' ],
				'permission_callback' => [ $this, 'require_contacts_cap' ],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$pagination = $this->get_pagination_params( $request );
		$search     = $this->get_string_param( $request, 'search' );
		$status     = $this->get_string_param( $request, 'status' );
		$owner_id   = $this->get_int_param( $request, 'owner_id' );
		$tag_id     = $this->get_int_param( $request, 'tag_id' );

		$query = ContactModel::query()
			->not_deleted()
			->where_if( $status, 'status' )
			->where_if( $owner_id ?: null, 'owner_id', '%d' )
			->order_by( 'created_at', 'DESC' );

		if ( $search ) {
			$query->search( $search, [ 'first_name', 'last_name', 'email', 'company' ] );
		}

		if ( $tag_id ) {
			$query->has_tag( $tag_id );
		}

		$result = $query->paginate( $pagination['per_page'], $pagination['page'] );

		return $this->paginated_response( $result, [ $this, 'format_contact' ] );
	}

	public function create_item( $request ) {
		$first_name = $this->get_string_param( $request, 'first_name' );
		$email      = sanitize_email( wp_unslash( $request->get_param( 'email' ) ?? '' ) );

		if ( empty( $first_name ) && empty( $email ) ) {
			return $this->validation_error( [
				'first_name' => __( 'First name or email is required.', 'pressio-crm' ),
			] );
		}

		$data    = $this->sanitise_contact_fields( $request );
		$tag_ids = $data['_tags'] ?? [];
		unset( $data['_tags'] );

		if ( ! empty( $data['owner_id'] ) && ! get_userdata( (int) $data['owner_id'] ) ) {
			return $this->validation_error( [ 'owner_id' => __( 'Invalid owner — user does not exist.', 'pressio-crm' ) ] );
		}

		try {
			$contact = ContactModel::create( $data );
		} catch ( \RuntimeException $e ) {
			return $this->bad_request( 'pressio_crm_create_failed', $e->getMessage() );
		}

		if ( ! empty( $tag_ids ) ) {
			$contact->sync_tags( $tag_ids );
		}

		return $this->created( $this->format_contact( $contact ) );
	}

	public function get_item( $request ) {
		$contact = ContactModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $contact ) {
			return $this->not_found();
		}

		return $this->ok( $this->format_contact( $contact ) );
	}

	public function update_item( $request ) {
		$contact = ContactModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $contact ) {
			return $this->not_found();
		}

		$data    = $this->sanitise_contact_fields( $request );
		$tag_ids = $data['_tags'] ?? null;
		unset( $data['_tags'] );

		if ( ! empty( $data['owner_id'] ) && ! get_userdata( (int) $data['owner_id'] ) ) {
			return $this->validation_error( [ 'owner_id' => __( 'Invalid owner — user does not exist.', 'pressio-crm' ) ] );
		}

		if ( ! empty( $data ) ) {
			$contact->update( $data );
		}

		// Only sync tags when the caller explicitly sent the tags param.
		if ( null !== $tag_ids ) {
			$contact->sync_tags( $tag_ids );
		}

		return $this->ok( $this->format_contact( $contact ) );
	}

	public function delete_item( $request ) {
		$contact = ContactModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $contact ) {
			return $this->not_found();
		}

		$contact->delete();

		return $this->no_content();
	}

	public function timeline( WP_REST_Request $request ) {
		$id         = absint( $request->get_param( 'id' ) );
		$pagination = $this->get_pagination_params( $request );

		$contact = ContactModel::find( $id );

		if ( null === $contact ) {
			return $this->not_found();
		}

		$result = ActivityModel::get_for_contact( $id, $pagination['per_page'], $pagination['page'] );

		return $this->paginated_response( $result, [ $this, 'format_activity' ] );
	}

	public function add_note( WP_REST_Request $request ) {
		$id      = absint( $request->get_param( 'id' ) );
		$content = wp_kses_post( wp_unslash( (string) $request->get_param( 'content' ) ) );

		if ( empty( $content ) ) {
			return $this->validation_error( [
				'content' => __( 'Note content is required.', 'pressio-crm' ),
			] );
		}

		$contact = ContactModel::find( $id );

		if ( null === $contact ) {
			return $this->not_found();
		}

		$activity = ActivityModel::log( [
			'type'        => 'note_added',
			'contact_id'  => $id,
			'title'       => __( 'Note added', 'pressio-crm' ),
			'description' => $content,
		] );

		return $this->created( $this->format_activity( $activity ) );
	}

	public function bulk( WP_REST_Request $request ) {
		$action  = $this->get_string_param( $request, 'action' );
		$raw_ids = $request->get_param( 'ids' );

		if ( empty( $action ) || empty( $raw_ids ) || ! is_array( $raw_ids ) ) {
			return $this->bad_request( 'pressio_crm_invalid_bulk', __( 'action and ids are required.', 'pressio-crm' ) );
		}

		$ids = array_filter( array_map( 'absint', $raw_ids ) );

		if ( empty( $ids ) ) {
			return $this->bad_request( 'pressio_crm_invalid_ids', __( 'No valid IDs supplied.', 'pressio-crm' ) );
		}

		// Load all matching contacts in a single query instead of N individual SELECTs.
		$contacts = ContactModel::query()->where_in( 'id', array_values( $ids ), '%d' )->not_deleted()->get();
		$count    = 0;

		switch ( $action ) {
			case 'delete':
				foreach ( $contacts as $contact ) {
					$contact->delete();
					$count++;
				}
				break;

			case 'add_tag':
				$tag_id = absint( $request->get_param( 'tag_id' ) );
				if ( ! $tag_id ) {
					return $this->bad_request( 'pressio_crm_missing_tag', __( 'tag_id is required for add_tag.', 'pressio-crm' ) );
				}
				foreach ( $contacts as $contact ) {
					$contact->attach_tag( $tag_id );
					$count++;
				}
				break;

			case 'remove_tag':
				$tag_id = absint( $request->get_param( 'tag_id' ) );
				if ( ! $tag_id ) {
					return $this->bad_request( 'pressio_crm_missing_tag', __( 'tag_id is required for remove_tag.', 'pressio-crm' ) );
				}
				foreach ( $contacts as $contact ) {
					$contact->detach_tag( $tag_id );
					$count++;
				}
				break;

			case 'assign_owner':
				$owner_id = absint( $request->get_param( 'owner_id' ) );
				if ( ! $owner_id ) {
					return $this->bad_request( 'pressio_crm_missing_owner', __( 'owner_id is required for assign_owner.', 'pressio-crm' ) );
				}
				if ( ! get_userdata( $owner_id ) ) {
					return $this->bad_request( 'invalid_owner', __( 'The specified owner does not exist.', 'pressio-crm' ) );
				}
				foreach ( $contacts as $contact ) {
					$contact->update( [ 'owner_id' => $owner_id ] );
					$count++;
				}
				break;

			default:
				return $this->bad_request( 'pressio_crm_unknown_action', __( 'Unknown bulk action.', 'pressio-crm' ) );
		}

		return $this->ok( [ 'processed' => $count ] );
	}

	public function export_csv( WP_REST_Request $request ): WP_REST_Response {
		// Hard cap at 10 000 rows to prevent memory exhaustion.
		$contacts = ContactModel::query()
			->not_deleted()
			->order_by( 'created_at', 'DESC' )
			->limit( 10000 )
			->get();

		$columns = [
			'first_name', 'last_name', 'email', 'phone', 'company', 'job_title',
			'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
			'country', 'source', 'status', 'notes', 'created_at',
		];

		$rows   = [];
		$rows[] = $columns; // header row

		foreach ( $contacts as $contact ) {
			$row = [];
			foreach ( $columns as $col ) {
				$row[] = $contact->$col ?? '';
			}
			$rows[] = $row;
		}

		$csv = '';
		foreach ( $rows as $row ) {
			$escaped = array_map( static function ( $cell ) {
				$cell = (string) $cell;
				// Wrap in double-quotes if the value contains comma, quote, or newline.
				if ( str_contains( $cell, ',' ) || str_contains( $cell, '"' ) || str_contains( $cell, "\n" ) ) {
					$cell = '"' . str_replace( '"', '""', $cell ) . '"';
				}
				return $cell;
			}, $row );
			$csv .= implode( ',', $escaped ) . "\n";
		}

		$filename = 'contacts-' . gmdate( 'Y-m-d' ) . '.csv';

		return $this->ok( [ 'csv' => $csv, 'filename' => $filename ] );
	}

	public function import_csv( WP_REST_Request $request ) {
		$csv_text    = $request->get_param( 'csv' );
		$on_duplicate = sanitize_text_field( wp_unslash( $request->get_param( 'on_duplicate' ) ?? 'skip' ) ); // 'skip' | 'update'

		if ( empty( $csv_text ) ) {
			return $this->bad_request( 'pressio_crm_missing_csv', __( 'csv is required.', 'pressio-crm' ) );
		}

		if ( mb_strlen( $csv_text, '8bit' ) > 5 * 1024 * 1024 ) {
			return $this->bad_request( 'pressio_crm_csv_too_large', __( 'CSV file must be under 5 MB.', 'pressio-crm' ) );
		}

		if ( ! in_array( $on_duplicate, [ 'skip', 'update' ], true ) ) {
			$on_duplicate = 'skip';
		}

		// Normalise line endings and split into rows.
		$csv_text = str_replace( [ "\r\n", "\r" ], "\n", $csv_text );
		$lines    = explode( "\n", trim( $csv_text ) );

		if ( empty( $lines ) ) {
			return $this->bad_request( 'pressio_crm_empty_csv', __( 'CSV file is empty.', 'pressio-crm' ) );
		}

		// Limit to 5000 data rows per import.
		$max_rows = 5001; // +1 for header
		if ( count( $lines ) > $max_rows ) {
			$lines = array_slice( $lines, 0, $max_rows );
		}

		$headers = $this->parse_csv_line( array_shift( $lines ) );
		$headers = array_map( 'strtolower', array_map( 'trim', $headers ) );

		// Map CSV header names to contact fields.
		$field_aliases = [
			'first name'   => 'first_name',
			'firstname'    => 'first_name',
			'last name'    => 'last_name',
			'lastname'     => 'last_name',
			'name'         => 'first_name',
			'full name'    => 'first_name',
			'email'        => 'email',
			'email address'=> 'email',
			'phone'        => 'phone',
			'telephone'    => 'phone',
			'mobile'       => 'phone',
			'company'      => 'company',
			'organisation' => 'company',
			'organization' => 'company',
			'job title'    => 'job_title',
			'jobtitle'     => 'job_title',
			'title'        => 'job_title',
			'position'     => 'job_title',
			'address'      => 'address_line_1',
			'address_line_1'=> 'address_line_1',
			'address line 1'=> 'address_line_1',
			'address_line_2'=> 'address_line_2',
			'address line 2'=> 'address_line_2',
			'city'         => 'city',
			'town'         => 'city',
			'state'        => 'state',
			'county'       => 'state',
			'province'     => 'state',
			'zip'          => 'postal_code',
			'postcode'     => 'postal_code',
			'postal_code'  => 'postal_code',
			'postal code'  => 'postal_code',
			'country'      => 'country',
			'source'       => 'source',
			'status'       => 'status',
			'notes'        => 'notes',
			'note'         => 'notes',
		];

		$col_map = []; // index → field name
		foreach ( $headers as $i => $header ) {
			$clean = trim( $header, " \t\n\r\0\x0B\"'" );
			if ( isset( $field_aliases[ $clean ] ) ) {
				$col_map[ $i ] = $field_aliases[ $clean ];
			} elseif ( in_array( $clean, array_values( $field_aliases ), true ) ) {
				$col_map[ $i ] = $clean;
			}
		}

		$imported = 0;
		$skipped  = 0;
		$updated  = 0;

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$values = $this->parse_csv_line( $line );
			$data   = [];

			foreach ( $col_map as $idx => $field ) {
				$data[ $field ] = isset( $values[ $idx ] ) ? trim( $values[ $idx ] ) : '';
			}

			// Must have at least email or first name.
			if ( empty( $data['email'] ) && empty( $data['first_name'] ) ) {
				$skipped++;
				continue;
			}

			// Sanitise fields.
			$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';

			// Check for duplicate by email.
			$existing = $email ? ContactModel::find_by_email( $email ) : null;

			if ( $existing ) {
				if ( 'update' === $on_duplicate ) {
					$sanitised = $this->sanitise_import_row( $data );
					$existing->update( $sanitised );
					$updated++;
				} else {
					$skipped++;
				}
				continue;
			}

			try {
				ContactModel::create( $this->sanitise_import_row( $data ) );
				$imported++;
			} catch ( \Exception $e ) {
				$skipped++;
			}
		}

		return $this->ok( [
			'imported' => $imported,
			'updated'  => $updated,
			'skipped'  => $skipped,
		] );
	}

	private function parse_csv_line( string $line ): array {
		// PHP's str_getcsv handles quoted fields with embedded commas/newlines correctly.
		return str_getcsv( $line );
	}

	private function sanitise_import_row( array $data ): array {
		$out    = [];
		$string = [ 'first_name', 'last_name', 'phone', 'company', 'job_title',
			'address_line_1', 'address_line_2', 'city', 'state', 'postal_code',
			'country', 'source' ];

		foreach ( $string as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$out[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( isset( $data['email'] ) ) {
			$out['email'] = sanitize_email( $data['email'] );
		}

		if ( isset( $data['notes'] ) ) {
			$out['notes'] = sanitize_textarea_field( $data['notes'] );
		}

		$allowed_status = [ 'active', 'inactive', 'lead', 'customer', 'archived' ];
		if ( isset( $data['status'] ) && in_array( $data['status'], $allowed_status, true ) ) {
			$out['status'] = $data['status'];
		}

		return $out;
	}

	public function format_contact( object $contact ): array {
		$tags = [];
		if ( method_exists( $contact, 'get_tags' ) ) {
			foreach ( $contact->get_tags() as $tag ) {
				$tags[] = [
					'id'    => (int) $tag->id,
					'name'  => (string) $tag->name,
					'slug'  => (string) $tag->slug,
					'color' => (string) $tag->color,
				];
			}
		}

		$meta = [];
		if ( method_exists( $contact, 'get_all_meta' ) ) {
			$meta = $contact->get_all_meta();
		}

		return [
			'id'             => (int) $contact->id,
			'owner_id'       => (int) $contact->owner_id,
			'first_name'     => (string) $contact->first_name,
			'last_name'      => (string) $contact->last_name,
			'email'          => (string) $contact->email,
			'phone'          => (string) $contact->phone,
			'company'        => (string) $contact->company,
			'job_title'      => (string) $contact->job_title,
			'address_line_1' => (string) $contact->address_line_1,
			'address_line_2' => (string) $contact->address_line_2,
			'city'           => (string) $contact->city,
			'state'          => (string) $contact->state,
			'postal_code'    => (string) $contact->postal_code,
			'country'        => (string) $contact->country,
			'source'         => (string) $contact->source,
			'status'         => (string) $contact->status,
			'notes'          => (string) $contact->notes,
			'created_at'     => (string) $contact->created_at,
			'updated_at'     => (string) $contact->updated_at,
			'tags'           => $tags,
			'meta'           => $meta,
		];
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

	private function sanitise_contact_fields( WP_REST_Request $request ): array {
		$data = [];

		$string_fields = [
			'first_name', 'last_name', 'phone', 'company', 'job_title',
			'address_line_1', 'address_line_2', 'city', 'state',
			'postal_code', 'country', 'source',
		];

		foreach ( $string_fields as $field ) {
			$value = $request->get_param( $field );
			if ( null !== $value ) {
				$data[ $field ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		$status = $request->get_param( 'status' );
		if ( null !== $status ) {
			$allowed_statuses = [ 'active', 'inactive', 'lead', 'customer', 'archived' ];
			$clean            = sanitize_text_field( wp_unslash( $status ) );
			if ( in_array( $clean, $allowed_statuses, true ) ) {
				$data['status'] = $clean;
			}
		}

		$email = $request->get_param( 'email' );
		if ( null !== $email ) {
			$data['email'] = sanitize_email( wp_unslash( $email ) );
		}

		$notes = $request->get_param( 'notes' );
		if ( null !== $notes ) {
			$data['notes'] = wp_kses_post( wp_unslash( $notes ) );
		}

		$owner_id = $request->get_param( 'owner_id' );
		if ( null !== $owner_id ) {
			$data['owner_id'] = absint( $owner_id );
		}

		$tags = $request->get_param( 'tags' );
		if ( null !== $tags && is_array( $tags ) ) {
			// Tags are handled separately via sync_tags — strip from data but return the ids.
			$data['_tags'] = array_filter( array_map( 'absint', $tags ) );
		}

		return $data;
	}
}
