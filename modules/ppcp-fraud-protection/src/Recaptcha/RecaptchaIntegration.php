<?php

declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\FraudProtection\Recaptcha;

use WC_Integration;
class RecaptchaIntegration extends WC_Integration
{
    public const ID = 'ppcp-recaptcha';
    public function __construct()
    {
        $this->id = self::ID;
        $this->method_title = 'WooCommerce PayPal Payments reCAPTCHA';
        $this->method_description = sprintf('<h3>Protect your store from card-testing fraud and potential fines.</h3>Protect your store from card-testing fraud and potential fines. Card networks like Visa, Mastercard, American Express and Discover are actively penalizing merchants without fraud prevention controls—consequences include per-transaction fines, processing restrictions, and account termination.<br>Enable reCAPTCHA protection below to block automated card-testing attacks without adding friction for real customers. Both reCAPTCHA v3 and v2 keys must be configured for protection to activate. %s', '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/fraud-and-disputes/#section-4" target="_blank">Learn more</a>');
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
    }
    public function init_form_fields()
    {
        $this->form_fields = array('enabled' => array('title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable reCAPTCHA protection', 'default' => 'no'), 'log' => array('type' => 'ppcp_recaptcha_log'), 'v3_title' => array('title' => 'reCAPTCHA v3 Settings', 'type' => 'title', 'description' => sprintf('Invisible protection that scores visitor behavior (0.0–1.0). Create a <b>Score based (v3)</b> site at <a href="%s" target="_blank">Google reCAPTCHA Admin</a>. v2 keys are also required below.', 'https://www.google.com/recaptcha/admin')), 'site_key_v3' => array('title' => 'v3 Site Key', 'type' => 'text', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v3 site key'), 'secret_key_v3' => array('title' => 'v3 Secret Key', 'type' => 'password', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v3 secret key'), 'score_threshold' => array('title' => 'Score Threshold', 'type' => 'number', 'default' => '0.5', 'custom_attributes' => array('min' => '0', 'max' => '1', 'step' => '0.1'), 'desc_tip' => \true, 'description' => 'Minimum score to pass (0.0–1.0). Lower scores trigger v2 fallback. Recommended: 0.5'), 'v2_title' => array('title' => 'reCAPTCHA v2 Settings', 'type' => 'title', 'description' => sprintf('Visible checkbox challenge when v3 score falls below threshold. Create a <b>Challenge (v2) → "I\'m not a robot" Checkbox</b> site at <a href="%s" target="_blank">Google reCAPTCHA Admin</a>. Required alongside v3 above.', 'https://www.google.com/recaptcha/admin')), 'site_key_v2' => array('title' => 'v2 Site Key', 'type' => 'text', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v2 (checkbox) site key'), 'secret_key_v2' => array('title' => 'v2 Secret Key', 'type' => 'password', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v2 secret key'), 'v2_theme' => array('title' => 'v2 Theme', 'type' => 'select', 'default' => 'light', 'options' => array('light' => 'Light', 'dark' => 'Dark'), 'desc_tip' => \true, 'description' => 'Color theme for the v2 checkbox'), 'scope_title' => array('title' => 'Protection Scope', 'type' => 'title', 'description' => 'Limit protection scope (requires both v3 and v2 keys configured)'), 'guest_only' => array('title' => 'Guest Orders Only', 'type' => 'checkbox', 'label' => 'Only verify for non-logged-in users', 'default' => 'yes', 'description' => 'Skip reCAPTCHA for logged-in customers'), 'advanced_title' => array('title' => 'Advanced Options', 'type' => 'title'), 'show_metabox' => array('title' => 'Order Metabox', 'type' => 'checkbox', 'label' => 'Show reCAPTCHA status metabox on order pages', 'default' => 'no', 'description' => 'Display reCAPTCHA verification details in a metabox on order edit pages'), 'log_rejections' => array('title' => 'Log', 'type' => 'checkbox', 'label' => 'Log rejected attempts', 'default' => 'no', 'description' => 'Log information about the checkout attempts rejected by v3 reCAPTCHA'));
    }
    public function process_admin_options()
    {
        $post_data = $this->get_post_data();
        $enabled = $post_data['woocommerce_' . $this->id . '_enabled'] ?? \false;
        $site_key_v3 = $post_data['woocommerce_' . $this->id . '_site_key_v3'] ?? '';
        $secret_key_v3 = $post_data['woocommerce_' . $this->id . '_secret_key_v3'] ?? '';
        $site_key_v2 = $post_data['woocommerce_' . $this->id . '_site_key_v2'] ?? '';
        $secret_key_v2 = $post_data['woocommerce_' . $this->id . '_secret_key_v2'] ?? '';
        if (!empty($enabled) && (empty($site_key_v3) || empty($secret_key_v3) || empty($site_key_v2) || empty($secret_key_v2))) {
            \WC_Admin_Settings::add_error(__('All reCAPTCHA keys (v3 and v2) must be configured to enable this feature.', 'woocommerce-paypal-payments'));
            return \false;
        }
        return parent::process_admin_options();
    }
}
