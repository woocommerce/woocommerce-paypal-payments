<?php

/**
 * The factory for links from CUSTOMER_ACTION_REQUIRED v2/vault/payment-tokens response.
 *
 * @package WooCommerce\PayPalCommerce\ApiClient\Factory
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\ApiClient\Factory;

use stdClass;
use WooCommerce\PayPalCommerce\ApiClient\Entity\PaymentTokenActionLinks;
use WooCommerce\PayPalCommerce\ApiClient\Exception\RuntimeException;
/**
 * Class PaymentTokenActionLinksFactory
 */
class PaymentTokenActionLinksFactory
{
    /**
     * Returns a PaymentTokenActionLinks object based off a PayPal response.
     *
     * @param stdClass $data The JSON object.
     *
     * @return PaymentTokenActionLinks
     * @throws RuntimeException When JSON object is malformed.
     */
    public function from_paypal_response(stdClass $data): PaymentTokenActionLinks
    {
        if (!isset($data->links)) {
            throw new RuntimeException('Links not found.');
        }
        $links_map = array();
        foreach ($data->links as $link) {
            if (!isset($link->rel) || !isset($link->href)) {
                throw new RuntimeException('Invalid link data.');
            }
            $links_map[$link->rel] = $link->href;
        }
        if (!array_key_exists('approve', $links_map)) {
            throw new RuntimeException('Payment token approve link not found.');
        }
        return new PaymentTokenActionLinks($links_map['approve'], $links_map['confirm'] ?? '', $links_map['status'] ?? '');
    }
}
