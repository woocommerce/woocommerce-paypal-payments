<?php
/**
 * The services of the session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Session;

use Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;

return array(
	'session.handler'                 => function ( ContainerInterface $container ) : SessionHandler {

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
	'session.cancellation.view'       => function ( ContainerInterface $container ) : CancelView {
		return new CancelView(
			$container->get( 'wcgateway.settings' ),
			$container->get( 'wcgateway.funding-source.renderer' )
		);
	},
	'session.cancellation.controller' => function ( ContainerInterface $container ) : CancelController {
		return new CancelController(
			$container->get( 'session.handler' ),
			$container->get( 'session.cancellation.view' )
		);
	},
);
