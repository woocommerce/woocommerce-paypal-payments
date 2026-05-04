<?php

/**
 *
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AdminNotices\Endpoint;

use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\NonceValidationException;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\PersistentMessage;
/**
 * Permanently mutes an admin notification for the current user.
 */
class MuteMessageEndpoint
{
    const ENDPOINT = 'ppc-mute-message';
    private RequestData $request_data;
    public function __construct(RequestData $request_data)
    {
        $this->request_data = $request_data;
    }
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
        } catch (NonceValidationException $error) {
            wp_send_json_error(array('message' => $error->getMessage()), 400);
        } catch (RuntimeException $ex) {
            wp_send_json_error();
        }
        $id = $data['id'] ?? '';
        if (!$id || !is_string($id)) {
            wp_send_json_error();
        }
        /**
         * Create a dummy message with the provided ID and mark it as muted.
         *
         * This helps to keep code cleaner and make the mute-endpoint more reliable,
         * as other modules do not need to register the PersistentMessage on every
         * ajax request.
         */
        $message = new PersistentMessage($id, '', '', '');
        $message->mute();
        wp_send_json_success();
    }
}
