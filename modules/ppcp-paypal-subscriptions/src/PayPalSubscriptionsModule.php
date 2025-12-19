<?php

/**
 * The PayPalSubscriptions module.
 *
 * @package WooCommerce\PayPalCommerce\PayPalSubscriptions
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\PayPalSubscriptions;

use ActionScheduler_Store;
use WC_Order;
use WC_Product;
use WC_Product_Subscription_Variation;
use WC_Subscription;
use WC_Subscriptions_Product;
use WooCommerce\PayPalCommerce\ApiClient\Endpoint\BillingSubscriptions;
use WooCommerce\PayPalCommerce\ApiClient\Exception\PayPalApiException;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Exception\NotFoundException;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WP_Post;
/**
 * Class SavedPaymentCheckerModule
 */
class PayPalSubscriptionsModule implements ServiceModule, ExtendingModule, ExecutableModule
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
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
        $subscriptions_helper = $c->get('wc-subscriptions.helper');
        assert($subscriptions_helper instanceof SubscriptionHelper);
        if (!$subscriptions_helper->plugin_is_active()) {
            return \true;
        }
        add_filter('woocommerce_subscription_payment_gateway_supports', function (bool $payment_gateway_supports, string $payment_gateway_feature, \WC_Subscription $wc_order): bool {
            if (!in_array($payment_gateway_feature, array('gateway_scheduled_payments', 'subscription_date_changes', 'subscription_amount_changes', 'subscription_payment_method_change', 'subscription_payment_method_change_customer', 'subscription_payment_method_change_admin'), \true)) {
                return $payment_gateway_supports;
            }
            $subscription = wcs_get_subscription($wc_order->get_id());
            if (!is_a($subscription, WC_Subscription::class)) {
                return $payment_gateway_supports;
            }
            $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
            if (!$subscription_id) {
                return $payment_gateway_supports;
            }
            if ($payment_gateway_feature === 'gateway_scheduled_payments') {
                return \true;
            }
            return \false;
        }, 100, 3);
        add_filter('woocommerce_can_subscription_be_updated_to_active', function (bool $can_be_updated, \WC_Subscription $subscription) use ($c) {
            $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
            if ($subscription_id && $subscription->get_status() === 'pending-cancel') {
                return \true;
            }
            return $can_be_updated;
        }, 10, 2);
        add_filter('woocommerce_can_subscription_be_updated_to_new-payment-method', function (bool $can_be_updated, \WC_Subscription $subscription) use ($c) {
            $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
            if ($subscription_id) {
                return \false;
            }
            return $can_be_updated;
        }, 10, 2);
        add_filter(
            'woocommerce_paypal_payments_before_order_process',
            /**
             * WC_Payment_Gateway $gateway type removed.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function (bool $process, $gateway, \WC_Order $wc_order) use ($c) {
                if (!$gateway instanceof PayPalGateway || $gateway::ID !== 'ppcp-gateway') {
                    return $process;
                }
                $paypal_subscription_id = \WC()->session->get('ppcp_subscription_id');
                if (empty($paypal_subscription_id) || !is_string($paypal_subscription_id)) {
                    return $process;
                }
                $order = $c->get('session.handler')->order();
                $gateway->add_paypal_meta($wc_order, $order, $c->get('settings.environment'));
                $subscriptions = function_exists('wcs_get_subscriptions_for_order') ? wcs_get_subscriptions_for_order($wc_order) : array();
                foreach ($subscriptions as $subscription) {
                    $subscription->update_meta_data('ppcp_subscription', $paypal_subscription_id);
                    $subscription->save();
                    // translators: %s PayPal Subscription id.
                    $subscription->add_order_note(sprintf(__('PayPal subscription %s added.', 'woocommerce-paypal-payments'), $paypal_subscription_id));
                }
                $transaction_id = $gateway->get_paypal_order_transaction_id($order);
                if ($transaction_id) {
                    $gateway->update_transaction_id($transaction_id, $wc_order, $c->get('woocommerce.logger.woocommerce'));
                }
                $wc_order->payment_complete();
                return \false;
            },
            10,
            3
        );
        add_action(
            'save_post',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($product_id) use ($c) {
                $settings = $c->get('wcgateway.settings');
                assert($settings instanceof Settings);
                try {
                    $subscriptions_mode = $settings->get('subscriptions_mode');
                } catch (NotFoundException $exception) {
                    return;
                }
                // phpcs:ignore WordPress.Security.NonceVerification
                $nonce = wc_clean(wp_unslash($_POST['_wcsnonce'] ?? ''));
                if ($subscriptions_mode !== 'subscriptions_api' || wcs_is_manual_renewal_enabled() || !is_string($nonce) || !wp_verify_nonce($nonce, 'wcs_subscription_meta')) {
                    return;
                }
                $product = wc_get_product($product_id);
                if (!is_a($product, WC_Product::class)) {
                    return;
                }
                $subscriptions_api_handler = $c->get('paypal-subscriptions.api-handler');
                assert($subscriptions_api_handler instanceof \WooCommerce\PayPalCommerce\PayPalSubscriptions\SubscriptionsApiHandler);
                $this->update_subscription_product_meta($product, $subscriptions_api_handler);
            },
            12
        );
        add_filter(
            'woocommerce_add_to_cart_validation',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            static function ($passed_validation, $product_id) use ($c) {
                if (WC()->cart->is_empty() || wcs_is_manual_renewal_enabled()) {
                    return $passed_validation;
                }
                $product = wc_get_product($product_id);
                if (!is_a($product, WC_Product::class)) {
                    wc_add_notice(__('Cannot add this product to cart (invalid product).', 'woocommerce-paypal-payments'), 'error');
                    return \false;
                }
                $settings = $c->get('wcgateway.settings');
                assert($settings instanceof Settings);
                $subscriptions_mode = $settings->has('subscriptions_mode') ? $settings->get('subscriptions_mode') : '';
                $is_paypal_subscription = static function ($product) use ($subscriptions_mode): bool {
                    return $product && in_array($product->get_type(), array('subscription', 'variable-subscription'), \true) && 'subscriptions_api' === $subscriptions_mode && $product->get_meta('_ppcp_enable_subscription_product', \true) === 'yes';
                };
                if ($is_paypal_subscription($product)) {
                    if (!$product->get_sold_individually()) {
                        $product->set_sold_individually(\true);
                        $product->save();
                    }
                    wc_add_notice(__('You cannot add a PayPal Subscription product to a cart with other items.', 'woocommerce-paypal-payments'), 'error');
                    return \false;
                }
                foreach (WC()->cart->get_cart() as $cart_item) {
                    $cart_product = wc_get_product($cart_item['product_id']);
                    if ($is_paypal_subscription($cart_product)) {
                        wc_add_notice(__('You can only have one PayPal Subscription product in your cart.', 'woocommerce-paypal-payments'), 'error');
                        return \false;
                    }
                }
                return $passed_validation;
            },
            10,
            2
        );
        add_action(
            'woocommerce_save_product_variation',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($variation_id) use ($c) {
                // phpcs:ignore WordPress.Security.NonceVerification
                $wcsnonce_save_variations = wc_clean(wp_unslash($_POST['_wcsnonce_save_variations'] ?? ''));
                if (!WC_Subscriptions_Product::is_subscription($variation_id) || wcs_is_manual_renewal_enabled() || !is_string($wcsnonce_save_variations) || !wp_verify_nonce($wcsnonce_save_variations, 'wcs_subscription_variations')) {
                    return;
                }
                $product = wc_get_product($variation_id);
                if (!is_a($product, WC_Product_Subscription_Variation::class)) {
                    return;
                }
                $subscriptions_api_handler = $c->get('paypal-subscriptions.api-handler');
                assert($subscriptions_api_handler instanceof \WooCommerce\PayPalCommerce\PayPalSubscriptions\SubscriptionsApiHandler);
                $this->update_subscription_product_meta($product, $subscriptions_api_handler);
            },
            30
        );
        /**
         * Executed when updating WC Subscription.
         */
        add_action(
            'woocommerce_process_shop_subscription_meta',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($id) use ($c) {
                $subscription = wcs_get_subscription($id);
                if ($subscription === \false) {
                    return;
                }
                $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if (!$subscription_id) {
                    return;
                }
                $subscription_status = $c->get('paypal-subscriptions.status');
                assert($subscription_status instanceof \WooCommerce\PayPalCommerce\PayPalSubscriptions\SubscriptionStatus);
                $subscription_status->update_status($subscription->get_status(), $subscription_id);
            },
            20
        );
        /**
         * Update subscription status from WC Subscriptions list page action link.
         */
        add_action('woocommerce_subscription_status_updated', function (WC_Subscription $subscription) use ($c) {
            $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
            if (!$subscription_id) {
                return;
            }
            $subscription_status = $c->get('paypal-subscriptions.status');
            assert($subscription_status instanceof \WooCommerce\PayPalCommerce\PayPalSubscriptions\SubscriptionStatus);
            $subscription_status->update_status($subscription->get_status(), $subscription_id);
        });
        add_action(
            'woocommerce_subscription_before_actions',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($subscription) use ($c) {
                $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if ($subscription_id) {
                    $environment = $c->get('settings.environment');
                    $host = $environment->current_environment_is(Environment::SANDBOX) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
                    ?>
					<tr>
						<td><?php 
                    esc_html_e('PayPal Subscription', 'woocommerce-paypal-payments');
                    ?></td>
						<td>
							<a href="<?php 
                    echo esc_url($host . "/myaccount/autopay/connect/{$subscription_id}");
                    ?>" id="ppcp-subscription-id" target="_blank"><?php 
                    echo esc_html($subscription_id);
                    ?></a>
						</td>
					</tr>
					<?php 
                }
            }
        );
        add_filter(
            'woocommerce_order_data_store_cpt_get_orders_query',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($query, $query_vars): array {
                if (!empty($query_vars['ppcp_subscription'])) {
                    $query['meta_query'][] = array('key' => 'ppcp_subscription', 'value' => esc_attr($query_vars['ppcp_subscription']));
                }
                return $query;
            },
            10,
            2
        );
        add_action(
            'woocommerce_customer_changed_subscription_to_cancelled',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($subscription) use ($c) {
                $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if ($subscription_id) {
                    $subscriptions_endpoint = $c->get('api.endpoint.billing-subscriptions');
                    assert($subscriptions_endpoint instanceof BillingSubscriptions);
                    try {
                        $subscriptions_endpoint->suspend($subscription_id);
                    } catch (RuntimeException $exception) {
                        $error = $exception->getMessage();
                        if (is_a($exception, PayPalApiException::class)) {
                            $error = $exception->get_details($error);
                        }
                        $logger = $c->get('woocommerce.logger.woocommerce');
                        $logger->error('Could not suspend subscription product on PayPal. ' . $error);
                    }
                }
            }
        );
        add_action(
            'woocommerce_customer_changed_subscription_to_active',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($subscription) use ($c) {
                $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if ($subscription_id) {
                    $subscriptions_endpoint = $c->get('api.endpoint.billing-subscriptions');
                    assert($subscriptions_endpoint instanceof BillingSubscriptions);
                    try {
                        $subscriptions_endpoint->activate($subscription_id);
                    } catch (RuntimeException $exception) {
                        $error = $exception->getMessage();
                        if (is_a($exception, PayPalApiException::class)) {
                            $error = $exception->get_details($error);
                        }
                        $logger = $c->get('woocommerce.logger.woocommerce');
                        $logger->error('Could not active subscription product on PayPal. ' . $error);
                    }
                }
            }
        );
        add_action('woocommerce_product_options_general_product_data', function () use ($c) {
            if (wcs_is_manual_renewal_enabled()) {
                return;
            }
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            try {
                $subscriptions_mode = $settings->get('subscriptions_mode');
                if ($subscriptions_mode === 'subscriptions_api') {
                    /**
                     * Needed for getting global post object.
                     *
                     * @psalm-suppress InvalidGlobal
                     */
                    global $post;
                    $product = wc_get_product($post->ID);
                    if (!is_a($product, WC_Product::class)) {
                        return;
                    }
                    $environment = $c->get('settings.environment');
                    echo '<div class="options_group subscription_pricing show_if_subscription hidden">';
                    $this->render_paypal_subscription_fields($product, $environment);
                    echo '</div>';
                }
            } catch (NotFoundException $exception) {
                return;
            }
        });
        add_action(
            'woocommerce_variation_options_pricing',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($loop, $variation_data, $variation) use ($c) {
                if (wcs_is_manual_renewal_enabled()) {
                    return;
                }
                $settings = $c->get('wcgateway.settings');
                assert($settings instanceof Settings);
                try {
                    $subscriptions_mode = $settings->get('subscriptions_mode');
                    if ($subscriptions_mode === 'subscriptions_api') {
                        $product = wc_get_product($variation->ID);
                        if (!is_a($product, WC_Product_Subscription_Variation::class)) {
                            return;
                        }
                        $environment = $c->get('settings.environment');
                        $this->render_paypal_subscription_fields($product, $environment);
                    }
                } catch (NotFoundException $exception) {
                    return;
                }
            },
            10,
            3
        );
        add_action(
            'admin_enqueue_scripts',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($hook) use ($c) {
                if (!is_string($hook) || wcs_is_manual_renewal_enabled()) {
                    return;
                }
                $settings = $c->get('wcgateway.settings');
                $subscription_mode = $settings->has('subscriptions_mode') ? $settings->get('subscriptions_mode') : '';
                if (!in_array($hook, array('post.php', 'post-new.php'), \true) || $subscription_mode !== 'subscriptions_api') {
                    return;
                }
                $module_url = $c->get('paypal-subscriptions.module.url');
                wp_enqueue_script('ppcp-paypal-subscription', untrailingslashit($module_url) . '/assets/js/paypal-subscription.js', array('jquery'), $c->get('ppcp.asset-version'), \true);
                wp_set_script_translations('ppcp-paypal-subscription', 'woocommerce-paypal-payments');
                $product = wc_get_product();
                if (!$product) {
                    return;
                }
                wp_localize_script('ppcp-paypal-subscription', 'PayPalCommerceGatewayPayPalSubscriptionProducts', array('ajax' => array('deactivate_plan' => array('endpoint' => \WC_AJAX::get_endpoint(\WooCommerce\PayPalCommerce\PayPalSubscriptions\DeactivatePlanEndpoint::ENDPOINT), 'nonce' => wp_create_nonce(\WooCommerce\PayPalCommerce\PayPalSubscriptions\DeactivatePlanEndpoint::ENDPOINT))), 'product_id' => $product->get_id(), 'i18n' => array('prices_must_be_above_zero' => __('Prices must be above zero for PayPal Subscriptions!', 'woocommerce-paypal-payments'), 'not_allowed_period_interval' => __('Not allowed period interval combination for PayPal Subscriptions!', 'woocommerce-paypal-payments'))));
            }
        );
        add_action('wc_ajax_' . \WooCommerce\PayPalCommerce\PayPalSubscriptions\DeactivatePlanEndpoint::ENDPOINT, function () use ($c) {
            $c->get('paypal-subscriptions.deactivate-plan-endpoint')->handle_request();
        });
        add_action(
            'add_meta_boxes',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function (string $post_type, $post_or_order_object) use ($c) {
                if (!function_exists('wcs_get_subscription')) {
                    return;
                }
                $order = $post_or_order_object instanceof WP_Post ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;
                if (!is_a($order, WC_Order::class)) {
                    return;
                }
                $subscription = wcs_get_subscription($order->get_id());
                if (!is_a($subscription, WC_Subscription::class)) {
                    return;
                }
                $subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if (!$subscription_id) {
                    return;
                }
                $screen_id = wc_get_page_screen_id('shop_subscription');
                remove_meta_box('woocommerce-subscription-schedule', $screen_id, 'side');
                $host = $c->get('api.paypal-website-url');
                add_meta_box('ppcp_paypal_subscription', __('PayPal Subscription', 'woocommerce-paypal-payments'), function () use ($subscription_id, $host) {
                    $url = trailingslashit($host) . 'billing/subscriptions/' . $subscription_id;
                    echo '<p>' . esc_html__('This subscription is linked to a PayPal Subscription, Cancel it to unlink.', 'woocommerce-paypal-payments') . '</p>';
                    echo '<p><strong>' . esc_html__('Subscription:', 'woocommerce-paypal-payments') . '</strong> <a href="' . esc_url($url) . '" target="_blank">' . esc_attr($subscription_id) . '</a></p>';
                }, $post_type, 'side');
            },
            30,
            2
        );
        return \true;
    }
    /**
     * Updates subscription product meta.
     *
     * @param WC_Product              $product The product.
     * @param SubscriptionsApiHandler $subscriptions_api_handler The subscription api handler.
     * @return void
     *
     * @psalm-suppress PossiblyInvalidCast
     */
    private function update_subscription_product_meta(WC_Product $product, \WooCommerce\PayPalCommerce\PayPalSubscriptions\SubscriptionsApiHandler $subscriptions_api_handler): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification
        $enable_subscription_product = wc_string_to_bool((string) wc_clean(wp_unslash($_POST['_ppcp_enable_subscription_product'] ?? '')));
        $product->update_meta_data('_ppcp_enable_subscription_product', wc_bool_to_string($enable_subscription_product));
        if (!$enable_subscription_product) {
            $product->save();
            return;
        }
        if (!$product->get_sold_individually()) {
            $product->set_sold_individually(\true);
        }
        $product->save();
        if ($product->get_type() === 'subscription' || $product->get_type() === 'subscription_variation') {
            if ($product->meta_exists('ppcp_subscription_product') && $product->meta_exists('ppcp_subscription_plan')) {
                $subscriptions_api_handler->update_product($product);
                $subscriptions_api_handler->update_plan($product);
                return;
            }
            if (!$product->meta_exists('ppcp_subscription_product')) {
                $subscriptions_api_handler->create_product($product);
            }
            if ($product->meta_exists('ppcp_subscription_product') && !$product->meta_exists('ppcp_subscription_plan')) {
                // phpcs:ignore WordPress.Security.NonceVerification
                $subscription_plan_name = wc_clean(wp_unslash($_POST['_ppcp_subscription_plan_name'] ?? ''));
                if (!is_string($subscription_plan_name)) {
                    return;
                }
                $product->update_meta_data('_ppcp_subscription_plan_name', $subscription_plan_name);
                $product->save();
                $subscriptions_api_handler->create_plan($subscription_plan_name, $product);
            }
        }
    }
    /**
     * Render PayPal Subscriptions fields.
     *
     * @param WC_Product  $product WC Product.
     * @param Environment $environment The environment.
     * @return void
     */
    private function render_paypal_subscription_fields(WC_Product $product, Environment $environment): void
    {
        $enable_subscription_product = $product->get_meta('_ppcp_enable_subscription_product');
        $style = $product->get_type() === 'subscription_variation' ? 'float:left; width:150px;' : '';
        $subscription_product = $product->get_meta('ppcp_subscription_product');
        $subscription_plan = $product->get_meta('ppcp_subscription_plan');
        $subscription_plan_name = $product->get_meta('_ppcp_subscription_plan_name');
        echo '<p class="form-field">';
        echo sprintf(
            // translators: %1$s and %2$s are label open and close tags.
            esc_html__('%1$sConnect to PayPal%2$s', 'woocommerce-paypal-payments'),
            '<label for="ppcp_enable_subscription_product-' . esc_attr((string) $product->get_id()) . '" style="' . esc_attr($style) . '">',
            '</label>'
        );
        $plan_id = $subscription_plan['id'] ?? '';
        echo '<input type="checkbox" id="ppcp_enable_subscription_product-' . esc_attr((string) $product->get_id()) . '" data-subs-plan="' . esc_attr((string) $plan_id) . '" name="_ppcp_enable_subscription_product" value="yes" ' . checked($enable_subscription_product, 'yes', \false) . '/>';
        echo sprintf(
            // translators: %1$s and %2$s are label open and close tags.
            esc_html__('%1$sConnect Product to PayPal Subscriptions Plan%2$s', 'woocommerce-paypal-payments'),
            '<span class="description">',
            '</span>'
        );
        echo wc_help_tip(esc_html__('Create a subscription product and plan to bill customers at regular intervals. Be aware that certain subscription settings cannot be modified once the PayPal Subscription is linked to this product. Unlink the product to edit disabled fields.', 'woocommerce-paypal-payments'));
        echo '</p>';
        if ($subscription_product || $subscription_plan) {
            $display_unlink_p = 'display:none;';
            if ($enable_subscription_product !== 'yes') {
                $display_unlink_p = '';
            }
            echo sprintf(
                // translators: %1$s and %2$s are button and wrapper html tags.
                esc_html__('%1$sUnlink PayPal Subscription Plan%2$s', 'woocommerce-paypal-payments'),
                '<p class="form-field ppcp-enable-subscription" id="ppcp-enable-subscription-' . esc_attr((string) $product->get_id()) . '" style="' . esc_attr($display_unlink_p) . '"><label></label><button class="button ppcp-unlink-sub-plan" id="ppcp-unlink-sub-plan-' . esc_attr((string) $product->get_id()) . '">',
                '</button><span class="spinner is-active" id="spinner-unlink-plan-' . esc_attr((string) $product->get_id()) . '" style="float: none; display:none;"></span></p>'
            );
            echo sprintf(
                // translators: %1$s and %2$s is open and closing paragraph tag.
                esc_html__('%1$sPlan unlinked successfully ✔️%2$s', 'woocommerce-paypal-payments'),
                '<p class="form-field pcpp-plan-unlinked" id="pcpp-plan-unlinked-' . esc_attr((string) $product->get_id()) . '" style="display: none;">',
                '</p>'
            );
            $host = $environment->current_environment_is(Environment::SANDBOX) ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
            if ($subscription_product) {
                echo sprintf(
                    // translators: %1$s and %2$s are wrapper html tags.
                    esc_html__('%1$sProduct%2$s', 'woocommerce-paypal-payments'),
                    '<p class="form-field pcpp-product" id="pcpp-product-' . esc_attr((string) $product->get_id()) . '"><label style="' . esc_attr($style) . '">',
                    '</label><a href="' . esc_url($host . '/billing/plans/products/' . $subscription_product['id']) . '" target="_blank">' . esc_attr($subscription_product['id']) . '</a></p>'
                );
            }
            if ($subscription_plan) {
                echo sprintf(
                    // translators: %1$s and %2$s are wrapper html tags.
                    esc_html__('%1$sPlan%2$s', 'woocommerce-paypal-payments'),
                    '<p class="form-field pcpp-plan" id="pcpp-plan-' . esc_attr((string) $product->get_id()) . '"><label style="' . esc_attr($style) . '">',
                    '</label><a href="' . esc_url($host . '/billing/plans/' . $subscription_plan['id']) . '" target="_blank">' . esc_attr($subscription_plan['id']) . '</a></p>'
                );
            }
        } else {
            $display_plan_name_p = '';
            if ($enable_subscription_product !== 'yes') {
                $display_plan_name_p = 'display:none;';
            }
            echo sprintf(
                // translators: %1$s and %2$s are wrapper html tags.
                esc_html__('%1$sPlan Name%2$s', 'woocommerce-paypal-payments'),
                '<p class="form-field ppcp_subscription_plan_name_p" id="ppcp_subscription_plan_name_p-' . esc_attr((string) $product->get_id()) . '" style="' . esc_attr($display_plan_name_p) . '"><label for="_ppcp_subscription_plan_name-' . esc_attr((string) $product->get_id()) . '">',
                '</label><input type="text" class="short ppcp_subscription_plan_name" id="ppcp_subscription_plan_name-' . esc_attr((string) $product->get_id()) . '" name="_ppcp_subscription_plan_name" value="' . esc_attr($subscription_plan_name) . '"></p>'
            );
        }
    }
}
