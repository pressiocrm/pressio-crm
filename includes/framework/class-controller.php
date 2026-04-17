<?php
namespace PressioCRM\Framework;

defined( 'ABSPATH' ) || exit;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

abstract class Controller extends WP_REST_Controller {

	/** REST namespace shared by all endpoints. */
	protected $namespace = 'pressio-crm/v1';

	/**
	 * Permission callback for contact/deal/task endpoints.
	 *
	 * @return true|WP_Error
	 */
	public function require_contacts_cap() {
		if ( ! current_user_can( 'pressio_crm_manage_contacts' ) ) {
			return new WP_Error(
				'pressio_crm_forbidden',
				__( 'You do not have permission to access CRM data.', 'pressio-crm' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	public function require_export_cap() {
		if ( ! current_user_can( 'pressio_crm_export_data' ) ) {
			return new WP_Error(
				'pressio_crm_forbidden',
				__( 'You do not have permission to export CRM data.', 'pressio-crm' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * Permission callback for settings endpoints.
	 *
	 * @return true|WP_Error
	 */
	public function require_settings_cap() {
		if ( ! current_user_can( 'pressio_crm_manage_settings' ) ) {
			return new WP_Error(
				'pressio_crm_forbidden',
				__( 'You do not have permission to manage CRM settings.', 'pressio-crm' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * @param mixed $data
	 */
	protected function ok( $data, int $status = 200 ): WP_REST_Response {
		return new WP_REST_Response( $data, $status );
	}

	/**
	 * @param mixed $data
	 */
	protected function created( $data ): WP_REST_Response {
		return $this->ok( $data, 201 );
	}

	protected function no_content(): WP_REST_Response {
		return $this->ok( null, 204 );
	}

	protected function not_found( string $message = '' ): WP_Error {
		return new WP_Error(
			'pressio_crm_not_found',
			$message ?: __( 'Resource not found.', 'pressio-crm' ),
			[ 'status' => 404 ]
		);
	}

	protected function bad_request( string $code, string $message ): WP_Error {
		return new WP_Error( $code, $message, [ 'status' => 400 ] );
	}

	/**
	 * 422 Unprocessable Entity — validation errors.
	 *
	 * @param array $errors Field => message pairs.
	 */
	protected function validation_error( array $errors ): WP_Error {
		return new WP_Error(
			'pressio_crm_validation_error',
			__( 'Validation failed.', 'pressio-crm' ),
			[ 'status' => 422, 'errors' => $errors ]
		);
	}

	/**
	 * Build a paginated response with X-WP-Total and X-WP-TotalPages headers.
	 *
	 * @param array         $result    Result from Query Builder paginate().
	 * @param callable|null $transform Optional callable to transform each item.
	 */
	protected function paginated_response( array $result, ?callable $transform = null ): WP_REST_Response {
		$items = $result['items'];

		if ( $transform ) {
			$items = array_map( $transform, $items );
		} else {
			$items = array_map( fn( $item ) => $item->to_array(), $items );
		}

		$response = $this->ok( $items );
		$response->header( 'X-WP-Total', (string) $result['total'] );
		$response->header( 'X-WP-TotalPages', (string) $result['pages'] );

		return $response;
	}

	/**
	 * @return array{ per_page: int, page: int }
	 */
	protected function get_pagination_params( WP_REST_Request $request ): array {
		return [
			'per_page' => max( 1, min( 100, absint( $request->get_param( 'per_page' ) ?: 20 ) ) ),
			'page'     => max( 1, absint( $request->get_param( 'page' ) ?: 1 ) ),
		];
	}

	protected function get_string_param( WP_REST_Request $request, string $param, string $default = '' ): string {
		$value = $request->get_param( $param );
		return $value !== null ? sanitize_text_field( wp_unslash( $value ) ) : $default;
	}

	protected function get_int_param( WP_REST_Request $request, string $param, int $default = 0 ): int {
		$value = $request->get_param( $param );
		return $value !== null ? absint( $value ) : $default;
	}
}
