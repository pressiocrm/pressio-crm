<?php
namespace PressioCRM\Framework;

defined( 'ABSPATH' ) || exit;

class QueryBuilder {

	/** @var \wpdb */
	private $wpdb;

	/** @var string Fully-qualified table name (with WP prefix). */
	private string $table;

	/** @var string Model class to hydrate results into. */
	private string $model_class;

	/** @var string[] SELECT columns. */
	private array $selects = [ '*' ];

	/** @var array[] WHERE clauses: [ 'sql' => string, 'bindings' => array ] */
	private array $wheres = [];

	/** @var array[] JOIN clauses. */
	private array $joins = [];

	/** @var string[] ORDER BY clauses. */
	private array $orders = [];

	/** @var int|null LIMIT value. */
	private ?int $limit = null;

	/** @var int OFFSET value. */
	private int $offset = 0;

	/** @var string[] GROUP BY columns. */
	private array $groups = [];

	public function __construct( $wpdb, string $table, string $model_class ) {
		$this->wpdb        = $wpdb;
		$this->table       = $table;
		$this->model_class = $model_class;
	}

	/**
	 * Set the columns to select. Pass a string of comma-separated columns.
	 *
	 * @param string $columns e.g. 'id, first_name, email'
	 */
	public function select( string $columns ): self {
		$this->selects = [ $columns ];
		return $this;
	}

	/**
	 * Add a simple equality WHERE clause.
	 *
	 * @param string $column Column name (not user-supplied — always a code constant).
	 * @param mixed  $value  Value to match.
	 * @param string $format $wpdb->prepare format: %s, %d, %f.
	 */
	public function where( string $column, $value, string $format = '%s' ): self {
		$this->wheres[] = [
			'sql'      => "`{$column}` = {$format}",
			'bindings' => [ $value ],
		];
		return $this;
	}

	/**
	 * Add a WHERE clause only if $value is non-empty.
	 * Useful for optional filter params from REST requests.
	 */
	public function where_if( $value, string $column, string $format = '%s' ): self {
		if ( $value !== null && $value !== '' && $value !== false ) {
			$this->where( $column, $value, $format );
		}
		return $this;
	}

	public function where_null( string $column ): self {
		$this->wheres[] = [
			'sql'      => "`{$column}` IS NULL",
			'bindings' => [],
		];
		return $this;
	}

	public function where_not_null( string $column ): self {
		$this->wheres[] = [
			'sql'      => "`{$column}` IS NOT NULL",
			'bindings' => [],
		];
		return $this;
	}

	/**
	 * @param string $column  Column name.
	 * @param array  $values  Array of values.
	 * @param string $format  Format for each value.
	 */
	public function where_in( string $column, array $values, string $format = '%s' ): self {
		if ( empty( $values ) ) {
			// Nothing matches an empty IN — add always-false clause.
			$this->wheres[] = [ 'sql' => '1=0', 'bindings' => [] ];
			return $this;
		}

		$placeholders   = implode( ', ', array_fill( 0, count( $values ), $format ) );
		$this->wheres[] = [
			'sql'      => "`{$column}` IN ({$placeholders})",
			'bindings' => array_values( $values ),
		];
		return $this;
	}

	/**
	 * Add a raw WHERE clause with explicit bindings.
	 * Column names in $sql must be hard-coded, never user input.
	 *
	 * @param string $sql      Raw SQL fragment with %s/%d placeholders.
	 * @param array  $bindings Values to bind.
	 */
	public function where_raw( string $sql, array $bindings = [] ): self {
		$this->wheres[] = [
			'sql'      => $sql,
			'bindings' => $bindings,
		];
		return $this;
	}

	public function not_deleted(): self {
		return $this->where_null( 'deleted_at' );
	}

	public function only_deleted(): self {
		return $this->where_not_null( 'deleted_at' );
	}

	/**
	 * FULLTEXT search using MATCH … AGAINST.
	 *
	 * @param string|null $term    Search term.
	 * @param string[]    $columns Columns to search (must have FULLTEXT index).
	 */
	public function search( ?string $term, array $columns = [] ): self {
		if ( empty( $term ) || empty( $columns ) ) {
			return $this;
		}

		$term           = sanitize_text_field( $term );
		$cols           = implode( ', ', $columns );
		$this->wheres[] = [
			'sql'      => "MATCH({$cols}) AGAINST (%s IN BOOLEAN MODE)",
			'bindings' => [ $term . '*' ],
		];
		return $this;
	}

	/**
	 * Filter by tag via pcrm_contact_tag join.
	 *
	 * @param int|null $tag_id
	 */
	public function has_tag( ?int $tag_id ): self {
		if ( ! $tag_id ) {
			return $this;
		}

		$tag_table     = $this->wpdb->prefix . 'pcrm_contact_tag';
		$this->joins[] = "INNER JOIN `{$tag_table}` __ct ON __ct.contact_id = `{$this->table}`.id";
		$this->wheres[] = [
			'sql'      => '__ct.tag_id = %d',
			'bindings' => [ $tag_id ],
		];
		return $this;
	}

