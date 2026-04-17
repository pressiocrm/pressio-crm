<?php
namespace PressioCRM\Framework;

defined( 'ABSPATH' ) || exit;

use RuntimeException;

abstract class Model {

	/**
	 * Table name without the WordPress prefix.
	 * Override in child: protected static string $table = 'pcrm_contacts';
	 */
	protected static string $table = '';

	/**
	 * Columns allowed for mass assignment (create/update).
	 * Override in child: protected static array $fillable = ['first_name', ...];
	 */
	protected static array $fillable = [];

	/**
	 * Column value casts applied when hydrating from DB rows.
	 * Supported types: 'int', 'float', 'bool', 'json', 'date'.
	 * Override in child: protected static array $casts = ['value' => 'float'];
	 */
	protected static array $casts = [];

	/**
	 * The model's data — populated by hydrate() or set directly on create/update.
	 * Access column values as properties: $contact->first_name
	 */
	protected array $data = [];

	public function __get( string $name ) {
		return $this->data[ $name ] ?? null;
	}

	public function __set( string $name, $value ): void {
		$this->data[ $name ] = $value;
	}

	public function __isset( string $name ): bool {
		return isset( $this->data[ $name ] );
	}

	public static function find( int $id ) {
		$query = static::query()->where( 'id', $id, '%d' );
		if ( static::has_soft_deletes() ) {
			$query->not_deleted();
		}
		return $query->first();
	}

	/**
	 * @throws RuntimeException
	 */
	public static function find_or_fail( int $id ) {
		$instance = static::find( $id );
		if ( null === $instance ) {
			throw new RuntimeException(
				/* translators: 1: model class name, 2: record ID */
				sprintf( esc_html__( 'Pressio CRM: %1$s with id %2$d not found.', 'pressio-crm' ), esc_html( static::class ), (int) $id )
			);
		}
		return $instance;
	}

	public static function query(): QueryBuilder {
		global $wpdb;
		return new QueryBuilder( $wpdb, static::table(), static::class );
	}

	/**
	 * @throws RuntimeException On DB insert failure.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$filtered = static::filter_fillable( $data );
		$filtered = apply_filters( 'pressio_crm_before_' . static::model_key() . '_save', $filtered );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( static::table(), $filtered );

		if ( false === $result ) {
			throw new RuntimeException(
				/* translators: 1: model class name, 2: database error message */
				sprintf( esc_html__( 'Pressio CRM: Failed to insert %1$s. DB error: %2$s', 'pressio-crm' ), esc_html( static::class ), esc_html( $wpdb->last_error ) )
			);
		}

		return static::find_or_fail( (int) $wpdb->insert_id );
	}

	public function update( array $data ): bool {
		global $wpdb;

		$filtered = static::filter_fillable( $data );
		$filtered = apply_filters( 'pressio_crm_before_' . static::model_key() . '_save', $filtered );

		if ( empty( $filtered ) ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			static::table(),
			$filtered,
			[ 'id' => $this->id ]
		);

		if ( false !== $result ) {
			// Sync local data.
			foreach ( $filtered as $key => $value ) {
				$this->data[ $key ] = $value;
			}
		}

		return false !== $result;
	}

	/**
	 * Soft-delete this row by setting deleted_at to now.
	 * Tables without deleted_at use hard delete.
	 */
	public function delete(): bool {
		global $wpdb;

		// Check if this table has a deleted_at column.
		if ( in_array( 'deleted_at', static::$fillable, true ) || static::has_soft_deletes() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			return false !== $wpdb->update(
				static::table(),
				[ 'deleted_at' => current_time( 'mysql' ) ],
				[ 'id' => $this->id ]
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( static::table(), [ 'id' => $this->id ] );
	}

	public function force_delete(): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->delete( static::table(), [ 'id' => $this->id ] );
	}

	/**
	 * Return model data as an array, suitable for REST API responses.
	 * Child models can override to add/remove/transform fields.
	 */
	public function to_array(): array {
		return $this->data;
	}

	/**
	 * Create a model instance from a $wpdb result row (stdClass).
	 * Applies column casts defined in $casts.
	 *
	 * Called by Query Builder — not intended for direct use.
	 *
	 * @param object $row Raw DB row.
	 */
	public static function hydrate( object $row ) {
		$instance       = new static();
		$instance->data = (array) $row;

		foreach ( static::$casts as $column => $type ) {
			if ( ! isset( $instance->data[ $column ] ) ) {
				continue;
			}
			$instance->data[ $column ] = static::cast_value( $instance->data[ $column ], $type );
		}

		return $instance;
	}

	protected static function table(): string {
		global $wpdb;
		return $wpdb->prefix . static::$table;
	}

	protected static function filter_fillable( array $data ): array {
		if ( empty( static::$fillable ) ) {
			return $data;
		}
		return array_intersect_key( $data, array_flip( static::$fillable ) );
	}

	/**
	 * Derive a short snake_case model key from the class name for use in hooks.
	 * PressioCRM\Models\ContactModel → contact
	 */
	protected static function model_key(): string {
		$parts = explode( '\\', static::class );
		$short = end( $parts );
		// Remove trailing "Model" suffix and convert PascalCase to snake_case.
		$short = preg_replace( '/Model$/', '', $short );
		return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $short ) );
	}

	/**
	 * Whether this model uses soft deletes.
	 * Override in child to return true when the table has a deleted_at column.
	 */
	protected static function has_soft_deletes(): bool {
		return false;
	}

	/**
	 * @param mixed  $value Raw value.
	 * @param string $type  Cast type: int, float, bool, json.
	 */
	private static function cast_value( $value, string $type ) {
		switch ( $type ) {
			case 'int':
				return (int) $value;
			case 'float':
				return (float) $value;
			case 'bool':
				return (bool) $value;
			case 'json':
				return json_decode( $value, true );
			default:
				return $value;
		}
	}
}
