<?php
/**
 * Helper class to determine which disclaimer content should display based on shop location country.
 *
 * @package WooCommerce\PayPalCommerce\Button\Helper
 */

declare(strict_types=1);

namespace WooCommerce\PayPalCommerce\Button\Helper;

class MessagesDisclaimers {

    private $disclaimers = [
        'US' => [
            'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/us/',
        ],
        'UK' => [
            'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/uk/',
        ],
        'DE' => [
            'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/de/',
        ],
        'AU' => [
            'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/au/',
        ],
        'FR' => [
            'link' => 'https://developer.paypal.com/docs/commerce-platforms/admin-panel/woocommerce/fr/',
        ],
    ];

    public function link_for_country(): string {
        $region  = wc_get_base_location();
        $country = $region['country'];

        return $this->disclaimers[$country]['link'] ?? '';
    }
}
