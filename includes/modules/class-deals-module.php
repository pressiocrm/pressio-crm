<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Models\DealModel;
use PressioCRM\Models\DealMetaModel;
use PressioCRM\API\RestDeals;

class DealsModule extends Module {

	public function register(): void {
		$this->container->singleton( 'model.deal', fn() => new DealModel() );
		$this->container->singleton( 'model.deal_meta', fn() => new DealMetaModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestDeals() )->register_routes();
	}
}
