<?php
/**
 * WooCommerce Mobile Integration module
 *
 * @package WooCommerce\PayPalCommerce\WooCommerceMobile
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\WooCommerceMobile;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;

/**
 * Class WooCommerceMobileModule
 */
class WooCommerceMobileModule implements ServiceModule, ExtendingModule, ExecutableModule {

    use ModuleClassNameIdTrait;

    /**
     * Constructor for debugging
     */
    public function __construct() {
        error_log('PayPal Mobile Module: constructor called');
    }

    /**
     * {@inheritdoc}
     */
    public function services(): array {
        return require __DIR__ . '/services.php';
    }

    /**
     * {@inheritdoc}
     */
    public function extensions(): array {
        return require __DIR__ . '/extensions.php';
    }

    /**
     * {@inheritdoc}
     */
    public function run( ContainerInterface $container ): bool {

        // Debug: Log that mobile module is running
        error_log('PayPal Mobile Module: run() method called');

        // Register the REST API endpoints for mobile integration
        add_action(
            'rest_api_init',
            static function () use ( $container ) {
                error_log('PayPal Mobile Module: rest_api_init action fired');
                // Test endpoint first
                register_rest_route(
                    'wc/v3',
                    '/paypal/test',
                    array(
                        'methods' => 'GET',
                        'callback' => function() { return array('status' => 'mobile module loaded'); },
                        'permission_callback' => '__return_true',
                    )
                );

                // Payment capture endpoint
                $capture_endpoint = $container->get( 'woocommerce-mobile.capture-paypal-payment-endpoint' );
                $capture_endpoint->register_routes();

                // Credentials endpoint
                $credentials_endpoint = $container->get( 'woocommerce-mobile.get-plugin-credentials-endpoint' );
                $credentials_endpoint->register_routes();
            }
        );

        return true;
    }
}

return static function (): WooCommerceMobileModule {
    return new WooCommerceMobileModule();
};