	/**
	 * Add a raw JOIN clause (table names are always code constants, not user input).
	 */
	public function join( string $join_sql ): self {
		$this->joins[] = $join_sql;
		return $this;
	}

	/**
	 * @param string $column    Column name — must be a code constant.
	 * @param string $direction ASC or DESC.
	 */
	public function order_by( string $column, string $direction = 'ASC' ): self {
		$direction      = strtoupper( $direction ) === 'DESC' ? 'DESC' : 'ASC';
		$this->orders[] = "`{$column}` {$direction}";
		return $this;
	}

	public function group_by( string $column ): self {
		$this->groups[] = "`{$column}`";
		return $this;
	}

	public function limit( int $limit ): self {
		$this->limit = $limit;
		return $this;
	}

	/**
	 * Execute and return paginated results.
	 *
	 * @param int $per_page Items per page (1-100).
	 * @param int $page     1-based page number.
	 *
	 * @return array{ items: object[], total: int, pages: int, page: int, per_page: int }
	 */
	public function paginate( int $per_page = 20, int $page = 1 ): array {
		$per_page = max( 1, min( 100, $per_page ) );
		$page     = max( 1, $page );
		$offset   = ( $page - 1 ) * $per_page;

		$total = $this->count();
		$pages = $total > 0 ? (int) ceil( $total / $per_page ) : 0;

		$this->limit  = $per_page;
		$this->offset = $offset;

		$items = $this->get();

		return compact( 'items', 'total', 'pages', 'page', 'per_page' );
	}

	/**
	 * @return object[]
	 */
	public function get(): array {
		$sql  = $this->build_select_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is built and prepared internally by build_select_sql().
		$rows = $this->wpdb->get_results( $sql );

		if ( ! $rows ) {
			return [];
		}

		return array_map( [ $this->model_class, 'hydrate' ], $rows );
	}

	public function first() {
		$this->limit  = 1;
		$this->offset = 0;

		$sql = $this->build_select_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is built and prepared internally by build_select_sql().
		$row = $this->wpdb->get_row( $sql );

		return $row ? ( $this->model_class )::hydrate( $row ) : null;
	}

	public function count(): int {
		// Save and override selects for COUNT.
		$saved_selects = $this->selects;
		$saved_limit   = $this->limit;
		$saved_offset  = $this->offset;

		$this->selects = [ 'COUNT(*)' ];
		$this->limit   = null;
		$this->offset  = 0;

		$sql   = $this->build_select_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is built and prepared internally by build_select_sql().
		$count = (int) $this->wpdb->get_var( $sql );

		// Restore.
		$this->selects = $saved_selects;
		$this->limit   = $saved_limit;
		$this->offset  = $saved_offset;

		return $count;
	}

	public function exists(): bool {
		return $this->count() > 0;
	}

	/**
	 * Return a flat array of values from a single column.
	 */
	public function pluck( string $column ): array {
		$this->selects = [ "`{$column}`" ];
		$sql           = $this->build_select_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- SQL is built and prepared internally by build_select_sql().
		$rows          = $this->wpdb->get_col( $sql );
		return $rows ?: [];
	}

	private function build_select_sql(): string {
		$select = implode( ', ', $this->selects );
		$sql    = "SELECT {$select} FROM `{$this->table}`";

		// JOINs.
		if ( ! empty( $this->joins ) ) {
			$sql .= ' ' . implode( ' ', $this->joins );
		}

		// WHERE.
		if ( ! empty( $this->wheres ) ) {
			$conditions   = [];
			$all_bindings = [];
			foreach ( $this->wheres as $clause ) {
				$conditions[]   = $clause['sql'];
				$all_bindings   = array_merge( $all_bindings, $clause['bindings'] );
			}
			$where_sql = 'WHERE ' . implode( ' AND ', $conditions );

			if ( ! empty( $all_bindings ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where_sql uses placeholders; bindings are passed to prepare().
				$sql .= ' ' . $this->wpdb->prepare( $where_sql, $all_bindings );
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No user input; clause contains only static conditions like "deleted_at IS NULL".
				$sql .= ' ' . $where_sql;
			}
		}

		// GROUP BY.
		if ( ! empty( $this->groups ) ) {
			$sql .= ' GROUP BY ' . implode( ', ', $this->groups );
		}

		// ORDER BY.
		if ( ! empty( $this->orders ) ) {
			$sql .= ' ORDER BY ' . implode( ', ', $this->orders );
		}

		// LIMIT / OFFSET.
		if ( $this->limit !== null ) {
			$sql .= $this->wpdb->prepare( ' LIMIT %d OFFSET %d', $this->limit, $this->offset );
		}

		return $sql;
	}
}
