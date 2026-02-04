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
use WC_Payment_Tokens;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\SavePaymentMethods\Service\PaymentMethodTokensChecker;
use WooCommerce\PayPalCommerce\Vaulting\PaymentTokenRepository;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExecutableModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ExtendingModule;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ModuleClassNameIdTrait;
use WooCommerce\PayPalCommerce\Vendor\Inpsyde\Modularity\Module\ServiceModule;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Processor\TransactionIdHandlingTrait;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
use WooCommerce\PayPalCommerce\WcSubscriptions\Endpoint\SubscriptionChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\FreeTrialSubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcSubscriptions\Service\ChangePaymentMethod;
use WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2\ChangePaymentMethodVaultV2;
use WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2\DisplaySavedPaymentTokens;
use WooCommerce\PayPalCommerce\WcSubscriptions\VaultV2\VaultedPayPalEmail;
/**
 * Class SubscriptionModule
 */
class WcSubscriptionsModule implements ServiceModule, ExtendingModule, ExecutableModule
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
    public function extensions(): array
    {
        return require __DIR__ . '/../extensions.php';
    }
    /**
     * {@inheritDoc}
     */
    public function run(ContainerInterface $c): bool
    {
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
         * It currently handles both cases Vault v3 and v2.
         * Vault v2 would be removed when Vault v3 becomes the only available vaulting method.
         */
        add_filter(
            'woocommerce_paypal_payments_before_order_process',
            /**
             * WC_Payment_Gateway $gateway type removed.
             *
             * @psalm-suppress MissingClosureParamType
             * @throws Exception When changing payment fails.
             */
            function (bool $process, $gateway, WC_Order $wc_order) use ($c) {
                if (!$gateway instanceof PayPalGateway || $gateway::ID !== PayPalGateway::ID) {
                    return $process;
                }
                if ($c->has('save-payment-methods.eligible') && $c->get('save-payment-methods.eligible')) {
                    $change_payment_method = $c->get('wc-subscriptions.change-payment-method');
                    assert($change_payment_method instanceof ChangePaymentMethod);
                    return $change_payment_method->to_paypal_payment();
                }
                $change_payment_method_vault_v2 = $c->get('wc-subscriptions.vault-v2.change-payment-method');
                assert($change_payment_method_vault_v2 instanceof ChangePaymentMethodVaultV2);
                try {
                    return $change_payment_method_vault_v2->to_paypal_payment($wc_order);
                } catch (Exception $exception) {
                    throw new Exception($exception->getMessage());
                }
            },
            10,
            3
        );
        /**
         * Vault v2 - Adds Payment Token ID to subscription after initial payment.
         * It will be removed when Vault v3 becomes the only available vaulting method.
         */
        add_action(
            'woocommerce_subscription_payment_complete',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($subscription) use ($c) {
                if (!in_array($subscription->get_payment_method(), array(PayPalGateway::ID, CreditCardGateway::ID, CardButtonGateway::ID), \true)) {
                    return;
                }
                $paypal_subscription_id = $subscription->get_meta('ppcp_subscription') ?? '';
                if ($paypal_subscription_id) {
                    return;
                }
                $payment_token_repository = $c->get('vaulting.repository.payment-token');
                $logger = $c->get('woocommerce.logger.woocommerce');
                if (!$c->has('save-payment-methods.eligible') || !$c->get('save-payment-methods.eligible')) {
                    $this->add_payment_token_id($subscription, $payment_token_repository, $logger);
                }
                if (count($subscription->get_related_orders()) === 1) {
                    $parent_order = $subscription->get_parent();
                    if (is_a($parent_order, WC_Order::class)) {
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
        /**
         * Vault v2 - Hides PayPal and Credit Card gateways if customer has no saved payments.
         * It will be removed when Vault v3 becomes the only available vaulting method.
         */
        add_filter(
            'woocommerce_available_payment_gateways',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($methods) use ($c) {
                if (!is_array($methods)) {
                    return $methods;
                }
                //phpcs:disable WordPress.Security.NonceVerification.Recommended
                if (!(isset($_GET['change_payment_method']) && is_wc_endpoint_url('order-pay'))) {
                    return $methods;
                }
                if ($c->has('save-payment-methods.eligible') && $c->get('save-payment-methods.eligible')) {
                    return $methods;
                }
                // Vault v2 - If customer does not have saved PayPal payments, remove PayPal gateway from available payment methods.
                // The reason is that it's not possible to save a payment without purchasing.
                $paypal_tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), PayPalGateway::ID);
                if (!$paypal_tokens) {
                    unset($methods[PayPalGateway::ID]);
                }
                // Vault v2 - If customer does not have saved card payments, remove credit card gateway from available payment methods.
                // The reason is that it's not possible to save a payment without purchasing.
                $card_tokens = WC_Payment_Tokens::get_customer_tokens(get_current_user_id(), CreditCardGateway::ID);
                if (!$card_tokens) {
                    unset($methods[CreditCardGateway::ID]);
                }
                return $methods;
            }
        );
        /**
         * Vault v2 - Custom saved PayPal payment tokens implementation.
         * It will be removed when Vault v3 becomes the only available vaulting method.
         */
        add_filter(
            'woocommerce_gateway_description',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($description, $id) use ($c) {
                if ($c->has('save-payment-methods.eligible') && $c->get('save-payment-methods.eligible')) {
                    return $description;
                }
                $display_saved_payment_tokens = $c->get('wc-subscriptions.vault-v2.display-saved-payment-tokens');
                assert($display_saved_payment_tokens instanceof DisplaySavedPaymentTokens);
                return $display_saved_payment_tokens->display_saved_paypal_payments((string) $id, (string) $description);
            },
            10,
            2
        );
        /**
         * Vault v2 - Custom saved credit card payment tokens implementation.
         * It will be removed when Vault v3 becomes the only available vaulting method.
         */
        add_filter(
            'woocommerce_credit_card_form_fields',
            /**
             * Param types removed to avoid third-party issues.
             *
             * @psalm-suppress MissingClosureParamType
             */
            function ($default_fields, $id) use ($c) {
                if ($c->has('save-payment-methods.eligible') && $c->get('save-payment-methods.eligible')) {
                    return $default_fields;
                }
                $display_saved_payment_tokens = $c->get('wc-subscriptions.vault-v2.display-saved-payment-tokens');
                assert($display_saved_payment_tokens instanceof DisplaySavedPaymentTokens);
                return $display_saved_payment_tokens->display_saved_credit_cards((string) $id, $default_fields);
            },
            20,
            2
        );
        /**
         * Vault v2 Free trial subscription, adds PayPal email into checkout form.
         */
        add_action('woocommerce_paypal_payments_smart_button_render_wrapper', function () use ($c) {
            // Return early if save payment methods (Vault v3) is enabled.
            if ($c->has('save-payment-methods.eligible') && $c->get('save-payment-methods.eligible')) {
                return;
            }
            $free_trial_subscription_helper = $c->get('wc-subscriptions.free-trial-subscription-helper');
            assert($free_trial_subscription_helper instanceof FreeTrialSubscriptionHelper);
            if (!$free_trial_subscription_helper->is_free_trial_cart()) {
                return;
            }
            add_action('woocommerce_review_order_after_submit', function () use ($c) {
                $vaulted_paypal_email = $c->get('wc-subscriptions.vault-v2.vaulted-paypal-email');
                assert($vaulted_paypal_email instanceof VaultedPayPalEmail);
                $vaulted_email = $vaulted_paypal_email->get_vaulted_paypal_email();
                if (!$vaulted_email) {
                    return;
                }
                ?>
						<div class="ppcp-vaulted-paypal-details">
							<?php 
                echo wp_kses_post(sprintf(
                    // translators: %1$s - email, %2$s, %3$s - HTML tags for a link.
                    esc_html__('Using %2$s%1$s%3$s PayPal.', 'woocommerce-paypal-payments'),
                    $vaulted_email,
                    '<b>',
                    '</b>'
                ));
                ?>
						</div>
						<?php 
            });
        });
        /**
         * Vault v2 Free trial subscription, adds vaulted PayPal email to localized script data.
         */
        add_filter('woocommerce_paypal_payments_localized_script_data', function (array $localized_script_data) use ($c) {
            if ($c->has('save-payment-methods.eligible') && $c->get('save-payment-methods.eligible')) {
                return $localized_script_data;
            }
            $vaulted_paypal_email = $c->get('wc-subscriptions.vault-v2.vaulted-paypal-email');
            assert($vaulted_paypal_email instanceof VaultedPayPalEmail);
            $vaulted_email = $vaulted_paypal_email->get_vaulted_paypal_email();
            if (!$vaulted_email) {
                return $localized_script_data;
            }
            $free_trial_subscription_helper = $c->get('wc-subscriptions.free-trial-subscription-helper');
            assert($free_trial_subscription_helper instanceof FreeTrialSubscriptionHelper);
            $localized_script_data['vaulted_paypal_email'] = is_checkout() && $free_trial_subscription_helper->is_free_trial_cart() ? $vaulted_paypal_email->get_vaulted_paypal_email() : '';
            return $localized_script_data;
        });
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
     * Adds Payment token ID to subscription.
     *
     * @param \WC_Subscription       $subscription The subscription.
     * @param PaymentTokenRepository $payment_token_repository The payment repository.
     * @param LoggerInterface        $logger The logger.
     */
    protected function add_payment_token_id(\WC_Subscription $subscription, PaymentTokenRepository $payment_token_repository, LoggerInterface $logger): void
    {
        try {
            $tokens = $payment_token_repository->all_for_user_id($subscription->get_customer_id());
            if ($tokens) {
                $latest_token_id = end($tokens)->id() ? end($tokens)->id() : '';
                $subscription->update_meta_data('payment_token_id', $latest_token_id);
                $subscription->save();
            }
        } catch (RuntimeException $error) {
            $message = sprintf(
                // translators: %1$s is the payment token Id, %2$s is the error message.
                __('Could not add token Id to subscription %1$s: %2$s', 'woocommerce-paypal-payments'),
                $subscription->get_id(),
                $error->getMessage()
            );
            $logger->log('warning', $message);
        }
    }
    /**
     * Groups all filters for adding WC Subscriptions gateway support.
     *
     * @param ContainerInterface $c The container.
     * @return void
     */
    private function add_gateways_support(ContainerInterface $c): void
    {
        $subscriptions_helper = $c->get('wc-subscriptions.helper');
        assert($subscriptions_helper instanceof SubscriptionHelper);
        if (!$subscriptions_helper->plugin_is_active()) {
            return;
        }
        add_filter('woocommerce_paypal_payments_paypal_gateway_supports', function (array $supports) use ($c): array {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $subscriptions_mode = $settings->has('subscriptions_mode') ? $settings->get('subscriptions_mode') : '';
            if ('disable_paypal_subscriptions' === $subscriptions_mode) {
                return $supports;
            }
            return array_merge($supports, self::VAULT_SUPPORTS_SUBSCRIPTIONS);
        });
        add_filter('woocommerce_paypal_payments_credit_card_gateway_supports', function (array $supports) use ($c): array {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $subscriptions_mode = $settings->has('subscriptions_mode') ? $settings->get('subscriptions_mode') : '';
            if ('disable_paypal_subscriptions' === $subscriptions_mode) {
                return $supports;
            }
            $vaulting_enabled = $settings->has('vault_enabled_dcc') && $settings->get('vault_enabled_dcc');
            if (!$vaulting_enabled) {
                return $supports;
            }
            return array_merge($supports, self::VAULT_SUPPORTS_SUBSCRIPTIONS);
        });
        add_filter('woocommerce_paypal_payments_card_button_gateway_supports', function (array $supports) use ($c): array {
            $settings = $c->get('wcgateway.settings');
            assert($settings instanceof Settings);
            $subscriptions_mode = $settings->has('subscriptions_mode') ? $settings->get('subscriptions_mode') : '';
            if ('disable_paypal_subscriptions' === $subscriptions_mode) {
                return $supports;
            }
            return array_merge($supports, self::VAULT_SUPPORTS_SUBSCRIPTIONS);
        });
    }
}
