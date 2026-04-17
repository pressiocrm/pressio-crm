<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;
use RuntimeException;

class DealModel extends Model {

	protected static string $table = 'pcrm_deals';

	protected static array $fillable = [
		'contact_id',
		'pipeline_id',
		'stage_id',
		'owner_id',
		'title',
		'value',
		'currency',
		'expected_close',
		'closed_at',
		'status',
		'position',
		'notes',
	];

	protected static array $casts = [
		'id'          => 'int',
		'contact_id'  => 'int',
		'pipeline_id' => 'int',
		'stage_id'    => 'int',
		'owner_id'    => 'int',
		'value'       => 'float',
	];

	protected static function has_soft_deletes(): bool {
		return true;
	}

	/**
	 * @throws RuntimeException On DB insert failure.
	 */
	public static function create( array $data ) {
		$instance = parent::create( $data );
		$id       = (int) $instance->id;

		do_action( 'pressio_crm_deal_created', $id, $data );

		ActivityModel::log( [
			'deal_id'    => $id,
			'contact_id' => isset( $data['contact_id'] ) ? (int) $data['contact_id'] : null,
			'type'       => 'deal_created',
			'title'      => __( 'Deal created', 'pressio-crm' ),
		] );

		return $instance;
	}

	public function update( array $data ): bool {
		$result = parent::update( $data );

		if ( $result ) {
			// pressio_crm_deal_updated — Pro can hook general deal edits (not stage changes).
			do_action( 'pressio_crm_deal_updated', (int) $this->id, $data );
		}

		return $result;
	}

	public function delete(): bool {
		$result = parent::delete();

		if ( $result ) {
			do_action( 'pressio_crm_deal_deleted', (int) $this->id );
		}

		return $result;
	}

	/**
	 * Move this deal to a new pipeline stage.
	 *
	 * Determines the correct status (open/won/lost) by inspecting the
	 * destination stage's `type` field. Sets closed_at when the deal
	 * reaches a terminal stage (won or lost) and clears it when returning
	 * to an open stage. Fires stage_changed, and optionally deal_won or
	 * deal_lost, then logs a stage_change activity.
	 *
	 * @param int   $new_stage_id Destination stage ID.
	 * @param float $new_position DECIMAL position within the new stage column.
	 */
	public function move_stage( int $new_stage_id, float $new_position ): bool {
		$old_stage_id = (int) $this->stage_id;

		// Load the old stage before the update so we can record its name in the activity log.
		$old_stage = PipelineStageModel::find( $old_stage_id );

		// Load destination stage to determine status and validate it belongs to this pipeline.
		$new_stage = PipelineStageModel::find( $new_stage_id );
		if ( null === $new_stage || (int) $new_stage->pipeline_id !== (int) $this->pipeline_id ) {
			return false;
		}

		$update_data = [
			'stage_id' => $new_stage_id,
			'position' => $new_position,
		];

		$stage_type = $new_stage->type ?? 'open';

		if ( 'won' === $stage_type ) {
			$update_data['status']    = 'won';
			$update_data['closed_at'] = current_time( 'mysql' );
		} elseif ( 'lost' === $stage_type ) {
			$update_data['status']    = 'lost';
			$update_data['closed_at'] = current_time( 'mysql' );
		} else {
			$update_data['status']    = 'open';
			$update_data['closed_at'] = null;
		}

		$result = parent::update( $update_data );

		// $wpdb->update() returns 0 (not false) when the row data is unchanged.
		// Only treat an explicit false as a failure.
		if ( false === $result ) {
			return false;
		}

		$deal_id = (int) $this->id;

		// Fire the general stage-changed hook.
		do_action( 'pressio_crm_deal_stage_changed', $deal_id, $new_stage_id, $old_stage_id );

		// Fire terminal-state hooks.
		if ( 'won' === $stage_type ) {
			do_action( 'pressio_crm_deal_won', $deal_id );
		} elseif ( 'lost' === $stage_type ) {
			do_action( 'pressio_crm_deal_lost', $deal_id );
		}

		// Log the stage change as an activity.
		ActivityModel::log( [
			'deal_id'    => $deal_id,
			'contact_id' => isset( $this->contact_id ) ? (int) $this->contact_id : null,
			'type'       => 'stage_change',
			'title'      => '',
			'meta'       => [
				'old_stage_id'   => $old_stage_id,
				'new_stage_id'   => $new_stage_id,
				'old_stage_name' => $old_stage->name ?? '',
				'new_stage_name' => $new_stage->name ?? '',
			],
		] );

		return true;
	}

	/**
	 * Soft-delete all deals belonging to a pipeline in a single UPDATE query.
	 * Used when deleting a pipeline to avoid N+1 individual deal deletes.
	 */
	public static function bulk_soft_delete_for_pipeline( int $pipeline_id ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( $wpdb->prepare(
			"UPDATE `{$wpdb->prefix}pcrm_deals` SET deleted_at = %s WHERE pipeline_id = %d AND deleted_at IS NULL",
			current_time( 'mysql' ),
			$pipeline_id
		) );
	}

	public function get_meta( string $key ): ?string {
		return DealMetaModel::get_meta( (int) $this->id, $key );
	}

	public function set_meta( string $key, string $value ): void {
		DealMetaModel::set_meta( (int) $this->id, $key, $value );
	}
}
