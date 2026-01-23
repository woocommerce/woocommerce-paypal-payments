<?php

/**
 * Renders the Sections Tab.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Settings
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\ApiClient\Helper\DccApplies;
use WooCommerce\PayPalCommerce\Button\Helper\MessagesApply;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CardButtonGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\CreditCardGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\OXXO\OXXOGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayUponInvoice\PayUponInvoiceGateway;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DCCProductStatus;
use WooCommerce\PayPalCommerce\WcGateway\Helper\PayUponInvoiceProductStatus;
/**
 * Class SectionsRenderer
 */
class SectionsRenderer
{
    const KEY = 'ppcp-tab';
    /**
     * ID of the current PPCP gateway settings page, or empty if it is not such page.
     *
     * @var string
     */
    protected $page_id;
    /**
     * Whether onboarding was completed and the merchant is connected to PayPal.
     *
     * @var bool
     */
    private bool $is_connected;
    /**
     * The DCC product status
     *
     * @var DCCProductStatus
     */
    private $dcc_product_status;
    /**
     * The DCC applies
     *
     * @var DccApplies
     */
    private $dcc_applies;
    /**
     * The messages apply.
     *
     * @var MessagesApply
     */
    private $messages_apply;
    /**
     * The PUI product status.
     *
     * @var PayUponInvoiceProductStatus
     */
    private $pui_product_status;
    /**
     * SectionsRenderer constructor.
     *
     * @param string                      $page_id ID of the current PPCP gateway settings page, or empty if it is not such page.
     * @param bool                        $is_connected Whether the merchant completed onboarding.
     * @param DCCProductStatus            $dcc_product_status The DCC product status.
     * @param DccApplies                  $dcc_applies The DCC applies.
     * @param MessagesApply               $messages_apply The Messages apply.
     * @param PayUponInvoiceProductStatus $pui_product_status The PUI product status.
     */
    public function __construct(string $page_id, bool $is_connected, DCCProductStatus $dcc_product_status, DccApplies $dcc_applies, MessagesApply $messages_apply, PayUponInvoiceProductStatus $pui_product_status)
    {
        $this->page_id = $page_id;
        $this->is_connected = $is_connected;
        $this->dcc_product_status = $dcc_product_status;
        $this->dcc_applies = $dcc_applies;
        $this->messages_apply = $messages_apply;
        $this->pui_product_status = $pui_product_status;
    }
    /**
     * Whether the sections tab should be rendered.
     *
     * @return bool
     */
    public function should_render(): bool
    {
        return $this->page_id && $this->is_connected;
    }
    /**
     * Renders the Sections tab.
     *
     * @return string
     */
    public function render(): string
    {
        if (!$this->should_render()) {
            return '';
        }
        $html = '<nav class="nav-tab-wrapper woo-nav-tab-wrapper">';
        foreach ($this->sections() as $id => $label) {
            $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=' . (string) $id);
            if (in_array($id, array(\WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::CONNECTION_TAB_ID, CreditCardGateway::ID, \WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::PAY_LATER_TAB_ID), \true)) {
                // We need section=ppcp-gateway for the webhooks page because it is not a gateway,
                // and for DCC because otherwise it will not render the page if gateway is not available (country/currency).
                // Other gateways render fields differently, and their pages are not expected to work when gateway is not available.
                $url = admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&' . self::KEY . '=' . $id);
            }
            $html .= '<a href="' . esc_url($url) . '" class="nav-tab ' . ($this->page_id === $id ? 'nav-tab-active' : '') . '">' . esc_html($label) . '</a> ';
        }
        $html .= '</nav>';
        return $html;
    }
    /**
     * Returns sections as Key - page/gateway ID, value - displayed text.
     *
     * @return array
     */
    private function sections(): array
    {
        $sections = array(\WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::CONNECTION_TAB_ID => __('Connection', 'woocommerce-paypal-payments'), PayPalGateway::ID => __('Standard Payments', 'woocommerce-paypal-payments'), \WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::PAY_LATER_TAB_ID => __('Pay Later', 'woocommerce-paypal-payments'), CreditCardGateway::ID => __('Advanced Card Processing', 'woocommerce-paypal-payments'), CardButtonGateway::ID => __('Standard Card Button', 'woocommerce-paypal-payments'), OXXOGateway::ID => __('OXXO', 'woocommerce-paypal-payments'), PayUponInvoiceGateway::ID => __('Pay upon Invoice', 'woocommerce-paypal-payments'));
        // Remove for all not registered in WC gateways that cannot render anything in this case.
        $gateways = WC()->payment_gateways->payment_gateways();
        foreach (array_diff(array_keys($sections), array(\WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::CONNECTION_TAB_ID, PayPalGateway::ID, CreditCardGateway::ID, \WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::PAY_LATER_TAB_ID)) as $id) {
            if (!isset($gateways[$id])) {
                unset($sections[$id]);
            }
        }
        if (!$this->dcc_product_status->is_active() || !$this->dcc_applies->for_country_currency()) {
            unset($sections['ppcp-credit-card-gateway']);
        }
        if (!$this->messages_apply->for_country()) {
            unset($sections[\WooCommerce\PayPalCommerce\WcGateway\Settings\Settings::PAY_LATER_TAB_ID]);
        }
        if (!$this->pui_product_status->is_active()) {
            unset($sections[PayUponInvoiceGateway::ID]);
        }
        return $sections;
    }
}
