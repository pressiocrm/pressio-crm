<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Database\Schema;
use PressioCRM\Database\Migrator;

class DatabaseModule extends Module {

	public function register(): void {
		$this->container->singleton( 'db.schema', fn() => new Schema() );

		$this->container->singleton(
			'db.migrator',
			fn() => new Migrator( PRESSIO_CRM_PATH . 'includes/database/migrations' )
		);
	}

	public function boot(): void {
		// Priority 1 ensures migrations run before any REST controller registers routes
		// at the default priority of 10 on rest_api_init (which fires after init).
		add_action( 'init', [ $this, 'maybe_migrate' ], 1 );

		// Flush rewrite rules once after activation via transient.
		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ] );
	}

	public function maybe_migrate(): void {
		$migrator = $this->container->make( 'db.migrator' );

		if ( $migrator->needs_migration() ) {
			$migrator->run();
		}
	}

	public function maybe_flush_rewrite_rules(): void {
		if ( get_transient( 'pressio_crm_flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
			delete_transient( 'pressio_crm_flush_rewrite_rules' );
		}
	}
}
