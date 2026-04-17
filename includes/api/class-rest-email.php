<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\EmailRenderer;
use PressioCRM\Framework\Controller;
use PressioCRM\Mail\Mailer;
use PressioCRM\Models\ContactModel;
use PressioCRM\Models\EmailModel;
use PressioCRM\Models\ActivityModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestEmail extends Controller {

	protected $rest_base = 'contacts';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/email',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'send' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'history' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
			]
		);
	}

	public function send( WP_REST_Request $request ): WP_REST_Response {
		$contact = ContactModel::find( $this->get_int_param( $request, 'id' ) );

		if ( ! $contact ) {
			return $this->not_found();
		}

		$subject = $this->get_string_param( $request, 'subject' );
		$body    = wp_kses_post( (string) $request->get_param( 'body' ) );

		if ( empty( $subject ) ) {
			return $this->validation_error( [ 'subject' => __( 'Subject is required.', 'pressio-crm' ) ] );
		}

		if ( empty( $body ) ) {
			return $this->validation_error( [ 'body' => __( 'Body is required.', 'pressio-crm' ) ] );
		}

		if ( mb_strlen( $body, '8bit' ) > 65535 ) {
			return $this->validation_error( [ 'body' => __( 'Email body is too long (max 64KB).', 'pressio-crm' ) ] );
		}

		if ( empty( $contact->email ) ) {
			return $this->bad_request( 'no_email', __( 'This contact has no email address.', 'pressio-crm' ) );
		}

		$current_user = wp_get_current_user();
		$from_email   = sanitize_email( (string) $request->get_param( 'from_email' ) ) ?: $current_user->user_email;
		$from_name    = sanitize_text_field( (string) $request->get_param( 'from_name' ) ) ?: $current_user->display_name;
		$reply_to     = sanitize_email( (string) $request->get_param( 'reply_to' ) ) ?: '';

		// Store the email record before sending so we have an ID regardless of outcome.
		$email = EmailModel::create( [
			'contact_id' => (int) $contact->id,
			'email_hash' => md5( uniqid( (string) $contact->id, true ) ),
			'from_email' => $from_email,
			'to_email'   => $contact->email,
			'subject'    => $subject,
			'body'       => $body,
			'status'     => 'queued',
		] );

		$rendered = EmailRenderer::render( $body, (array) $contact );

		$sent = Mailer::send( [
			'to'         => $contact->email,
			'subject'    => $subject,
			'body'       => $rendered,
			'from_email' => $from_email,
			'from_name'  => $from_name,
			'reply_to'   => $reply_to,
		] );

		if ( $sent ) {
			$email->mark_sent();

			ActivityModel::log( [
				'contact_id' => (int) $contact->id,
				'user_id'    => get_current_user_id(),
				'type'       => 'email_sent',
				'title'      => sprintf(
					/* translators: %s: email subject */
					__( 'Email sent: %s', 'pressio-crm' ),
					$subject
				),
				'meta'       => [
					'email_id' => (int) $email->id,
					'subject'  => $subject,
					'to'       => $contact->email,
				],
			] );

			do_action( 'pressio_crm_email_sent', (int) $email->id, (int) $contact->id );

			return $this->created( [ 'id' => (int) $email->id, 'status' => 'sent' ] );
		}

		$email->mark_failed( __( 'wp_mail() returned false. Check your mail configuration.', 'pressio-crm' ) );

		do_action( 'pressio_crm_email_failed', (int) $email->id, (int) $contact->id, 'wp_mail_returned_false' );

		return $this->bad_request( 'send_failed', __( 'Email could not be sent. Check your WordPress mail configuration.', 'pressio-crm' ) );
	}

	public function history( WP_REST_Request $request ): WP_REST_Response {
		$contact = ContactModel::find( $this->get_int_param( $request, 'id' ) );

		if ( ! $contact ) {
			return $this->not_found();
		}

		[ 'per_page' => $per_page, 'page' => $page ] = $this->get_pagination_params( $request );

		$result = EmailModel::get_for_contact( (int) $contact->id, $per_page, $page );

		return $this->paginated_response(
			$result,
			function ( $email ) {
				return [
					'id'         => (int) $email->id,
					'subject'    => $email->subject,
					'to_email'   => $email->to_email,
					'from_email' => $email->from_email,
					'status'     => $email->status,
					'sent_at'    => $email->sent_at,
					'created_at' => $email->created_at,
				];
			}
		);
	}
}
