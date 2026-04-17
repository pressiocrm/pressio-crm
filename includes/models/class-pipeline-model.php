<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;
use RuntimeException;

class PipelineModel extends Model {

	protected static string $table = 'pcrm_pipelines';

	protected static array $fillable = [
		'name',
		'is_default',
		'position',
	];

	protected static array $casts = [
		'id'         => 'int',
		'is_default' => 'bool',
		'position'   => 'int',
	];

	/**
	 * Enforce the pipeline limit before inserting.
	 *
	 * The pressio_crm_pipeline_limit filter defaults to 1 (free tier). Pro overrides
	 * it to PHP_INT_MAX. Throws RuntimeException when the limit is reached so
	 * controllers can return a clean 403/402 error to the frontend.
	 *
	 * @throws RuntimeException When pipeline count is at the allowed limit.
	 */
	public static function create( array $data ) {
		// Note: check-then-insert is not atomic. Acceptable for single-user admin context.
		$limit   = (int) apply_filters( 'pressio_crm_pipeline_limit', 1 );
		$current = static::query()->count();

		if ( $current >= $limit ) {
			throw new RuntimeException( esc_html__( 'Pipeline limit reached.', 'pressio-crm' ) );
		}

		return parent::create( $data );
	}

	public static function get_default(): ?self {
		return static::query()->where( 'is_default', 1, '%d' )->first();
	}

	/**
	 * Return all pipelines, each with a populated `stages` property.
	 *
	 * Avoids N+1 by fetching all stages in a single query and grouping
	 * them in PHP before attaching to the parent pipeline object.
	 *
	 * @return static[]
	 */
	public static function get_with_stages(): array {
		$pipelines = static::query()->order_by( 'position', 'ASC' )->get();

		if ( empty( $pipelines ) ) {
			return [];
		}

		// Collect all pipeline IDs in one pass.
		$pipeline_ids = array_map( static fn( $p ) => (int) $p->id, $pipelines );

		// Fetch all stages for all pipelines in a single query.
		$all_stages = PipelineStageModel::query()
			->where_in( 'pipeline_id', $pipeline_ids, '%d' )
			->order_by( 'position', 'ASC' )
			->get();

		// Group stages by pipeline_id.
		$stages_by_pipeline = [];
		foreach ( $all_stages as $stage ) {
			$stages_by_pipeline[ (int) $stage->pipeline_id ][] = $stage;
		}

		// Attach stages to each pipeline.
		foreach ( $pipelines as $pipeline ) {
			$pipeline->stages = $stages_by_pipeline[ (int) $pipeline->id ] ?? [];
		}

		return $pipelines;
	}
}
