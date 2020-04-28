<?php

declare(strict_types=1);

namespace Inpsyde\PayPalCommerce\WcGateway\Notice;

use Inpsyde\PayPalCommerce\AdminNotices\Entity\Message;

class AuthorizeOrderActionNotice
{
    public const QUERY_PARAM = 'ppcp-authorized-message';

    public const NO_INFO = 81;
    public const ALREADY_CAPTURED = 82;
    public const FAILED = 83;
    public const SUCCESS = 84;
    public const NOT_FOUND = 85;

    public function message(): ?Message
    {

        $message = $this->currentMessage();
        if (! $message) {
            return null;
        }

        return new Message($message['message'], $message['type']);
    }

    private function currentMessage(): array
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

        //phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (! isset($_GET[self::QUERY_PARAM])) { // Input ok.
            return [];
        }
        $messageId = absint($_GET[self::QUERY_PARAM]); // Input ok.
        //phpcs:enable WordPress.Security.NonceVerification.Recommended
        return (isset($messages[$messageId])) ? $messages[$messageId] : [];
    }

    public function displayMessage(int $messageCode): void
    {
        add_filter(
            'redirect_post_location',
            static function ($location) use ($messageCode) {
                return add_query_arg(
                    self::QUERY_PARAM,
                    $messageCode,
                    $location
                );
            }
        );
    }
}
