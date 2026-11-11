<?php

/**
 * Contains the messages to display, when capturing an authorization manually.
 *
 * @package WooCommerce\PayPalCommerce\WcGateway\Notice
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\WcGateway\Notice;

use WooCommerce\PayPalCommerce\AdminNotices\Entity\Message;
/**
 * Class AuthorizeOrderActionNotice
 */
class AuthorizeOrderActionNotice
{
    const QUERY_PARAM = 'ppcp-authorized-message';
    const NO_INFO = 81;
    const ALREADY_CAPTURED = 82;
    const FAILED = 83;
    const SUCCESS = 84;
    const NOT_FOUND = 85;
    const BAD_AUTHORIZATION = 86;
    /**
     * Returns the current message if there is one.
     *
     * @return Message|null
     */
    public function message()
    {
        $message = $this->current_message();
        if (!$message) {
            return null;
        }
        return new Message($message['message'], $message['type']);
    }
    /**
     * Returns the current message.
     *
     * @return array
     */
    private function current_message(): array
    {
        $messages[self::NO_INFO] = array('message' => __('Could not retrieve information. Try again later.', 'woocommerce-paypal-payments'), 'type' => 'error');
        $messages[self::ALREADY_CAPTURED] = array('message' => __('Payment already captured.', 'woocommerce-paypal-payments'), 'type' => 'error');
        $messages[self::FAILED] = array('message' => __('Failed to capture. Try again later or checks the logs.', 'woocommerce-paypal-payments'), 'type' => 'error');
        $messages[self::BAD_AUTHORIZATION] = array('message' => __('Cannot capture, no valid payment authorization.', 'woocommerce-paypal-payments'), 'type' => 'error');
        $messages[self::NOT_FOUND] = array('message' => __('Could not find payment to process.', 'woocommerce-paypal-payments'), 'type' => 'error');
        $messages[self::SUCCESS] = array('message' => __('Payment successfully captured.', 'woocommerce-paypal-payments'), 'type' => 'success');
        //phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET[self::QUERY_PARAM])) {
            // Input ok.
            return array();
        }
        $message_id = absint($_GET[self::QUERY_PARAM]);
        // Input ok.
        //phpcs:enable WordPress.Security.NonceVerification.Recommended
        return isset($messages[$message_id]) ? $messages[$message_id] : array();
    }
    /**
     * Adds the query parameter for the message to 'redirect_post_location'.
     *
     * @param int $message_code The message code.
     */
    public function display_message(int $message_code)
    {
        add_filter('redirect_post_location', static function ($location) use ($message_code) {
            return add_query_arg(self::QUERY_PARAM, $message_code, $location);
        });
    }
}
