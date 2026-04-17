<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;

class EmailModel extends Model {

	protected static string $table = 'pcrm_emails';

	protected static array $fillable = [
		'contact_id',
		'email_hash',
		'from_email',
		'to_email',
		'subject',
		'body',
		'status',
		'sent_at',
		'failed_at',
		'error_message',
	];

	protected static array $casts = [
		'id'         => 'int',
		'contact_id' => 'int',
	];

	public static function get_for_contact( int $contact_id, int $per_page = 20, int $page = 1 ): array {
		return static::query()
			->where( 'contact_id', $contact_id, '%d' )
			->order_by( 'created_at', 'DESC' )
			->paginate( $per_page, $page );
	}

	public static function find_by_hash( string $hash ): ?self {
		return static::query()
			->where( 'email_hash', sanitize_text_field( $hash ) )
			->first();
	}

	public function mark_sent(): bool {
		return $this->update( [
			'status'  => 'sent',
			'sent_at' => current_time( 'mysql' ),
		] );
	}

	public function mark_failed( string $error ): bool {
		return $this->update( [
			'status'        => 'failed',
			'failed_at'     => current_time( 'mysql' ),
			'error_message' => sanitize_text_field( $error ),
		] );
	}
}
