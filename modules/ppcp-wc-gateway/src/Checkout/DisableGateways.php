<?php

/**
 * Determines whether specific gateways need to be disabled.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Checkout
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Checkout;

use WooCommerce\PayPalCommerce\Button\Helper\Context;
use WooCommerce\PayPalCommerce\Session\SessionHandler;
use WooCommerce\PayPalCommerce\WcSubscriptions\Helper\SubscriptionHelper;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\SettingsStatus;
/**
 * Class DisableGateways
 */
class DisableGateways
{
    /**
     * @var Context Context data provider.
     */
    private Context $context;
    /**
     * The Settings.
     *
     * @var ContainerInterface
     */
    private $settings;
    /**
     * The Settings status helper.
     *
     * @var SettingsStatus
     */
    protected $settings_status;
    /**
     * The subscription helper.
     *
     * @var SubscriptionHelper
     */
    private $subscription_helper;
    /**
     * DisableGateways constructor.
     *
     * @param ContainerInterface $settings The Settings.
     * @param SettingsStatus     $settings_status The Settings status helper.
     * @param SubscriptionHelper $subscription_helper The subscription helper.
     */
    public function __construct(ContainerInterface $settings, SettingsStatus $settings_status, SubscriptionHelper $subscription_helper, Context $context)
    {
        $this->settings = $settings;
        $this->settings_status = $settings_status;
        $this->subscription_helper = $subscription_helper;
        $this->context = $context;
    }
    /**
     * Controls the logic for enabling/disabling gateways.
     *
     * @param array $methods The Gateways.
     *
     * @return array
     */
    public function handler(array $methods): array
    {
        if (!isset($methods[PayPalGateway::ID]) && !isset($methods[CreditCardGateway::ID])) {
            return $methods;
        }
        if ($this->disable_all_gateways()) {
            unset($methods[PayPalGateway::ID]);
            unset($methods[CreditCardGateway::ID]);
            unset($methods[CardButtonGateway::ID]);
            return $methods;
        }
        if (!$this->settings->has('client_id') || empty($this->settings->get('client_id'))) {
            unset($methods[CreditCardGateway::ID]);
        }
        if (!$this->settings_status->is_smart_button_enabled_for_location('checkout')) {
            unset($methods[CardButtonGateway::ID]);
            if ($this->subscription_helper->cart_contains_subscription()) {
                unset($methods[PayPalGateway::ID]);
            }
        }
        if (!$this->needs_to_disable_gateways()) {
            return $methods;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $payment_method = wc_clean(wp_unslash($_POST['payment_method'] ?? ''));
        if ($payment_method && is_string($payment_method)) {
            return array($payment_method => $methods[$payment_method]);
        }
        return array(PayPalGateway::ID => $methods[PayPalGateway::ID]);
    }
    /**
     * Whether all gateways should be disabled or not.
     *
     * @return bool
     */
    private function disable_all_gateways(): bool
    {
        if (is_null(WC()->payment_gateways)) {
            return \false;
        }
        foreach (WC()->payment_gateways->payment_gateways() as $gateway) {
            if (PayPalGateway::ID === $gateway->id && $gateway->enabled !== 'yes') {
                return \true;
            }
        }
        if (!$this->settings->has('merchant_email') || !is_email($this->settings->get('merchant_email'))) {
            return \true;
        }
        return \false;
    }
    /**
     * Whether the Gateways need to be disabled. When we come to the checkout with a running PayPal
     * session, we need to disable the other Gateways, so the customer can smoothly sail through the
     * process.
     *
     * @return bool
     */
    private function needs_to_disable_gateways(): bool
    {
        return $this->context->is_paypal_continuation();
    }
}
