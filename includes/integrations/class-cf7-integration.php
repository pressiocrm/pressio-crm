<?php
namespace PressioCRM\Integrations;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Models\ContactModel;
use PressioCRM\Models\ActivityModel;
use PressioCRM\Models\IntegrationModel;
use WPCF7_ContactForm;

class CF7Integration {

	public function slug(): string {
		return 'cf7';
	}

	/**
	 * Called by IntegrationManager::register().
	 * Hooks up the CF7 form editor tab and submission handler.
	 */
	public function register(): void {
		// Add "Pressio CRM" tab to the CF7 form editor.
		add_filter( 'wpcf7_editor_panels', [ $this, 'add_editor_panel' ] );

		// Save our field mapping when the CF7 form is saved.
		add_action( 'wpcf7_save_contact_form', [ $this, 'save_mapping' ] );

		// Process the submission and create/update the contact.
		add_action( 'wpcf7_mail_sent', [ $this, 'handle_submission' ] );
	}

	// -------------------------------------------------------------------------
	// CF7 Editor Panel
	// -------------------------------------------------------------------------

	/** @param array<string, array> $panels */
	public function add_editor_panel( array $panels ): array {
		$panels['pressio-crm'] = [
			'title'    => __( 'Pressio CRM', 'pressio-crm' ),
			'callback' => [ $this, 'render_editor_panel' ],
		];

		return $panels;
	}

	public function render_editor_panel( WPCF7_ContactForm $form ): void {
		$form_id = $form->id();
		$mapping = $this->get_mapping( $form_id );
		$config  = $mapping ? json_decode( $mapping->config ?? '{}', true ) : [];
		if ( ! is_array( $config ) ) {
			$config = [];
		}

		wp_nonce_field( 'pressio_crm_cf7_save_' . $form_id, 'pressio_crm_cf7_nonce' );

		$fields = [
			'email'      => __( 'Email field name', 'pressio-crm' ),
			'first_name' => __( 'First name field name', 'pressio-crm' ),
			'last_name'  => __( 'Last name field name', 'pressio-crm' ),
			'phone'      => __( 'Phone field name', 'pressio-crm' ),
			'company'    => __( 'Company field name', 'pressio-crm' ),
		];

		echo '<div class="pressio-crm-cf7-panel" style="padding:15px 0">';
		echo '<h2>' . esc_html__( 'Pressio CRM Integration', 'pressio-crm' ) . '</h2>';
		echo '<p>' . esc_html__( 'Map CF7 field names to Pressio CRM contact fields. Leave blank to skip.', 'pressio-crm' ) . '</p>';
		echo '<table class="form-table"><tbody>';

		foreach ( $fields as $key => $label ) {
			$value = esc_attr( $config[ $key ] ?? '' );
			echo '<tr>';
			echo '<th scope="row"><label for="pressio_crm_cf7_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" id="pressio_crm_cf7_' . esc_attr( $key ) . '" name="pressio_crm_cf7[' . esc_attr( $key ) . ']" value="' . esc_attr( $value ) . '" class="regular-text" /></td>';
			echo '</tr>';
		}

		// "Enable" toggle.
		$is_active = isset( $mapping ) && $mapping->is_active;
		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Enable', 'pressio-crm' ) . '</th>';
		echo '<td><label><input type="checkbox" name="pressio_crm_cf7[is_active]" value="1"' . checked( $is_active, true, false ) . ' /> ';
		echo esc_html__( 'Send submissions to Pressio CRM', 'pressio-crm' ) . '</label></td>';
		echo '</tr>';

		echo '</tbody></table>';
		echo '</div>';
	}

	// -------------------------------------------------------------------------
	// Save mapping
	// -------------------------------------------------------------------------

