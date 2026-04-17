<?php
namespace PressioCRM\Database;

defined( 'ABSPATH' ) || exit;

class Schema {

	public static function get_tables(): array {
		global $wpdb;
		$p = $wpdb->prefix; // e.g. "wp_"

		$charset = $wpdb->get_charset_collate();

		return [
			// ── Contacts ────────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_contacts (
				id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				owner_id       BIGINT UNSIGNED DEFAULT NULL,
				first_name     VARCHAR(100)    NOT NULL DEFAULT '',
				last_name      VARCHAR(100)    NOT NULL DEFAULT '',
				email          VARCHAR(200)    NOT NULL DEFAULT '',
				phone          VARCHAR(50)     NOT NULL DEFAULT '',
				company        VARCHAR(200)    NOT NULL DEFAULT '',
				job_title      VARCHAR(200)    NOT NULL DEFAULT '',
				address_line_1 VARCHAR(255)    NOT NULL DEFAULT '',
				address_line_2 VARCHAR(255)    NOT NULL DEFAULT '',
				city           VARCHAR(100)    NOT NULL DEFAULT '',
				state          VARCHAR(100)    NOT NULL DEFAULT '',
				postal_code    VARCHAR(20)     NOT NULL DEFAULT '',
				country        VARCHAR(100)    NOT NULL DEFAULT '',
				source         VARCHAR(50)     NOT NULL DEFAULT 'manual',
				status         VARCHAR(20)     NOT NULL DEFAULT 'active',
				notes          TEXT,
				created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at     DATETIME        DEFAULT NULL,
				PRIMARY KEY  (id),
				INDEX        idx_owner_status    (owner_id, status, deleted_at),
				INDEX        idx_status_created  (status, deleted_at, created_at),
				INDEX        idx_name            (last_name, first_name),
				INDEX        idx_email           (email(100)),
				INDEX        idx_created_deleted (created_at, deleted_at),
				FULLTEXT KEY ft_search           (first_name, last_name, email, company)
			) {$charset};",

			// ── Contact meta ─────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_contact_meta (
				meta_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				contact_id BIGINT UNSIGNED NOT NULL,
				meta_key   VARCHAR(255)    NOT NULL,
				meta_value LONGTEXT,
				PRIMARY KEY (meta_id),
				INDEX       idx_contact_key (contact_id, meta_key(100))
			) {$charset};",

