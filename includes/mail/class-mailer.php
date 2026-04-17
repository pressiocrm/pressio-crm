<?php
namespace PressioCRM\Mail;

defined( 'ABSPATH' ) || exit;

class Mailer {

	/**
	 * Send an email. Entry point for all outgoing mail in Pressio CRM.
	 *
	 * Follows FluentCRM's Mailer pattern: thin wrapper around wp_mail() so the
	 * site's configured SMTP (WP Mail SMTP, FluentSMTP, etc.) handles delivery.
	 *
	 * @param array{
	 *   to:         string,
	 *   subject:    string,
	 *   body:       string,
	 *   from_email?: string,
	 *   from_name?:  string,
	 *   reply_to?:   string,
	 * } $data
	 */
	public static function send( array $data ): bool {
		$headers = static::build_headers( $data );

		// Allow test suites or staging environments to skip actual delivery.
		if ( apply_filters( 'pressio_crm_is_simulated_mail', false, $data, $headers ) ) {
			return true;
		}

		$result = wp_mail(
			sanitize_email( $data['to'] ),
			sanitize_text_field( $data['subject'] ),
			$data['body'],
			$headers
		);

		return (bool) $result;
	}

	/** @return string[] */
	private static function build_headers( array $data ): array {
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$from_email = ! empty( $data['from_email'] )
			? sanitize_email( $data['from_email'] )
			: get_option( 'admin_email' );

		$from_name = ! empty( $data['from_name'] )
			? sanitize_text_field( $data['from_name'] )
			: get_option( 'blogname' );

		// Quote the display name to handle commas, quotes, and special characters per RFC 5321.
		$headers[] = sprintf( 'From: "%s" <%s>', addslashes( $from_name ), $from_email );

		if ( ! empty( $data['reply_to'] ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $data['reply_to'] );
		}

		return (array) apply_filters( 'pressio_crm_email_headers', $headers, $data );
	}
}
