<?php

/**
 * The Applepay module extensions.
 *
 * @package WooCommerce\PayPalCommerce\Applepay
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\Applepay;

use WooCommerce\PayPalCommerce\Applepay\Assets\PropertiesDictionary;
use WooCommerce\PayPalCommerce\WcGateway\Helper\Environment;
use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Helper\DisplayManager;
use WooCommerce\PayPalCommerce\WcGateway\Settings\Settings;
return array('wcgateway.settings.fields' => function (array $fields, ContainerInterface $container): array {
    // Used in various places to mark fields for the preview button.
    $apm_name = 'ApplePay';
    // Eligibility check.
    if (!$container->has('applepay.eligible') || !$container->get('applepay.eligible')) {
        return $fields;
    }
    $is_available = $container->get('applepay.available');
    $is_referral = $container->get('applepay.is_referral');
    $insert_after = function (array $array, string $key, array $new): array {
        $keys = array_keys($array);
        $index = array_search($key, $keys, \true);
        $pos = \false === $index ? count($array) : $index + 1;
        return array_merge(array_slice($array, 0, $pos), $new, array_slice($array, $pos));
    };
    $display_manager = $container->get('wcgateway.display-manager');
    assert($display_manager instanceof DisplayManager);
    // Domain registration.
    $env = $container->get('settings.environment');
    assert($env instanceof Environment);
    $domain_registration_url = 'https://www.paypal.com/uccservicing/apm/applepay';
    if ($env->current_environment_is(Environment::SANDBOX)) {
        $domain_registration_url = 'https://www.sandbox.paypal.com/uccservicing/apm/applepay';
    }
    // Domain validation.
    $domain_validation_text = __('Status: Domain validation failed ❌', 'woocommerce-paypal-payments');
    if (!$container->get('applepay.has_validated')) {
        $domain_validation_text = __('The domain has not yet been validated. Use the Apple Pay button to validate the domain ❌', 'woocommerce-paypal-payments');
    } elseif ($container->get('applepay.is_validated')) {
        $domain_validation_text = __('Status: Domain successfully validated ✔️', 'woocommerce-paypal-payments');
    }
    // Device eligibility.
    $device_eligibility_text = __('Status: Your current browser/device does not seem to support Apple Pay ❌', 'woocommerce-paypal-payments');
    $device_eligibility_notes = sprintf(
        // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
        __('Though the button may display in previews, it won\'t appear in the shop. For details, refer to the %1$sApple Pay requirements%2$s.', 'woocommerce-paypal-payments'),
        '<a href="https://woo.com/document/woocommerce-paypal-payments/#apple-pay" target="_blank">',
        '</a>'
    );
    if ($container->get('applepay.is_browser_supported')) {
        $device_eligibility_text = __('Status: Your current browser/device seems to support Apple Pay ✔️', 'woocommerce-paypal-payments');
        $device_eligibility_notes = __('The Apple Pay button will be visible both in previews and below the PayPal buttons in the shop.', 'woocommerce-paypal-payments');
    }
    $module_url = $container->get('applepay.url');
    // Connection tab fields.
    $fields = $insert_after($fields, 'ppcp_reference_transactions_status', array('applepay_status' => array('title' => __('Apple Pay Payments', 'woocommerce-paypal-payments'), 'type' => 'ppcp-text', 'text' => $container->get('applepay.settings.connection.status-text'), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => Settings::CONNECTION_TAB_ID)));
    if (!$is_available && $is_referral) {
        $connection_url = admin_url('admin.php?page=wc-settings&tab=checkout&section=ppcp-gateway&ppcp-tab=ppcp-connection#field-credentials_feature_onboarding_heading');
        $connection_link = '<a href="' . $connection_url . '" style="pointer-events: auto">';
        return $insert_after($fields, 'digital_wallet_heading', array('applepay_button_enabled' => array('title' => __('Apple Pay Button', 'woocommerce-paypal-payments'), 'title_html' => sprintf('<img src="%sassets/images/applepay.svg" alt="%s" style="max-width: 150px; max-height: 45px;" />', $module_url, __('Apple Pay', 'woocommerce-paypal-payments')), 'type' => 'checkbox', 'class' => array('ppcp-grayed-out-text'), 'input_class' => array('ppcp-disabled-checkbox'), 'label' => __('Enable Apple Pay button', 'woocommerce-paypal-payments') . '<p class="description">' . sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Your PayPal account  %1$srequires additional permissions%2$s to enable Apple Pay.', 'woocommerce-paypal-payments'),
            $connection_link,
            '</a>'
        ) . '</p>', 'default' => 'yes', 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-display' => wp_json_encode(array($display_manager->rule()->condition_is_true(\false)->action_enable('applepay_button_enabled')->to_array()))), 'classes' => array('ppcp-valign-label-middle', 'ppcp-align-label-center'))));
    }
    return $insert_after($fields, 'digital_wallet_heading', array('spacer' => array('title' => '', 'type' => 'ppcp-text', 'text' => '', 'class' => array(), 'classes' => array('ppcp-active-spacer'), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array()), 'applepay_button_enabled' => array('title' => __('Apple Pay Button', 'woocommerce-paypal-payments'), 'title_html' => sprintf('<img src="%sassets/images/applepay.svg" alt="%s" style="max-width: 150px; max-height: 45px;" />', $module_url, __('Apple Pay', 'woocommerce-paypal-payments')), 'type' => 'checkbox', 'label' => __('Enable Apple Pay button', 'woocommerce-paypal-payments') . '<p class="description">' . sprintf(
        // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
        __('Buyers can use %1$sApple Pay%2$s to make payments on the web using the Safari web browser or an iOS device.', 'woocommerce-paypal-payments'),
        '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#apple-pay" target="_blank">',
        '</a>'
    ) . '</p>', 'default' => 'yes', 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-display' => wp_json_encode(array($display_manager->rule()->condition_element('applepay_button_enabled', '1')->action_visible('applepay_button_domain_registration')->action_visible('applepay_button_domain_validation')->action_visible('applepay_button_device_eligibility')->action_visible('applepay_button_color')->action_visible('applepay_button_type')->action_visible('applepay_button_language')->action_visible('applepay_checkout_data_mode')->action_visible('applepay_button_preview')->action_class('applepay_button_enabled', 'active')->to_array())), 'data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'is_enabled'), 'classes' => array('ppcp-valign-label-middle', 'ppcp-align-label-center')), 'applepay_button_domain_registration' => array('title' => __('Domain Registration', 'woocommerce-paypal-payments'), 'type' => 'ppcp-text', 'text' => '<a href="' . $domain_registration_url . '" class="button" target="_blank">' . __('Manage Domain Registration', 'woocommerce-paypal-payments') . '</a>' . '<p class="description">' . __('Any (sub)domain names showing an Apple Pay button must be registered on the PayPal website. If the domain displaying the Apple Pay button isn\'t registered, the payment method won\'t work.', 'woocommerce-paypal-payments') . '</p>', 'desc_tip' => \true, 'description' => __('Registering the website domain on the PayPal site is mandated by Apple. Payments will fail if the Apple Pay button is used on an unregistered domain.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array()), 'applepay_button_domain_validation' => array('title' => __('Domain Validation', 'woocommerce-paypal-payments'), 'type' => 'ppcp-text', 'text' => $domain_validation_text . '<p class="description">' . sprintf(
        // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
        __('<strong>Note:</strong> PayPal Payments automatically presents the %1$sdomain association file%2$s for Apple to validate your registered domain.', 'woocommerce-paypal-payments'),
        '<a href="/.well-known/apple-developer-merchantid-domain-association" target="_blank">',
        '</a>'
    ) . '</p>', 'desc_tip' => \true, 'description' => __('Apple requires the website domain to be registered and validated. PayPal Payments automatically presents your domain association file for Apple to validate the manually registered domain.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array()), 'applepay_button_device_eligibility' => array('title' => __('Device Eligibility', 'woocommerce-paypal-payments'), 'type' => 'ppcp-text', 'text' => $device_eligibility_text . '<p class="description">' . $device_eligibility_notes . '</p>', 'desc_tip' => \true, 'description' => __('Apple Pay demands certain Apple devices for secure payment execution. This helps determine if your current device is compliant.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array()), 'applepay_button_type' => array('title' => __('Button Label', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('This controls the label of the Apple Pay button.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'plain', 'options' => PropertiesDictionary::button_types(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'type')), 'applepay_button_color' => array('title' => __('Button Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('The Apple Pay Button may appear as a black button with white lettering, white button with black lettering, or a white button with black lettering and a black outline.', 'woocommerce-paypal-payments'), 'label' => '', 'input_class' => array('wc-enhanced-select'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'default' => 'black', 'options' => PropertiesDictionary::button_colors(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'color')), 'applepay_button_language' => array('title' => __('Button Language', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('The language and region used for the displayed Apple Pay button. The default value is the current language and region setting in a browser.', 'woocommerce-paypal-payments'), 'classes' => array('ppcp-field-indent'), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'en', 'options' => PropertiesDictionary::button_languages(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array(), 'custom_attributes' => array('data-ppcp-apm-name' => $apm_name, 'data-ppcp-field-name' => 'language')), 'applepay_checkout_data_mode' => array('title' => __('Send checkout billing and shipping data to Apple Pay', 'woocommerce-paypal-payments'), 'type' => 'select', 'classes' => array('ppcp-field-indent'), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'desc_tip' => \true, 'description' => __('Using the WC form data increases convenience for the customers, but can cause issues if Apple Pay details do not match the billing and shipping data in the checkout form.', 'woocommerce-paypal-payments'), 'default' => PropertiesDictionary::BILLING_DATA_MODE_DEFAULT, 'options' => PropertiesDictionary::billing_data_modes(), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'dcc', 'requirements' => array()), 'applepay_button_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcp' . $apm_name . 'ButtonPreview', 'type' => 'button', 'message' => __('Button Styling Preview', 'woocommerce-paypal-payments'), 'apm' => $apm_name, 'single' => \true), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'dcc')));
});
