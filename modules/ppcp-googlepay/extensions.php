<?php

/**
 * The Googlepay module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Googlepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Googlepay;

use WooCommerce\PayPalCommerce\Googlepay\Helper\PropertiesDictionary;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
return array('wcgateway.settings.fields' => function (array $fields, ContainerInterface $container): array {
    // Used in various places to mark fields for the preview button.
    $apm_name = 'GooglePay';
    // Eligibility check.
    if (!$container->has('googlepay.eligible') || !$container->get('googlepay.eligible')) {
        return $fields;
    }
    $is_available = $container->get('googlepay.available');
    $is_referral = $container->get('googlepay.is_referral');
    $insert_after = function (array $array, string $key, array $new): array {
        $keys = array_keys($array);
        $index = array_search($key, $keys, \true);
        $pos = \false === $index ? count($array) : $index + 1;
        return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
    };
    $display_manager = $container->get('wcgateway.display-manager');
    assert($display_manager instanceof DisplayManager);
    $module_url = $container->get('googlepay.url');
    // Connection tab fields.
    $fields = $insert_after($fields, 'ppcp_reference_transactions_status', array('googlepay_status' => array('title' => __('Google Pay Payments', 'woocommerce-paypal-payments'), 'type' => 'ppcp-text', 'text' => $container->get('googlepay.settings.connection.status-text'), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => Settings::CONNECTION_TAB_ID)));
    if (!$is_available && $is_referral) {
        $connection_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection#field-credentials_feature_onboarding_heading');
        $connection_link = '<a href="' . $connection_url . '" style="pointer-events: auto">';
        return $insert_after($fields, 'digital_wallet_heading', array('googlepay_button_enabled' => array('title' => __('Google Pay Button', 'woocommerce-paypal-payments'), 'title_html' => sprintf('<img src="%sassets/images/googlepay.svg" alt="%s" style="max-width: 150px; max-height: 45px;" />', $module_url, __('Google Pay', 'woocommerce-paypal-payments')), 'type' => 'checkbox', 'class' => array('ppcp-grayed-out-text'), 'input_class' => array('ppcp-disabled-checkbox'), 'label' => __('Enable Google Pay button', 'woocommerce-paypal-payments') . '<p class="description">' . sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Your PayPal account  %1$srequires additional permissions%2$s to enable Google Pay.', 'woocommerce-paypal-payments'),
            $connection_link,
            '</a>'
        ) . '</p>', 'default' => 'yes', 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-display' => wp_json_encode(array($display_manager->rule()->condition_is_true(\false)->action_enable('googlepay_button_enabled')->to_array()))), 'classes' => array('ppcp-valign-label-middle', 'ppcp-align-label-center'))));
    }
    // Standard Payments tab fields.
    return $insert_after($fields, 'digital_wallet_heading', array('googlepay_button_enabled' => array('title' => __('Google Pay Button', 'woocommerce-paypal-payments'), 'title_html' => sprintf('<img src="%sassets/images/googlepay.svg" alt="%s" style="max-width: 150px; max-height: 45px;" />', $module_url, __('Google Pay', 'woocommerce-paypal-payments')), 'type' => 'checkbox', 'label' => __('Enable Google Pay button', 'woocommerce-paypal-payments') . '<p class="description">' . sprintf(
        // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
        __('Buyers can use %1$sGoogle Pay%2$s to make payments on the web using a web browser.', 'woocommerce-paypal-payments'),
        '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#google-pay" target="_blank">',
        '</a>'
    ) . '</p>', 'default' => 'yes', 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-display' => wp_json_encode(array($display_manager->rule()->condition_element('googlepay_button_enabled', '1')->action_visible('googlepay_button_type')->action_visible('googlepay_button_color')->action_visible('googlepay_button_language')->action_visible('googlepay_button_shipping_enabled')->action_visible('googlepay_button_preview')->action_class('googlepay_button_enabled', 'active')->to_array())), 'data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'is_enabled'), 'classes' => array('ppcp-valign-label-middle', 'ppcp-align-label-center')), 'googlepay_button_type' => array('title' => __('Button Label', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('This controls the label of the Google Pay button.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'plain', 'options' => PropertiesDictionary::button_types(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'type')), 'googlepay_button_color' => array('title' => __('Button Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('Google Pay payment buttons exist in two styles: dark and light. To provide contrast, use dark buttons on light backgrounds and light buttons on dark or colorful backgrounds.', 'woocommerce-paypal-payments'), 'label' => '', 'input_class' => array('wc-enhanced-select'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'default' => 'black', 'options' => PropertiesDictionary::button_colors(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'color')), 'googlepay_button_language' => array('title' => __('Button Language', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('The language and region used for the displayed Google Pay button. The default value is the current language and region setting in a browser.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'en', 'options' => PropertiesDictionary::button_languages(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'language')), 'googlepay_button_shipping_enabled' => array('title' => __('Shipping Callback', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'desc_tip' => \true, 'description' => __('Synchronizes your available shipping options with Google Pay. Enabling this may impact the buyer experience.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent ppcp'), 'label' => __('Enable Google Pay shipping callback', 'woocommerce-paypal-payments'), 'default' => 'no', 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array()), 'googlepay_button_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcp' . $apm_name . 'ButtonPreview', 'type' => 'button', 'message' => __('Button Styling Preview', 'woocommerce-paypal-payments'), 'apm' => $apm_name, 'single' => \true), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'dcc')));
});
