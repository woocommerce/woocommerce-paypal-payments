<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Dhii\Data\Container\ContainerInterface;
use Inpsyde\PayPalCommerce\Session\Cancellation\CancelController;
use Inpsyde\PayPalCommerce\Session\Cancellation\CancelView;

return [
    'session.handler' => function (ContainerInterface $container) : SessionHandler {

        if (is_admin()) {
            return new SessionHandler();
        }
        if (is_null(WC()->session)) {
            return new SessionHandler();
        }
        $result = WC()->session->get(SessionHandler::ID);
        if (is_a($result, SessionHandler::class)) {
            return $result;
        }
        $sessionHandler = new SessionHandler();
        WC()->session->set(SessionHandler::ID, $sessionHandler);
        return $sessionHandler;
    },
    'session.cancellation.view' => function (ContainerInterface $container) : CancelView {
        return new CancelView();
    },
    'session.cancellation.controller' => function (ContainerInterface $container) : CancelController {
        return new CancelController(
            $container->get('session.handler'),
            $container->get('session.cancellation.view')
        );
    },
];
