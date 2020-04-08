<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Session;

use Dhii\Data\Container\ContainerInterface;

return [
    'session.handler' => function (ContainerInterface $container) : SessionHandler {
        if (is_admin()) {
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
];
