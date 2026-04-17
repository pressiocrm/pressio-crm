<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Models\TagModel;
use PressioCRM\API\RestTags;

class TagsModule extends Module {

	public function register(): void {
		$this->container->singleton( 'model.tag', fn() => new TagModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestTags() )->register_routes();
	}
}
