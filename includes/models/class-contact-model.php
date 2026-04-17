<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;
use RuntimeException;

class ContactModel extends Model {

	protected static string $table = 'pcrm_contacts';

	protected static array $fillable = [
		'owner_id',
		'first_name',
		'last_name',
		'email',
		'phone',
		'company',
		'job_title',
		'address_line_1',
		'address_line_2',
		'city',
		'state',
		'postal_code',
		'country',
		'source',
		'status',
		'notes',
	];

	protected static array $casts = [
		'id'       => 'int',
		'owner_id' => 'int',
	];

	protected static function has_soft_deletes(): bool {
		return true;
	}

	/**
	 * Find a contact by email address. Returns null if not found or soft-deleted.
	 */
	public static function find_by_email( string $email ): ?self {
		$email = sanitize_email( $email );
		if ( empty( $email ) ) {
			return null;
		}

		return static::query()
			->not_deleted()
			->where( 'email', $email )
			->first();
	}

	/**
	 * @throws RuntimeException On DB insert failure.
	 */
	public static function create( array $data ) {
		$instance = parent::create( $data );
		$id       = (int) $instance->id;

		do_action( 'pressio_crm_contact_created', $id, $data );

		$type = ( isset( $data['source'] ) && 'manual' !== $data['source'] ) ? 'contact_created_from_form' : 'contact_created';

		ActivityModel::log( [
			'contact_id' => $id,
			'type'       => $type,
			'title'      => __( 'Contact created', 'pressio-crm' ),
			'meta'       => [
				'source' => $data['source'] ?? 'manual',
			],
		] );

		return $instance;
	}

	public function update( array $data ): bool {
		$old_data = $this->data;
		$result   = parent::update( $data );

		if ( $result ) {
			do_action( 'pressio_crm_contact_updated', (int) $this->id, $data, $old_data );
		}

		return $result;
	}

	public function delete(): bool {
		$result = parent::delete();

		if ( $result ) {
			do_action( 'pressio_crm_contact_deleted', (int) $this->id );
		}

		return $result;
	}

	/** @return TagModel[] */
	public function get_tags(): array {
		return TagModel::get_for_contact( (int) $this->id );
	}

	/**
	 * Attach a tag to this contact.
	 * Uses INSERT IGNORE to silently skip duplicate pivots.
	 */
	public function attach_tag( int $tag_id ): void {
		global $wpdb;

		$pivot = $wpdb->prefix . 'pcrm_contact_tag';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is internally derived.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO `{$pivot}` (contact_id, tag_id) VALUES (%d, %d)",
				(int) $this->id,
				$tag_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public function detach_tag( int $tag_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			$wpdb->prefix . 'pcrm_contact_tag',
			[
				'contact_id' => (int) $this->id,
				'tag_id'     => $tag_id,
			],
			[ '%d', '%d' ]
		);
	}

	/**
	 * Replace all tags on this contact with the given set of IDs.
	 * Passing an empty array removes all tags.
	 *
	 * @param int[] $tag_ids
	 */
	public function sync_tags( array $tag_ids ): void {
		global $wpdb;

		$pivot      = $wpdb->prefix . 'pcrm_contact_tag';
		$contact_id = (int) $this->id;

		// Remove all existing tag associations for this contact.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $pivot, [ 'contact_id' => $contact_id ], [ '%d' ] );

		if ( empty( $tag_ids ) ) {
			return;
		}

		// Re-insert the new set.
		foreach ( $tag_ids as $tag_id ) {
			$tag_id = (int) $tag_id;
			if ( $tag_id > 0 ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is internally derived.
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO `{$pivot}` (contact_id, tag_id) VALUES (%d, %d)",
						$contact_id,
						$tag_id
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
			}
		}
	}

	public function get_meta( string $key ): ?string {
		return ContactMetaModel::get_meta( (int) $this->id, $key );
	}

	public function set_meta( string $key, string $value ): void {
		ContactMetaModel::set_meta( (int) $this->id, $key, $value );
	}

	/** @return array<string,string> */
	public function get_all_meta(): array {
		return ContactMetaModel::get_all_meta( (int) $this->id );
	}
}
