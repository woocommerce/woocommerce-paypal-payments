<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Notice;

class AuthorizeOrderActionNotice
{
    const NO_INFO = 81;
    const ALREADY_AUTHORIZED = 82;
    const FAILED = 83;
    const SUCCESS = 84;

    public function registerMessages(array $messages): array
    {
        $messages['shop_order'][self::NO_INFO] = __(
            'Could not retrieve payment information. Try again.',
            'woocommerce-paypal-gateway'
        );
        $messages['shop_order'][self::ALREADY_AUTHORIZED] = __(
            'Payment was previously authorized.',
            'woocommerce-paypal-gateway'
        );
        $messages['shop_order'][self::FAILED] = __(
            'Authorization failed',
            'woocommerce-paypal-gateway'
        );
        $messages['shop_order'][self::SUCCESS] = __(
            'Authorization successful',
            'woocommerce-paypal-gateway'
        );

        return $messages;
    }

    public static function displayMessage(int $messageCode): void
    {
        add_filter(
            'redirect_post_location',
            function ($location) use ($messageCode) {
                return add_query_arg(
                    'message',
                    $messageCode,
                    $location
                );
            }
        );
    }
}
