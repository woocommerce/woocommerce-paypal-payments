<?php

/**
 * Permanently mutes an admin notification for the current user.
 *
 * @package WooCommerce\PayPalCommerce\AdminNotices\Endpoint
 */
declare (strict_types=1);
namespace WooCommerce\PayPalCommerce\AdminNotices\Endpoint;

use WooCommerce\PayPalCommerce\AdminNotices\Repository\Repository;
use WooCommerce\PayPalCommerce\Button\Endpoint\RequestData;
use WooCommerce\PayPalCommerce\Button\Exception\RuntimeException;
use WooCommerce\PayPalCommerce\AdminNotices\Entity\PersistentMessage;
/**
 * Class MuteMessageEndpoint
 */
class MuteMessageEndpoint
{
    const ENDPOINT = 'ppc-mute-message';
    /**
     * The request data helper.
     *
     * @var RequestData
     */
    private $request_data;
    /**
     * Message repository to retrieve the message object to mute.
     *
     * @var Repository
     */
    private $message_repository;
    /**
     * UpdateShippingEndpoint constructor.
     *
     * @param RequestData $request_data       The Request Data Helper.
     * @param Repository  $message_repository Message repository, to access messages.
     */
    public function __construct(RequestData $request_data, Repository $message_repository)
    {
        $this->request_data = $request_data;
        $this->message_repository = $message_repository;
    }
    /**
     * Returns the nonce.
     *
     * @return string
     */
    public static function nonce(): string
    {
        return self::ENDPOINT;
    }
    /**
     * Handles the request.
     *
     * @return void
     */
    public function handle_request(): void
    {
        try {
            $data = $this->request_data->read_request($this->nonce());
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
