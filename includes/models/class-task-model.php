<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;
use RuntimeException;

class TaskModel extends Model {

	protected static string $table = 'pcrm_tasks';

	protected static array $fillable = [
		'contact_id',
		'deal_id',
		'owner_id',
		'title',
		'description',
		'type',
		'status',
		'priority',
		'due_date',
		'completed_at',
	];

	protected static array $casts = [
		'id'         => 'int',
		'contact_id' => 'int',
		'deal_id'    => 'int',
		'owner_id'   => 'int',
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

		do_action( 'pressio_crm_task_created', $id, $data );

		ActivityModel::log( [
			'contact_id' => isset( $data['contact_id'] ) ? (int) $data['contact_id'] : null,
			'deal_id'    => isset( $data['deal_id'] ) ? (int) $data['deal_id'] : null,
			'type'       => 'task_created',
			'title'      => __( 'Task created', 'pressio-crm' ),
			'meta'       => [
				'task_id' => $id,
				'title'   => $data['title'] ?? '',
			],
		] );

		return $instance;
	}

	/**
	 * Mark this task as completed.
	 *
	 * Sets status to 'completed', stamps completed_at, fires
	 * pressio_crm_task_completed, and logs the activity.
	 */
	public function complete(): bool {
		$result = $this->update( [
			'status'       => 'completed',
			'completed_at' => current_time( 'mysql' ),
		] );

		if ( $result ) {
			$task_id = (int) $this->id;

			do_action( 'pressio_crm_task_completed', $task_id );

			ActivityModel::log( [
				'contact_id' => isset( $this->contact_id ) ? (int) $this->contact_id : null,
				'deal_id'    => isset( $this->deal_id ) ? (int) $this->deal_id : null,
				'type'       => 'task_completed',
				'title'      => __( 'Task completed', 'pressio-crm' ),
				'meta'       => [
					'task_id' => $task_id,
					'title'   => $this->title ?? '',
				],
			] );
		}

		return $result;
	}

	public function delete(): bool {
		$result = parent::delete();

		if ( $result ) {
			// pressio_crm_task_deleted — available for Pro extensions.
			do_action( 'pressio_crm_task_deleted', (int) $this->id );
		}

		return $result;
	}
}
