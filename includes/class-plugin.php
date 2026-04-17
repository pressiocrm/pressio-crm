<?php
namespace PressioCRM;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Container;
use PressioCRM\Framework\Module;
use PressioCRM\Modules\DatabaseModule;
use PressioCRM\Modules\SettingsModule;
use PressioCRM\Modules\ContactsModule;
use PressioCRM\Modules\TagsModule;
use PressioCRM\Modules\PipelineModule;
use PressioCRM\Modules\DealsModule;
use PressioCRM\Modules\TasksModule;
use PressioCRM\Modules\ActivitiesModule;
use PressioCRM\Modules\DashboardModule;
use PressioCRM\Modules\IntegrationsModule;
use PressioCRM\Modules\EmailModule;
use PressioCRM\Modules\AdminModule;

final class Plugin {

	/** @var Plugin|null */
	private static ?Plugin $instance = null;

	/** @var Container */
	private Container $container;

	/** @var Module[] */
	private array $modules = [];

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor — use instance().
	 * Initialises container and loads modules immediately so hooks are
	 * registered before WordPress fires 'init'.
	 */
	private function __construct() {
		$this->container = new Container();
		$this->bind_core_services();
		$this->load_modules();
		$this->init_hooks();
	}

	public function make( string $abstract ) {
		return $this->container->make( $abstract );
	}

	public function container(): Container {
		return $this->container;
	}

	private function bind_core_services(): void {
		// The $wpdb wrapper — so models never import global directly.
		$this->container->singleton( 'db', function () {
			global $wpdb;
			return $wpdb;
		} );
	}

	/**
	 * Instantiate all modules and call register() on each.
	 *
	 * Modules are filtered so Pro can inject its own modules:
	 *   add_filter( 'pressio_crm_modules', fn($m) => [...$m, My_Pro_Module::class] );
	 *
	 * register() runs immediately (before 'init') — use it only to bind
	 * things into the container.
	 * boot() is hooked to 'init' — safe to call WP functions there.
	 */
	private function load_modules(): void {
		$module_classes = apply_filters( 'pressio_crm_modules', [
			DatabaseModule::class,
			SettingsModule::class,
			ContactsModule::class,
			TagsModule::class,
			PipelineModule::class,
			DealsModule::class,
			TasksModule::class,
			ActivitiesModule::class,
			DashboardModule::class,
			IntegrationsModule::class,
			EmailModule::class,
			AdminModule::class,
		] );

		foreach ( $module_classes as $class ) {
			$module = new $class( $this->container );
			$module->register();
			$this->modules[] = $module;
		}
	}

	private function init_hooks(): void {
		// Boot all modules on 'init'.
		add_action( 'init', [ $this, 'boot_modules' ] );
	}

	public function boot_modules(): void {
		foreach ( $this->modules as $module ) {
			$module->boot();
		}
	}

	// Prevent cloning / unserialization of the singleton.
	public function __clone() {}
	public function __wakeup() {}
}
