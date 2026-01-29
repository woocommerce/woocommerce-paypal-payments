<?php

/**
 * The services of the session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session;

use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelView;
return array('session.handler' => function (ContainerInterface $container): \WooCommerce\PayPalCommerce\Session\SessionHandler {
    return new \WooCommerce\PayPalCommerce\Session\SessionHandler();
}, 'session.cancellation.view' => function (ContainerInterface $container): CancelView {
    return new CancelView($container->get('wcgateway.settings'), $container->get('wcgateway.funding-source.renderer'));
}, 'session.cancellation.controller' => function (ContainerInterface $container): CancelController {
    return new CancelController($container->get('session.handler'), $container->get('session.cancellation.view'), $container->get('button.helper.context'));
});
