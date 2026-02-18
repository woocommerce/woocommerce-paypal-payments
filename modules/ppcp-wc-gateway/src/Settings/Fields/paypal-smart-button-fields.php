<?php

/**
 * The services of the Gateway module.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway
 */
// phpcs:disable WordPress.Security.NonceVerification.Recommended
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Settings;

use WooCommerce\PayPalCommerce\Onboarding\State;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
use WooCommerce\PayPalCommerce\WcGateway\Gateway\PayPalGateway;
return function (ContainerInterface $container, array $fields): array {
    $current_page_id = $container->get('wcgateway.current-ppcp-settings-page-id');
    if ($current_page_id !== PayPalGateway::ID) {
        return $fields;
    }
    $settings = $container->get('wcgateway.settings');
    assert($settings instanceof \WooCommerce\PayPalCommerce\WcGateway\Settings\Settings);
    $has_enabled_separate_button_gateways = $container->get('wcgateway.settings.has_enabled_separate_button_gateways');
    $preview_message = __('Button Styling Preview', 'woocommerce-paypal-payments');
    $axo_smart_button_location_notice = $container->has('axo.smart-button-location-notice') ? $container->get('axo.smart-button-location-notice') : '';
    $smart_button_fields = array(
        'button_style_heading' => array('heading' => __('PayPal Smart Buttons', 'woocommerce-paypal-payments'), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal', 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the
					%1$sCheckout page%5$s, %2$sSingle Product Page%5$s, %3$sCart page%5$s or on %4$sMini Cart%5$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-checkout" target="_blank">',
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-single-product" target="_blank">',
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-cart" target="_blank">',
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-mini-cart" target="_blank">',
            '</a>'
        )),
        'smart_button_locations' => array('title' => __('Smart Button Locations', 'woocommerce-paypal-payments'), 'type' => 'ppcp-multiselect', 'input_class' => array('wc-enhanced-select'), 'default' => $container->get('wcgateway.button.default-locations'), 'description' => __('Select where the PayPal smart buttons should be displayed.', 'woocommerce-paypal-payments') . $axo_smart_button_location_notice, 'options' => $container->get('wcgateway.button.locations'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'smart_button_language' => array('title' => __('Smart Button Language', 'woocommerce-paypal-payments'), 'type' => 'select', 'desc_tip' => \true, 'description' => __('The language and region used for the displayed PayPal Smart Buttons. The default value is the current language and region setting in a browser.', 'woocommerce-paypal-payments'), 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'en', 'options' => $container->get('wcgateway.wp-paypal-locales-map'), 'screens' => array(State::STATE_ONBOARDED), 'gateway' => 'paypal', 'requirements' => array()),
        'smart_button_enable_styling_per_location' => array('title' => __('Customize Smart Buttons Per Location', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('Customize smart button style per location', 'woocommerce-paypal-payments'), 'default' => \false, 'description' => $container->has('wcgateway.button.recommended-styling-notice') ? $container->get('wcgateway.button.recommended-styling-notice') : '', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // General button styles.
        'button_general_layout' => array('title' => __('Button Layout', 'woocommerce-paypal-payments'), 'type' => 'select', 'classes' => $has_enabled_separate_button_gateways ? array('hide') : array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'vertical', 'desc_tip' => \true, 'description' => __('If additional funding sources are available to the buyer through PayPal, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.', 'woocommerce-paypal-payments'), 'options' => array('vertical' => __('Vertical', 'woocommerce-paypal-payments'), 'horizontal' => __('Horizontal', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_general_tagline' => array('title' => __('Tagline', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'default' => \false, 'label' => __('Enable tagline', 'woocommerce-paypal-payments'), 'desc_tip' => \true, 'description' => __('Enable to show the tagline below the payment button. Requires button width of 300px minimum to appear.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_general_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_general_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_general_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_general_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpGeneralButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // Checkout page.
        'button_checkout_heading' => array('heading' => __('Classic Checkout Buttons', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the %1$sClassic Checkout page%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-checkout" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_layout' => array('title' => __('Button Layout', 'woocommerce-paypal-payments'), 'type' => 'select', 'classes' => $has_enabled_separate_button_gateways ? array('hide') : array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'vertical', 'desc_tip' => \true, 'description' => __('If additional funding sources are available to the buyer through PayPal, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.', 'woocommerce-paypal-payments'), 'options' => array('vertical' => __('Vertical', 'woocommerce-paypal-payments'), 'horizontal' => __('Horizontal', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_tagline' => array('title' => __('Tagline', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'default' => \false, 'label' => __('Enable tagline', 'woocommerce-paypal-payments'), 'desc_tip' => \true, 'description' => __('Enable to show the tagline below the payment button. Requires button width of 300px minimum to appear.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpCheckoutButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // Single product page.
        'button_product_heading' => array('heading' => __('Single Product Page Buttons', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the %1$sSingle Product Page%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-single-product" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_product_layout' => array('title' => __('Button Layout', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'horizontal', 'desc_tip' => \true, 'description' => __('If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.', 'woocommerce-paypal-payments'), 'options' => array('vertical' => __('Vertical', 'woocommerce-paypal-payments'), 'horizontal' => __('Horizontal', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_product_tagline' => array('title' => __('Tagline', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('Enable tagline', 'woocommerce-paypal-payments'), 'default' => \false, 'desc_tip' => \true, 'description' => __('Enable to show the tagline below the payment button. Requires button width of 300px minimum to appear.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_product_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button on product pages.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_product_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_product_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_product_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_product_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpProductButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // Cart settings.
        'button_cart_heading' => array('heading' => __('Classic Cart Buttons', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the %1$sClassic Cart page%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-cart" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart_layout' => array('title' => __('Button Layout', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'horizontal', 'desc_tip' => \true, 'description' => __('If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.', 'woocommerce-paypal-payments'), 'options' => array('vertical' => __('Vertical', 'woocommerce-paypal-payments'), 'horizontal' => __('Horizontal', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart_tagline' => array('title' => __('Tagline', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('Enable tagline', 'woocommerce-paypal-payments'), 'default' => \false, 'desc_tip' => \true, 'description' => __('Enable to show the tagline below the payment button. Requires button width of 300px minimum to appear.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button in cart.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_cart_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_cart_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpCartButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // Mini cart settings.
        'button_mini-cart_heading' => array('heading' => __('Mini Cart Buttons', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the %1$sMini Cart%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-mini-cart" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_mini-cart_layout' => array('title' => __('Button Layout', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'vertical', 'desc_tip' => \true, 'description' => __('If additional funding sources are available to the buyer through PayPal, such as Venmo, then multiple buttons are displayed in the space provided. Choose "vertical" for a dynamic list of alternative and local payment options, or "horizontal" when space is limited.', 'woocommerce-paypal-payments'), 'options' => array('vertical' => __('Vertical', 'woocommerce-paypal-payments'), 'horizontal' => __('Horizontal', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_mini-cart_tagline' => array('title' => __('Tagline', 'woocommerce-paypal-payments'), 'type' => 'checkbox', 'label' => __('Enable tagline', 'woocommerce-paypal-payments'), 'default' => \false, 'desc_tip' => \true, 'description' => __('Enable to show the tagline below the payment button. Requires button width of 300px minimum to appear.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_mini-cart_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button in mini cart.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_mini_cart_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_mini-cart_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_mini-cart_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_mini-cart_height' => array('title' => __('Button Height', 'woocommerce-paypal-payments'), 'type' => 'number', 'default' => '35', 'custom_attributes' => array('min' => 25, 'max' => 55), 'desc_tip' => \true, 'description' => __('Add a value from 25 to 55.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_mini-cart_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpMiniCartButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // Block express checkout settings.
        'button_checkout-block-express_heading' => array('heading' => __('Express Checkout Buttons', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the %1$sExpress Checkout%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-block-express-checkout" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_checkout-block-express_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button in block express checkout.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_checkout_block_express_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_checkout-block-express_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_checkout-block-express_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_checkout-block-express_height' => array('title' => __('Button Height', 'woocommerce-paypal-payments'), 'type' => 'number', 'default' => '48', 'custom_attributes' => array('min' => 40, 'max' => 55), 'desc_tip' => \true, 'description' => __('Set a value from 40 to 55.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_checkout-block-express_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpCheckoutBlockExpressButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        // Block cart settings.
        'button_cart-block_heading' => array('heading' => __('Cart Buttons', 'woocommerce-paypal-payments'), 'description' => sprintf(
            // translators: %1$s and %2$s are the opening and closing of HTML <a> tag.
            __('Customize the appearance of the PayPal smart buttons on the %1$sCart%2$s.', 'woocommerce-paypal-payments'),
            '<a href="https://woocommerce.com/document/woocommerce-paypal-payments/#button-on-cart-block" target="_blank">',
            '</a>'
        ), 'type' => 'ppcp-heading', 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart-block_label' => array(
            'title' => __('Button Label', 'woocommerce-paypal-payments'),
            'type' => 'select',
            'class' => array(),
            'input_class' => array('wc-enhanced-select'),
            /**
             * Returns default label ID of the PayPal button in block cart.
             */
            'default' => apply_filters('woocommerce_paypal_payments_button_cart_block_label_default', 'paypal'),
            'desc_tip' => \true,
            'description' => __('This controls the label on the primary button.', 'woocommerce-paypal-payments'),
            'options' => array('paypal' => __('PayPal', 'woocommerce-paypal-payments'), 'checkout' => __('Checkout', 'woocommerce-paypal-payments'), 'buynow' => __('PayPal Buy Now', 'woocommerce-paypal-payments'), 'pay' => __('Pay with PayPal', 'woocommerce-paypal-payments')),
            'screens' => array(State::STATE_START, State::STATE_ONBOARDED),
            'requirements' => array(),
            'gateway' => 'paypal',
        ),
        'button_cart-block_color' => array('title' => __('Color', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'gold', 'desc_tip' => \true, 'description' => __('Controls the background color of the primary button. Use "Gold" to leverage PayPal\'s recognition and preference, or change it to match your site design or aesthetic.', 'woocommerce-paypal-payments'), 'options' => array('gold' => __('Gold (Recommended)', 'woocommerce-paypal-payments'), 'blue' => __('Blue', 'woocommerce-paypal-payments'), 'silver' => __('Silver', 'woocommerce-paypal-payments'), 'black' => __('Black', 'woocommerce-paypal-payments'), 'white' => __('White', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart-block_shape' => array('title' => __('Shape', 'woocommerce-paypal-payments'), 'type' => 'select', 'class' => array(), 'input_class' => array('wc-enhanced-select'), 'default' => 'rect', 'desc_tip' => \true, 'description' => __('The pill-shaped button\'s unique and powerful shape signifies PayPal in people\'s minds. Use the rectangular button as an alternative when pill-shaped buttons might pose design challenges.', 'woocommerce-paypal-payments'), 'options' => array('pill' => __('Pill', 'woocommerce-paypal-payments'), 'rect' => __('Rectangle', 'woocommerce-paypal-payments')), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart-block_height' => array('title' => __('Button Height', 'woocommerce-paypal-payments'), 'type' => 'number', 'default' => '48', 'custom_attributes' => array('min' => 40, 'max' => 55), 'desc_tip' => \true, 'description' => __('Set a value from 40 to 55.', 'woocommerce-paypal-payments'), 'screens' => array(State::STATE_START, State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
        'button_cart-block_preview' => array('type' => 'ppcp-preview', 'preview' => array('id' => 'ppcpCartBlockButtonPreview', 'type' => 'button', 'message' => $preview_message), 'screens' => array(State::STATE_ONBOARDED), 'requirements' => array(), 'gateway' => 'paypal'),
    );
    return array_merge($fields, $smart_button_fields);
};
