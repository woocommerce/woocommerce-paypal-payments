<?php

/**
 * The subscription module.
 *
 * @package WooCommerce\PayPalCommerce\WcSubscriptions
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcSubscriptions;

use Exception;
use WooCommerce\PayPalCommerce\Vendor\Psr\Log\LoggerInterface;
use WC_Order;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Service\PaymentMethodTokensChecker;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\Settings\Data\SettingsProvider;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Service\ChangePaymentMethod;
/**
 * Class SubscriptionModule
 */
class WcSubscriptionsModule implements ServiceModule, ExecutableModule
{
    use ModuleClassNameIdTrait;
    use TransactionIdHandlingTrait;
    private const VAULT_SUPPORTS_SUBSCRIPTIONS = array('subscriptions', 'subscription_cancellation', 'subscription_suspension', 'subscription_reactivation', 'subscription_amount_changes', 'subscription_date_changes', 'subscription_payment_method_change', 'subscription_payment_method_change_customer', 'subscription_payment_method_change_admin', 'multiple_subscriptions');
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
        $subscriptions_helper = $c->get('wc-subscriptions.helper');
        assert($subscriptions_helper instanceof SubscriptionHelper);
        if (!$subscriptions_helper->plugin_is_active()) {
            return \true;
        }
        $this->add_gateways_support($c);
        add_action(
            'woocommerce_scheduled_subscription_payment_' . PayPalGateway::ID,
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($amount, $order) use ($c) {
                $this->renew($order, $c);
            },
            10,
            2
        );
        add_action(
            'woocommerce_scheduled_subscription_payment_' . CreditCardGateway::ID,
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($amount, $order) use ($c) {
                $this->renew($order, $c);
            },
            10,
            2
        );
        add_filter(
            'woocommerce_subscription_payment_method_to_display',
            /**
             * Corrects the payment method name for subscriptions.
             *
             * @param string $payment_method_to_display The payment method string.
             * @param \WC_Subscription $subscription The subscription instance.
             * @param string $context The context, ex: view.
             * @return string
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($payment_method_to_display, $subscription, $context) {
                $payment_gateway = wc_get_payment_gateway_by_order($subscription);
                if ($payment_gateway instanceof \WC_Payment_Gateway && $payment_gateway->id === PayPalGateway::ID) {
                    return $subscription->get_payment_method_title($context);
                }
                return $payment_method_to_display;
            },
            10,
            3
        );
        add_action('wc_ajax_' . SubscriptionChangePaymentMethod::ENDPOINT, static function () use ($c) {
            $endpoint = $c->get('wc-subscriptions.endpoint.subscription-change-payment-method');
            assert($endpoint instanceof SubscriptionChangePaymentMethod);
            $endpoint->handle_request();
        });
        add_action('woocommerce_subscriptions_change_payment_after_submit', function () use ($c) {
            $context = $c->get('button.helper.context');
            assert($context instanceof Context);
            if (!is_user_logged_in() || !$context->is_subscription_change_payment_method_page()) {
                return;
            }
            $payment_method_tokens_checked = $c->get('save-payment-methods.service.payment-method-tokens-checker');
            assert($payment_method_tokens_checked instanceof PaymentMethodTokensChecker);
            $customer_id = get_user_meta(get_current_user_id(), '_ppcp_target_customer_id', \true);
            // Do not display PayPal button if the user already has a PayPal payment token.
            if ($payment_method_tokens_checked->has_paypal_payment_token($customer_id)) {
                return;
            }
            echo '<div id="ppc-button-' . esc_attr(PayPalGateway::ID) . '-save-payment-method"></div>';
        });
        /**
         * If customer has chosen change Subscription payment to PayPal payment.
         */
        add_filter(
            'woocommerce_paypal_payments_before_order_process',
            /**
             * WC_Payment_Gateway $gateway type removed.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function (bool $process, $gateway, WC_Order $wc_order) use ($c) {
                if (!$gateway instanceof PayPalGateway || $gateway::ID !== PayPalGateway::ID) {
                    return $process;
                }
                $change_payment_method = $c->get('wc-subscriptions.change-payment-method');
                assert($change_payment_method instanceof ChangePaymentMethod);
                return $change_payment_method->to_paypal_payment();
            },
            10,
            3
        );
        add_action(
            'woocommerce_subscription_payment_complete',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($subscription) {
                if (!in_array($subscription->get_payment_method(), array(PayPalGateway::ID, CreditCardGateway::ID, CardButtonGateway::ID), \true)) {
                    return;
                }
                $paypal_subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if ($paypal_subscription_id) {
                    return;
                }
                if (count($subscription->get_related_orders()) === 1) {
                    $parent_order = $subscription->get_parent();
                    if ($parent_order instanceof WC_Order) {
                        // Update the initial payment method title if not the same as the first order.
                        $payment_method_title = $parent_order->get_payment_method_title();
                        if ($payment_method_title && $subscription instanceof \WC_Subscription && $subscription->get_payment_method_title() !== $payment_method_title) {
                            $subscription->set_payment_method_title($payment_method_title);
                            $subscription->save();
                        }
                    }
                }
            }
        );
        return \true;
    }
    /**
     * Handles a Subscription product renewal.
     *
     * @param WC_Order           $order WooCommerce order.
     * @param ContainerInterface $container The container.
     * @return void
     */
    protected function renew(WC_Order $order, ContainerInterface $container)
    {
        $handler = $container->get('wc-subscriptions.renewal-handler');
        assert($handler instanceof \WooCommerce\PayPalCommerce\WcSubscriptions\RenewalHandler);
        $handler->renew($order);
    }
    /**
     * Groups all filters for adding WC Subscriptions gateway support.
     *
     * @param ContainerInterface $c The container.
     * @return void
     */
    private function add_gateways_support(ContainerInterface $c): void
    {
        add_filter('woocommerce_paypal_payments_paypal_gateway_supports', function (array $supports) use ($c): array {
            $settings_provider = $c->get('settings.settings-provider');
            assert($settings_provider instanceof SettingsProvider);
            $subscription_helper = $c->get('wc-subscriptions.helper');
            assert($subscription_helper instanceof SubscriptionHelper);
            $subscriptions_mode = $this->get_subscriptions_mode($settings_provider, $subscription_helper);
            if ('disable_paypal_subscriptions' === $subscriptions_mode) {
                return $supports;
            }
            return array_merge($supports, self::VAULT_SUPPORTS_SUBSCRIPTIONS);
        });
        add_filter('woocommerce_paypal_payments_credit_card_gateway_supports', function (array $supports) use ($c): array {
            $settings_provider = $c->get('settings.settings-provider');
            assert($settings_provider instanceof SettingsProvider);
            $subscription_helper = $c->get('wc-subscriptions.helper');
            assert($subscription_helper instanceof SubscriptionHelper);
            $subscriptions_mode = $this->get_subscriptions_mode($settings_provider, $subscription_helper);
            if ('disable_paypal_subscriptions' === $subscriptions_mode) {
                return $supports;
            }
            if (!$settings_provider->save_card_details()) {
                return $supports;
            }
            return array_merge($supports, self::VAULT_SUPPORTS_SUBSCRIPTIONS);
        });
        add_filter('woocommerce_paypal_payments_card_button_gateway_supports', function (array $supports) use ($c): array {
            $settings_provider = $c->get('settings.settings-provider');
            assert($settings_provider instanceof SettingsProvider);
            $subscription_helper = $c->get('wc-subscriptions.helper');
            assert($subscription_helper instanceof SubscriptionHelper);
            $subscriptions_mode = $this->get_subscriptions_mode($settings_provider, $subscription_helper);
            if ('disable_paypal_subscriptions' === $subscriptions_mode) {
                return $supports;
            }
            return array_merge($supports, self::VAULT_SUPPORTS_SUBSCRIPTIONS);
        });
    }
    /**
     * Gets the subscriptions mode based on settings.
     *
     * @param SettingsProvider   $settings_provider The settings provider.
     * @param SubscriptionHelper $subscription_helper The subscription helper.
     * @return string The subscriptions mode ('vaulting_api', 'subscriptions_api', or 'disable_paypal_subscriptions').
     */
    private function get_subscriptions_mode(SettingsProvider $settings_provider, SubscriptionHelper $subscription_helper): string
    {
        if (!$subscription_helper->plugin_is_active()) {
            return '';
        }
        $subscription_mode_disabled = (bool) apply_filters('woocommerce_paypal_payments_subscription_mode_disabled', \false);
        if ($subscription_mode_disabled) {
            return 'disable_paypal_subscriptions';
        }
        return $settings_provider->save_paypal_and_venmo() ? 'vaulting_api' : 'subscriptions_api';
    }
}
