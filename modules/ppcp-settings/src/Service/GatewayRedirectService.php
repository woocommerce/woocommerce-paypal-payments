<?php

/**
 * Provides gateway redirect handling logic.
 *
 * @package WooCommerce\PayPalCommerce\Settings\Service
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Settings\Service;

use WooCommerce\PayPalCommerce\Axo\Gateway\AxoGateway;
use WooCommerce\PayPalCommerce\Applepay\ApplePayGateway;
use WooCommerce\PayPalCommerce\Googlepay\GooglePayGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BancontactGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\BlikGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\EPSGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\IDealGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MultibancoGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\MyBankGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\P24Gateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\PWCGateway;
use WooCommerce\PayPalCommerce\LocalAlternativePaymentMethods\TrustlyGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXO;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
/**
 * GatewayRedirectService class. Handles redirects from individual gateway
 * settings URLs to the new Settings UI page.
 */
class GatewayRedirectService
{
    /**
     * List of gateways to redirect.
     *
     * @var string[]
     */
    private array $gateways;
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->gateways = array(AxoGateway::ID, GooglePayGateway::ID, ApplePayGateway::ID, CreditCardGateway::ID, CardButtonGateway::ID, BancontactGateway::ID, BlikGateway::ID, EPSGateway::ID, IDealGateway::ID, MyBankGateway::ID, P24Gateway::ID, TrustlyGateway::ID, MultibancoGateway::ID, OXXO::ID, PayUponInvoiceGateway::ID, PWCGateway::ID);
    }
    /**
     * Register hooks.
     *
     * @return void
     */
    public function register(): void
    {
        add_action('admin_init', array($this, 'handle_redirects'));
    }
    /**
     * Handle redirects for gateway settings pages.
     *
     * @return void
     */
    public function handle_redirects(): void
    {
        if (!is_admin()) {
            return;
        }
        // Get current URL parameters.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? wc_clean(wp_unslash($_GET['page'])) : '';
        $tab = isset($_GET['tab']) ? wc_clean(wp_unslash($_GET['tab'])) : '';
        $section = isset($_GET['section']) ? wc_clean(wp_unslash($_GET['section'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended
        // Check if we're on a WooCommerce settings page and checkout tab.
        if ($page !== 'wc-settings' || $tab !== 'checkout') {
            return;
        }
        // Check if we're on one of the gateway settings pages we want to redirect.
        if (in_array($section, $this->gateways, \true)) {
            $redirect_url = admin_url(sprintf('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&panel=payment-methods&highlight=%s', $section));
            wp_safe_redirect($redirect_url);
            exit;
        }
    }
}
