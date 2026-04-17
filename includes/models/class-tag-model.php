<?php
namespace PressioCRM\Models;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Model;

class TagModel extends Model {

	protected static string $table = 'pcrm_tags';

	protected static array $fillable = [
		'name',
		'slug',
		'color',
	];

	/**
	 * Delete this tag and its pivot rows in pcrm_contact_tag.
	 * Wrapped in a transaction so both operations succeed or both roll back.
	 */
	public function delete(): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'START TRANSACTION' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pivot_deleted = $wpdb->delete(
			$wpdb->prefix . 'pcrm_contact_tag',
			[ 'tag_id' => (int) $this->id ],
			[ '%d' ]
		);

		if ( false === $pivot_deleted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$tag_deleted = parent::delete();

		if ( ! $tag_deleted ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query( 'COMMIT' );
		return true;
	}

	public static function find_by_slug( string $slug ): ?self {
		$slug = sanitize_title( $slug );
		if ( empty( $slug ) ) {
			return null;
		}

		return static::query()->where( 'slug', $slug )->first();
	}

	/**
	 * Return all tags attached to a given contact.
	 *
	 * Performs a JOIN on pcrm_contact_tag to retrieve only the tags
	 * associated with this contact.
	 *
	 * @return static[]
	 */
	public static function get_for_contact( int $contact_id ): array {
		global $wpdb;

		$tag_table   = $wpdb->prefix . static::$table;
		$pivot_table = $wpdb->prefix . 'pcrm_contact_tag';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are internally derived, not user input.
		$sql = $wpdb->prepare(
			"SELECT t.id, t.name, t.slug, t.color, t.created_at
			 FROM `{$tag_table}` t
			 INNER JOIN `{$pivot_table}` ct ON ct.tag_id = t.id
			 WHERE ct.contact_id = %d
			 ORDER BY t.name ASC",
			$contact_id
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Already prepared above.
		$rows = $wpdb->get_results( $sql );

		if ( ! $rows ) {
			return [];
		}

		return array_map( [ static::class, 'hydrate' ], $rows );
	}

	/**
	 * Create a tag from a human-readable name.
	 *
	 * Auto-generates a URL-safe slug. Appends -2, -3 etc. on collision
	 * to guarantee uniqueness. Default color is #6366f1 (indigo).
	 */
	public static function create_from_name( string $name ): self {
		$name = sanitize_text_field( $name );
		$base = sanitize_title( $name );
		$slug = $base;
		$i    = 2;

		// Ensure slug uniqueness by checking for collisions.
		while ( static::query()->where( 'slug', $slug )->exists() ) {
			$slug = $base . '-' . $i;
			++$i;
		}

		return static::create( [
			'name'  => $name,
			'slug'  => $slug,
			'color' => '#6366f1',
		] );
	}
}
