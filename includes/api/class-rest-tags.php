<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\TagModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestTags extends Controller {

	protected $rest_base = 'tags';

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
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		global $wpdb;

		$search = $this->get_string_param( $request, 'search' );

		$query = TagModel::query()->order_by( 'name', 'ASC' );

		if ( $search ) {
			$query->where_raw( '`name` LIKE %s', [ '%' . $wpdb->esc_like( $search ) . '%' ] );
		}

		$tags = $query->get();

		return $this->ok( array_map( [ $this, 'format_tag' ], $tags ) );
	}

	public function create_item( $request ) {
		$name = $this->get_string_param( $request, 'name' );

		if ( empty( $name ) ) {
			return $this->validation_error( [ 'name' => __( 'Tag name is required.', 'pressio-crm' ) ] );
		}

		$color = $this->get_string_param( $request, 'color' );
		if ( $color && ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color ) ) {
			return $this->validation_error( [ 'color' => __( 'color must be a valid hex value (e.g. #6366f1).', 'pressio-crm' ) ] );
		}

		try {
			if ( $color ) {
				$tag = TagModel::create( [
					'name'  => $name,
					'slug'  => sanitize_title( $name ),
					'color' => $color,
				] );
			} else {
				$tag = TagModel::create_from_name( $name );
			}
		} catch ( \RuntimeException $e ) {
			return $this->bad_request( 'pressio_crm_create_failed', $e->getMessage() );
		}

		return $this->created( $this->format_tag( $tag ) );
	}

	public function update_item( $request ) {
		$tag = TagModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $tag ) {
			return $this->not_found();
		}

		$data = [];

		$name = $request->get_param( 'name' );
		if ( null !== $name ) {
			$data['name'] = sanitize_text_field( wp_unslash( $name ) );
			$data['slug'] = sanitize_title( $data['name'] );
		}

		$color = $request->get_param( 'color' );
		if ( null !== $color ) {
			$clean = sanitize_text_field( wp_unslash( $color ) );
			if ( ! preg_match( '/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $clean ) ) {
				return $this->validation_error( [ 'color' => __( 'color must be a valid hex value (e.g. #6366f1).', 'pressio-crm' ) ] );
			}
			$data['color'] = $clean;
		}

		if ( ! empty( $data ) ) {
			$tag->update( $data );
		}

		return $this->ok( $this->format_tag( $tag ) );
	}

	public function delete_item( $request ) {
		$tag = TagModel::find( absint( $request->get_param( 'id' ) ) );

		if ( null === $tag ) {
			return $this->not_found();
		}

		$tag->delete();

		return $this->no_content();
	}

	public function format_tag( object $tag ): array {
		return [
			'id'         => (int) $tag->id,
			'name'       => (string) $tag->name,
			'slug'       => (string) $tag->slug,
			'color'      => (string) $tag->color,
			'created_at' => (string) $tag->created_at,
		];
	}
}
