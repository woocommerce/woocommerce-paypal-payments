<?php
/**
 * The services of the session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Dhii\Data\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;

return array(
	'session.handler'                 => function ( $container ) : SessionHandler {

		if ( is_null( WC()->session ) ) {
			return new SessionHandler();
		}
		$result = WC()->session->get( SessionHandler::ID );
		if ( is_a( $result, SessionHandler::class ) ) {
			return $result;
		}
		$session_handler = new SessionHandler();
		WC()->session->set( SessionHandler::ID, $session_handler );
		return $session_handler;
	},
	'session.cancellation.view'       => function ( $container ) : CancelView {
		return new CancelView();
	},
	'session.cancellation.controller' => function ( $container ) : CancelController {
		return new CancelController(
			$container->get( 'session.handler' ),
			$container->get( 'session.cancellation.view' )
		);
	},
);
