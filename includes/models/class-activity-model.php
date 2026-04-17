<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;

class ActivityModel extends Model {

	protected static string $table = 'pcrm_activities';

	protected static array $fillable = [
		'contact_id',
		'deal_id',
		'user_id',
		'type',
		'title',
		'description',
		'meta',
	];

	protected static array $casts = [
		'contact_id' => 'int',
		'deal_id'    => 'int',
		'user_id'    => 'int',
	];

	/**
	 * Log a CRM activity.
	 *
	 * Auto-sets user_id to the current WP user when not supplied.
	 * Encodes a meta array to JSON automatically.
	 * Fires pressio_crm_activity_logged after insert.
	 *
	 * @param array $data Must include at minimum: type, title.
	 */
	public static function log( array $data ): self {
		if ( empty( $data['user_id'] ) ) {
			$data['user_id'] = get_current_user_id();
		}

		// Encode meta array to JSON if passed as array.
		if ( isset( $data['meta'] ) && is_array( $data['meta'] ) ) {
			$data['meta'] = wp_json_encode( $data['meta'] );
		}

		$instance = static::create( $data );

		do_action( 'pressio_crm_activity_logged', (int) $instance->id, $instance->type, $data );

		return $instance;
	}

	public static function get_for_contact( int $contact_id, int $per_page = 20, int $page = 1 ): array {
		return static::query()
			->where( 'contact_id', $contact_id, '%d' )
			->order_by( 'created_at', 'DESC' )
			->paginate( $per_page, $page );
	}

	public static function get_for_deal( int $deal_id, int $per_page = 20, int $page = 1 ): array {
		return static::query()
			->where( 'deal_id', $deal_id, '%d' )
			->order_by( 'created_at', 'DESC' )
			->paginate( $per_page, $page );
	}

	/**
	 * @param int $limit Maximum rows to return (default 20, max 100).
	 * @return static[]
	 */
	public static function get_recent( int $limit = 20 ): array {
		$limit = min( $limit, 100 );

		return static::query()
			->order_by( 'created_at', 'DESC' )
			->limit( $limit )
			->get();
	}
}
