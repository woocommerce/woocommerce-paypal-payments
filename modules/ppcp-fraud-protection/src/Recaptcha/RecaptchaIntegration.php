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
        $this->method_description = 'Protects PayPal for WooCommerce checkout and cart with Google reCAPTCHA v3 (primary) and v2 (fallback).';
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_integration_' . $this->id, array($this, 'process_admin_options'));
    }
    public function init_form_fields()
    {
        $this->form_fields = array('enabled' => array('title' => 'Enable/Disable', 'type' => 'checkbox', 'label' => 'Enable reCAPTCHA protection', 'default' => 'no'), 'log' => array('type' => 'ppcp_recaptcha_log'), 'v3_title' => array('title' => 'reCAPTCHA v3 Settings', 'type' => 'title', 'description' => sprintf('Primary invisible protection. To get the keys go to <a href="%s" target="_blank">Google reCAPTCHA Admin</a> and create a site with <b>Score based (v3)</b> reCAPTCHA type.', 'https://www.google.com/recaptcha/admin')), 'site_key_v3' => array('title' => 'v3 Site Key', 'type' => 'text', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v3 site key'), 'secret_key_v3' => array('title' => 'v3 Secret Key', 'type' => 'password', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v3 secret key'), 'score_threshold' => array('title' => 'Score Threshold', 'type' => 'number', 'default' => '0.5', 'custom_attributes' => array('min' => '0', 'max' => '1', 'step' => '0.1'), 'desc_tip' => \true, 'description' => 'Minimum score to pass (0.0–1.0). Lower scores trigger v2 fallback. Recommended: 0.5'), 'v2_title' => array('title' => 'reCAPTCHA v2 Settings', 'type' => 'title', 'description' => sprintf('Fallback visible checkbox when v3 score is below threshold. To get the keys go to <a href="%s" target="_blank">Google reCAPTCHA Admin</a> and create a site with <b>Challenge (v2) -> "I\'m not a robot" Checkbox</b> reCAPTCHA type.', 'https://www.google.com/recaptcha/admin')), 'site_key_v2' => array('title' => 'v2 Site Key', 'type' => 'text', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v2 (checkbox) site key'), 'secret_key_v2' => array('title' => 'v2 Secret Key', 'type' => 'password', 'desc_tip' => \true, 'description' => 'Your reCAPTCHA v2 secret key'), 'v2_theme' => array('title' => 'v2 Theme', 'type' => 'select', 'default' => 'light', 'options' => array('light' => 'Light', 'dark' => 'Dark'), 'desc_tip' => \true, 'description' => 'Color theme for the v2 checkbox'), 'scope_title' => array('title' => 'Protection Scope', 'type' => 'title', 'description' => 'Configure where reCAPTCHA protection is applied'), 'guest_only' => array('title' => 'Guest Orders Only', 'type' => 'checkbox', 'label' => 'Only verify for non-logged-in users', 'default' => 'yes', 'description' => 'Skip reCAPTCHA for logged-in customers'), 'advanced_title' => array('title' => 'Advanced Options', 'type' => 'title'), 'show_metabox' => array('title' => 'Order Metabox', 'type' => 'checkbox', 'label' => 'Show reCAPTCHA status metabox on order pages', 'default' => 'no', 'description' => 'Display reCAPTCHA verification details in a metabox on order edit pages'), 'log_rejections' => array('title' => 'Log', 'type' => 'checkbox', 'label' => 'Log rejected attempts', 'default' => 'no', 'description' => 'Log information about the checkout attempts rejected by v3 reCAPTCHA'));
    }
}
