<?php
namespace PressioCRM;

defined( 'ABSPATH' ) || exit;

class EmailRenderer {

	/**
	 * Render a complete HTML email.
	 *
	 * @param string $body_html    The composed message body (HTML).
	 * @param array  $contact_data Associative array of contact fields for merge-tag replacement.
	 * @return string Full HTML email ready to pass to wp_mail().
	 */
	public static function render( string $body_html, array $contact_data = [] ): string {
		$settings = self::get_settings();

		$body_html = self::replace_tags( $body_html, $contact_data, $settings );
		$footer    = self::replace_tags( $settings['email_footer'], $contact_data, $settings );
		$accent    = esc_attr( $settings['email_accent_color'] );
		$header    = self::build_header( $settings, $accent );

		// Outer table keeps email centred and provides background.
		return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<style>
body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
body{margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;font-size:14px;color:#1d2327}
a{color:' . $accent . '}
</style>
</head>
<body>
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:24px 0">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1)">
      ' . $header . '
      <tr>
        <td style="padding:28px 32px 24px;line-height:1.6">
          ' . $body_html . '
        </td>
      </tr>
      ' . self::build_footer( $footer, $accent ) . '
    </table>
  </td></tr>
</table>
</body>
</html>';
	}

	/**
	 * Return the wp_mail() headers array with the configured From / Reply-To.
	 */
	public static function get_headers(): array {
		$settings = self::get_settings();

		$from_name  = $settings['email_from_name']  ?: get_bloginfo( 'name' );
		$from_email = $settings['email_from_email'] ?: get_option( 'admin_email' );
		$reply_to   = $settings['email_reply_to'];

		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			'From: ' . sanitize_text_field( $from_name ) . ' <' . sanitize_email( $from_email ) . '>',
		];

		if ( ! empty( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . sanitize_email( $reply_to );
		}

		return $headers;
	}

	// ── Private helpers ──────────────────────────────────────────────────────

	private static function get_settings(): array {
		$defaults = [
			'email_from_name'       => '',
			'email_from_email'      => '',
			'email_reply_to'        => '',
			'email_header_type'     => 'none',
			'email_header_logo_url' => '',
			'email_header_custom'   => '',
			'email_accent_color'    => '#2271b1',
			'email_footer'          => '',
			'company_name'          => '',
		];

		$saved = get_option( 'pressio_crm_settings', [] );

		return array_merge( $defaults, is_array( $saved ) ? $saved : [] );
	}

	private static function build_header( array $settings, string $accent ): string {
		$type = $settings['email_header_type'];

		if ( 'logo' === $type && ! empty( $settings['email_header_logo_url'] ) ) {
			$url = esc_url( $settings['email_header_logo_url'] );
			return '<tr><td align="center" style="background:' . $accent . ';padding:20px">
				<img src="' . $url . '" alt="" style="max-height:60px;max-width:220px;display:block;margin:0 auto">
			</td></tr>';
		}

		if ( 'custom' === $type && ! empty( $settings['email_header_custom'] ) ) {
			return '<tr><td style="padding:0">' . wp_kses_post( $settings['email_header_custom'] ) . '</td></tr>';
		}

		return '';
	}

	private static function build_footer( string $footer_html, string $accent ): string {
		if ( empty( trim( wp_strip_all_tags( $footer_html ) ) ) ) {
			return '';
		}

		return '<tr>
			<td style="padding:16px 32px;border-top:1px solid #e2e8f0;font-size:12px;color:#50575e;line-height:1.5">
				' . wp_kses_post( $footer_html ) . '
			</td>
		</tr>';
	}

	/**
	 * Replace {{placeholder}} tags in content.
	 *
	 * Supported tags:
	 *   {{contact.first_name}}, {{contact.last_name}}, {{contact.email}}
	 *   {{business.name}}, {{business.email}}
	 */
	private static function replace_tags( string $content, array $contact, array $settings ): string {
		$map = [
			'{{contact.first_name}}' => esc_html( $contact['first_name'] ?? '' ),
			'{{contact.last_name}}'  => esc_html( $contact['last_name']  ?? '' ),
			'{{contact.email}}'      => esc_html( $contact['email']      ?? '' ),
			'{{business.name}}'      => esc_html( $settings['company_name'] ?: get_bloginfo( 'name' ) ),
			'{{business.email}}'     => esc_html( $settings['email_from_email'] ?: get_option( 'admin_email' ) ),
		];

		return str_replace( array_keys( $map ), array_values( $map ), $content );
	}
}
