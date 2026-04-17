<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;

class DealMetaModel extends Model {

	protected static string $table = 'pcrm_deal_meta';

	protected static array $fillable = [
		'deal_id',
		'meta_key',
		'meta_value',
	];

	protected static array $casts = [
		'deal_id' => 'int',
	];

	/**
	 * @return string|null Value string, or null if not found.
	 */
	public static function get_meta( int $deal_id, string $key ): ?string {
		global $wpdb;

		$table = $wpdb->prefix . static::$table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom meta table, name is internally derived.
		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_value FROM `{$table}` WHERE deal_id = %d AND meta_key = %s LIMIT 1",
				$deal_id,
				$key
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return null !== $value ? (string) $value : null;
	}

	/**
	 * Set a meta value for a deal (upsert — inserts or updates as needed).
	 */
	public static function set_meta( int $deal_id, string $key, string $value ): void {
		global $wpdb;

		$table = $wpdb->prefix . static::$table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom meta table, name is internally derived.
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT meta_id FROM `{$table}` WHERE deal_id = %d AND meta_key = %s LIMIT 1",
				$deal_id,
				$key
			)
		);

		if ( $exists ) {
			$wpdb->update(
				$table,
				[ 'meta_value' => $value ],
				[ 'deal_id' => $deal_id, 'meta_key' => $key ]
			);
		} else {
			$wpdb->insert(
				$table,
				[
					'deal_id'    => $deal_id,
					'meta_key'   => $key,
					'meta_value' => $value,
				]
			);
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	public static function delete_meta( int $deal_id, string $key ): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery -- Custom meta table.
		$wpdb->delete(
			$wpdb->prefix . static::$table,
			[
				'deal_id'  => $deal_id,
				'meta_key' => $key,
			]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery
	}

	/**
	 * @return array<string,string> Associative array of meta_key => meta_value.
	 */
	public static function get_all_meta( int $deal_id ): array {
		global $wpdb;

		$table = $wpdb->prefix . static::$table;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Custom meta table, name is internally derived.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM `{$table}` WHERE deal_id = %d ORDER BY meta_key ASC",
				$deal_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.SlowDBQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( ! $rows ) {
			return [];
		}

		$map = [];
		foreach ( $rows as $row ) {
			$map[ $row->meta_key ] = (string) $row->meta_value;
		}

		return $map;
	}
}
