<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Integrations\IntegrationManager;
use PressioCRM\Integrations\CF7Integration;

class IntegrationsModule extends Module {

	public function register(): void {
		$this->container->singleton(
			'integration.manager',
			fn() => new IntegrationManager()
		);
	}

	public function boot(): void {
		// Priority 20 on init — all plugins are already fully loaded by the time
		// init fires, so class_exists() checks for CF7 and other plugins are reliable.
		// Using init instead of plugins_loaded because boot() itself is called on init —
		// plugins_loaded has already fired by the time boot() runs.
		add_action( 'init', [ $this, 'init_integrations' ], 20 );
	}

	public function init_integrations(): void {
		$manager = $this->container->make( 'integration.manager' );

		/**
		 * Fires so Pro add-ons and third-party code can register their own integrations.
		 *
		 * @param IntegrationManager $manager
		 */
		do_action( 'pressio_crm_register_integration', $manager );

		if ( class_exists( 'WPCF7' ) ) {
			$manager->register( new CF7Integration() );
		}
	}
}
