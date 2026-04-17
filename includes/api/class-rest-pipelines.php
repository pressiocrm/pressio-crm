<?php
namespace PressioCRM\API;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Controller;
use PressioCRM\Models\DealModel;
use PressioCRM\Models\PipelineModel;
use PressioCRM\Models\PipelineStageModel;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class RestPipelines extends Controller {

	protected $rest_base = 'pipelines';

	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'require_contacts_cap' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => [ $this, 'require_settings_cap' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update_item' ],
					'permission_callback' => [ $this, 'require_settings_cap' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete_item' ],
					'permission_callback' => [ $this, 'require_settings_cap' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/stages',
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_stages' ],
				'permission_callback' => [ $this, 'require_settings_cap' ],
			]
		);
	}

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$pipelines = PipelineModel::get_with_stages();

		$data = array_map( [ $this, 'format_pipeline' ], $pipelines );

		return $this->ok( $data );
	}

	public function create_item( $request ) {
		$name = $this->get_string_param( $request, 'name' );

		if ( empty( $name ) ) {
			return $this->validation_error( [ 'name' => __( 'Pipeline name is required.', 'pressio-crm' ) ] );
		}

		$is_default = (bool) $request->get_param( 'is_default' );

		try {
			$pipeline = PipelineModel::create( [
				'name'       => $name,
				'is_default' => $is_default ? 1 : 0,
			] );
		} catch ( \RuntimeException $e ) {
			return $this->bad_request( 'pipeline_limit', $e->getMessage() );
		}

		return $this->created( $this->format_pipeline( $pipeline ) );
	}

	public function update_item( $request ) {
		$id       = absint( $request->get_param( 'id' ) );
		$pipeline = PipelineModel::find( $id );

		if ( null === $pipeline ) {
			return $this->not_found();
		}

		$data = [];

		$name = $request->get_param( 'name' );
		if ( null !== $name ) {
			$data['name'] = sanitize_text_field( wp_unslash( $name ) );
		}

		$is_default = $request->get_param( 'is_default' );
		if ( null !== $is_default ) {
			$data['is_default'] = (bool) $is_default ? 1 : 0;
		}

		if ( ! empty( $data ) ) {
			$pipeline->update( $data );
		}

		return $this->ok( $this->format_pipeline( $pipeline ) );
	}

	public function delete_item( $request ) {
		$id       = absint( $request->get_param( 'id' ) );
		$pipeline = PipelineModel::find( $id );

		if ( null === $pipeline ) {
			return $this->not_found();
		}

		$total = PipelineModel::query()->count();
		if ( $total <= 1 ) {
			return $this->bad_request(
				'pressio_crm_last_pipeline',
				__( 'Cannot delete the only pipeline.', 'pressio-crm' )
			);
		}

		if ( (bool) $pipeline->is_default ) {
			return $this->bad_request(
				'pressio_crm_default_pipeline',
				__( 'Cannot delete the default pipeline. Set another pipeline as default first.', 'pressio-crm' )
			);
		}

		// Soft-delete all deals in this pipeline in one query.
		DealModel::bulk_soft_delete_for_pipeline( $id );

		// Hard-delete all pipeline stages in one query.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( $wpdb->prefix . 'pcrm_pipeline_stages', [ 'pipeline_id' => $id ], [ '%d' ] );

		$pipeline->force_delete();

		return $this->no_content();
	}

	public function update_stages( WP_REST_Request $request ) {
		$id       = absint( $request->get_param( 'id' ) );
		$pipeline = PipelineModel::find( $id );

		if ( null === $pipeline ) {
			return $this->not_found();
		}

		$stages = $request->get_param( 'stages' );
		if ( empty( $stages ) || ! is_array( $stages ) ) {
			return $this->bad_request( 'pressio_crm_missing_stages', __( 'stages array is required.', 'pressio-crm' ) );
		}

		// Preload all stages for this pipeline in one query to avoid N SELECT queries.
		$pipeline_stages = PipelineStageModel::query()->where( 'pipeline_id', $id, '%d' )->get();
		$stages_by_id    = [];
		foreach ( $pipeline_stages as $ps ) {
			$stages_by_id[ (int) $ps->id ] = $ps;
		}

		$ordered_ids = [];

		foreach ( $stages as $stage_data ) {
			$stage_id = absint( $stage_data['id'] ?? 0 );
			if ( ! $stage_id ) {
				continue;
			}

			$stage = $stages_by_id[ $stage_id ] ?? null;
			if ( null === $stage ) {
				continue;
			}

			$ordered_ids[] = $stage_id;

			$update = [];

			if ( isset( $stage_data['name'] ) ) {
				$update['name'] = sanitize_text_field( wp_unslash( $stage_data['name'] ) );
			}
			if ( isset( $stage_data['color'] ) ) {
				$update['color'] = sanitize_text_field( wp_unslash( $stage_data['color'] ) );
			}
			if ( isset( $stage_data['type'] ) ) {
				$clean = sanitize_text_field( wp_unslash( $stage_data['type'] ) );
				if ( in_array( $clean, [ 'open', 'won', 'lost' ], true ) ) {
					$update['type'] = $clean;
				}
			}
			if ( isset( $stage_data['win_probability'] ) ) {
				$update['win_probability'] = absint( $stage_data['win_probability'] );
			}

			if ( ! empty( $update ) ) {
				$stage->update( $update );
			}
		}

		if ( ! empty( $ordered_ids ) ) {
			PipelineStageModel::reorder( $ordered_ids );
		}

		$updated_pipeline = PipelineModel::find( $id );
		$with_stages      = PipelineModel::get_with_stages();

		foreach ( $with_stages as $p ) {
			if ( (int) $p->id === $id ) {
				return $this->ok( $this->format_pipeline( $p ) );
			}
		}

		return $this->ok( $this->format_pipeline( $updated_pipeline ) );
	}

	public function format_pipeline( object $pipeline ): array {
		$stages = [];
		if ( isset( $pipeline->stages ) && is_array( $pipeline->stages ) ) {
			foreach ( $pipeline->stages as $stage ) {
				$stages[] = $this->format_stage( $stage );
			}
		}

		return [
			'id'         => (int) $pipeline->id,
			'name'       => (string) $pipeline->name,
			'is_default' => (bool) $pipeline->is_default,
			'position'   => (int) $pipeline->position,
			'stages'     => $stages,
		];
	}

	private function format_stage( object $stage ): array {
		return [
			'id'              => (int) $stage->id,
			'pipeline_id'     => (int) $stage->pipeline_id,
			'name'            => (string) $stage->name,
			'slug'            => (string) $stage->slug,
			'type'            => (string) $stage->type,
			'color'           => (string) $stage->color,
			'position'        => (float) $stage->position,
			'win_probability' => (int) $stage->win_probability,
		];
	}
}
