<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\API\RestSettings;

class SettingsModule extends Module {

	public function register(): void {
		// Settings are stored in wp_options — no model binding needed.
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestSettings() )->register_routes();
	}
}
