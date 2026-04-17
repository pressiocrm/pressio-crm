<?php
namespace PressioCRM\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Database\Migration;

class Migration002 extends Migration {

	public function run(): void {
		global $wpdb;

		$p       = $wpdb->prefix;
		$charset = $wpdb->get_charset_collate();

		$this->db_delta(
			"CREATE TABLE {$p}pcrm_emails (
				id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				contact_id    BIGINT UNSIGNED NOT NULL,
				email_hash    VARCHAR(32)     NOT NULL DEFAULT '',
				from_email    VARCHAR(200)    NOT NULL DEFAULT '',
				to_email      VARCHAR(200)    NOT NULL DEFAULT '',
				subject       VARCHAR(255)    NOT NULL DEFAULT '',
				body          LONGTEXT        NOT NULL,
				status        VARCHAR(20)     NOT NULL DEFAULT 'queued',
				sent_at       DATETIME        DEFAULT NULL,
				failed_at     DATETIME        DEFAULT NULL,
				error_message TEXT            DEFAULT NULL,
				created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY  (id),
				UNIQUE INDEX idx_hash       (email_hash),
				INDEX        idx_contact    (contact_id, created_at),
				INDEX        idx_status     (status, created_at)
			) {$charset};"
		);
	}
}
