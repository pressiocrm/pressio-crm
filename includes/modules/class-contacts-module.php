<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Models\ContactModel;
use PressioCRM\Models\ContactMetaModel;
use PressioCRM\API\RestContacts;

class ContactsModule extends Module {

	public function register(): void {
		$this->container->singleton( 'model.contact', fn() => new ContactModel() );
		$this->container->singleton( 'model.contact_meta', fn() => new ContactMetaModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestContacts() )->register_routes();
	}
}
