<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\Button\Endpoint;

use Inpsyde\PayPalCommerce\Button\Exception\RuntimeException;

class RequestData
{

    public function readRequest(string $nonce) : array
    {
        $stream = file_get_contents('php://input');
        $json = json_decode($stream, true);
        if (! isset($json['nonce'])
            || !wp_verify_nonce($json['nonce'], $nonce)
        ) {
            throw new RuntimeException(
                __('Could not validate nonce.', 'woocommerce-paypal-commerce-gateway')
            );
        }

        return $this->sanitize($json);
    }

    private function sanitize(array $assocArray) : array
    {
        $data = [];
        foreach ((array) $assocArray as $rawKey => $rawValue) {
            if (! is_array($rawValue)) {
                $data[sanitize_text_field($rawKey)] = sanitize_text_field($rawValue);
                continue;
            }
            $data[sanitize_text_field($rawKey)] = $this->sanitize($rawValue);
        }
        return $data;
    }
}
