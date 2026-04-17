<?php
namespace PressioCRM\Modules;

defined( 'ABSPATH' ) || exit;

use PressioCRM\Framework\Module;
use PressioCRM\Mail\Mailer;
use PressioCRM\Models\EmailModel;
use PressioCRM\API\RestEmail;

class EmailModule extends Module {

	public function register(): void {
		$this->container->singleton( 'mail.mailer', fn() => new Mailer() );
		$this->container->singleton( 'model.email', fn() => new EmailModel() );
	}

	public function boot(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		( new RestEmail() )->register_routes();
	}
}
