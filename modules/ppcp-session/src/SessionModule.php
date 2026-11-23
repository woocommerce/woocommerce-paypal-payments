<?php

/**
 * The session module.
 *
 * @package WooCommerce\PayPalCommerce\Session
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Session;

use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\Session\Cancellation\CancelController;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class SessionModule
 */
class SessionModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    /**
     * {@inheritDoc}
     */
    public function services(): array
    {
        return require __DIR__ . '/../services.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('woocommerce_init', function () use ($c) {
            $controller = $c->get('session.cancellation.controller');
            /**
             * The Cancel controller.
             *
             * @var CancelController $controller
             */
            $controller->run();
        });
        // WooCommerce empties the cart after an order completes, whichever
        // gateway took it, so a vault cannot carry over to a later checkout.
        add_action('woocommerce_cart_emptied', static function () use ($c): void {
            $session_handler = $c->get('session.handler');
            assert($session_handler instanceof \WooCommerce\PayPalCommerce\Session\SessionHandler);
            if ($session_handler->is_free_trial_vaulted()) {
                $session_handler->forget_free_trial_vaulted();
            }
        });
        add_action('ppcp_session_get_order', static function ($order, $session_handler) use ($c): void {
            if (!$session_handler instanceof \WooCommerce\PayPalCommerce\Session\SessionHandler) {
                return;
            }
            $reloader = $c->get('session.order-reloader');
            assert($reloader instanceof \WooCommerce\PayPalCommerce\Session\SessionOrderReloader);
            $reloader->maybe_reload($order instanceof Order ? $order : null, $session_handler);
        }, 10, 2);
        return \true;
    }
}
