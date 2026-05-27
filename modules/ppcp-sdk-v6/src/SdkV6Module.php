<?php

/**
 * The SDK v6 module.
 *
 * @package WooCommerce\PayPalCommerce\SdkV6
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\SdkV6;

use WooCommerce\PayPalCommerce\SdkV6\Assets\SdkV6Manager;
use WooCommerce\PayPalCommerce\SdkV6\Endpoint\ClientTokenEndpoint;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
/**
 * Class SdkV6Module
 */
class SdkV6Module implements ServiceModule, ExecutableModule
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
        add_action('wc_ajax_' . ClientTokenEndpoint::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('sdk-v6.endpoint.client-token');
            assert($endpoint instanceof ClientTokenEndpoint);
            $endpoint->handle_request();
        });
        add_action('wp_enqueue_scripts', static function () use ($c) {
            $manager = $c->get('sdk-v6.manager');
            assert($manager instanceof SdkV6Manager);
            $manager->enqueue();
        });
        return \true;
    }
}
