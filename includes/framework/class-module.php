<?php
namespace PressioCRM\Framework;

defined( 'ABSPATH' ) || exit;

abstract class Module {

	/** @var Container */
	protected Container $container;

	public function __construct( Container $container ) {
		$this->container = $container;
	}

	/**
	 * Bind services into the container.
	 * Must NOT call any WordPress functions.
	 */
	abstract public function register(): void;

	/**
	 * Register hooks, routes, menus — runs on WordPress 'init'.
	 */
	abstract public function boot(): void;
}
