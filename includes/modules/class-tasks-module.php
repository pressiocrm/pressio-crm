<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Models\TaskModel;
use PressioCRM\API\RestTasks;

class TasksModule extends Module {

	public function register(): void {
		$this->container->singleton( 'model.task', fn() => new TaskModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestTasks() )->register_routes();
	}
}