			// ── Custom field definitions (free core — Pro adds UI) ────────────
			"CREATE TABLE {$p}pcrm_custom_field_definitions (
				id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				object_type VARCHAR(30)     NOT NULL,
				label       VARCHAR(200)    NOT NULL,
				slug        VARCHAR(200)    NOT NULL,
				type        VARCHAR(30)     NOT NULL DEFAULT 'text',
				options     LONGTEXT        DEFAULT NULL,
				is_required TINYINT(1)      NOT NULL DEFAULT 0,
				position    INT UNSIGNED    NOT NULL DEFAULT 0,
				is_active   TINYINT(1)      NOT NULL DEFAULT 1,
				created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				UNIQUE INDEX idx_object_slug (object_type, slug(100)),
				INDEX        idx_object_type (object_type, is_active, position)
			) {$charset};",

			// ── Tags ──────────────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_tags (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name       VARCHAR(100)    NOT NULL,
				slug       VARCHAR(100)    NOT NULL,
				color      VARCHAR(7)      NOT NULL DEFAULT '#6366f1',
				created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE INDEX idx_slug (slug)
			) {$charset};",

			// ── Contact ↔ Tag pivot ───────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_contact_tag (
				contact_id BIGINT UNSIGNED NOT NULL,
				tag_id     BIGINT UNSIGNED NOT NULL,
				PRIMARY KEY (contact_id, tag_id),
				INDEX       idx_tag (tag_id)
			) {$charset};",

			// ── Pipelines ────────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_pipelines (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				name       VARCHAR(200)    NOT NULL,
				is_default TINYINT(1)      NOT NULL DEFAULT 0,
				position   INT UNSIGNED    NOT NULL DEFAULT 0,
				created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id)
			) {$charset};",

			// ── Pipeline stages ───────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_pipeline_stages (
				id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
				pipeline_id     BIGINT UNSIGNED  NOT NULL,
				name            VARCHAR(200)     NOT NULL,
				slug            VARCHAR(200)     NOT NULL,
				type            VARCHAR(20)      NOT NULL DEFAULT 'open',
				color           VARCHAR(7)       NOT NULL DEFAULT '#6366f1',
				position        DECIMAL(20,10)   NOT NULL DEFAULT 0,
				win_probability TINYINT UNSIGNED NOT NULL DEFAULT 50,
				PRIMARY KEY (id),
				INDEX       idx_pipeline_pos (pipeline_id, position)
			) {$charset};",

			// ── Deals ─────────────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_deals (
				id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				contact_id     BIGINT UNSIGNED DEFAULT NULL,
				pipeline_id    BIGINT UNSIGNED NOT NULL,
				stage_id       BIGINT UNSIGNED NOT NULL,
				owner_id       BIGINT UNSIGNED DEFAULT NULL,
				title          VARCHAR(255)    NOT NULL,
				value          DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
				currency       VARCHAR(3)      NOT NULL DEFAULT 'USD',
				expected_close DATE            DEFAULT NULL,
				closed_at      DATETIME        DEFAULT NULL,
				status         VARCHAR(20)     NOT NULL DEFAULT 'open',
				position       DECIMAL(20,10)  NOT NULL DEFAULT 0,
				notes          TEXT,
				created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at     DATETIME        DEFAULT NULL,
				PRIMARY KEY (id),
				INDEX idx_kanban     (pipeline_id, status, deleted_at, stage_id, position),
				INDEX idx_stage_pos  (stage_id, position),
				INDEX idx_won_closed (status, deleted_at, closed_at),
				INDEX idx_contact    (contact_id, deleted_at, created_at),
				INDEX idx_owner      (owner_id, deleted_at, status),
				INDEX idx_created    (created_at, deleted_at, status)
			) {$charset};",

			// ── Deal meta ─────────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_deal_meta (
				meta_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				deal_id    BIGINT UNSIGNED NOT NULL,
				meta_key   VARCHAR(255)    NOT NULL,
				meta_value LONGTEXT,
				PRIMARY KEY (meta_id),
				INDEX       idx_deal_key (deal_id, meta_key(100))
			) {$charset};",

			// ── Tasks ─────────────────────────────────────────────────────────
			"CREATE TABLE {$p}pcrm_tasks (
				id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				contact_id   BIGINT UNSIGNED DEFAULT NULL,
				deal_id      BIGINT UNSIGNED DEFAULT NULL,
				owner_id     BIGINT UNSIGNED DEFAULT NULL,
				title        VARCHAR(255)    NOT NULL,
				description  TEXT,
				type         VARCHAR(30)     NOT NULL DEFAULT 'task',
				status       VARCHAR(20)     NOT NULL DEFAULT 'pending',
				priority     VARCHAR(10)     NOT NULL DEFAULT 'medium',
				due_date     DATETIME        DEFAULT NULL,
				completed_at DATETIME        DEFAULT NULL,
				created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				deleted_at   DATETIME        DEFAULT NULL,
				PRIMARY KEY (id),
				INDEX idx_owner_status_due (owner_id, status, deleted_at, due_date),
				INDEX idx_due              (status, deleted_at, due_date),
				INDEX idx_contact          (contact_id, deleted_at, due_date),
				INDEX idx_deal             (deal_id, deleted_at)
			) {$charset};",

			// ── Activities (append-only, no soft delete) ──────────────────────
			"CREATE TABLE {$p}pcrm_activities (
				id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				contact_id  BIGINT UNSIGNED DEFAULT NULL,
				deal_id     BIGINT UNSIGNED DEFAULT NULL,
				user_id     BIGINT UNSIGNED DEFAULT NULL,
				type        VARCHAR(50)     NOT NULL,
				title       VARCHAR(255)    NOT NULL DEFAULT '',
				description TEXT,
				meta        LONGTEXT        DEFAULT NULL,
				created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				INDEX idx_contact_date (contact_id, created_at),
				INDEX idx_deal_date    (deal_id, created_at),
				INDEX idx_created      (created_at),
				INDEX idx_type_date    (type, created_at)
			) {$charset};",

			// ── Integrations (CF7 field mappings + Pro form integrations) ─────
			// Note: pcrm_emails is created by Migration002, not here.
			"CREATE TABLE {$p}pcrm_integrations (
				id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				type       VARCHAR(50)     NOT NULL,
				object_id  VARCHAR(100)    NOT NULL DEFAULT '',
				name       VARCHAR(200)    NOT NULL DEFAULT '',
				config     LONGTEXT        NOT NULL,
				is_active  TINYINT(1)      NOT NULL DEFAULT 1,
				created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				INDEX idx_type_active (type, is_active, object_id(50))
			) {$charset};",
		];
	}

	/**
	 * Return an array of all table names (without WP prefix) in dependency order.
	 * Used by uninstall.php to drop tables children-first.
	 */
	public static function get_table_names(): array {
		return [
			'pcrm_emails',
			'pcrm_activities',
			'pcrm_deal_meta',
			'pcrm_deals',
			'pcrm_tasks',
			'pcrm_contact_tag',
			'pcrm_contact_meta',
			'pcrm_custom_field_definitions',
			'pcrm_integrations',
			'pcrm_pipeline_stages',
			'pcrm_pipelines',
			'pcrm_tags',
			'pcrm_contacts',
		];
	}
}
