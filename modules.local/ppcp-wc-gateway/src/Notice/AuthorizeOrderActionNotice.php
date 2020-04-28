<?php
declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Notice;

use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;

class AuthorizeOrderActionNotice
{
    const QUERY_PARAM = 'ppcp-authorized-message';

    const NO_INFO = 81;
    const ALREADY_CAPTURED = 82;
    const FAILED = 83;
    const SUCCESS = 84;
    const NOT_FOUND = 85;

    public function message() : ?Message {
        $message = $this->getMessage();
        if (! $message) {
            return null;
        }

        return new Message($message['message'], $message['type']);
    }

    public function getMessage(): array
    {
        $messages[self::NO_INFO] = [
            'message' => __(
                'Could not retrieve information. Try again later.',
                'woocommerce-paypal-gateway'
            ),
            'type' => 'error',
        ];
        $messages[self::ALREADY_CAPTURED] = [
            'message' => __(
                'Payment already captured.',
                'woocommerce-paypal-gateway'
            ),
            'type' => 'error',
        ];
        $messages[self::FAILED] = [
            'message' => __(
                'Failed to capture. Try again later.',
                'woocommerce-paypal-gateway'
            ),
            'type' => 'error',
        ];
        $messages[self::NOT_FOUND] = [
            'message' => __(
                'Could not find payment to process.',
                'woocommerce-paypal-gateway'
            ),
            'type' => 'error',
        ];
        $messages[self::SUCCESS] = [
            'message' => __(
                'Payment successfully captured.',
                'woocommerce-paypal-gateway'
            ),
            'type' => 'success',
        ];

        if (! isset($_GET['ppcp-message'])) {
            return [];
        }
        $messageId = absint($_GET[self::QUERY_PARAM]);
        return (isset($messages[$messageId])) ? $messages[$messageId] : [];
    }

    public static function displayMessage(int $messageCode): void
    {
        add_filter(
            'redirect_post_location',
            function ($location) use ($messageCode) {
                return add_query_arg(
                    self::QUERY_PARAM,
                    $messageCode,
                    $location
                );
            }
        );
    }
}
