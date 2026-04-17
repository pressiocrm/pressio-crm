<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Models\ActivityModel;
use PressioCRM\API\RestActivities;

class ActivitiesModule extends Module {

	public function register(): void {
		$this->container->singleton( 'model.activity', fn() => new ActivityModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestActivities() )->register_routes();
	}
}
