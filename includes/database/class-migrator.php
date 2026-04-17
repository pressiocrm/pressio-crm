<?php
namespace PressioCRM\Database;

defined( 'ABSPATH' ) || exit;

class Migrator {

	/** Option key that stores the current installed DB version. */
	const VERSION_OPTION = 'pressio_crm_db_version';

	/** Directory where numbered migration files live. */
	private string $migrations_dir;

	/**
	 * @param string $migrations_dir Absolute path to the migrations/ directory.
	 */
	public function __construct( string $migrations_dir ) {
		$this->migrations_dir = trailingslashit( $migrations_dir );
	}

	/**
	 * Discover and run all pending migrations. Returns the count run.
	 */
	public function run(): int {
		$installed = $this->get_installed_version();
		$pending   = $this->get_pending_migrations( $installed );

		if ( empty( $pending ) ) {
			return 0;
		}

		$ran = 0;

		foreach ( $pending as $version => $file ) {
			require_once $file;

			$class = $this->version_to_class( $version );

			if ( ! class_exists( $class ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( sprintf( 'Pressio CRM Migrator: migration class "%s" not found in %s', $class, $file ) );
				continue;
			}

			/** @var Migration $migration */
			$migration = new $class();
			$migration->run();

			$this->set_installed_version( $version );
			$ran++;
		}

		return $ran;
	}

	/**
	 * Returns '000' when nothing has been installed yet.
	 */
	public function get_installed_version(): string {
		return (string) get_option( self::VERSION_OPTION, '000' );
	}

	public function needs_migration(): bool {
		$installed = $this->get_installed_version();
		$pending   = $this->get_pending_migrations( $installed );
		return ! empty( $pending );
	}

	/**
	 * Discover migration files newer than $installed.
	 * Files must be named:  NNN-description.php  (e.g. 001-initial-schema.php)
	 *
	 * @param  string $installed Currently installed version, e.g. '001'.
	 * @return array<string, string> Map of version string → absolute file path, sorted ascending.
	 */
	private function get_pending_migrations( string $installed ): array {
		$files  = glob( $this->migrations_dir . '*.php' );
		$result = [];

		if ( empty( $files ) ) {
			return $result;
		}

		foreach ( $files as $file ) {
			$basename = basename( $file, '.php' );

			// Extract the leading numeric version portion: "001-initial-schema" → "001".
			if ( ! preg_match( '/^(\d+)-/', $basename, $matches ) ) {
				continue;
			}

			$version = $matches[1];

			if ( $version > $installed ) {
				$result[ $version ] = $file;
			}
		}

		ksort( $result );

		return $result;
	}

	private function set_installed_version( string $version ): void {
		update_option( self::VERSION_OPTION, $version, false );
	}

	/**
	 * e.g. '001' → 'PressioCRM\Database\Migrations\Migration001'
	 */
	private function version_to_class( string $version ): string {
		return 'PressioCRM\\Database\\Migrations\\Migration' . $version;
	}
}

/**
 * Abstract base class for individual migration files.
 *
 * Each migration in migrations/NNN-description.php must define a class
 * extending Migration and implement run().
 */
abstract class Migration {

	/**
	 * Execute this migration.
	 * Called exactly once when the migration is first applied.
	 */
	abstract public function run(): void;

	/**
	 * Run one or more SQL statements through dbDelta().
	 *
	 * @param string|string[] $sql One SQL string or an array of SQL strings.
	 */
	protected function db_delta( $sql ): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		if ( is_array( $sql ) ) {
			foreach ( $sql as $statement ) {
				dbDelta( $statement );
			}
		} else {
			dbDelta( $sql );
		}
	}
}
