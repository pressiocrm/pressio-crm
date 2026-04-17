<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;

class PipelineStageModel extends Model {

	protected static string $table = 'pcrm_pipeline_stages';

	protected static array $fillable = [
		'pipeline_id',
		'name',
		'slug',
		'type',
		'color',
		'position',
		'win_probability',
	];

	protected static array $casts = [
		'id'              => 'int',
		'pipeline_id'     => 'int',
		'position'        => 'float',
		'win_probability' => 'int',
	];

	/** @return static[] */
	public static function get_for_pipeline( int $pipeline_id ): array {
		return static::query()
			->where( 'pipeline_id', $pipeline_id, '%d' )
			->order_by( 'position', 'ASC' )
			->get();
	}

	/**
	 * Assign fresh DECIMAL positions to stages given their desired order.
	 *
	 * On a full reorder (e.g. settings UI drag-drop) we reassign all positions
	 * starting at 1000.0 with a 1000.0 step. This gives ample room for future
	 * fractional inserts between any two adjacent stages without another reorder.
	 *
	 * @param int[] $ordered_ids Stage IDs in the desired display order.
	 */
	public static function reorder( array $ordered_ids ): void {
		global $wpdb;

		$table    = $wpdb->prefix . static::$table;
		$position = 1000.0;
		$step     = 1000.0;

		foreach ( $ordered_ids as $stage_id ) {
			$stage_id = (int) $stage_id;
			if ( $stage_id <= 0 ) {
				continue;
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[ 'position' => number_format( $position, 10, '.', '' ) ],
				[ 'id' => $stage_id ],
				[ '%s' ],
				[ '%d' ]
			);

			$position += $step;
		}
	}
}