	public function save_mapping( WPCF7_ContactForm $form ): void {
		$form_id = $form->id();

		if ( ! isset( $_POST['pressio_crm_cf7_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pressio_crm_cf7_nonce'] ) ), 'pressio_crm_cf7_save_' . $form_id ) ) {
			return;
		}

		if ( ! current_user_can( 'wpcf7_edit_contact_form', $form_id ) ) {
			return;
		}

		$raw = isset( $_POST['pressio_crm_cf7'] ) && is_array( $_POST['pressio_crm_cf7'] )
			? $_POST['pressio_crm_cf7']  // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitised below
			: [];

		$allowed_keys = [ 'email', 'first_name', 'last_name', 'phone', 'company' ];
		$config       = [];
		foreach ( $allowed_keys as $key ) {
			if ( isset( $raw[ $key ] ) && '' !== $raw[ $key ] ) {
				$config[ $key ] = sanitize_text_field( $raw[ $key ] );
			}
		}

		$is_active = ! empty( $raw['is_active'] ) ? 1 : 0;
		$object_id = (string) $form_id;

		$existing = IntegrationModel::get_for_object( 'cf7', $object_id );

		if ( $existing ) {
			$existing->update( [
				'config'    => wp_json_encode( $config ),
				'is_active' => $is_active,
			] );
		} else {
			IntegrationModel::create( [
				'type'      => 'cf7',
				'object_id' => $object_id,
				'name'      => $form->title(),
				'config'    => wp_json_encode( $config ),
				'is_active' => $is_active,
			] );
		}
	}

	// -------------------------------------------------------------------------
	// Handle submission
	// -------------------------------------------------------------------------

	public function handle_submission( WPCF7_ContactForm $form ): void {
		$form_id = (string) $form->id();
		$mapping = $this->get_mapping( $form_id );

		if ( ! $mapping || ! $mapping->is_active ) {
			return;
		}

		$config = json_decode( $mapping->config ?? '{}', true );
		if ( ! is_array( $config ) || empty( $config ) ) {
			return;
		}

		$submission = \WPCF7_Submission::get_instance();
		if ( ! $submission ) {
			return;
		}

		$posted = $submission->get_posted_data();

		// Build contact data from mapped field names.
		$contact_data = [ 'source' => 'cf7' ];
		$string_fields = [ 'email', 'first_name', 'last_name', 'phone', 'company' ];

		foreach ( $string_fields as $field ) {
			if ( empty( $config[ $field ] ) ) {
				continue;
			}

			$cf7_key = $config[ $field ];
			$value   = $posted[ $cf7_key ] ?? '';

			if ( is_array( $value ) ) {
				$value = implode( ', ', $value );
			}

			$value = sanitize_text_field( $value );
			if ( 'email' === $field ) {
				$value = sanitize_email( $value );
			}

			if ( '' !== $value ) {
				$contact_data[ $field ] = $value;
			}
		}

		// Email is required to create/update a contact.
		if ( empty( $contact_data['email'] ) ) {
			return;
		}

		$existing = ContactModel::find_by_email( $contact_data['email'] );

		$is_new = false;

		if ( $existing ) {
			// Update with any newly provided values, without overwriting non-empty existing values.
			$update_data = [];
			foreach ( $contact_data as $key => $value ) {
				if ( 'source' === $key || 'email' === $key ) {
					continue;
				}
				if ( '' !== $value && empty( $existing->{$key} ) ) {
					$update_data[ $key ] = $value;
				}
			}

			if ( ! empty( $update_data ) ) {
				$existing->update( $update_data );
			}

			$contact_id = (int) $existing->id;
		} else {
			$contact    = ContactModel::create( $contact_data );
			$contact_id = (int) $contact->id;
			$is_new     = true;
		}

		// For existing contacts, log the form submission activity.
		// For new contacts, ContactModel::create() already logged contact_created_from_form.
		if ( ! $is_new ) {
			ActivityModel::log( [
				'contact_id' => $contact_id,
				'type'       => 'contact_created_from_form',
				'title'      => sprintf(
					/* translators: %s: CF7 form title */
					__( 'Submitted form: %s', 'pressio-crm' ),
					sanitize_text_field( $form->title() )
				),
				'meta'       => [
					'source'    => 'cf7',
					'form_name' => sanitize_text_field( $form->title() ),
					'form_id'   => $form_id,
				],
			] );
		}

		do_action( 'pressio_crm_contact_created_from_form', $contact_id, 'cf7', $posted );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_mapping( string $form_id ): ?IntegrationModel {
		return IntegrationModel::get_for_object( 'cf7', $form_id );
	}
}
