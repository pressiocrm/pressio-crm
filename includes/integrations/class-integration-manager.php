<?php
namespace PressioCRM\Integrations;

defined( 'ABSPATH' ) || exit;

class IntegrationManager {

	/** @var array<string, object> Registered integrations keyed by slug. */
	private array $integrations = [];

	/**
	 * Register an integration with the manager.
	 * Each integration must expose a `slug()` method returning a unique string.
	 */
	public function register( object $integration ): void {
		$slug = $integration->slug();
		$this->integrations[ $slug ] = $integration;
		$integration->register();
	}

	/** @return array<string, object> */
	public function get_all(): array {
		return $this->integrations;
	}

	public function get( string $slug ): ?object {
		return $this->integrations[ $slug ] ?? null;
	}

	public function has( string $slug ): bool {
		return isset( $this->integrations[ $slug ] );
	}
}
