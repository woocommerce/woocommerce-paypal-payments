<?php

/**
 * PayPal Agentic Commerce Status Tab
 *
 * @package WooCommerce\PayPalCommerce\StoreSync\Inspector
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\StoreSync\Inspector;

use WooCommerce\PayPalCommerce\StoreSync\Inspector\Page\CartSessionSection;
use WooCommerce\PayPalCommerce\StoreSync\Inspector\Page\RegistrationStatusSection;
/**
 * Class InspectionStatusPage
 *
 * Coordinates the PayPal Agentic Commerce status tab in WooCommerce → Status page.
 * Acts as a coordinator, delegating rendering to section classes.
 */
class InspectionStatusPage
{
    private \WooCommerce\PayPalCommerce\StoreSync\Inspector\InspectionFormHandler $form_handler;
    private RegistrationStatusSection $registration_section;
    private CartSessionSection $session_section;
    public function __construct(\WooCommerce\PayPalCommerce\StoreSync\Inspector\InspectionFormHandler $form_handler, RegistrationStatusSection $registration_section, CartSessionSection $session_section)
    {
        $this->form_handler = $form_handler;
        $this->registration_section = $registration_section;
        $this->session_section = $session_section;
    }
    /**
     * Initialize the status tab and form handler by registering WordPress hooks.
     */
    public function init(): void
    {
        $this->form_handler->init();
        add_filter('woocommerce_admin_status_tabs', fn(array $tabs) => $this->add_tab($tabs), 99);
        add_action('woocommerce_admin_status_content_paypal-agentic', fn() => $this->registration_section->render(), 10);
        add_action('woocommerce_admin_status_content_paypal-agentic', fn() => $this->session_section->render(), 11);
    }
    /**
     * Add PayPal Agentic tab to WooCommerce status tabs.
     *
     * @param array $tabs Existing status tabs.
     * @return array Modified tabs array with PayPal Agentic tab added.
     */
    private function add_tab(array $tabs): array
    {
        $tabs['paypal-agentic'] = __('Store Sync', 'woocommerce-paypal-payments');
        return $tabs;
    }
}
