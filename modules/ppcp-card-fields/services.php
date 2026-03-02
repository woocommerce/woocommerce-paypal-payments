<?php

/**
 * The Card Fields module services.
 *
 * @package WooCommerce\PayPalCommerce\CardFields
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\CardFields;

use WooCommerce\PayPalCommerce\CardFields\Helper\CardFieldsApplies;
use WooCommerce\PayPalCommerce\CardFields\Service\CardCaptureValidator;
use WooCommerce\PayPalCommerce\Vendor\Psr\Container\ContainerInterface;
return array(
    // @deprecated - use `card-fields.eligibility.check` instead.
    'card-fields.eligible' => static function (ContainerInterface $container): bool {
        $eligibility_check = $container->get('card-fields.eligibility.check');
        return $eligibility_check();
    },
    'card-fields.eligibility.check' => static function (ContainerInterface $container): callable {
        $save_payment_methods_applies = $container->get('card-fields.helpers.save-payment-methods-applies');
        assert($save_payment_methods_applies instanceof CardFieldsApplies);
        return static function () use ($save_payment_methods_applies): bool {
            return $save_payment_methods_applies->for_country() && $save_payment_methods_applies->for_merchant();
        };
    },
    'card-fields.helpers.save-payment-methods-applies' => static function (ContainerInterface $container): CardFieldsApplies {
        return new CardFieldsApplies($container->get('card-fields.supported-country-matrix'), $container->get('api.merchant.country'));
    },
    'card-fields.supported-country-matrix' => static function (ContainerInterface $container): array {
        return apply_filters('woocommerce_paypal_payments_card_fields_supported_country_matrix', array('AU', 'AT', 'BE', 'BG', 'CA', 'CN', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR', 'DE', 'GR', 'HK', 'HU', 'IE', 'IT', 'LV', 'LI', 'LT', 'LU', 'MT', 'MX', 'NL', 'PL', 'PT', 'RO', 'SK', 'SG', 'SI', 'ES', 'SE', 'GB', 'US', 'NO', 'YT', 'RE', 'GP', 'GF', 'MQ'));
    },
    'card-fields.service.card-capture-validator' => static function (ContainerInterface $container): CardCaptureValidator {
        return new CardCaptureValidator();
    },
);
