<?php

/**
 * The API module.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient;

use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\UserIdToken;
use WooCommerce\PayPalCommerce\ApiClient\Helper\Cache;
use WooCommerce\PayPalCommerce\ApiClient\Helper\FailureRegistry;
use WooCommerce\PayPalCommerce\ApiClient\Helper\OrderTransient;
use WooCommerce\PayPalCommerce\ApiClient\Helper\PartnerAttribution;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\FactoryModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\ApiClient\Entity\Order;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\PayPalBearer;
use WooCommerce\PayPalCommerce\ApiClient\Authentication\SdkClientToken;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
/**
 * Class ApiModule
 */
class ApiModule implements ServiceModule, FactoryModule, ExtendingModule, ExecutableModule
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
    public function factories(): array
    {
        return require __DIR__ . '/../factories.php';
    }
    /**
     * {@inheritDoc}
     */
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        add_action('woocommerce_after_calculate_totals', function (\WC_Cart $cart) {
            $fees = $cart->fees_api()->get_fees();
            WC()->session->set('ppcp_fees', $fees);
        });
        add_filter('ppcp_create_order_request_body_data', function (array $data) use ($c) {
            foreach ($data['purchase_units'] ?? array() as $purchase_unit_index => $purchase_unit) {
                foreach ($purchase_unit['items'] ?? array() as $item_index => $item) {
                    $data['purchase_units'][$purchase_unit_index]['items'][$item_index]['name'] = apply_filters('woocommerce_paypal_payments_cart_line_item_name', $item['name'], $item['cart_item_key'] ?? null);
                }
            }
            return $data;
        });
        add_action('woocommerce_paypal_payments_paypal_order_created', function (Order $order) use ($c) {
            $transient = $c->has('api.helper.order-transient') ? $c->get('api.helper.order-transient') : null;
            if ($transient instanceof OrderTransient) {
                $transient->on_order_created($order);
            }
        }, 10, 1);
        add_action('woocommerce_paypal_payments_woocommerce_order_created', function (WC_Order $wc_order, Order $order) use ($c) {
            $transient = $c->has('api.helper.order-transient') ? $c->get('api.helper.order-transient') : null;
            if ($transient instanceof OrderTransient) {
                $transient->on_woocommerce_order_created($wc_order, $order);
            }
        }, 10, 2);
        add_action('woocommerce_paypal_payments_clear_apm_product_status', function () use ($c) {
            $failure_registry = $c->has('api.helper.failure-registry') ? $c->get('api.helper.failure-registry') : null;
            if ($failure_registry instanceof FailureRegistry) {
                $failure_registry->clear_failures(FailureRegistry::SELLER_STATUS_KEY);
            }
        }, 10, 2);
        /**
         * Flushes the API client caches.
         */
        add_action('woocommerce_paypal_payments_flush_api_cache', static function () use ($c) {
            $caches = array('api.paypal-bearer-cache', 'api.client-credentials-cache', 'settings.service.signup-link-cache');
            $logger = $c->get('woocommerce.logger.woocommerce');
            assert($logger instanceof LoggerInterface);
            $logger->info('Flushing API caches...');
            foreach ($caches as $cache_id) {
                $cache = $c->get($cache_id);
                assert($cache instanceof Cache);
                $cache->flush();
            }
        });
        /**
         * Filters the request arguments to add the `'PayPal-Partner-Attribution-Id'` header.
         *
         * This ensures that all API requests include the appropriate BN code retrieved
         * from the `PartnerAttribution` helper. Using this approach avoids the need
         * for extensive refactoring of existing classes that use the `RequestTrait`.
         *
         * The filter is applied in {@see RequestTrait::request()} before making an API request.
         *
         * @see PartnerAttribution::get_bn_code() Retrieves the BN code dynamically.
         * @see RequestTrait::request() Where the filter `ppcp_request_args` is applied.
         */
        add_filter('ppcp_request_args', static function (array $args) use ($c) {
            if (isset($args['headers']['PayPal-Partner-Attribution-Id'])) {
                return $args;
            }
            $partner_attribution = $c->get('api.helper.partner-attribution');
            assert($partner_attribution instanceof PartnerAttribution);
            if (!isset($args['headers']) || !is_array($args['headers'])) {
                $args['headers'] = array();
            }
            $args['headers']['PayPal-Partner-Attribution-Id'] = $partner_attribution->get_bn_code();
            return $args;
        });
        return \true;
    }
}
