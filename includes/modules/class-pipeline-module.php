<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Models\PipelineModel;
use PressioCRM\Models\PipelineStageModel;
use PressioCRM\API\RestPipelines;

class PipelineModule extends Module {

	public function register(): void {
		$this->container->singleton( 'model.pipeline', fn() => new PipelineModel() );
		$this->container->singleton( 'model.pipeline_stage', fn() => new PipelineStageModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestPipelines() )->register_routes();
	}
}
