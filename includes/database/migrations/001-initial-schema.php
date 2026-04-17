<?php
namespace PressioCRM\Database\Migrations;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Database\Migration;
use PressioCRM\Database\Schema;

class Migration001 extends Migration {

	public function run(): void {
		// Load the Schema class if it isn't already available.
		if ( ! class_exists( Schema::class ) ) {
			require_once PRESSIO_CRM_PATH . 'includes/database/class-schema.php';
		}

		$this->db_delta( Schema::get_tables() );
	}
}
