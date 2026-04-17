<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;

class IntegrationModel extends Model {

	protected static string $table = 'pcrm_integrations';

	protected static array $fillable = [
		'type',
		'object_id',
		'name',
		'config',
		'is_active',
	];

	protected static array $casts = [
		'id'        => 'int',
		'is_active' => 'bool',
	];

	/** @return static[] */
	public static function get_active(): array {
		return static::query()
			->where( 'is_active', 1, '%d' )
			->get();
	}

	/**
	 * @param string $type Integration type slug (e.g. 'cf7').
	 * @return static[]
	 */
	public static function get_by_type( string $type ): array {
		return static::query()
			->where( 'type', $type )
			->where( 'is_active', 1, '%d' )
			->get();
	}

	/**
	 * Find the integration config for a specific type + object_id pair.
	 *
	 * Used by the CF7 integration to look up the field mapping for a given
	 * form ID at submission time.
	 *
	 * @param string $type      Integration type slug (e.g. 'cf7').
	 * @param string $object_id Identifier of the source object (e.g. CF7 form ID).
	 */
	public static function get_for_object( string $type, string $object_id ): ?self {
		return static::query()
			->where( 'type', $type )
			->where( 'object_id', $object_id )
			->where( 'is_active', 1, '%d' )
			->first();
	}
}
