<?php
namespace PressioCRM\Framework;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

class Container {

	/** @var array<string, callable> */
	private array $bindings = [];

	/** @var array<string, mixed> */
	private array $instances = [];

	public function bind( string $abstract, callable $factory ): void {
		$this->bindings[ $abstract ] = $factory;
		// Clear any cached singleton so re-binding takes effect.
		unset( $this->instances[ $abstract ] );
	}

	/**
	 * The factory is called once; subsequent calls return the cached instance.
	 */
	public function singleton( string $abstract, callable $factory ): void {
		$this->bindings[ $abstract ] = function () use ( $abstract, $factory ) {
			if ( ! isset( $this->instances[ $abstract ] ) ) {
				$this->instances[ $abstract ] = $factory( $this );
			}
			return $this->instances[ $abstract ];
		};
	}

	/**
	 * Register a pre-built instance as a singleton.
	 *
	 * @param mixed $instance Already-constructed object.
	 */
	public function instance( string $abstract, $instance ): void {
		$this->instances[ $abstract ] = $instance;
		$this->bindings[ $abstract ]  = fn() => $instance;
	}

	/**
	 * @throws RuntimeException When no binding is registered.
	 */
	public function make( string $abstract ) {
		if ( ! isset( $this->bindings[ $abstract ] ) ) {
			throw new RuntimeException(
				sprintf( 'Pressio CRM Container: no binding registered for "%s".', esc_html( $abstract ) )
			);
		}

		return ( $this->bindings[ $abstract ] )( $this );
	}

	public function has( string $abstract ): bool {
		return isset( $this->bindings[ $abstract ] );
	}
}
